<?php

namespace App\Controllers\Panel;

use App\Libraries\TicketRepository;
use App\Models\TicketFormAnswerModel;
use App\Models\TicketMessageModel;
use App\Models\TicketModel;

class Tickets extends BasePanelController
{
    private const FILTER_KEYS = ['q', 'status', 'priority', 'company', 'branch', 'category', 'assigned', 'sort'];

    /**
     * GET /panel/tickets
     */
    public function index()
    {
        $filters = [];

        foreach (self::FILTER_KEYS as $key) {
            $filters[$key] = trim((string) ($this->request->getGet($key) ?? ''));
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->config->perPage;
        $result  = (new TicketRepository())->paginate($filters, $page, $perPage, $this->user['id'] ?? null);

        $active = match (true) {
            $filters['assigned'] === 'me'   => 'mine',
            $filters['assigned'] === 'none' => 'unassigned',
            default                         => 'tickets',
        };

        return $this->render('tickets/index', [
            'active'  => $active,
            'title'   => match ($active) {
                'mine'       => 'Mis tickets asignados',
                'unassigned' => 'Tickets sin asignar',
                default      => 'Tickets',
            },
            'filters' => $filters,
            'tickets' => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page,
            'pages'   => max(1, (int) ceil($result['total'] / $perPage)),
            'lookups' => $this->lookups(),
        ]);
    }

    /**
     * GET /panel/tickets/{id}
     */
    public function show(int $id)
    {
        $ticket = (new TicketRepository())->find($id);

        if ($ticket === null) {
            return redirect()->to(site_url('panel/tickets'))->with('error', 'El ticket no existe.');
        }

        $db = db_connect();

        return $this->render('tickets/show', [
            'active'      => 'tickets',
            'title'       => ticket_code($id),
            'ticket'      => $ticket,
            'messages'    => model(TicketMessageModel::class)->forTicket($id),
            'formAnswers' => $this->formAnswers($id),
            'attachments' => $db->table('TicketAttachments')->where('TicketId', $id)->orderBy('CreatedAt')->get()->getResultArray(),
            'history'     => $db->table('Tickets')
                ->select('IdTicket, Subject, Status, CreatedAt')
                ->where('RequesterEmail', $ticket['RequesterEmail'])
                ->where('IdTicket !=', $id)
                ->orderBy('CreatedAt', 'DESC')
                ->limit(5)
                ->get()
                ->getResultArray(),
            'lookups'     => $this->lookups(),
        ]);
    }

    /**
     * POST /panel/tickets/{id}
     * Cambia estado, prioridad o agente asignado.
     */
    public function update(int $id)
    {
        $model = model(TicketModel::class);

        if ($model->find($id) === null) {
            return redirect()->to(site_url('panel/tickets'))->with('error', 'El ticket no existe.');
        }

        $data = [];

        $status = $this->request->getPost('Status');
        if (is_string($status) && isset($this->config->statuses[$status])) {
            $data['Status'] = $status;
        }

        $priority = $this->request->getPost('Priority');
        if (is_string($priority) && isset($this->config->priorities[$priority])) {
            $data['Priority'] = $priority;
        }

        $assigned = $this->request->getPost('AssignedUserId');
        if ($assigned !== null) {
            $assigned = trim((string) $assigned);
            $exists   = $assigned === '' || db_connect()->table('Users')->where('IdUser', $assigned)->countAllResults() > 0;

            if (! $exists) {
                return redirect()->back()->with('error', 'El agente seleccionado no existe.');
            }

            $data['AssignedUserId'] = $assigned === '' ? null : $assigned;
        }

        if ($data === []) {
            return redirect()->back();
        }

        // Solo se validan los campos que cambian.
        $model->skipValidation(true)->update($id, $data);

        return redirect()->to(site_url("panel/tickets/{$id}"))->with('success', 'Ticket actualizado.');
    }

    /**
     * GET /panel/tickets/{id}/messages?after={MessageId}
     * Mensajes nuevos para refrescar el chat (JSON).
     */
    public function messages(int $id)
    {
        $after = (int) ($this->request->getGet('after') ?? 0);

        $rows = model(TicketMessageModel::class)
            ->where('IdTicket', $id)
            ->where('MessageId >', $after)
            ->orderBy('MessageId', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'messages' => array_map(fn ($m) => $this->messagePayload($m), $rows),
        ]);
    }

