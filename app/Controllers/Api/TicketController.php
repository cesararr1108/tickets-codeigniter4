<?php

namespace App\Controllers\Api;

use App\Models\TicketAttachmentModel;
use App\Models\TicketFormAnswerModel;
use App\Models\TicketMessageModel;
use App\Models\TicketModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class TicketController extends BaseApiController
{
    protected string $modelName = TicketModel::class;

    /** Tamaño máximo por archivo adjunto (bytes). */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /** Extensiones permitidas para adjuntos. */
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'zip', 'rar', 'msg', 'eml',
    ];

    /**
     * POST /tickets
     *
     * Acepta JSON o multipart/form-data con:
     *   - campos del ticket (CodCompanies, CodBranches, Subject, ...)
     *   - Description (opcional): se guarda como primer mensaje
     *   - FormKey + Answers (opcional): respuestas del formulario adicional;
     *     Answers es una lista [{key, label, value}] (o su JSON en multipart)
     *   - file / files[] (opcional): adjuntos
     *
     * Todo se guarda en una transacción: si algo falla no queda nada a medias.
     */
    public function create()
    {
        $payload = $this->request->getPost() ?: $this->getPayload();

        $answers = $payload['Answers'] ?? [];
        if (is_string($answers)) {
            $answers = json_decode($answers, true) ?: [];
        }

        $formKey     = trim((string) ($payload['FormKey'] ?? ''));
        $description = trim((string) ($payload['Description'] ?? ''));
        $senderName  = trim((string) ($payload['SenderName'] ?? ($payload['RequesterEmail'] ?? '')));

        $files = $this->uploadedFiles();

        foreach ($files as $file) {
            if ($error = $this->fileError($file)) {
                return $this->failValidationErrors(['file' => $error]);
            }
        }

        $ticketFields = array_intersect_key($payload, array_flip([
            'CodCompanies', 'CodBranches', 'AssignedUserId', 'RequesterEmail',
            'Subject', 'IdCategory', 'IdSubCategory', 'Priority', 'Status',
        ]));

        $db    = db_connect();
        $model = model(TicketModel::class);

        $db->transBegin();

        try {
            if (! $model->insert($ticketFields)) {
                $db->transRollback();

                return $this->failValidationErrors($model->errors());
            }

            $id = (int) $model->getInsertID();

            if ($description !== '') {
                model(TicketMessageModel::class)->insert([
                    'IdTicket'   => $id,
                    'SenderType' => 'cliente',
                    'SenderName' => mb_substr($senderName !== '' ? $senderName : 'Solicitante', 0, 150),
                    'Message'    => $description,
                ]);
            }

            if ($formKey !== '' && is_array($answers) && $answers !== []) {
                model(TicketFormAnswerModel::class)->saveForTicket($id, $formKey, $answers);
            }

            foreach ($files as $file) {
                $this->storeAttachment($id, $file);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Error de base de datos al guardar el ticket.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', '[API tickets] ' . $e->getMessage());

            return $this->failServerError('No fue posible guardar el ticket.');
        }

        return $this->respondCreated($model->find($id));
    }

    /** Estados que cuentan como pendientes / resueltos en "Mis tickets". */
    private const PENDING_STATUSES  = ['abierto', 'en_progreso'];
    private const RESOLVED_STATUSES = ['cerrado'];

    /**
     * GET /tickets/mine?email=...&status=pendientes|resueltos&page=1&perPage=5
     *
     * Tickets del solicitante (por correo), paginados, más el conteo de
     * pendientes y resueltos para las pestañas y el badge del widget.
     */
    public function mine()
    {
        $email = trim((string) $this->request->getGet('email'));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failValidationErrors(['email' => 'Indica el correo del solicitante.']);
        }

        $status  = $this->request->getGet('status') === 'resueltos' ? 'resueltos' : 'pendientes';
        $perPage = max(1, min(50, (int) ($this->request->getGet('perPage') ?: 5)));
        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));

        $db = db_connect();

        $count = static fn (array $statuses) => $db->table('Tickets')
            ->where('RequesterEmail', $email)
            ->whereIn('Status', $statuses)
            ->countAllResults();

        $counts = [
            'pendientes' => $count(self::PENDING_STATUSES),
            'resueltos'  => $count(self::RESOLVED_STATUSES),
        ];

        $total = $counts[$status];
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min($page, $pages);

        $rows = $db->table('Tickets t')
            ->select('t.IdTicket, t.Subject, t.Status, t.Priority, t.CreatedAt, cat.Category, sc.SubCategory')
            ->join('Category cat', 'cat.IdCategory = t.IdCategory', 'left')
            ->join('SubCategory sc', 'sc.IdSubCategory = t.IdSubCategory', 'left')
            ->where('t.RequesterEmail', $email)
            ->whereIn('t.Status', $status === 'resueltos' ? self::RESOLVED_STATUSES : self::PENDING_STATUSES)
            ->orderBy('t.CreatedAt', 'DESC')
            ->orderBy('t.IdTicket', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return $this->respond([
            'data'    => $rows,
            'status'  => $status,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => $pages,
            'total'   => $total,
            'counts'  => $counts,
        ]);
    }

    /**
     * GET /tickets/{id}/detalle?email=...
     *
     * Detalle de un ticket para su solicitante: datos, respuestas del
     * formulario, seguimiento de TI, conversación y adjuntos. Solo responde
     * si el correo coincide con el del ticket.
     */
    public function requesterDetail($id = null)
    {
        $email = trim((string) $this->request->getGet('email'));

        $ticket = db_connect()->table('Tickets t')
            ->select('t.IdTicket, t.Subject, t.Status, t.Priority, t.CreatedAt, t.RequesterEmail,
                c.Companies, b.Branches, cat.Category, sc.SubCategory, u.FullName AS AssignedName')
            ->join('Companies c', 'c.CodCompanies = t.CodCompanies', 'left')
            ->join('Branches b', 'b.CodBranches = t.CodBranches', 'left')
            ->join('Category cat', 'cat.IdCategory = t.IdCategory', 'left')
            ->join('SubCategory sc', 'sc.IdSubCategory = t.IdSubCategory', 'left')
            ->join('Users u', 'u.IdUser = t.AssignedUserId', 'left')
            ->where('t.IdTicket', (int) $id)
            ->get()
            ->getRowArray();

        if ($ticket === null || $email === '' || strcasecmp((string) $ticket['RequesterEmail'], $email) !== 0) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        $answers = [];
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
            log_message('warning', '[API tickets] Respuestas del formulario: ' . $e->getMessage());
        }

        $messages = array_map(static fn ($m) => [
            'sender'    => $m['SenderName'],
            'type'      => $m['SenderType'],
            'message'   => $m['Message'],
            'createdAt' => $m['CreatedAt'],
        ], model(TicketMessageModel::class)->forTicket((int) $id));

        $attachments = array_map(static fn ($a) => $a['FileName'], model(TicketAttachmentModel::class)
            ->where('TicketId', (int) $id)
            ->findAll());

        unset($ticket['RequesterEmail']);

        return $this->respond([
            'ticket'      => $ticket,
            'answers'     => $answers,
            'followUp'    => $followUp,
            'messages'    => $messages,
            'attachments' => $attachments,
        ]);
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

    private function fileError(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return 'No se pudo recibir "' . $file->getClientName() . '": ' . $file->getErrorString();
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return '"' . $file->getClientName() . '" supera el máximo de 10 MB.';
        }

        $extension = strtolower($file->getClientExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return 'Tipo de archivo no permitido: "' . $file->getClientName() . '".';
        }

        return null;
    }

    /**
     * Guarda el archivo en writable/uploads/tickets/{id}/ y lo registra.
     * FilePath queda relativo a writable/uploads.
     */
    private function storeAttachment(int $idTicket, UploadedFile $file): void
    {
        $folder = 'tickets/' . $idTicket;
        $name   = bin2hex(random_bytes(10)) . '.' . strtolower($file->getClientExtension());

        $file->move(WRITEPATH . 'uploads/' . $folder, $name);

        $saved = model(TicketAttachmentModel::class)->insert([
            'TicketId' => $idTicket,
            'FileName' => mb_substr($file->getClientName(), 0, 255),
            'FilePath' => $folder . '/' . $name,
        ]);

        if ($saved === false) {
            throw new \RuntimeException('No se pudo registrar el adjunto ' . $file->getClientName());
        }
    }

    /**
     * GET /tickets/{id}/messages
     */
    public function messages($id = null)
    {
        if ($this->model()->find($id) === null) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        $messages = model(TicketMessageModel::class)->forTicket((int) $id);

        return $this->respond($messages);
    }

    /**
     * POST /tickets/{id}/messages
     */
    public function addMessage($id = null)
    {
        if ($this->model()->find($id) === null) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        $model = model(TicketMessageModel::class);
        $data  = $this->getPayload();
        $data['IdTicket'] = (int) $id;

        if (! $model->insert($data)) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated($model->find($model->getInsertID()));
    }

    /**
     * GET /tickets/{id}/attachments
     */
    public function attachments($id = null)
    {
        if ($this->model()->find($id) === null) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        $rows = model(TicketAttachmentModel::class)
            ->where('TicketId', $id)
            ->findAll();

        return $this->respond($rows);
    }

    /**
     * POST /tickets/{id}/attachments
     */
    public function addAttachment($id = null)
    {
        if ($this->model()->find($id) === null) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        $model = model(TicketAttachmentModel::class);
        $data  = $this->getPayload();
        $data['TicketId'] = (int) $id;

        if (! $model->insert($data)) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated($model->find($model->getInsertID()));
    }
}
