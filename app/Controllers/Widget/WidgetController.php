<?php

namespace App\Controllers\Widget;

use App\Controllers\BaseController;
use App\Libraries\WidgetToken;
use App\Models\TicketAttachmentModel;
use App\Models\TicketMessageModel;
use App\Models\TicketModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Widget;

/**
 * API pública del widget (/widget/*).
 *
 * Solo expone lo mínimo que necesita el widget y siempre respeta los datos
 * firmados en el token (compañía, sucursal, correo): lo que envíe el
 * navegador no puede contradecirlos.
 */
class WidgetController extends BaseController
{
    use ResponseTrait;

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

    /**
     * GET /widget/me — datos que el anfitrión fijó en el token.
     */
    public function me()
    {
        $db = db_connect();

        $company = null;
        $branch  = null;

        if (! empty($this->claims['company'])) {
            $company = $db->table('Companies')
                ->select('CodCompanies AS id, Companies AS name')
                ->where('CodCompanies', $this->claims['company'])
                ->get()->getRowArray();
        }

        if (! empty($this->claims['branch'])) {
            $builder = $db->table('Branches')
                ->select('CodBranches AS id, Branches AS name, CodCompanies AS company')
                ->where('CodBranches', $this->claims['branch']);

            if ($company !== null) {
                $builder->where('CodCompanies', $company['id']);
            }

            $branch = $builder->get()->getRowArray();

            // Sucursal sin compañía fijada en el token: se deduce de la sucursal.
            if ($branch !== null && $company === null) {
                $company = $db->table('Companies')
                    ->select('CodCompanies AS id, Companies AS name')
                    ->where('CodCompanies', $branch['company'])
                    ->get()->getRowArray();
            }
        }

        return $this->respond([
            'email'   => $this->claims['email'] ?? null,
            'name'    => $this->claims['name'] ?? null,
            'phone'   => $this->claims['phone'] ?? null,
            'company' => $company,
            'branch'  => $branch,
        ]);
    }

    /**
     * GET /widget/companies
     */
    public function companies()
    {
        $builder = db_connect()->table('Companies')->select('CodCompanies, Companies')->orderBy('Companies');

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

        if (! empty($this->claims['branch'])) {
            $builder->where('CodBranches', $this->claims['branch']);
        }

        return $this->respond($builder->get()->getResultArray());
    }

    /**
     * GET /widget/categories
     */
    public function categories()
    {
        return $this->respond(
            db_connect()->table('Category')->select('IdCategory, Category, Description')->orderBy('Category')->get()->getResultArray()
        );
    }

    /**
     * GET /widget/subcategories
     */
    public function subcategories()
    {
        return $this->respond(
            db_connect()->table('SubCategory')->select('IdSubCategory, SubCategory, IdCategory')->orderBy('SubCategory')->get()->getResultArray()
        );
    }

    /**
     * GET /widget/subcategories/category/{id}
     */
    public function subcategoriesByCategory($id = null)
    {
        return $this->respond(
            db_connect()->table('SubCategory')
                ->select('IdSubCategory, SubCategory, IdCategory')
                ->where('IdCategory', (int) $id)
                ->orderBy('SubCategory')
                ->get()->getResultArray()
        );
    }

    /**
     * GET /widget/tickets — solo los del solicitante del token.
     */
    public function tickets()
    {
        if (empty($this->claims['email'])) {
            // Sin correo verificado no se puede saber de quién son los tickets.
            return $this->respond([]);
        }

        $builder = db_connect()->table('Tickets')
            ->select('IdTicket, Subject, Status, Priority, CreatedAt')
            ->where('RequesterEmail', $this->claims['email'])
            ->orderBy('CreatedAt', 'DESC')
            ->limit(50);

        if (($company = $this->lockedCompany()) !== null) {
            $builder->where('CodCompanies', $company);
        }

        return $this->respond($builder->get()->getResultArray());
    }