    /**
     * POST /panel/tickets/{id}/messages
     * Respuesta del agente en el chat.
     */
    public function addMessage(int $id)
    {
        $isAjax  = $this->request->isAJAX();
        $message = trim((string) $this->request->getPost('Message'));

        if (model(TicketModel::class)->find($id) === null || $message === '') {
            $error = $message === '' ? 'Escribe un mensaje.' : 'El ticket no existe.';

            return $isAjax
                ? $this->response->setStatusCode(422)->setJSON(['message' => $error, 'csrf' => csrf_hash()])
                : redirect()->back()->with('error', $error);
        }

        $model = model(TicketMessageModel::class);

        $model->insert([
            'IdTicket'   => $id,
            'SenderType' => 'agente',
            'SenderName' => $this->user['name'],
            'Message'    => $message,
        ]);

        $row = $model->find($model->getInsertID());

        if (! $isAjax) {
            return redirect()->to(site_url("panel/tickets/{$id}") . '#chat');
        }

        return $this->response->setJSON([
            'message' => $row ? $this->messagePayload($row) : null,
            'csrf'    => csrf_hash(),
        ]);
    }

    /**
     * GET /panel/tickets/nuevo
     */
    public function create()
    {
        return $this->render('tickets/create', [
            'active'  => 'new',
            'title'   => 'Nueva solicitud',
            'lookups' => $this->lookups(),
            'errors'  => session()->getFlashdata('errors') ?? [],
        ]);
    }

    /**
     * POST /panel/tickets
     */
    public function store()
    {
        $post = $this->request->getPost();

        $data = [
            'CodCompanies'   => trim((string) ($post['CodCompanies'] ?? '')),
            'CodBranches'    => trim((string) ($post['CodBranches'] ?? '')),
            'RequesterEmail' => trim((string) ($post['RequesterEmail'] ?? '')),
            'Subject'        => trim((string) ($post['Subject'] ?? '')),
            'IdCategory'     => (int) ($post['IdCategory'] ?? 0) ?: '',
            'Priority'       => (string) ($post['Priority'] ?? ''),
            'Status'         => 'abierto',
        ];

        if (! empty($post['IdSubCategory'])) {
            $data['IdSubCategory'] = (int) $post['IdSubCategory'];
        }

        if (! empty($post['AssignedUserId'])) {
            $data['AssignedUserId'] = (string) $post['AssignedUserId'];
        }

        $description = trim((string) ($post['Description'] ?? ''));
        $model       = model(TicketModel::class);

        $branchOk = db_connect()->table('Branches')
            ->where('CodBranches', $data['CodBranches'])
            ->where('CodCompanies', $data['CodCompanies'])
            ->countAllResults() > 0;

        if (! $branchOk) {
            return redirect()->back()->withInput()->with('errors', ['CodBranches' => 'La sucursal no pertenece a la compañía seleccionada.']);
        }

        if (! $model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $id = (int) $model->getInsertID();

        if ($description !== '') {
            model(TicketMessageModel::class)->insert([
                'IdTicket'   => $id,
                'SenderType' => 'cliente',
                'SenderName' => $data['RequesterEmail'],
                'Message'    => $description,
            ]);
        }

        return redirect()->to(site_url("panel/tickets/{$id}"))
            ->with('success', 'Ticket ' . ticket_code($id) . ' creado.');
    }

    /**
     * GET /panel/tickets/{id}/adjuntos/{attachmentId}
     * Descarga un adjunto guardado en writable/uploads.
     */
    public function attachment(int $id, int $attachmentId)
    {
        $file = db_connect()->table('TicketAttachments')
            ->where('AttachmentId', $attachmentId)
            ->where('TicketId', $id)
            ->get()
            ->getRowArray();

        if ($file === null) {
            return redirect()->to(site_url("panel/tickets/{$id}"))->with('error', 'El adjunto no existe.');
        }

        if (preg_match('#^https?://#i', (string) $file['FilePath'])) {
            return redirect()->to($file['FilePath']);
        }

        $base = realpath(WRITEPATH . 'uploads');
        $path = realpath(WRITEPATH . 'uploads/' . ltrim((string) $file['FilePath'], '/\\'));

        // Evita salir de writable/uploads con rutas manipuladas.
        if ($base === false || $path === false || ! str_starts_with($path, $base . DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return redirect()->to(site_url("panel/tickets/{$id}"))->with('error', 'El archivo del adjunto no se encuentra en el servidor.');
        }

        return $this->response->download($path, null)->setFileName((string) $file['FileName']);
    }

    /**
     * Respuestas del formulario adicional del ticket (vacío si la tabla
     * TicketFormAnswers aún no existe).
     *
     * @return list<array<string, mixed>>
     */
    private function formAnswers(int $id): array
    {
        try {
            return model(TicketFormAnswerModel::class)->forTicket($id);
        } catch (\Throwable $e) {
            log_message('warning', '[Panel] No se pudieron leer las respuestas del formulario: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private function messagePayload(array $message): array
    {
        return [
            'id'   => (int) $message['MessageId'],
            'html' => view('panel/partials/message', ['m' => $message]),
        ];
    }
}
