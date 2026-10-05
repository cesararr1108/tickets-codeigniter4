<?php

namespace App\Controllers\Panel;

use App\Libraries\TicketRepository;
use App\Models\TicketEscalationModel;
use App\Models\TicketFormAnswerModel;
use App\Libraries\TicketNotifier;
use App\Models\TicketMessageModel;
use App\Models\TicketModel;

class Tickets extends BasePanelController
{
    private const FILTER_KEYS = ['q', 'status', 'priority', 'company', 'branch', 'category', 'assigned', 'escalated', 'sort'];

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
            $filters['escalated'] === '1'   => 'escalated',
            $filters['assigned'] === 'me'   => 'mine',
            $filters['assigned'] === 'none' => 'unassigned',
            default                         => 'tickets',
        };

        return $this->render('tickets/index', [
            'active'  => $active,
            'title'   => match ($active) {
                'escalated'  => 'Tickets escalados',
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

        $me         = panel_user_id();
        $isAdmin    = panel_is_admin();
        $isAssignee = $ticket['AssignedUserId'] !== null && (string) $ticket['AssignedUserId'] === $me;
        $escalation = model(TicketEscalationModel::class);

        return $this->render('tickets/show', [
            'active'      => 'tickets',
            'title'       => ticket_code($id),
            'ticket'      => $ticket,
            'perm'        => [
                'isAdmin'  => $isAdmin,
                // Solo el responsable conversa con el solicitante.
                'canChat'  => $isAssignee,
                // Estado/prioridad: el administrador o el responsable.
                'canManage' => $isAdmin || $isAssignee,
                // Un técnico solo puede tomar tickets sin responsable.
                'canTake'  => ! $isAssignee && ($isAdmin || $ticket['AssignedUserId'] === null),
                'canEscalate' => $isAssignee && TicketEscalationModel::available(),
                'isAssignee'  => $isAssignee,
            ],
            'escalation'  => $escalation->activeFor($id),
            'escalations' => $escalation->historyFor($id),
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
        $model  = model(TicketModel::class);
        $ticket = $model->find($id);

        if ($ticket === null) {
            return redirect()->to(site_url('panel/tickets'))->with('error', 'El ticket no existe.');
        }

        $me         = panel_user_id();
        $isAdmin    = panel_is_admin();
        $current    = $ticket['AssignedUserId'] === null ? '' : (string) $ticket['AssignedUserId'];
        $isAssignee = $current !== '' && $current === $me;

        // Técnico: solo gestiona tickets propios o sin responsable, y solo se asigna a sí mismo.
        if (! $isAdmin) {
            $assignedPost = $this->request->getPost('AssignedUserId');
            $assignedPost = $assignedPost === null ? $current : trim((string) $assignedPost);

            if ($current !== '' && ! $isAssignee) {
                return redirect()->back()->with('error', 'Este ticket lo atiende otra persona. Solo el administrador puede reasignarlo.');
            }

            if ($assignedPost !== '' && $assignedPost !== $me) {
                return redirect()->back()->with('error', 'Solo puedes asignarte el ticket a ti mismo.');
            }

            if ($assignedPost === '' && ! $isAssignee) {
                return redirect()->back()->with('error', 'Primero toma el ticket para gestionarlo.');
            }
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

        $this->notifyRequester($ticket, $data);

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

        $ticket = model(TicketModel::class)->find($id);

        if ($ticket !== null && (string) ($ticket['AssignedUserId'] ?? '') !== panel_user_id()) {
            $error = 'Solo el responsable del ticket puede conversar con el solicitante. Toma el ticket primero.';

            return $isAjax
                ? $this->response->setStatusCode(403)->setJSON(['message' => $error, 'csrf' => csrf_hash()])
                : redirect()->back()->with('error', $error);
        }

        if ($ticket === null || $message === '') {
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
            if (! panel_is_admin() && (string) $post['AssignedUserId'] !== panel_user_id()) {
                return redirect()->back()->withInput()->with('errors', ['AssignedUserId' => 'Solo puedes asignarte el ticket a ti mismo.']);
            }

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

        if ($created = $model->find($id)) {
            (new TicketNotifier())->ticketCreated($created, panel_user_id());
        }

        return redirect()->to(site_url("panel/tickets/{$id}"))
            ->with('success', 'Ticket ' . ticket_code($id) . ' creado.');
    }

    /**
     * POST /panel/tickets/{id}/tomar
     * El usuario se asigna el ticket (técnico: solo si no tiene responsable).
     */
    public function take(int $id)
    {
        $model  = model(TicketModel::class);
        $ticket = $model->find($id);

        if ($ticket === null) {
            return redirect()->to(site_url('panel/tickets'))->with('error', 'El ticket no existe.');
        }

        if ($ticket['AssignedUserId'] !== null && ! panel_is_admin()) {
            return redirect()->back()->with('error', 'Este ticket ya tiene responsable. Solo el administrador puede reasignarlo.');
        }

        $data = ['AssignedUserId' => panel_user_id()];

        if ($ticket['Status'] === 'abierto') {
            $data['Status'] = 'en_progreso';
        }

        $model->skipValidation(true)->update($id, $data);

        $this->notifyRequester($ticket, $data);

        return redirect()->to(site_url("panel/tickets/{$id}"))->with('success', 'Ahora eres el responsable de este ticket.');
    }

    /**
     * POST /panel/tickets/{id}/escalar
     * El responsable escala el ticket al administrador explicando el motivo.
     */
    public function escalate(int $id)
    {
        $ticket = model(TicketModel::class)->find($id);
        $reason = trim((string) $this->request->getPost('Reason'));

        if ($ticket === null || (string) ($ticket['AssignedUserId'] ?? '') !== panel_user_id()) {
            return redirect()->back()->with('error', 'Solo el responsable del ticket puede escalarlo.');
        }

        if (! TicketEscalationModel::available()) {
            return redirect()->back()->with('error', 'Falta crear la tabla TicketEscalations (app/Database/sql/RolesYEscalamientos.sql).');
        }

        $model = model(TicketEscalationModel::class);

        if ($model->activeFor($id) !== null) {
            return redirect()->back()->with('error', 'El ticket ya está escalado.');
        }

        if ($reason === '' || mb_strlen($reason) > 2000) {
            return redirect()->back()->with('error', 'Explica en máximo 2000 caracteres por qué no puedes resolverlo.');
        }

        $model->insert([
            'IdTicket'        => $id,
            'EscalatedBy'     => panel_user_id(),
            'EscalatedByName' => $this->user['name'] ?? panel_user_id(),
            'Reason'          => $reason,
        ]);

        (new TicketNotifier())->requesterEscalated($ticket);

        return redirect()->to(site_url("panel/tickets/{$id}"))->with('success', 'Ticket escalado al administrador.');
    }

    /**
     * POST /panel/tickets/{id}/escalamiento
     * El administrador atiende el escalamiento: deja una nota y puede
     * reasignar el ticket (a sí mismo o a otro técnico).
     */
    public function resolveEscalation(int $id)
    {
        if (! panel_is_admin()) {
            return redirect()->back()->with('error', 'Solo el administrador atiende los escalamientos.');
        }

        $model      = model(TicketEscalationModel::class);
        $escalation = $model->activeFor($id);

        if ($escalation === null) {
            return redirect()->back()->with('error', 'El ticket no tiene un escalamiento activo.');
        }

        $note     = trim((string) $this->request->getPost('ResolutionNote'));
        $assignTo = trim((string) $this->request->getPost('AssignedUserId'));

        if ($assignTo !== '') {
            if (db_connect()->table('Users')->where('IdUser', $assignTo)->countAllResults() === 0) {
                return redirect()->back()->with('error', 'El usuario seleccionado no existe.');
            }

            $before = model(TicketModel::class)->find($id);

            model(TicketModel::class)->skipValidation(true)->update($id, ['AssignedUserId' => $assignTo]);

            if ($before !== null) {
                $this->notifyRequester($before, ['AssignedUserId' => $assignTo]);
            }
        }

        $model->update($escalation['IdEscalation'], [
            'ResolvedAt'     => gmdate('Y-m-d H:i:s'),
            'ResolvedBy'     => panel_user_id(),
            'ResolvedByName' => $this->user['name'] ?? panel_user_id(),
            'ResolutionNote' => mb_substr($note, 0, 2000),
        ]);

        return redirect()->to(site_url("panel/tickets/{$id}"))->with('success', 'Escalamiento atendido.');
    }

    /**
     * POST /panel/tickets/{id}/seguimiento
     * Guarda los campos que llena el área (estado del proyecto, diagnóstico,
     * solución...). Ver Config\Tickets::$areaFields.
     */
    public function followUp(int $id)
    {
        $formKey = (string) $this->request->getPost('FormKey');
        $fields  = $this->config->areaFields[$formKey] ?? null;

        if ($fields === null || model(TicketModel::class)->find($id) === null) {
            return redirect()->to(site_url("panel/tickets/{$id}"))->with('error', 'No hay campos de seguimiento para este ticket.');
        }

        $ticket = model(TicketModel::class)->find($id);

        if (! panel_is_admin() && (string) ($ticket['AssignedUserId'] ?? '') !== panel_user_id()) {
            return redirect()->to(site_url("panel/tickets/{$id}"))->with('error', 'Solo el administrador o el responsable pueden actualizar el seguimiento.');
        }

        try {
            model(TicketFormAnswerModel::class)->saveAreaFields($id, $formKey, $fields, (array) $this->request->getPost('Area'));
        } catch (\Throwable $e) {
            log_message('error', '[Panel] Seguimiento: ' . $e->getMessage());

            return redirect()->to(site_url("panel/tickets/{$id}"))->with('error', 'No se pudo guardar el seguimiento.');
        }

        return redirect()->to(site_url("panel/tickets/{$id}"))->with('success', 'Seguimiento actualizado.');
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
     * Push al solicitante: finalizado (prioridad), nuevo responsable o cambio de estado.
     *
     * @param array<string, mixed> $before Ticket antes del cambio
     * @param array<string, mixed> $data   Campos que se actualizaron
     */
    private function notifyRequester(array $before, array $data): void
    {
        $after    = array_merge($before, $data);
        $notifier = new TicketNotifier();

        $statusChanged   = isset($data['Status']) && $data['Status'] !== $before['Status'];
        $assigneeChanged = array_key_exists('AssignedUserId', $data)
            && $data['AssignedUserId'] !== null
            && (string) $data['AssignedUserId'] !== (string) ($before['AssignedUserId'] ?? '');

        if ($statusChanged && $data['Status'] === 'cerrado') {
            $notifier->requesterStatus($after);
        } elseif ($assigneeChanged) {
            // Un solo aviso aunque además pase de "abierto" a "en progreso".
            $notifier->requesterAssigned($after);
        } elseif ($statusChanged) {
            $notifier->requesterStatus($after);
        }
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