    /**
     * POST /widget/tickets
     */
    public function createTicket()
    {
        $input = $this->input();
        $db    = db_connect();

        $errors = [];

        // Datos fijados por el token tienen prioridad sobre los del navegador.
        $email = $this->claims['email'] ?? trim((string) ($input['RequesterEmail'] ?? ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            $errors['RequesterEmail'] = 'Ingresa un correo válido.';
        }

        $company = $this->lockedCompany() ?? trim((string) ($input['CodCompanies'] ?? ''));
        $branch  = $this->claims['branch'] ?? trim((string) ($input['CodBranches'] ?? ''));

        $branchRow = $db->table('Branches')->where('CodBranches', $branch)->get()->getRowArray();

        if ($branchRow === null) {
            $errors['CodBranches'] = 'La sucursal no existe.';
        } elseif ($company === '' || $branchRow['CodCompanies'] !== $company) {
            // También cubre el caso en que el token no fija la compañía: se toma de la sucursal
            // solo si el navegador la envía igual; nunca se acepta una combinación distinta.
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

        if ($description === '' || mb_strlen($description) > 4000) {
            $errors['Description'] = 'La descripción es obligatoria (máximo 4000 caracteres).';
        }

        $file = $this->request->getFile('file');
        $file = $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE ? $file : null;

        if ($file !== null && ($fileError = $this->checkFile($file)) !== null) {
            $errors['file'] = $fileError;
        }

        if ($errors !== []) {
            return $this->respond(['status' => 422, 'message' => implode(' ', $errors), 'messages' => $errors], 422);
        }

        // Límite adicional por correo (además del límite por IP).
        if (! service('throttler')->check('wgm_' . md5(mb_strtolower($email)), $this->config->ticketsPerHour, HOUR)) {
            return $this->respond(['status' => 429, 'message' => 'Has creado demasiados tickets. Intenta más tarde.'], 429);
        }

        $model = model(TicketModel::class);

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
            return $this->respond(['status' => 422, 'message' => implode(' ', $model->errors()), 'messages' => $model->errors()], 422);
        }

        $id = (int) $model->getInsertID();

        // Nombre y teléfono del token van al primer mensaje del chat.
        $header = array_filter([
            isset($this->claims['name']) ? 'Nombre: ' . $this->claims['name'] : null,
            isset($this->claims['phone']) ? 'Teléfono: ' . $this->claims['phone'] : null,
        ]);

        model(TicketMessageModel::class)->insert([
            'IdTicket'   => $id,
            'SenderType' => 'cliente',
            'SenderName' => mb_substr($this->claims['name'] ?? $email, 0, 150),
            'Message'    => ($header ? implode("\n", $header) . "\n\n" : '') . $description,
        ]);

        if ($file !== null) {
            $this->saveFile($file, $id);
        }

        return $this->respondCreated(['IdTicket' => $id]);
    }

    /**
     * Compañía fijada por el token o, si solo fija sucursal, la de esa sucursal.
     */
    private function lockedCompany(): ?string
    {
        if (! empty($this->claims['company'])) {
            return (string) $this->claims['company'];
        }

        if (! empty($this->claims['branch'])) {
            $row = db_connect()->table('Branches')->select('CodCompanies')->where('CodBranches', $this->claims['branch'])->get()->getRowArray();

            return $row['CodCompanies'] ?? null;
        }

        return null;
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

    private function checkFile(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return 'No se pudo subir el archivo.';
        }

        if ($file->getSize() > $this->config->maxUploadMb * 1024 * 1024) {
            return "El archivo supera {$this->config->maxUploadMb} MB.";
        }

        $extension = strtolower($file->getClientExtension());

        if (! in_array($extension, $this->config->allowedExtensions, true)) {
            return 'Tipo de archivo no permitido. Permitidos: ' . implode(', ', $this->config->allowedExtensions) . '.';
        }

        // La extensión declarada debe coincidir con el contenido real.
        $guessed = $file->guessExtension();
        $aliases = ['jpeg' => 'jpg', 'jpe' => 'jpg', 'csv' => 'txt', 'docx' => 'zip', 'xlsx' => 'zip', 'doc' => 'xls'];
        $norm    = static fn (?string $e) => $aliases[$e ?? ''] ?? $e;

        if ($guessed !== null && $norm($guessed) !== $norm($extension) && ! in_array($extension, ['txt', 'csv', 'doc', 'docx', 'xls', 'xlsx'], true)) {
            return 'El contenido del archivo no coincide con su extensión.';
        }

        return null;
    }

    private function saveFile(UploadedFile $file, int $ticketId): void
    {
        $dir  = WRITEPATH . 'uploads/tickets/' . $ticketId;
        $name = $file->getRandomName();

        $file->move($dir, $name);

        // Nombre original, sin rutas ni caracteres de control.
        $original = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $file->getClientName()) ?? 'archivo';

        model(TicketAttachmentModel::class)->insert([
            'TicketId' => $ticketId,
            'FileName' => mb_substr($original, 0, 255) ?: 'archivo',
            'FilePath' => "tickets/{$ticketId}/{$name}",
        ]);
    }
}
