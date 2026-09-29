<?php

namespace App\Controllers\Widget;

use App\Controllers\BaseController;
use App\Libraries\WidgetToken;
use App\Models\TicketAttachmentModel;
use App\Models\TicketEscalationModel;
use App\Models\TicketFormAnswerModel;
use App\Models\TicketMessageModel;
use App\Models\TicketModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Widget;

/**
 * API pública del widget (/widget/*).
 *
 * Solo expone lo que el widget necesita y SIEMPRE respeta los datos firmados
 * en el token (correo, compañía, sucursal, rol): lo que envíe el navegador no
 * puede contradecirlos. El correo del solicitante sale del token, nunca de un
 * parámetro, así que nadie puede consultar tickets ajenos.
 */
class WidgetController extends BaseController
{
    use ResponseTrait;

    /** Estados que cuentan como pendientes / resueltos en "Mis tickets". */
    private const PENDING_STATUSES  = ['abierto', 'en_progreso'];
    private const RESOLVED_STATUSES = ['cerrado'];

    /** Adjuntos cuyo contenido real debe coincidir con la extensión. */
    private const CONTENT_CHECKED = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf'];

    private Widget $config;

    /**
     * @var array<string, mixed>
     */
    private array $claims = [];

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->config = config(Widget::class);
        $this->claims = WidgetToken::claims();
    }

    // ======================================================================
    // Identidad y catálogos
    // ======================================================================

    /**
     * GET /widget/me — datos que el anfitrión firmó en el token.
     */
    public function me()
    {
        $db = db_connect();

        $company = null;
        $branch  = null;

        if (! empty($this->claims['branch'])) {
            $branch = $db->table('Branches')
                ->select('CodBranches AS id, Branches AS name, CodCompanies AS company')
                ->where('CodBranches', $this->claims['branch'])
                ->get()->getRowArray();
        }

        $companyId = $this->claims['company'] ?? ($branch['company'] ?? null);

        if ($companyId !== null) {
            $company = $db->table('Companies')
                ->select('CodCompanies AS id, Companies AS name')
                ->where('CodCompanies', $companyId)
                ->get()->getRowArray();
        }

        // Una sucursal que no es de la compañía indicada se ignora.
        if ($branch !== null && $company !== null && $branch['company'] !== $company['id']) {
            $branch = null;
        }

        return $this->respond([
            'email'   => $this->claims['email'] ?? null,
            'name'    => $this->claims['name'] ?? null,
            'phone'   => $this->claims['phone'] ?? null,
            'user'    => $this->claims['user'] ?? null,
            'area'    => $this->claims['area'] ?? null,
            'role'    => $this->claims['role'] ?? null,
            'company' => $company,
            'branch'  => $branch,
            // ¿Puede cambiar compañía y sucursal, o quedan fijas?
            'locked'  => $this->lockedCompany() !== null,
        ]);
    }

    /**
     * GET /widget/companies
     */
    public function companies()
    {
        $builder = db_connect()->table('Companies')->orderBy('Companies');

        if (($company = $this->lockedCompany()) !== null) {
            $builder->where('CodCompanies', $company);
        }

        return $this->respond($builder->get()->getResultArray());
    }

    /**
     * GET /widget/branches?company=X
     */
    public function branches()
    {
        $company = $this->lockedCompany() ?? trim((string) $this->request->getGet('company'));

        if ($company === '') {
            return $this->respond([]);
        }

        $builder = db_connect()->table('Branches')
            ->select('CodBranches, Branches, CodCompanies')
            ->where('CodCompanies', $company)
            ->orderBy('Branches');

        if (($branch = $this->lockedBranch()) !== null) {
            $builder->where('CodBranches', $branch);
        }

        return $this->respond($builder->get()->getResultArray());
    }

    /**
     * GET /widget/categories
     */
    public function categories()
    {
        return $this->respond(db_connect()->table('Category')->orderBy('Category')->get()->getResultArray());
    }

    /**
     * GET /widget/subcategories
     */
    public function subcategories()
    {
        return $this->respond(db_connect()->table('SubCategory')->orderBy('SubCategory')->get()->getResultArray());
    }

    /**
     * GET /widget/subcategories/category/{id}
     */
    public function subcategoriesByCategory($id = null)
    {
        return $this->respond(
            db_connect()->table('SubCategory')->where('IdCategory', (int) $id)->orderBy('SubCategory')->get()->getResultArray()
        );
    }

    /**
     * GET /widget/ticket-forms — formularios adicionales (id y nombre).
     */
    public function ticketForms()
    {
        return $this->respond(db_connect()->table('TicketForms')->get()->getResultArray());
    }

    /**
     * GET /widget/users — solo nombres de usuarios activos (para elegir
     * responsables en los formularios). No incluye correos ni identificadores.
     */
    public function users()
    {
        $rows = db_connect()->table('Users')
            ->select('FullName')
            ->where('IsActive', 1)
            ->orderBy('FullName')
            ->get()->getResultArray();

        return $this->respond(array_map(static fn ($row) => ['FullName' => $row['FullName'], 'IsActive' => 1], $rows));
    }

    // ======================================================================
    // Tickets del solicitante (siempre limitados por el correo del token)
    // ======================================================================

    /**
     * GET /widget/tickets — lista simple de los tickets del correo del token.
     */
    public function tickets()
    {
        if (empty($this->claims['email'])) {
            return $this->respond([]);
        }

        return $this->respond(
            db_connect()->table('Tickets')
                ->select('IdTicket, Subject, Status, Priority, CreatedAt')
                ->where('RequesterEmail', $this->claims['email'])
                ->orderBy('CreatedAt', 'DESC')
                ->limit(50)
                ->get()->getResultArray()
        );
    }

    /**
     * GET /widget/tickets/mine?status=pendientes|resueltos&from=&to=&page=&perPage=
     *
     * Tickets del solicitante del token, paginados, con el conteo de
     * pendientes y resueltos para las pestañas y el badge del widget.
     */
    public function mine()
    {
        $email   = $this->claims['email'] ?? '';
        $status  = $this->request->getGet('status') === 'resueltos' ? 'resueltos' : 'pendientes';
        $perPage = max(1, min(50, (int) ($this->request->getGet('perPage') ?: 5)));
        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));

        $from = $this->dateParam('from');
        $to   = $this->dateParam('to');

        $empty = [
            'data' => [], 'status' => $status, 'page' => 1, 'perPage' => $perPage, 'pages' => 1, 'total' => 0,
            'counts' => ['pendientes' => 0, 'resueltos' => 0], 'from' => $from, 'to' => $to,
        ];

        // Sin correo verificado en el token no hay de quién listar tickets.
        if ($email === '') {
            return $this->respond($empty);
        }

        if ($from !== null && $to !== null && $from > $to) {
            return $this->failValidationErrors(['from' => 'La fecha inicial no puede ser mayor que la final.']);
        }

        $db = db_connect();

        $inRange = static function ($builder, string $column) use ($from, $to) {
            if ($from !== null) {
                $builder->where($column . ' >=', $from . ' 00:00:00');
            }

            if ($to !== null) {
                $builder->where($column . ' <', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00');
            }

            return $builder;
        };

        $count = static fn (array $statuses) => $inRange(
            $db->table('Tickets')->where('RequesterEmail', $email)->whereIn('Status', $statuses),
            'CreatedAt'
        )->countAllResults();

        $counts = [
            'pendientes' => $count(self::PENDING_STATUSES),
            'resueltos'  => $count(self::RESOLVED_STATUSES),
        ];

        $pages = max(1, (int) ceil($counts[$status] / $perPage));
        $page  = min($page, $pages);

        $builder = $db->table('Tickets t')
            ->select('t.IdTicket, t.Subject, t.Status, t.Priority, t.CreatedAt, cat.Category, sc.SubCategory, u.FullName AS AssignedName')
            ->join('Category cat', 'cat.IdCategory = t.IdCategory', 'left')
            ->join('SubCategory sc', 'sc.IdSubCategory = t.IdSubCategory', 'left')
            ->join('Users u', 'u.IdUser = t.AssignedUserId', 'left');

        if (TicketEscalationModel::available()) {
            $builder->select('(SELECT COUNT(*) FROM TicketEscalations e WHERE e.IdTicket = t.IdTicket AND e.ResolvedAt IS NULL) AS Escalated', false);
        }

        $rows = $inRange($builder, 't.CreatedAt')
            ->where('t.RequesterEmail', $email)
            ->whereIn('t.Status', $status === 'resueltos' ? self::RESOLVED_STATUSES : self::PENDING_STATUSES)
            ->orderBy('t.CreatedAt', 'DESC')
            ->orderBy('t.IdTicket', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();

        return $this->respond([
            'data'    => $rows,
            'status'  => $status,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => $pages,
            'total'   => $counts[$status],
            'counts'  => $counts,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    /**
     * GET /widget/tickets/{id}/detalle
     *
     * Detalle para el solicitante: datos, respuestas del formulario,
     * seguimiento de TI, conversación y adjuntos. Solo si el ticket es del
     * correo del token; si no, responde 404 como si no existiera.
     */
    public function detail($id = null)
    {
        $ticket = $this->ownTicket((int) $id, true);

        if ($ticket === null) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        $answers  = [];
        $followUp = [];

        try {
            $rows       = model(TicketFormAnswerModel::class)->forTicket((int) $id);
            $formKey    = $rows[0]['FormKey'] ?? null;
            $areaFields = $formKey !== null ? (config('Tickets')->areaFields[$formKey] ?? []) : [];

            foreach ($rows as $row) {
                $item = ['label' => $row['Label'], 'value' => (string) $row['Value']];

                if (isset($areaFields[$row['FieldKey']])) {
                    if ($item['value'] !== '') {
                        $followUp[] = $item;
                    }
                } else {
                    $answers[] = $item;
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', '[Widget] Respuestas del formulario: ' . $e->getMessage());
        }

        $messages = array_map(static fn ($m) => [
            'sender'    => $m['SenderName'],
            'type'      => $m['SenderType'],
            'message'   => $m['Message'],
            'createdAt' => $m['CreatedAt'],
        ], model(TicketMessageModel::class)->forTicket((int) $id));

        $attachments = array_map(
            static fn ($a) => $a['FileName'],
            model(TicketAttachmentModel::class)->where('TicketId', (int) $id)->findAll()
        );

        unset($ticket['RequesterEmail']);

        $escalation            = model(TicketEscalationModel::class)->activeFor((int) $id);
        $ticket['Escalated']   = $escalation !== null;
        $ticket['EscalatedAt'] = $escalation['CreatedAt'] ?? null;

        return $this->respond([
            'ticket'      => $ticket,
            'answers'     => $answers,
            'followUp'    => $followUp,
            'messages'    => $messages,
            'attachments' => $attachments,
        ]);
    }

    /**
     * POST /widget/tickets/{id}/responder  Body: { message }
     *
     * El solicitante escribe en la conversación de su ticket (no cerrado).
     * El nombre sale del token; el correo, también.
     */
    public function reply($id = null)
    {
        $ticket = $this->ownTicket((int) $id);

        if ($ticket === null) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        if ($ticket['Status'] === 'cerrado') {
            return $this->failValidationErrors(['message' => 'El ticket está resuelto; crea uno nuevo si necesitas más ayuda.']);
        }

        $message = trim((string) ($this->input()['message'] ?? ''));

        if ($message === '') {
            return $this->failValidationErrors(['message' => 'Escribe un mensaje.']);
        }

        if (mb_strlen($message) > 4000) {
            return $this->failValidationErrors(['message' => 'El mensaje supera los 4000 caracteres.']);
        }

        $model = model(TicketMessageModel::class);

        $saved = $model->insert([
            'IdTicket'   => (int) $id,
            'SenderType' => 'cliente',
            'SenderName' => mb_substr($this->claims['name'] ?? $this->claims['email'], 0, 150),
            'Message'    => $message,
        ]);

        if ($saved === false) {
            return $this->failValidationErrors($model->errors());
        }

        $row = $model->find($model->getInsertID());

        return $this->respondCreated([
            'sender'    => $row['SenderName'],
            'type'      => $row['SenderType'],
            'message'   => $row['Message'],
            'createdAt' => $row['CreatedAt'],
        ]);
    }

    // ======================================================================
    // Crear ticket
    // ======================================================================

    /**
     * POST /widget/tickets
     *
     * JSON o multipart con los campos del ticket, Description (primer mensaje),
     * FormKey + Answers (formulario adicional) y file / files[] (adjuntos).
     * Todo se guarda en una transacción.
     */
    public function createTicket()
    {
        $input = $this->input();
        $db    = db_connect();

        $errors = [];

        // El correo del token manda; sin token con correo, se acepta el escrito.
        $email = $this->claims['email'] ?? trim((string) ($input['RequesterEmail'] ?? ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            $errors['RequesterEmail'] = 'Ingresa un correo válido.';
        }

        // Compañía y sucursal: las del token si están fijas; si no, las elegidas.
        $company = $this->lockedCompany() ?? trim((string) ($input['CodCompanies'] ?? ''));
        $branch  = $this->lockedBranch() ?? trim((string) ($input['CodBranches'] ?? ''));

        $branchRow = $db->table('Branches')->where('CodBranches', $branch)->get()->getRowArray();

        if ($branchRow === null) {
            $errors['CodBranches'] = 'La sucursal no existe.';
        } elseif ($company === '' || $branchRow['CodCompanies'] !== $company) {
            $errors['CodBranches'] = 'La sucursal no pertenece a la compañía.';
        }

        $category = (int) ($input['IdCategory'] ?? 0);

        if ($category < 1 || $db->table('Category')->where('IdCategory', $category)->countAllResults() === 0) {
            $errors['IdCategory'] = 'Selecciona una categoría válida.';
        }

        $sub = (int) ($input['IdSubCategory'] ?? 0);

        if ($sub > 0 && $db->table('SubCategory')->where('IdSubCategory', $sub)->where('IdCategory', $category)->countAllResults() === 0) {
            $errors['IdSubCategory'] = 'La subcategoría no pertenece a la categoría.';
        }

        $subject = trim((string) ($input['Subject'] ?? ''));

        if ($subject === '' || mb_strlen($subject) > 150) {
            $errors['Subject'] = 'El asunto es obligatorio (máximo 150 caracteres).';
        }

        $priority = (string) ($input['Priority'] ?? '');

        if (! in_array($priority, ['alta', 'media', 'baja'], true)) {
            $errors['Priority'] = 'Selecciona una prioridad válida.';
        }

        $description = trim((string) ($input['Description'] ?? ''));

        if (mb_strlen($description) > 4000) {
            $errors['Description'] = 'La descripción no puede superar 4000 caracteres.';
        }

        [$formKey, $answers] = $this->formAnswers($input);

        if ($description === '' && $answers === []) {
            $errors['Description'] = 'Describe tu solicitud.';
        }

        $files = $this->uploadedFiles();

        if (count($files) > $this->config->maxFiles) {
            $errors['file'] = "Máximo {$this->config->maxFiles} archivos por ticket.";
        }

        foreach ($files as $file) {
            if (($fileError = $this->checkFile($file)) !== null) {
                $errors['file'] = $fileError;
                break;
            }
        }

        if ($errors !== []) {
            return $this->respond(['status' => 422, 'message' => implode(' ', $errors), 'messages' => $errors], 422);
        }

        // Límite adicional por correo (además del límite por IP).
        if (! service('throttler')->check('wgm_' . md5(mb_strtolower($email)), $this->config->ticketsPerHour, HOUR)) {
            return $this->respond(['status' => 429, 'message' => 'Has creado demasiados tickets. Intenta más tarde.'], 429);
        }

        $model = model(TicketModel::class);

        $db->transBegin();

        try {
            $inserted = $model->insert([
                'CodCompanies'   => $company,
                'CodBranches'    => $branch,
                'RequesterEmail' => $email,
                'Subject'        => $subject,
                'IdCategory'     => $category,
                'IdSubCategory'  => $sub > 0 ? $sub : null,
                'Priority'       => $priority,
                'Status'         => 'abierto',   // siempre; el navegador no lo decide
            ]);

            if (! $inserted) {
                $db->transRollback();

                return $this->respond(['status' => 422, 'message' => implode(' ', $model->errors()), 'messages' => $model->errors()], 422);
            }

            $id = (int) $model->getInsertID();

            if ($description !== '') {
                $phone = isset($this->claims['phone']) ? "Teléfono: {$this->claims['phone']}\n\n" : '';

                model(TicketMessageModel::class)->insert([
                    'IdTicket'   => $id,
                    'SenderType' => 'cliente',
                    'SenderName' => mb_substr($this->claims['name'] ?? $email, 0, 150),
                    'Message'    => $phone . $description,
                ]);
            }

            if ($formKey !== '' && $answers !== []) {
                model(TicketFormAnswerModel::class)->saveForTicket($id, $formKey, $answers);
            }

            foreach ($files as $file) {
                $this->saveFile($file, $id);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Error de base de datos al guardar el ticket.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', '[Widget tickets] ' . $e->getMessage());

            return $this->failServerError('No fue posible guardar el ticket.');
        }

        return $this->respondCreated(['IdTicket' => $id]);
    }

    // ======================================================================
    // Utilidades
    // ======================================================================

    /**
     * ¿El rol del token es administrador? (compañía y sucursal se pueden cambiar)
     */
    private function isAdmin(): bool
    {
        $role = strtolower(preg_replace('/[\x{0300}-\x{036f}]/u', '', \Normalizer::normalize((string) ($this->claims['role'] ?? ''), \Normalizer::FORM_D) ?: '') ?? '');

        return str_contains($role, 'administrador');
    }

    /**
     * Compañía fija del token (o la de su sucursal). null si el rol es
     * administrador o el token no trae compañía/sucursal.
     */
    private function lockedCompany(): ?string
    {
        if ($this->isAdmin()) {
            return null;
        }

        if (! empty($this->claims['company'])) {
            return (string) $this->claims['company'];
        }

        if (! empty($this->claims['branch'])) {
            $row = db_connect()->table('Branches')->select('CodCompanies')->where('CodBranches', $this->claims['branch'])->get()->getRowArray();

            return $row['CodCompanies'] ?? null;
        }

        return null;
    }

    private function lockedBranch(): ?string
    {
        return ! $this->isAdmin() && ! empty($this->claims['branch']) ? (string) $this->claims['branch'] : null;
    }

    /**
     * Ticket que pertenece al correo del token, o null. Con $withDetail trae
     * también los nombres de compañía, sucursal, categoría y responsable.
     *
     * @return array<string, mixed>|null
     */
    private function ownTicket(int $id, bool $withDetail = false): ?array
    {
        $email = $this->claims['email'] ?? '';

        if ($email === '') {
            return null;
        }

        $builder = db_connect()->table('Tickets t')->where('t.IdTicket', $id);

        if ($withDetail) {
            $builder
                ->select('t.IdTicket, t.Subject, t.Status, t.Priority, t.CreatedAt, t.RequesterEmail,
                    c.Companies, b.Branches, cat.Category, sc.SubCategory, u.FullName AS AssignedName')
                ->join('Companies c', 'c.CodCompanies = t.CodCompanies', 'left')
                ->join('Branches b', 'b.CodBranches = t.CodBranches', 'left')
                ->join('Category cat', 'cat.IdCategory = t.IdCategory', 'left')
                ->join('SubCategory sc', 'sc.IdSubCategory = t.IdSubCategory', 'left')
                ->join('Users u', 'u.IdUser = t.AssignedUserId', 'left');
        }

        $ticket = $builder->get()->getRowArray();

        if ($ticket === null) {
            return null;
        }

        // El correo se compara con el propio ticket (sin distinguir mayúsculas).
        $owner = $ticket['RequesterEmail'] ?? db_connect()->table('Tickets')->select('RequesterEmail')->where('IdTicket', $id)->get()->getRow('RequesterEmail');

        return strcasecmp((string) $owner, $email) === 0 ? $ticket : null;
    }

    /**
     * Fecha AAAA-MM-DD del query string, o null si no viene o no es válida.
     */
    private function dateParam(string $name): ?string
    {
        $value = trim((string) $this->request->getGet($name));
        $date  = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    /**
     * Cuerpo de la petición (JSON o formulario).
     *
     * @return array<string, mixed>
     */
    private function input(): array
    {
        if (str_contains($this->request->getHeaderLine('Content-Type'), 'json')) {
            $data = $this->request->getJSON(true);

            return is_array($data) ? $data : [];
        }

        return $this->request->getPost() ?? [];
    }

    /**
     * Respuestas del formulario adicional, saneadas.
     *
     * @param array<string, mixed> $input
     *
     * @return array{0: string, 1: list<array{key: string, label: string, value: string}>}
     */
    private function formAnswers(array $input): array
    {
        $formKey = trim((string) ($input['FormKey'] ?? ''));

        if ($formKey === '' || ! preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $formKey)) {
            return ['', []];
        }

        $raw = $input['Answers'] ?? [];

        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        $clean = [];

        foreach (array_slice(is_array($raw) ? $raw : [], 0, 120) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $value = $item['value'] ?? '';

            $clean[] = [
                'key'   => mb_substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($item['key'] ?? '')) ?? '', 0, 60),
                'label' => mb_substr(trim((string) ($item['label'] ?? '')), 0, 150),
                'value' => mb_substr(is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE), 0, 8000),
            ];
        }

        return [$formKey, $clean];
    }

    /**
     * Archivos enviados como "file" o "files[]".
     *
     * @return list<UploadedFile>
     */
    private function uploadedFiles(): array
    {
        $files = [];

        foreach (['file', 'files'] as $name) {
            $value = $this->request->getFileMultiple($name) ?? [];

            if ($value === [] && ($single = $this->request->getFile($name)) !== null) {
                $value = [$single];
            }

            foreach ($value as $file) {
                if ($file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function checkFile(UploadedFile $file): ?string
    {
        $name = $file->getClientName();

        if (! $file->isValid()) {
            return 'No se pudo recibir "' . $name . '".';
        }

        if ($file->getSize() > $this->config->maxUploadMb * 1024 * 1024) {
            return '"' . $name . '" supera el máximo de ' . $this->config->maxUploadMb . ' MB.';
        }

        $extension = strtolower($file->getClientExtension());

        if (! in_array($extension, $this->config->allowedExtensions, true)) {
            return 'Tipo de archivo no permitido: "' . $name . '".';
        }

        // Imágenes y PDF: el contenido real debe coincidir con la extensión.
        if (in_array($extension, self::CONTENT_CHECKED, true)) {
            $alias   = ['jpeg' => 'jpg', 'jpe' => 'jpg'];
            $guessed = strtolower((string) $file->guessExtension());

            if (($alias[$guessed] ?? $guessed) !== ($alias[$extension] ?? $extension)) {
                return 'El contenido de "' . $name . '" no coincide con su extensión.';
            }
        }

        return null;
    }

    private function saveFile(UploadedFile $file, int $ticketId): void
    {
        $folder = 'tickets/' . $ticketId;
        $name   = bin2hex(random_bytes(10)) . '.' . strtolower($file->getClientExtension());

        $file->move(WRITEPATH . 'uploads/' . $folder, $name);

        // Nombre original, sin rutas ni caracteres de control.
        $original = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $file->getClientName()) ?? 'archivo';

        $saved = model(TicketAttachmentModel::class)->insert([
            'TicketId' => $ticketId,
            'FileName' => mb_substr($original, 0, 255) ?: 'archivo',
            'FilePath' => $folder . '/' . $name,
        ]);

        if ($saved === false) {
            throw new \RuntimeException('No se pudo registrar el adjunto ' . $file->getClientName());
        }
    }
}
