<?php

namespace App\Controllers\Panel;

use App\Libraries\DevelopmentBoard;
use App\Libraries\TicketNotifier;
use App\Models\TicketChangeModel;
use App\Models\TicketModel;
use App\Models\TicketTimeLogModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Seguimiento de desarrollos: plan del mes, ficha de cada desarrollo y
 * cierre de mes. Reglas en App\Libraries\DevelopmentBoard.
 *
 * El administrador planea, asigna, fija la fecha comprometida, resuelve
 * inconvenientes, marca recursos disponibles, aprueba y cierra el mes.
 * El programador asignado registra tareas, recursos, inconvenientes, la
 * fecha estimada y la evidencia.
 */
class Developments extends BasePanelController
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    private const MAX_IMAGE_SIZE   = 10 * 1024 * 1024;

    private DevelopmentBoard $board;

    private function board(): DevelopmentBoard
    {
        return $this->board ??= new DevelopmentBoard();
    }

    /**
     * GET /panel/desarrollos?mes=AAAA-MM
     */
    public function index()
    {
        if ($missing = $this->missingTables()) {
            return $missing;
        }

        $month   = DevelopmentBoard::validMonth($this->request->getGet('mes')) ?? DevelopmentBoard::currentMonth();
        $isAdmin = panel_is_admin();
        $all     = $this->board()->list($month);

        return $this->render('developments/plan', [
            'active'   => 'developments',
            'title'    => 'Plan de desarrollos',
            'month'    => $month,
            'summary'  => DevelopmentBoard::summary($all, $month),
            'rows'     => $isAdmin ? $all : array_values(array_filter($all, static fn ($d) => (string) $d['AssignedUserId'] === panel_user_id())),
            'backlog'  => $isAdmin ? $this->board()->backlog() : [],
            'closure'  => $this->board()->closure($month) ?? $this->board()->closure(DevelopmentBoard::shiftMonth($month, -1)),
            'closedMonth' => $this->board()->closure($month) !== null,
            'agents'   => $this->lookups()['agents'],
        ]);
    }

    /**
     * GET /panel/desarrollos/todos
     */
    public function all()
    {
        if ($missing = $this->missingTables()) {
            return $missing;
        }

        return $this->render('developments/list', [
            'active' => 'developments',
            'title'  => 'Desarrollos',
            'rows'   => $this->board()->list(null, panel_is_admin() ? null : panel_user_id()),
        ]);
    }

    /**
     * GET /panel/desarrollos/{id}
     */
    public function show(int $id)
    {
        if ($missing = $this->missingTables()) {
            return $missing;
        }

        $dev = $this->board()->find($id);

        if ($dev === null) {
            return redirect()->to(site_url('panel/desarrollos'))->with('error', 'El desarrollo no existe.');
        }

        $db = db_connect();

        return $this->render('developments/show', [
            'active'    => 'developments',
            'title'     => $dev['Code'],
            'dev'       => $dev,
            'canWork'   => DevelopmentBoard::canWork($dev),
            'isAdmin'   => panel_is_admin(),
            'tasks'     => $db->table('DevelopmentTasks')->where('IdDevelopment', $id)->orderBy('IdTask')->get()->getResultArray(),
            'resources' => $db->table('DevelopmentResources')->where('IdDevelopment', $id)->orderBy('IdResource')->get()->getResultArray(),
            'issues'    => $db->table('DevelopmentIssues')->where('IdDevelopment', $id)->orderBy('IdIssue', 'DESC')->get()->getResultArray(),
            'evidence'  => $db->table('DevelopmentEvidence')->where('IdDevelopment', $id)->orderBy('IdEvidence')->get()->getResultArray(),
            'agents'    => $this->lookups()['agents'],
        ]);
    }

    /**
     * POST /panel/desarrollos/planear
     * Agrega al plan de un mes un ticket de desarrollo (lo registra) o un
     * desarrollo sin mes. Solo el administrador.
     */
    public function plan()
    {
        if (! panel_is_admin()) {
            return redirect()->back()->with('error', 'Solo el administrador planea los desarrollos.');
        }

        $month     = DevelopmentBoard::validMonth($this->request->getPost('PlanMonth'));
        $committed = DevelopmentBoard::validDate($this->request->getPost('CommittedDate'));
        $userId    = trim((string) $this->request->getPost('AssignedUserId'));
        $ticketId  = (int) $this->request->getPost('IdTicket');
        $devId     = (int) $this->request->getPost('IdDevelopment');
        $db        = db_connect();

        if ($month === null || $committed === null) {
            return redirect()->back()->with('error', 'Indica el mes y la fecha de entrega comprometida.');
        }

        if ($userId === '' || $db->table('Users')->where('IdUser', $userId)->countAllResults() === 0) {
            return redirect()->back()->with('error', 'Elige el programador que hará el desarrollo.');
        }

        $data = [
            'AssignedUserId' => $userId,
            'PlanMonth'      => $month,
            'CommittedDate'  => $committed,
            'EstimatedDate'  => $committed,
        ];

        if ($devId > 0) {
            $dev = $this->board()->find($devId);

            if ($dev === null || $dev['ApprovedAt'] !== null) {
                return redirect()->back()->with('error', 'El desarrollo no existe o ya fue aprobado.');
            }

            $db->table('Developments')->where('IdDevelopment', $devId)->update($data);
        } else {
            $ticket = model(TicketModel::class)->find($ticketId);

            if ($ticket === null || $ticket['Status'] === 'cerrado') {
                return redirect()->back()->with('error', 'El ticket no existe o está cerrado.');
            }

            if ($this->board()->findByTicket($ticketId) !== null) {
                return redirect()->back()->with('error', 'Ese ticket ya tiene un desarrollo.');
            }

            $db->table('Developments')->insert($data + ['IdTicket' => $ticketId]);
            $devId = (int) $db->insertID();

            // El programador queda también como responsable del ticket.
            if ((string) ($ticket['AssignedUserId'] ?? '') !== $userId) {
                $change = ['AssignedUserId' => $userId] + ($ticket['Status'] === 'abierto' ? ['Status' => 'en_progreso'] : []);
                model(TicketModel::class)->skipValidation(true)->update($ticketId, $change);
                model(TicketChangeModel::class)->record($ticketId, $ticket, $change, $this->user);
            }
        }

        return redirect()->to(site_url('panel/desarrollos?mes=' . $month))
            ->with('success', DevelopmentBoard::code($devId) . ' agregado al plan de ' . DevelopmentBoard::monthName($month) . '.');
    }

    /**
     * POST /panel/desarrollos/{id}/quitar-plan
     */
    public function unplan(int $id)
    {
        $dev = $this->board()->find($id);

        if ($dev === null || ! panel_is_admin() || $dev['ApprovedAt'] !== null) {
            return redirect()->back()->with('error', 'Solo el administrador puede quitar del plan un desarrollo no aprobado.');
        }

        db_connect()->table('Developments')->where('IdDevelopment', $id)
            ->update(['PlanMonth' => null, 'CommittedDate' => null, 'EstimatedDate' => null]);

        return redirect()->back()->with('success', $dev['Code'] . ' volvió a "Por planear".');
    }

    /**
     * POST /panel/desarrollos/{id}
     * Administrador: programador y fecha comprometida. Programador: fecha estimada.
     */
    public function update(int $id)
    {
        [$dev, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        $data = [];

        $estimated = $this->request->getPost('EstimatedDate');
        if ($estimated !== null && ($estimated = DevelopmentBoard::validDate($estimated)) !== null) {
            $data['EstimatedDate'] = $estimated;
        }

        if (panel_is_admin()) {
            $committed = $this->request->getPost('CommittedDate');
            if ($committed !== null && ($committed = DevelopmentBoard::validDate($committed)) !== null) {
                $data['CommittedDate'] = $committed;
            }

            $userId = $this->request->getPost('AssignedUserId');
            if ($userId !== null) {
                $userId = trim((string) $userId);

                if ($userId !== '' && db_connect()->table('Users')->where('IdUser', $userId)->countAllResults() === 0) {
                    return $this->back($id, 'El programador seleccionado no existe.', false);
                }

                $data['AssignedUserId'] = $userId === '' ? null : $userId;
            }
        }

        if ($data === []) {
            return $this->back($id, 'No hubo cambios.');
        }

        db_connect()->table('Developments')->where('IdDevelopment', $id)->update($data);

        if (array_key_exists('AssignedUserId', $data) && $data['AssignedUserId'] !== null
            && (string) $data['AssignedUserId'] !== (string) ($dev['AssignedUserId'] ?? '')) {
            $ticket = model(TicketModel::class)->find((int) $dev['IdTicket']);

            if ($ticket !== null && $ticket['Status'] !== 'cerrado') {
                $change = ['AssignedUserId' => $data['AssignedUserId']];
                model(TicketModel::class)->skipValidation(true)->update((int) $dev['IdTicket'], $change);
                model(TicketChangeModel::class)->record((int) $dev['IdTicket'], $ticket, $change, $this->user);
            }
        }

        return $this->back($id, 'Desarrollo actualizado.');
    }

    /**
     * POST /panel/desarrollos/{id}/tareas
     */
    public function addTask(int $id)
    {
        [, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        $text  = mb_substr(trim((string) $this->request->getPost('Description')), 0, 255);
        $hours = round((float) str_replace(',', '.', (string) $this->request->getPost('Hours')), 1);

        if ($text === '' || $hours <= 0 || $hours > 999) {
            return $this->back($id, 'Escribe la tarea y sus horas estimadas (mayor que 0).', false);
        }

        db_connect()->table('DevelopmentTasks')->insert([
            'IdDevelopment' => $id,
            'Description'   => $text,
            'Hours'         => $hours,
        ]);

        return $this->back($id, 'Tarea agregada.', true, 'tareas');
    }

    /**
     * POST /panel/desarrollos/{id}/tareas/{task}/marcar
     */
    public function toggleTask(int $id, int $taskId)
    {
        [, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        $table = db_connect()->table('DevelopmentTasks');
        $task  = $table->where('IdTask', $taskId)->where('IdDevelopment', $id)->get()->getRowArray();

        if ($task === null) {
            return $this->back($id, 'La tarea no existe.', false);
        }

        $done = $task['DoneAt'] === null;

        db_connect()->table('DevelopmentTasks')->where('IdTask', $taskId)->update([
            'DoneAt' => $done ? gmdate('Y-m-d H:i:s') : null,
            'DoneBy' => $done ? panel_user_id() : null,
        ]);

        $dev = $this->board()->find($id);
        $msg = $done ? 'Tarea terminada.' : 'Tarea marcada como pendiente.';

        if ($dev['Progress'] === 100 && (int) $dev['EvidenceCount'] === 0) {
            $msg .= ' Todas las tareas están listas: sube la evidencia para pedir la aprobación.';
        }

        return $this->back($id, $msg, true, 'tareas');
    }

    /**
     * POST /panel/desarrollos/{id}/tareas/{task}/eliminar
     */
    public function deleteTask(int $id, int $taskId)
    {
        [, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        db_connect()->table('DevelopmentTasks')->where('IdTask', $taskId)->where('IdDevelopment', $id)->delete();

        return $this->back($id, 'Tarea eliminada.', true, 'tareas');
    }

    /**
     * POST /panel/desarrollos/{id}/recursos
     */
    public function addResource(int $id)
    {
        [, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        $text = mb_substr(trim((string) $this->request->getPost('Description')), 0, 255);

        if ($text === '') {
            return $this->back($id, 'Describe el recurso que necesitas.', false);
        }

        db_connect()->table('DevelopmentResources')->insert([
            'IdDevelopment'   => $id,
            'Description'     => $text,
            'RequestedByName' => mb_substr((string) ($this->user['name'] ?? panel_user_id()), 0, 150),
        ]);

        return $this->back($id, 'Recurso solicitado.', true, 'recursos');
    }

    /**
     * POST /panel/desarrollos/{id}/recursos/{resource}/disponible
     */
    public function resourceReady(int $id, int $resourceId)
    {
        [, $error] = $this->workable($id, true);

        if ($error) {
            return $error;
        }

        db_connect()->table('DevelopmentResources')
            ->where('IdResource', $resourceId)->where('IdDevelopment', $id)
            ->update(['ReadyAt' => gmdate('Y-m-d H:i:s')]);

        return $this->back($id, 'Recurso marcado como disponible.', true, 'recursos');
    }

    /**
     * POST /panel/desarrollos/{id}/inconvenientes
     */
    public function addIssue(int $id)
    {
        [, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        $text = trim((string) $this->request->getPost('Description'));
        $type = $this->request->getPost('IssueType') === 'bloqueante' ? 'bloqueante' : 'riesgo';
        $days = max(0, min(365, (int) $this->request->getPost('ImpactDays')));

        if ($text === '' || mb_strlen($text) > 2000) {
            return $this->back($id, 'Describe el inconveniente (máximo 2000 caracteres).', false);
        }

        db_connect()->table('DevelopmentIssues')->insert([
            'IdDevelopment'  => $id,
            'IssueType'      => $type,
            'Description'    => $text,
            'ImpactDays'     => $days,
            'ReportedByName' => mb_substr((string) ($this->user['name'] ?? panel_user_id()), 0, 150),
        ]);

        return $this->back($id, 'Inconveniente reportado.', true, 'inconvenientes');
    }

    /**
     * POST /panel/desarrollos/{id}/inconvenientes/{issue}/resolver
     */
    public function resolveIssue(int $id, int $issueId)
    {
        [, $error] = $this->workable($id, true);

        if ($error) {
            return $error;
        }

        db_connect()->table('DevelopmentIssues')
            ->where('IdIssue', $issueId)->where('IdDevelopment', $id)->where('ResolvedAt', null)
            ->update([
                'ResolvedAt'     => gmdate('Y-m-d H:i:s'),
                'ResolvedByName' => mb_substr((string) ($this->user['name'] ?? panel_user_id()), 0, 150),
            ]);

        return $this->back($id, 'Inconveniente resuelto.', true, 'inconvenientes');
    }

    /**
     * POST /panel/desarrollos/{id}/evidencia
     * Imágenes del desarrollo terminado (writable/uploads/developments/{id}/).
     */
    public function addEvidence(int $id)
    {
        [, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        $files = [];

        foreach ($this->request->getFileMultiple('images') ?? [] as $file) {
            if ($file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                $files[] = $file;
            }
        }

        if ($files === []) {
            return $this->back($id, 'Elige al menos una imagen.', false);
        }

        foreach ($files as $file) {
            if (! $file->isValid()) {
                return $this->back($id, 'No se pudo recibir "' . $file->getClientName() . '".', false);
            }

            if ($file->getSize() > self::MAX_IMAGE_SIZE) {
                return $this->back($id, '"' . $file->getClientName() . '" supera el máximo de 10 MB.', false);
            }

            if (! in_array(strtolower($file->getClientExtension()), self::IMAGE_EXTENSIONS, true) || ! str_starts_with((string) $file->getMimeType(), 'image/')) {
                return $this->back($id, '"' . $file->getClientName() . '" no es una imagen (PNG, JPG, GIF o WEBP).', false);
            }
        }

        $db = db_connect();

        foreach ($files as $file) {
            $folder = 'developments/' . $id;
            $name   = bin2hex(random_bytes(10)) . '.' . strtolower($file->getClientExtension());

            $file->move(WRITEPATH . 'uploads/' . $folder, $name);

            $db->table('DevelopmentEvidence')->insert([
                'IdDevelopment'  => $id,
                'FileName'       => mb_substr($file->getClientName(), 0, 255),
                'FilePath'       => $folder . '/' . $name,
                'UploadedByName' => mb_substr((string) ($this->user['name'] ?? panel_user_id()), 0, 150),
            ]);
        }

        return $this->back($id, count($files) === 1 ? 'Imagen agregada.' : count($files) . ' imágenes agregadas.', true, 'evidencia');
    }

    /**
     * GET /panel/desarrollos/{id}/evidencia/{evidence}
     */
    public function evidence(int $id, int $evidenceId)
    {
        $row = db_connect()->table('DevelopmentEvidence')
            ->where('IdEvidence', $evidenceId)->where('IdDevelopment', $id)
            ->get()->getRowArray();

        $base = realpath(WRITEPATH . 'uploads');
        $path = $row === null ? false : realpath(WRITEPATH . 'uploads/' . ltrim((string) $row['FilePath'], '/\\'));

        if ($base === false || $path === false || ! str_starts_with($path, $base . DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('Imagen no encontrada.');
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        if (! str_starts_with($mime, 'image/')) {
            return $this->response->setStatusCode(404)->setBody('Imagen no encontrada.');
        }

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'inline; filename="' . str_replace('"', '', (string) $row['FileName']) . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
    }

    /**
     * POST /panel/desarrollos/{id}/aprobar
     * El administrador aprueba la entrega: el desarrollo queda en solo
     * lectura, cuenta como realizado y se cierra el ticket original.
     */
    public function approve(int $id)
    {
        [$dev, $error] = $this->workable($id, true);

        if ($error) {
            return $error;
        }

        if ($dev['State'] !== 'en_pruebas') {
            return $this->back($id, 'Para aprobar, todas las tareas deben estar terminadas y sin inconvenientes bloqueantes abiertos.', false);
        }

        if ((int) $dev['EvidenceCount'] === 0) {
            return $this->back($id, 'Falta subir al menos una imagen de evidencia.', false);
        }

        db_connect()->table('Developments')->where('IdDevelopment', $id)->update([
            'ApprovedAt'     => gmdate('Y-m-d H:i:s'),
            'ApprovedBy'     => panel_user_id(),
            'ApprovedByName' => mb_substr((string) ($this->user['name'] ?? panel_user_id()), 0, 150),
        ]);

        $ticketId = (int) $dev['IdTicket'];
        $ticket   = model(TicketModel::class)->find($ticketId);

        if ($ticket !== null && $ticket['Status'] !== 'cerrado') {
            $change = ['Status' => 'cerrado'];
            model(TicketModel::class)->skipValidation(true)->update($ticketId, $change);
            model(TicketChangeModel::class)->record($ticketId, $ticket, $change, $this->user);

            if (TicketTimeLogModel::available()) {
                model(TicketTimeLogModel::class)->stop($ticketId);
            }

            (new TicketNotifier())->requesterStatus(array_merge($ticket, $change));
        }

        return $this->back($id, 'Entrega aprobada. Cuenta como realizado y el ticket ' . ticket_code($ticketId) . ' quedó cerrado.');
    }

    /**
     * GET /panel/desarrollos/cierre?mes=AAAA-MM
     */
    public function closing()
    {
        if ($missing = $this->missingTables()) {
            return $missing;
        }

        $month   = DevelopmentBoard::validMonth($this->request->getGet('mes')) ?? DevelopmentBoard::currentMonth();
        $closure = $this->board()->closure($month);

        // Si el mes ya se cerró, sus pendientes están en el mes siguiente.
        $rows    = $this->board()->list($month);
        $carried = $closure !== null ? $this->board()->carriedFrom($month) : [];
        $all     = array_merge($rows, $carried);
        $done    = array_values(array_filter($rows, static fn ($d) => $d['State'] === 'terminado'));
        $pending = $closure !== null ? $carried : array_values(array_filter($rows, static fn ($d) => $d['State'] !== 'terminado'));
        $summary = DevelopmentBoard::summary($rows, $month);

        if ($closure !== null) {
            $total   = (int) $closure['Committed'];
            $summary = [
                'total' => $total,
                'done'  => (int) $closure['Delivered'],
                'open'  => (int) $closure['CarriedOver'],
                'pct'   => $total > 0 ? (int) round((int) $closure['Delivered'] / $total * 100) : 0,
            ] + $summary;
        }

        $db     = db_connect();
        $ids    = array_column($all, 'IdDevelopment');
        $issues = $ids === [] ? [] : $db->table('DevelopmentIssues i')
            ->select('i.*, d.IdDevelopment')
            ->join('Developments d', 'd.IdDevelopment = i.IdDevelopment')
            ->whereIn('i.IdDevelopment', $ids)
            ->orderBy('i.CreatedAt', 'DESC')
            ->get()
            ->getResultArray();

        return $this->render('developments/closing', [
            'active'   => 'developments',
            'title'    => 'Cierre de ' . DevelopmentBoard::monthName($month),
            'month'    => $month,
            'closure'  => $closure,
            'summary'  => $summary,
            'done'     => $done,
            'pending'  => $pending,
            'all'      => $all,
            'issues'   => $issues,
        ]);
    }

    /**
     * POST /panel/desarrollos/cierre
     * Registra el cierre del mes y pasa los pendientes al mes siguiente.
     */
    public function closeMonth()
    {
        $month = DevelopmentBoard::validMonth($this->request->getPost('PlanMonth'));

        if (! panel_is_admin() || $month === null) {
            return redirect()->back()->with('error', 'Solo el administrador cierra el mes.');
        }

        if ($this->board()->closure($month) !== null) {
            return redirect()->back()->with('error', DevelopmentBoard::monthName($month) . ' ya está cerrado.');
        }

        $rows    = $this->board()->list($month);
        $summary = DevelopmentBoard::summary($rows, $month);
        $next    = DevelopmentBoard::shiftMonth($month, 1);
        $nextEnd = DevelopmentBoard::lastDay($next);
        $db      = db_connect();

        $db->transStart();

        foreach ($rows as $dev) {
            if ($dev['State'] === 'terminado') {
                continue;
            }

            // La nueva fecha comprometida es la estimada si cae después del mes cerrado.
            $estimate  = $dev['EstimatedDate'];
            $committed = $estimate !== null && $estimate > DevelopmentBoard::lastDay($month) ? $estimate : $nextEnd;

            $db->table('Developments')->where('IdDevelopment', $dev['IdDevelopment'])->update([
                'PlanMonth'        => $next,
                'CarriedFromMonth' => $month,
                'CommittedDate'    => $committed,
                'EstimatedDate'    => $estimate !== null && $estimate > $committed ? $estimate : $committed,
            ]);
        }

        $db->table('DevelopmentMonthClosures')->insert([
            'PlanMonth'    => $month,
            'Committed'    => $summary['total'],
            'Delivered'    => $summary['done'],
            'CarriedOver'  => $summary['open'],
            'ClosedBy'     => panel_user_id(),
            'ClosedByName' => mb_substr((string) ($this->user['name'] ?? panel_user_id()), 0, 150),
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->with('error', 'No se pudo cerrar el mes.');
        }

        return redirect()->to(site_url('panel/desarrollos/cierre?mes=' . $month))
            ->with('success', DevelopmentBoard::monthName($month) . ' cerrado: ' . $summary['open'] . ' pendiente(s) pasaron a ' . DevelopmentBoard::monthName($next) . '.');
    }

    /**
     * Desarrollo que el usuario puede actualizar, o la redirección con el error.
     *
     * @return array{0: array<string, mixed>|null, 1: RedirectResponse|null}
     */
    private function workable(int $id, bool $adminOnly = false): array
    {
        $dev = $this->board()->find($id);

        if ($dev === null) {
            return [null, redirect()->to(site_url('panel/desarrollos'))->with('error', 'El desarrollo no existe.')];
        }

        if ($dev['ApprovedAt'] !== null) {
            return [$dev, $this->back($id, 'El desarrollo ya fue aprobado y no se puede modificar.', false)];
        }

        if ($adminOnly ? ! panel_is_admin() : ! DevelopmentBoard::canWork($dev)) {
            return [$dev, $this->back($id, $adminOnly
                ? 'Solo el administrador puede hacer esto.'
                : 'Solo el programador asignado o el administrador pueden actualizar este desarrollo.', false)];
        }

        return [$dev, null];
    }

    private function back(int $id, string $message, bool $ok = true, string $anchor = ''): RedirectResponse
    {
        return redirect()->to(site_url('panel/desarrollos/' . $id) . ($anchor !== '' ? '#' . $anchor : ''))
            ->with($ok ? 'success' : 'error', $message);
    }

    private function missingTables(): ?RedirectResponse
    {
        if (DevelopmentBoard::available()) {
            return null;
        }

        return redirect()->to(site_url('panel'))->with('error', 'Falta crear las tablas de desarrollos (app/Database/sql/Desarrollos.sql).');
    }
}
