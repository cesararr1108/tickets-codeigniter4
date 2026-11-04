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
 *
 * Proyectos (tickets con formulario o categoría de proyecto): además llevan
 * lo que se requiere, presupuesto por rubro, responsable por actividad y
 * reprogramaciones de la entrega por incidentes. Reporte de estado en
 * /panel/desarrollos/proyectos e informe de fin de mes imprimible en
 * /panel/desarrollos/cierre/informe.
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
        $all     = $this->board()->withMonthGoal($this->board()->list($month), $month);

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

        $kind = $this->request->getGet('tipo');
        $kind = isset(DevelopmentBoard::KINDS[$kind]) ? $kind : null;

        return $this->render('developments/list', [
            'active' => 'developments',
            'title'  => 'Desarrollos',
            'kind'   => $kind,
            'rows'   => $this->board()->list(null, panel_is_admin() ? null : panel_user_id(), $kind),
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
            'tasks'     => $db->table('DevelopmentTasks')->where('IdDevelopment', $id)->orderBy('DueDate')->orderBy('IdTask')->get()->getResultArray(),
            'resources' => $db->table('DevelopmentResources')->where('IdDevelopment', $id)->orderBy('IdResource')->get()->getResultArray(),
            'issues'    => $db->table('DevelopmentIssues')->where('IdDevelopment', $id)->orderBy('IdIssue', 'DESC')->get()->getResultArray(),
            'evidence'  => $db->table('DevelopmentEvidence')->where('IdDevelopment', $id)->orderBy('IdEvidence')->get()->getResultArray(),
            'agents'    => $this->lookups()['agents'],
            'goal'      => $this->board()->withMonthGoal([$dev], $dev['PlanMonth'] ?? DevelopmentBoard::currentMonth())[0],
            'projects'  => DevelopmentBoard::projectsAvailable(),
            'budget'    => DevelopmentBoard::projectsAvailable()
                ? $db->table('DevelopmentBudgetItems')->where('IdDevelopment', $id)->orderBy('IdBudgetItem')->get()->getResultArray()
                : [],
            'reschedules' => $this->board()->reschedules([$id]),
            'answers'   => $this->board()->formAnswers((int) $dev['IdTicket']),
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

        // El desarrollo va en un mes dentro de su rango de entrega.
        if ($committed < $month . '-01') {
            return redirect()->back()->with('error', 'La entrega comprometida (' . DevelopmentBoard::formatDate($committed)
                . ') no puede ser antes de ' . DevelopmentBoard::monthName($month) . '. Planea el desarrollo en el mes de su entrega o antes.');
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

        if (DevelopmentBoard::projectsAvailable()) {
            $kind = (string) $this->request->getPost('Kind');
            $data['OriginalCommittedDate'] = $committed;

            if (isset(DevelopmentBoard::KINDS[$kind])) {
                $data['Kind'] = $kind;
            }
        }

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
            ->update(['PlanMonth' => null, 'CommittedDate' => null, 'EstimatedDate' => null]
                + (DevelopmentBoard::projectsAvailable() ? ['OriginalCommittedDate' => null] : []));

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

        if (DevelopmentBoard::projectsAvailable()) {
            $requirements = $this->request->getPost('Requirements');
            if ($requirements !== null) {
                $requirements = trim((string) $requirements);

                if (mb_strlen($requirements) > 4000) {
                    return $this->back($id, 'Lo que se requiere admite máximo 4000 caracteres.', false, 'requiere');
                }

                if ($requirements !== (string) ($dev['Requirements'] ?? '')) {
                    $data['Requirements'] = $requirements === '' ? null : $requirements;
                }
            }

            $kind = $this->request->getPost('Kind');
            if (panel_is_admin() && isset(DevelopmentBoard::KINDS[$kind]) && $kind !== $dev['Kind']) {
                $data['Kind'] = $kind;
            }
        }

        $estimated = $this->request->getPost('EstimatedDate');
        if ($estimated !== null && ($estimated = DevelopmentBoard::validDate($estimated)) !== null) {
            $data['EstimatedDate'] = $estimated;
        }

        if (panel_is_admin()) {
            $committed = $this->request->getPost('CommittedDate');
            if ($committed !== null && ($committed = DevelopmentBoard::validDate($committed)) !== null) {
                if ($dev['PlanMonth'] !== null && $committed < $dev['PlanMonth'] . '-01') {
                    return $this->back($id, 'La entrega comprometida no puede ser antes de ' . DevelopmentBoard::monthName($dev['PlanMonth']) . ', el mes del plan.', false);
                }

                if ($committed !== $dev['CommittedDate']) {
                    $data['CommittedDate'] = $committed;
                }
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

        // Sin cambios reales en lo que se envió.
        foreach (['EstimatedDate', 'AssignedUserId'] as $k) {
            if (array_key_exists($k, $data) && (string) $data[$k] === (string) ($dev[$k] ?? '')) {
                unset($data[$k]);
            }
        }

        if ($data === []) {
            return $this->back($id, 'No hubo cambios.');
        }

        if (isset($data['CommittedDate']) && $dev['CommittedDate'] !== null) {
            $reason = trim((string) $this->request->getPost('RescheduleReason'));

            if ($reason === '' && $dev['IsProject']) {
                return $this->back($id, 'Indica el motivo de la reprogramación de la entrega.', false);
            }

            $this->board()->logReschedule($id, $dev['CommittedDate'], $data['CommittedDate'], $reason !== '' ? $reason : 'Cambio de la entrega comprometida', $this->userName());
        }

        if (isset($data['CommittedDate']) && DevelopmentBoard::projectsAvailable() && $dev['OriginalCommittedDate'] === null) {
            $data['OriginalCommittedDate'] = $data['CommittedDate'];
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
        [$dev, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        $text  = mb_substr(trim((string) $this->request->getPost('Description')), 0, 255);
        $hours = round((float) str_replace(',', '.', (string) $this->request->getPost('Hours')), 1);

        if ($text === '' || $hours <= 0 || $hours > 999) {
            return $this->back($id, 'Escribe la tarea y sus horas estimadas (mayor que 0).', false);
        }

        $data = [
            'IdDevelopment' => $id,
            'Description'   => $text,
            'Hours'         => $hours,
        ];

        if (DevelopmentBoard::milestonesAvailable()) {
            [$due, $dueError] = $this->dueDate($dev);

            if ($dueError !== null) {
                return $this->back($id, $dueError, false, 'tareas');
            }

            $data['DueDate'] = $due;
        }

        if (DevelopmentBoard::projectsAvailable()) {
            $responsible             = mb_substr(trim((string) $this->request->getPost('ResponsibleName')), 0, 150);
            $data['ResponsibleName'] = $responsible !== '' ? $responsible : ($dev['ProgrammerName'] ?? null);
        }

        db_connect()->table('DevelopmentTasks')->insert($data);

        return $this->back($id, $dev['IsProject'] ? 'Actividad agregada.' : 'Tarea agregada.', true, 'tareas');
    }

    /**
     * POST /panel/desarrollos/{id}/tareas/{task}/responsable
     */
    public function taskResponsible(int $id, int $taskId)
    {
        [, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        if (! DevelopmentBoard::projectsAvailable()) {
            return $this->back($id, 'Falta ejecutar app/Database/sql/Proyectos.sql.', false);
        }

        $name = mb_substr(trim((string) $this->request->getPost('ResponsibleName')), 0, 150);

        db_connect()->table('DevelopmentTasks')
            ->where('IdTask', $taskId)->where('IdDevelopment', $id)
            ->update(['ResponsibleName' => $name !== '' ? $name : null]);

        return $this->back($id, 'Responsable actualizado.', true, 'tareas');
    }

    /**
     * POST /panel/desarrollos/{id}/presupuesto
     * Agrega un rubro (concepto, presupuestado y ejecutado).
     */
    public function addBudget(int $id)
    {
        [, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        if (! DevelopmentBoard::projectsAvailable()) {
            return $this->back($id, 'Falta ejecutar app/Database/sql/Proyectos.sql.', false);
        }

        $concept = mb_substr(trim((string) $this->request->getPost('Concept')), 0, 255);
        $planned = DevelopmentBoard::parseMoney($this->request->getPost('Planned'));
        $spent   = DevelopmentBoard::parseMoney($this->request->getPost('Spent')) ?? 0.0;

        if ($concept === '' || $planned === null || $planned < 0 || $spent < 0 || $planned > 999999999999 || $spent > 999999999999) {
            return $this->back($id, 'Escribe el rubro y su valor presupuestado (y el ejecutado, si ya hay).', false, 'presupuesto');
        }

        db_connect()->table('DevelopmentBudgetItems')->insert([
            'IdDevelopment' => $id,
            'Concept'       => $concept,
            'Planned'       => $planned,
            'Spent'         => $spent,
            'CreatedByName' => $this->userName(),
        ]);

        return $this->back($id, 'Rubro agregado al presupuesto.', true, 'presupuesto');
    }

    /**
     * POST /panel/desarrollos/{id}/presupuesto/{item}
     * Actualiza el presupuestado y el ejecutado de un rubro.
     */
    public function updateBudget(int $id, int $itemId)
    {
        [, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        $planned = DevelopmentBoard::parseMoney($this->request->getPost('Planned'));
        $spent   = DevelopmentBoard::parseMoney($this->request->getPost('Spent'));

        if ($planned === null || $spent === null || $planned < 0 || $spent < 0 || $planned > 999999999999 || $spent > 999999999999) {
            return $this->back($id, 'Revisa los valores del rubro.', false, 'presupuesto');
        }

        db_connect()->table('DevelopmentBudgetItems')
            ->where('IdBudgetItem', $itemId)->where('IdDevelopment', $id)
            ->update(['Planned' => $planned, 'Spent' => $spent]);

        return $this->back($id, 'Presupuesto actualizado.', true, 'presupuesto');
    }

    /**
     * POST /panel/desarrollos/{id}/presupuesto/{item}/eliminar
     */
    public function deleteBudget(int $id, int $itemId)
    {
        [, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        db_connect()->table('DevelopmentBudgetItems')->where('IdBudgetItem', $itemId)->where('IdDevelopment', $id)->delete();

        return $this->back($id, 'Rubro eliminado.', true, 'presupuesto');
    }

    /**
     * POST /panel/desarrollos/{id}/inconvenientes/{issue}/reprogramar
     * El administrador corre la entrega comprometida los días de impacto del
     * incidente (una vez por incidente). La estimada nunca queda antes.
     */
    public function rescheduleFromIssue(int $id, int $issueId)
    {
        [$dev, $error] = $this->workable($id, true);

        if ($error) {
            return $error;
        }

        if (! DevelopmentBoard::projectsAvailable()) {
            return $this->back($id, 'Falta ejecutar app/Database/sql/Proyectos.sql.', false);
        }

        $db    = db_connect();
        $issue = $db->table('DevelopmentIssues')->where('IdIssue', $issueId)->where('IdDevelopment', $id)->get()->getRowArray();

        if ($issue === null || (int) $issue['ImpactDays'] <= 0) {
            return $this->back($id, 'El incidente no existe o no tiene días de impacto.', false, 'inconvenientes');
        }

        if ($dev['CommittedDate'] === null) {
            return $this->back($id, 'Primero planea el desarrollo con una entrega comprometida.', false, 'inconvenientes');
        }

        if ($db->table('DevelopmentReschedules')->where('IdIssue', $issueId)->countAllResults() > 0) {
            return $this->back($id, 'Este incidente ya reprogramó la entrega.', false, 'inconvenientes');
        }

        $new  = (new \DateTimeImmutable($dev['CommittedDate']))->modify('+' . (int) $issue['ImpactDays'] . ' day')->format('Y-m-d');
        $data = ['CommittedDate' => $new];

        if ($dev['EstimatedDate'] === null || $dev['EstimatedDate'] < $new) {
            $data['EstimatedDate'] = $new;
        }

        $db->transStart();
        $db->table('Developments')->where('IdDevelopment', $id)->update($data);
        $this->board()->logReschedule($id, $dev['CommittedDate'], $new,
            'Incidente: ' . mb_substr((string) $issue['Description'], 0, 900), $this->userName(), $issueId);
        $db->transComplete();

        return $this->back($id, 'Entrega reprogramada al ' . DevelopmentBoard::formatDate($new) . ' (+' . (int) $issue['ImpactDays'] . ' días por el incidente).', true, 'inconvenientes');
    }

    /**
     * POST /panel/desarrollos/{id}/tareas/{task}/fecha
     * Cambia la fecha de cumplimiento (hito) de una tarea.
     */
    public function taskDate(int $id, int $taskId)
    {
        [$dev, $error] = $this->workable($id);

        if ($error) {
            return $error;
        }

        if (! DevelopmentBoard::milestonesAvailable()) {
            return $this->back($id, 'Falta ejecutar app/Database/sql/DesarrollosHitosYCierre.sql.', false);
        }

        [$due, $dueError] = $this->dueDate($dev);

        if ($dueError !== null) {
            return $this->back($id, $dueError, false, 'tareas');
        }

        db_connect()->table('DevelopmentTasks')
            ->where('IdTask', $taskId)->where('IdDevelopment', $id)
            ->update(['DueDate' => $due]);

        return $this->back($id, 'Fecha de cumplimiento actualizada.', true, 'tareas');
    }

    /**
     * Fecha de cumplimiento de una tarea (POST DueDate): obligatoria, desde
     * el mes del plan y no después de la entrega del proyecto.
     *
     * @param array<string, mixed> $dev
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function dueDate(array $dev): array
    {
        $due = DevelopmentBoard::validDate($this->request->getPost('DueDate'));

        if ($due === null) {
            return [null, 'Indica la fecha de cumplimiento de la tarea.'];
        }

        if ($dev['PlanMonth'] !== null && $due < $dev['PlanMonth'] . '-01') {
            return [null, 'La fecha de cumplimiento no puede ser antes de ' . DevelopmentBoard::monthName($dev['PlanMonth']) . ', el mes del plan.'];
        }

        if ($dev['CommittedDate'] !== null && $due > $dev['CommittedDate']) {
            return [null, 'La fecha de cumplimiento no puede pasar la entrega del proyecto (' . DevelopmentBoard::formatDate($dev['CommittedDate']) . ').'];
        }

        return [$due, null];
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
     * Antes del cierre muestra la estadística en vivo; después, la guardada.
     */
    public function closing()
    {
        if ($missing = $this->missingTables()) {
            return $missing;
        }

        $month = DevelopmentBoard::validMonth($this->request->getGet('mes')) ?? DevelopmentBoard::currentMonth();
        [$closure, $rows, $summary] = $this->monthData($month);

        $done    = array_values(array_filter($rows, static fn ($d) => $d['State'] === 'terminado'));
        $pending = array_values(array_filter($rows, static fn ($d) => $d['State'] !== 'terminado'));

        $ids    = array_column($rows, 'IdDevelopment');
        $issues = $ids === [] ? [] : db_connect()->table('DevelopmentIssues i')
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
            'all'      => $rows,
            'issues'   => $issues,
        ]);
    }

    /**
     * GET /panel/desarrollos/cierre/reporte?mes=AAAA-MM
     * Reporte del mes en CSV (se abre en Excel): estadística y detalle.
     */
    public function report()
    {
        if ($missing = $this->missingTables()) {
            return $missing;
        }

        $month = DevelopmentBoard::validMonth($this->request->getGet('mes')) ?? DevelopmentBoard::currentMonth();
        [$closure, $rows, $s] = $this->monthData($month);

        if (! panel_is_admin()) {
            $rows = array_values(array_filter($rows, static fn ($d) => (string) ($d['AssignedUserId'] ?? '') === panel_user_id()));
        }

        $out = fopen('php://temp', 'w+b');
        $put = static fn (array $line) => fputcsv($out, $line, ';', '"', '');

        $put(['Reporte de desarrollos', DevelopmentBoard::monthName($month)]);
        $put(['Estado del mes', $closure !== null
            ? 'Cerrado el ' . format_date($closure['ClosedAt'], 'd/m/Y') . ' por ' . $closure['ClosedByName']
            : 'Abierto (cifras al ' . DevelopmentBoard::formatDate(DevelopmentBoard::today()) . ')']);
        $put([]);
        $put(['Desarrollos del mes', $s['total']]);
        $put(['Completos', $s['done']]);
        $put(['Sin asignar', $s['unassigned']]);
        $put(['Sin empezar', $s['notStarted']]);
        $put(['En curso', $s['inProgress']]);
        $put([$closure !== null ? 'Pasaron al mes siguiente' : 'Pasan al mes siguiente', $s['open']]);
        $put([]);
        $put(['Cuentan para el cumplimiento (asignados con hitos en el mes o con requerimiento)', $s['eligible']]);
        $put(['Cumplieron la meta del mes', $s['met']]);
        $put(['No cumplieron', $s['notMet']]);
        $put(['Cumplimiento del mes (%)', $s['pct']]);
        $put(['Hitos del mes cumplidos', $s['milestonesDone'] . ' de ' . $s['milestonesDue']]);
        $put([]);
        $put(['Desarrollo', 'Ticket', 'Compañía', 'Asunto', 'Programador', 'Clasificación', 'Estado', 'Entrega comprometida',
            'Hitos del mes', 'Hitos cumplidos', 'Meta del mes (%)', 'Avance del proyecto (%)', 'Requerimiento',
            'Cuenta para cumplimiento', 'Cumplió', 'Pasa al mes siguiente', 'Tipo', 'Presupuesto', 'Ejecutado', 'Días reprogramados']);

        foreach ($rows as $d) {
            $put([
                $d['Code'],
                ticket_code((int) $d['IdTicket']),
                $d['CodCompanies'] ?? '',
                $d['Subject'] ?? '',
                $d['ProgrammerName'] ?? 'Sin asignar',
                DevelopmentBoard::BUCKETS[$d['Bucket']] ?? $d['Bucket'],
                DevelopmentBoard::STATES[$d['State']] ?? $d['State'],
                DevelopmentBoard::formatDate($d['CommittedDate']),
                $d['MonthDue'],
                $d['MonthDone'],
                $d['GoalPct'] ?? '',
                $d['Progress'],
                (int) $d['HasRequirement'] === 1 ? 'Sí' : 'No',
                $d['Eligible'] ? 'Sí' : 'No',
                $d['Eligible'] ? ($d['Met'] ? 'Sí' : 'No') : '',
                $d['State'] !== 'terminado' ? 'Sí' : 'No',
                DevelopmentBoard::KINDS[$d['Kind'] ?? 'desarrollo'] ?? 'Desarrollo',
                ! empty($d['IsProject']) ? DevelopmentBoard::money($d['BudgetPlanned'] ?? 0) : '',
                ! empty($d['IsProject']) ? DevelopmentBoard::money($d['BudgetSpent'] ?? 0) : '',
                $d['RescheduledDays'] ?? 0,
            ]);
        }

        rewind($out);
        $csv = "\xEF\xBB\xBF" . stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="desarrollos-' . $month . '.csv"')
            ->setBody($csv);
    }

    /**
     * GET /panel/desarrollos/proyectos?estado=abiertos|todos&formato=csv
     * Reporte de cómo van los proyectos: semáforo, avance, actividades,
     * presupuesto, incidentes y reprogramaciones de la entrega.
     */
    public function projects()
    {
        if ($missing = $this->missingTables()) {
            return $missing;
        }

        if (! DevelopmentBoard::projectsAvailable()) {
            return redirect()->to(site_url('panel/desarrollos'))->with('error', 'Falta ejecutar app/Database/sql/Proyectos.sql (proyectos, presupuesto y reprogramaciones).');
        }

        $scope = $this->request->getGet('estado') === 'todos' ? 'todos' : 'abiertos';
        $rows  = $this->board()->list(null, panel_is_admin() ? null : panel_user_id(), 'proyecto');

        if ($scope === 'abiertos') {
            $rows = array_values(array_filter($rows, static fn ($d) => $d['State'] !== 'terminado'));
        }

        // Atrasados primero, luego en riesgo, en tiempo y entregados.
        $order = array_flip(['atrasado', 'en_riesgo', 'en_tiempo', 'entregado']);
        usort($rows, static fn ($a, $b) => [$order[$a['Health']], $a['CommittedDate'] ?? '9999'] <=> [$order[$b['Health']], $b['CommittedDate'] ?? '9999']);

        $ids  = array_map('intval', array_column($rows, 'IdDevelopment'));
        $db   = db_connect();
        $next = [];
        $open = [];

        if ($ids !== []) {
            // Próxima actividad pendiente de cada proyecto.
            foreach ($db->table('DevelopmentTasks')->whereIn('IdDevelopment', $ids)->where('DoneAt', null)
                ->orderBy('DueDate')->orderBy('IdTask')->get()->getResultArray() as $t) {
                $next[$t['IdDevelopment']] ??= $t;
            }

            // Incidente abierto más reciente.
            foreach ($db->table('DevelopmentIssues')->whereIn('IdDevelopment', $ids)->where('ResolvedAt', null)
                ->orderBy('CreatedAt', 'DESC')->get()->getResultArray() as $i) {
                $open[$i['IdDevelopment']] ??= $i;
            }
        }

        $summary = [
            'total'   => count($rows),
            'health'  => array_map(static fn ($h) => count(array_filter($rows, static fn ($d) => $d['Health'] === $h)), array_combine(array_keys(DevelopmentBoard::HEALTH), array_keys(DevelopmentBoard::HEALTH))),
            'planned' => array_sum(array_column($rows, 'BudgetPlanned')),
            'spent'   => array_sum(array_column($rows, 'BudgetSpent')),
            'progress' => $rows === [] ? 0 : (int) round(array_sum(array_column($rows, 'Progress')) / count($rows)),
            'impact'  => array_sum(array_column($rows, 'ImpactDaysTotal')),
            'rescheduled' => count(array_filter($rows, static fn ($d) => $d['RescheduledDays'] > 0)),
        ];

        if ($this->request->getGet('formato') === 'csv') {
            return $this->csv('proyectos-' . DevelopmentBoard::today() . '.csv', static function (callable $put) use ($rows, $next, $open, $summary) {
                $put(['Reporte de proyectos', 'Al ' . DevelopmentBoard::formatDate(DevelopmentBoard::today())]);
                $put(['Proyectos', $summary['total']]);
                foreach (DevelopmentBoard::HEALTH as $k => $label) {
                    $put([$label, $summary['health'][$k]]);
                }
                $put(['Presupuesto total', DevelopmentBoard::money($summary['planned'])]);
                $put(['Ejecutado', DevelopmentBoard::money($summary['spent'])]);
                $put([]);
                $put(['Proyecto', 'Ticket', 'Compañía', 'Nombre', 'Responsable', 'Semáforo', 'Estado', 'Avance (%)',
                    'Actividades terminadas', 'Actividades totales', 'Actividades vencidas', 'Próxima actividad', 'Responsable próxima', 'Fecha próxima',
                    'Entrega original', 'Entrega vigente', 'Entrega estimada', 'Días reprogramados', 'Reprogramaciones',
                    'Presupuesto', 'Ejecutado', 'Ejecución (%)', 'Incidentes', 'Incidentes abiertos', 'Días de impacto', 'Último incidente abierto']);

                foreach ($rows as $d) {
                    $n = $next[$d['IdDevelopment']] ?? null;
                    $i = $open[$d['IdDevelopment']] ?? null;
                    $put([
                        $d['Code'], ticket_code((int) $d['IdTicket']), $d['CodCompanies'] ?? '', $d['Subject'] ?? '',
                        $d['ProgrammerName'] ?? 'Sin asignar',
                        DevelopmentBoard::HEALTH[$d['Health']], DevelopmentBoard::STATES[$d['State']], $d['Progress'],
                        $d['TasksDone'], $d['TasksTotal'], $d['OverdueTasks'],
                        $n['Description'] ?? '', $n['ResponsibleName'] ?? '', DevelopmentBoard::formatDate($n['DueDate'] ?? null),
                        DevelopmentBoard::formatDate($d['OriginalCommittedDate']), DevelopmentBoard::formatDate($d['CommittedDate']),
                        DevelopmentBoard::formatDate($d['EstimatedDate']), $d['RescheduledDays'], $d['RescheduleCount'],
                        DevelopmentBoard::money($d['BudgetPlanned']), DevelopmentBoard::money($d['BudgetSpent']), $d['BudgetPct'] ?? '',
                        $d['IssuesTotal'], $d['OpenIssues'], $d['ImpactDaysTotal'], $i['Description'] ?? '',
                    ]);
                }
            });
        }

        return $this->render('developments/projects', [
            'active'  => 'developments',
            'title'   => 'Reporte de proyectos',
            'scope'   => $scope,
            'rows'    => $rows,
            'next'    => $next,
            'open'    => $open,
            'summary' => $summary,
        ]);
    }

    /**
     * GET /panel/desarrollos/cierre/informe?mes=AAAA-MM
     * Informe de fin de mes para imprimir o guardar en PDF: estadística,
     * entregas por programador, detalle, proyectos, incidentes y
     * reprogramaciones del mes.
     */
    public function monthReport()
    {
        if ($missing = $this->missingTables()) {
            return $missing;
        }

        $month = DevelopmentBoard::validMonth($this->request->getGet('mes')) ?? DevelopmentBoard::currentMonth();
        [$closure, $rows, $summary] = $this->monthData($month);

        if (! panel_is_admin()) {
            $rows = array_values(array_filter($rows, static fn ($d) => (string) ($d['AssignedUserId'] ?? '') === panel_user_id()));
        }

        $ids    = array_map('intval', array_column($rows, 'IdDevelopment'));
        $issues = $ids === [] ? [] : db_connect()->table('DevelopmentIssues')
            ->whereIn('IdDevelopment', $ids)
            ->groupStart()
                ->groupStart()
                    ->where('CreatedAt >=', $month . '-01 00:00:00')
                    ->where('CreatedAt <', DevelopmentBoard::shiftMonth($month, 1) . '-01 00:00:00')
                ->groupEnd()
                ->orWhere('ResolvedAt', null)
            ->groupEnd()
            ->orderBy('CreatedAt', 'DESC')
            ->get()
            ->getResultArray();

        // Datos vivos del proyecto (presupuesto, línea base) para el detalle.
        $live = [];
        foreach ($ids === [] ? [] : $this->board()->builder()->whereIn('d.IdDevelopment', $ids)->get()->getResultArray() as $r) {
            $live[$r['IdDevelopment']] = DevelopmentBoard::decorate($r);
        }

        return $this->render('developments/report', [
            'active'      => 'developments',
            'title'       => 'Informe de ' . DevelopmentBoard::monthName($month),
            'month'       => $month,
            'closure'     => $closure,
            'summary'     => $summary,
            'rows'        => $rows,
            'live'        => $live,
            'issues'      => $issues,
            'reschedules' => $this->board()->reschedules($ids, $month),
        ]);
    }

    /**
     * Respuesta CSV para Excel (UTF-8 con BOM, separador ;).
     *
     * @param callable(callable(list<mixed>): void): void $write
     */
    private function csv(string $filename, callable $write)
    {
        $out = fopen('php://temp', 'w+b');
        $write(static fn (array $line) => fputcsv($out, $line, ';', '"', ''));

        rewind($out);
        $csv = "\xEF\xBB\xBF" . stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    /**
     * POST /panel/desarrollos/cierre
     * Guarda la estadística y el detalle del mes y pasa TODOS los abiertos al
     * mes siguiente. Las tareas vencidas sin terminar pasan con fecha al fin
     * del mes siguiente (se guarda la original).
     */
    public function closeMonth()
    {
        $month = DevelopmentBoard::validMonth($this->request->getPost('PlanMonth'));

        if (! panel_is_admin() || $month === null) {
            return redirect()->back()->with('error', 'Solo el administrador cierra el mes.');
        }

        if ($missing = $this->missingTables()) {
            return $missing;
        }

        if ($this->board()->closure($month) !== null) {
            return redirect()->back()->with('error', DevelopmentBoard::monthName($month) . ' ya está cerrado.');
        }

        // Los meses se cierran en orden: si quedan abiertos de un mes anterior
        // sin cerrar, ese mes va primero.
        $older = db_connect()->table('Developments')
            ->select('MIN(PlanMonth) AS M', false)
            ->where('ApprovedAt', null)
            ->where('PlanMonth <', $month)
            ->get()->getRowArray()['M'] ?? null;

        if ($older !== null) {
            return redirect()->back()->with('error', 'Primero cierra ' . DevelopmentBoard::monthName($older) . ': tiene desarrollos abiertos sin cerrar.');
        }

        $rows    = $this->board()->withMonthGoal($this->board()->list($month), $month);
        $summary = DevelopmentBoard::summary($rows, $month);
        $last    = DevelopmentBoard::lastDay($month);
        $next    = DevelopmentBoard::shiftMonth($month, 1);
        $nextEnd = DevelopmentBoard::lastDay($next);
        $db      = db_connect();
        $itemsHaveKind = DevelopmentBoard::projectsAvailable() && $db->fieldExists('Kind', 'DevelopmentMonthClosureItems');

        $db->transStart();

        // 1. Estadística del mes.
        $db->table('DevelopmentMonthClosures')->insert([
            'PlanMonth'      => $month,
            'Committed'      => $summary['total'],
            'Delivered'      => $summary['done'],
            'CarriedOver'    => $summary['open'],
            'Unassigned'     => $summary['unassigned'],
            'NotStarted'     => $summary['notStarted'],
            'InProgress'     => $summary['inProgress'],
            'Eligible'       => $summary['eligible'],
            'Met'            => $summary['met'],
            'MilestonesDue'  => $summary['milestonesDue'],
            'MilestonesDone' => $summary['milestonesDone'],
            'ClosedBy'       => panel_user_id(),
            'ClosedByName'   => mb_substr((string) ($this->user['name'] ?? panel_user_id()), 0, 150),
        ]);

        foreach ($rows as $dev) {
            $open = $dev['State'] !== 'terminado';

            // 2. Detalle para el reporte.
            $db->table('DevelopmentMonthClosureItems')->insert([
                'PlanMonth'      => $month,
                'IdDevelopment'  => $dev['IdDevelopment'],
                'IdTicket'       => $dev['IdTicket'],
                'Subject'        => mb_substr((string) $dev['Subject'], 0, 255),
                'CodCompanies'   => $dev['CodCompanies'],
                'AssignedUserId' => $dev['AssignedUserId'],
                'ProgrammerName' => $dev['ProgrammerName'] !== null ? mb_substr((string) $dev['ProgrammerName'], 0, 150) : null,
                'State'          => $dev['State'],
                'Bucket'         => $dev['Bucket'],
                'CommittedDate'  => $dev['CommittedDate'],
                'MilestonesDue'  => $dev['MonthDue'],
                'MilestonesDone' => $dev['MonthDone'],
                'HasRequirement' => (int) $dev['HasRequirement'],
                'Eligible'       => $dev['Eligible'] ? 1 : 0,
                'Met'            => $dev['Met'] ? 1 : 0,
                'GoalPct'        => $dev['GoalPct'],
                'ProjectPct'     => $dev['Progress'],
                'CarriedOver'    => $open ? 1 : 0,
                'ApprovedAt'     => $dev['ApprovedAt'],
            ] + ($itemsHaveKind ? [
                'Kind'            => $dev['Kind'],
                'BudgetPlanned'   => $dev['BudgetPlanned'],
                'BudgetSpent'     => $dev['BudgetSpent'],
                'RescheduledDays' => $dev['RescheduledDays'],
            ] : []));

            if (! $open) {
                continue;
            }

            // 3. Todos los abiertos pasan al mes siguiente. Si la entrega ya
            //    venció, la nueva fecha es la estimada o el fin del mes siguiente.
            $data = ['PlanMonth' => $next];

            if ($dev['CommittedDate'] === null || $dev['CommittedDate'] <= $last) {
                $estimate  = $dev['EstimatedDate'];
                $committed = $estimate !== null && $estimate > $last ? $estimate : $nextEnd;

                $data += [
                    'CarriedFromMonth' => $month,
                    'CommittedDate'    => $committed,
                    'EstimatedDate'    => $estimate !== null && $estimate > $committed ? $estimate : $committed,
                ];
            }

            $db->table('Developments')->where('IdDevelopment', $dev['IdDevelopment'])->update($data);

            if (isset($data['CommittedDate'])) {
                $this->board()->logReschedule((int) $dev['IdDevelopment'], $dev['CommittedDate'], $data['CommittedDate'],
                    'Cierre de ' . DevelopmentBoard::monthName($month) . ': la entrega venció sin aprobarse', $this->userName());
            }

            // 4. Hitos vencidos sin terminar pasan al mes siguiente.
            $taskDue = min($nextEnd, $data['CommittedDate'] ?? $dev['CommittedDate'] ?? $nextEnd);

            $db->table('DevelopmentTasks')
                ->set('OriginalDueDate', 'COALESCE(OriginalDueDate, DueDate)', false)
                ->set('DueDate', $taskDue)
                ->set('CarriedFromMonth', $month)
                ->where('IdDevelopment', $dev['IdDevelopment'])
                ->where('DoneAt', null)
                ->where('DueDate <=', $last)
                ->update();
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->with('error', 'No se pudo cerrar el mes.');
        }

        return redirect()->to(site_url('panel/desarrollos/cierre?mes=' . $month))
            ->with('success', DevelopmentBoard::monthName($month) . ' cerrado: cumplimiento ' . $summary['pct'] . ' % ('
                . $summary['met'] . ' de ' . $summary['eligible'] . '). ' . $summary['open'] . ' pendiente(s) pasaron a '
                . DevelopmentBoard::monthName($next) . '.');
    }

    /**
     * Cierre guardado (o null), desarrollos y estadística de un mes.
     *
     * @return array{0: array<string, mixed>|null, 1: list<array<string, mixed>>, 2: array<string, int>}
     */
    private function monthData(string $month): array
    {
        $closure = $this->board()->closure($month);

        if ($closure !== null) {
            $items = $this->board()->closureItems($month);

            // Cierres hechos antes del detalle: se reconstruye con lo que hay.
            if ($items === [] && (int) $closure['Committed'] > 0) {
                $items = $this->board()->withMonthGoal($this->board()->list($month), $month);
            }

            return [$closure, $items, DevelopmentBoard::closureSummary($closure)];
        }

        $rows = $this->board()->withMonthGoal($this->board()->list($month), $month);

        return [null, $rows, DevelopmentBoard::summary($rows, $month)];
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

    private function userName(): string
    {
        return mb_substr((string) ($this->user['name'] ?? panel_user_id()), 0, 150);
    }

    private function back(int $id, string $message, bool $ok = true, string $anchor = ''): RedirectResponse
    {
        return redirect()->to(site_url('panel/desarrollos/' . $id) . ($anchor !== '' ? '#' . $anchor : ''))
            ->with($ok ? 'success' : 'error', $message);
    }

    private function missingTables(): ?RedirectResponse
    {
        if (! DevelopmentBoard::available()) {
            return redirect()->to(site_url('panel'))->with('error', 'Falta crear las tablas de desarrollos (app/Database/sql/Desarrollos.sql).');
        }

        if (! DevelopmentBoard::milestonesAvailable()) {
            return redirect()->to(site_url('panel'))->with('error', 'Falta ejecutar app/Database/sql/DesarrollosHitosYCierre.sql (hitos por tarea y cierre de mes).');
        }

        return null;
    }
}
