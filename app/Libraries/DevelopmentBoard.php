<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

/**
 * Seguimiento de desarrollos (ver app/Database/sql/Desarrollos.sql).
 *
 * Reglas:
 *  - Avance = horas de tareas terminadas / horas totales.
 *  - Estado: Terminado (aprobado) > Por planear (sin mes) > Bloqueado (algún
 *    inconveniente bloqueante abierto) > En pruebas (100 %) > En desarrollo
 *    (algo de avance) > Planeado.
 *  - La fecha comprometida (entrega del proyecto) la fija el administrador;
 *    el programador mueve la estimada. La diferencia es el retraso.
 *  - El desarrollo está en los meses de su rango: desde PlanMonth (mes en que
 *    se planeó o al que pasó en el último cierre) hasta que se aprueba. La
 *    entrega comprometida no puede quedar antes del mes del plan.
 *  - Cada tarea tiene su fecha de cumplimiento (hito). La meta del mes son
 *    las tareas que vencen en el mes: si se cumplen todas, la meta queda al
 *    100 % aunque el proyecto vaya, por ejemplo, al 50 %.
 *  - Cumplimiento del mes: solo cuentan los desarrollos asignados que tienen
 *    hitos en el mes, o que tienen requerimiento y su entrega vence en el mes
 *    (ahí la meta es la entrega aprobada).
 *  - Al cerrar el mes todos los abiertos pasan al mes siguiente.
 */
class DevelopmentBoard
{
    public const STATES = [
        'por_planear'   => 'Por planear',
        'planeado'      => 'Planeado',
        'en_desarrollo' => 'En desarrollo',
        'bloqueado'     => 'Bloqueado',
        'en_pruebas'    => 'En pruebas',
        'terminado'     => 'Terminado',
    ];

    /** Clasificación del cierre (excluyentes, en este orden). */
    public const BUCKETS = [
        'completo'    => 'Completos',
        'sin_asignar' => 'Sin asignar',
        'sin_empezar' => 'Sin empezar',
        'en_curso'    => 'En curso',
    ];

    public const MONTHS = [
        1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * ¿Existen las tablas? (sin el script el panel funciona sin desarrollos).
     */
    public static function available(): bool
    {
        static $exists = null;

        return $exists ??= db_connect()->tableExists('Developments');
    }

    /**
     * ¿Se ejecutó DesarrollosHitosYCierre.sql? (hitos y detalle del cierre).
     */
    public static function milestonesAvailable(): bool
    {
        static $exists = null;

        return $exists ??= self::available()
            && db_connect()->fieldExists('DueDate', 'DevelopmentTasks')
            && db_connect()->tableExists('DevelopmentMonthClosureItems');
    }

    private static function requirementsAvailable(): bool
    {
        static $exists = null;

        return $exists ??= db_connect()->tableExists('TicketFormAnswers')
            && config('Tickets')->developmentRequirementForms !== [];
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /**
     * Desarrollos con datos del ticket, programador y totales.
     */
    public function builder(): BaseBuilder
    {
        $sub = static fn (string $sql) => "({$sql})";

        $requirement = '0';

        if (self::requirementsAvailable()) {
            $forms       = implode(', ', array_map([$this->db, 'escape'], config('Tickets')->developmentRequirementForms));
            $requirement = "CASE WHEN EXISTS (SELECT 1 FROM TicketFormAnswers fa WHERE fa.IdTicket = d.IdTicket AND fa.FormKey IN ({$forms})) THEN 1 ELSE 0 END";
        }

        return $this->db->table('Developments d')
            ->select($requirement . ' AS HasRequirement', false)
            ->select('d.*, t.Subject, t.CodCompanies, t.RequesterEmail, t.Status AS TicketStatus, t.IdCategory,
                c.Companies, cat.Category, u.FullName AS ProgrammerName')
            ->select($sub('SELECT COALESCE(SUM(k.Hours), 0) FROM DevelopmentTasks k WHERE k.IdDevelopment = d.IdDevelopment') . ' AS HoursTotal', false)
            ->select($sub('SELECT COALESCE(SUM(k.Hours), 0) FROM DevelopmentTasks k WHERE k.IdDevelopment = d.IdDevelopment AND k.DoneAt IS NOT NULL') . ' AS HoursDone', false)
            ->select($sub("SELECT COUNT(*) FROM DevelopmentIssues i WHERE i.IdDevelopment = d.IdDevelopment AND i.ResolvedAt IS NULL AND i.IssueType = 'bloqueante'") . ' AS OpenBlocking', false)
            ->select($sub('SELECT COUNT(*) FROM DevelopmentIssues i WHERE i.IdDevelopment = d.IdDevelopment AND i.ResolvedAt IS NULL') . ' AS OpenIssues', false)
            ->select($sub('SELECT COUNT(*) FROM DevelopmentEvidence e WHERE e.IdDevelopment = d.IdDevelopment') . ' AS EvidenceCount', false)
            ->join('Tickets t', 't.IdTicket = d.IdTicket')
            ->join('Companies c', 'c.CodCompanies = t.CodCompanies', 'left')
            ->join('Category cat', 'cat.IdCategory = t.IdCategory', 'left')
            ->join('Users u', 'u.IdUser = d.AssignedUserId', 'left');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->builder()->where('d.IdDevelopment', $id)->get()->getRowArray();

        return $row === null ? null : self::decorate($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByTicket(int $ticketId): ?array
    {
        if (! self::available()) {
            return null;
        }

        $row = $this->builder()->where('d.IdTicket', $ticketId)->get()->getRowArray();

        return $row === null ? null : self::decorate($row);
    }

    /**
     * Desarrollos de un mes (o todos si $month es null). $userId limita a
     * los de un programador.
     *
     * Un desarrollo está en el mes M si se aprobó con PlanMonth = M, o si
     * sigue abierto y M está en su rango (PlanMonth <= M): mientras no se
     * entregue aparece en cada mes hasta su entrega y, si se atrasa, en los
     * siguientes.
     *
     * @return list<array<string, mixed>>
     */
    public function list(?string $month = null, ?string $userId = null): array
    {
        $builder = $this->builder();

        if ($month !== null) {
            $builder->groupStart()
                ->where('d.PlanMonth', $month)
                ->orGroupStart()
                    ->where('d.ApprovedAt', null)
                    ->where('d.PlanMonth IS NOT NULL', null, false)
                    ->where('d.PlanMonth <', $month)
                ->groupEnd()
            ->groupEnd();
        }

        if ($userId !== null) {
            $builder->where('d.AssignedUserId', $userId);
        }

        $rows = $builder
            ->orderBy('d.PlanMonth', 'DESC')
            ->orderBy('d.CommittedDate', 'ASC')
            ->orderBy('d.IdDevelopment', 'ASC')
            ->get()
            ->getResultArray();

        return array_map([self::class, 'decorate'], $rows);
    }

    /**
     * Desarrollos que se pasaron desde un mes cerrado.
     *
     * @return list<array<string, mixed>>
     */
    public function carriedFrom(string $month): array
    {
        $rows = $this->builder()->where('d.CarriedFromMonth', $month)->orderBy('d.IdDevelopment')->get()->getResultArray();

        return array_map([self::class, 'decorate'], $rows);
    }

    /**
     * Por planear: tickets abiertos de las categorías de desarrollo que aún
     * no tienen desarrollo, más los desarrollos sin mes.
     *
     * @return list<array<string, mixed>>
     */
    public function backlog(): array
    {
        $names = array_map('mb_strtolower', config('Tickets')->developmentCategories);
        $out   = [];

        if ($names !== []) {
            $tickets = $this->db->table('Tickets t')
                ->select('t.IdTicket, t.Subject, t.CodCompanies, t.RequesterEmail, t.AssignedUserId, t.CreatedAt, cat.Category, c.Companies')
                ->join('Category cat', 'cat.IdCategory = t.IdCategory')
                ->join('Companies c', 'c.CodCompanies = t.CodCompanies', 'left')
                ->where('LOWER(cat.Category) IN (' . implode(', ', array_map([$this->db, 'escape'], $names)) . ')', null, false)
                ->where('t.Status !=', 'cerrado')
                ->where('NOT EXISTS (SELECT 1 FROM Developments d WHERE d.IdTicket = t.IdTicket)', null, false)
                ->orderBy('t.CreatedAt', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($tickets as $t) {
                $out[] = $t + ['IdDevelopment' => null];
            }
        }

        foreach ($this->builder()->where('d.PlanMonth', null)->where('d.ApprovedAt', null)->get()->getResultArray() as $row) {
            $out[] = self::decorate($row);
        }

        return $out;
    }

    /**
     * Agrega a los desarrollos de un mes la meta del mes (hitos que vencen en
     * el mes y cuántos están terminados), si cuenta para el cumplimiento y si
     * lo cumplió, y su clasificación para el cierre.
     *
     * @param list<array<string, mixed>> $rows Desarrollos del mes (list())
     *
     * @return list<array<string, mixed>>
     */
    public function withMonthGoal(array $rows, string $month): array
    {
        $counts = [];
        $ids    = array_column($rows, 'IdDevelopment');

        if ($ids !== [] && self::milestonesAvailable()) {
            $result = $this->db->table('DevelopmentTasks')
                ->select('IdDevelopment, COUNT(*) AS Due, SUM(CASE WHEN DoneAt IS NOT NULL THEN 1 ELSE 0 END) AS Done', false)
                ->whereIn('IdDevelopment', $ids)
                ->where('DueDate >=', $month . '-01')
                ->where('DueDate <=', self::lastDay($month))
                ->groupBy('IdDevelopment')
                ->get()
                ->getResultArray();

            $counts = array_column($result, null, 'IdDevelopment');
        }

        $last = self::lastDay($month);

        foreach ($rows as &$d) {
            $c        = $counts[$d['IdDevelopment']] ?? ['Due' => 0, 'Done' => 0];
            $due      = (int) $c['Due'];
            $done     = (int) $c['Done'];
            $assigned = (string) ($d['AssignedUserId'] ?? '') !== '';
            $finished = $d['State'] === 'terminado';
            $delivery = $d['CommittedDate'] !== null && $d['CommittedDate'] <= $last;

            $d['MonthDue']  = $due;
            $d['MonthDone'] = $done;
            $d['GoalPct']   = null;
            $d['Eligible']  = false;
            $d['Met']       = false;

            if ($due > 0) {
                // Meta del mes = sus hitos del mes.
                $d['Eligible'] = $assigned;
                $d['Met']      = $done >= $due;
                $d['GoalPct']  = (int) floor($done / $due * 100);
            } elseif ((int) ($d['HasRequirement'] ?? 0) === 1 && ($delivery || $finished)) {
                // Requerimiento sin hitos: la meta es la entrega aprobada.
                $d['Eligible'] = $assigned;
                $d['Met']      = $finished;
                $d['GoalPct']  = $finished ? 100 : 0;
            }

            $d['Bucket'] = match (true) {
                $finished                      => 'completo',
                ! $assigned                    => 'sin_asignar',
                (float) $d['HoursDone'] <= 0   => 'sin_empezar',
                default                        => 'en_curso',
            };
        }
        unset($d);

        return $rows;
    }

    /**
     * Estadística de un mes (filas de withMonthGoal()).
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, int>
     */
    public static function summary(array $rows, string $month): array
    {
        $count = static fn (callable $fn) => count(array_filter($rows, $fn));
        $last  = self::lastDay($month);

        $total    = count($rows);
        $eligible = $count(static fn ($d) => $d['Eligible']);
        $met      = $count(static fn ($d) => $d['Eligible'] && $d['Met']);
        $due      = array_sum(array_map(static fn ($d) => $d['Eligible'] ? $d['MonthDue'] : 0, $rows));
        $doneMs   = array_sum(array_map(static fn ($d) => $d['Eligible'] ? $d['MonthDone'] : 0, $rows));

        return [
            'total'          => $total,
            'done'           => $count(static fn ($d) => $d['Bucket'] === 'completo'),
            'unassigned'     => $count(static fn ($d) => $d['Bucket'] === 'sin_asignar'),
            'notStarted'     => $count(static fn ($d) => $d['Bucket'] === 'sin_empezar'),
            'inProgress'     => $count(static fn ($d) => $d['Bucket'] === 'en_curso'),
            'open'           => $count(static fn ($d) => $d['State'] !== 'terminado'),
            'blocked'        => $count(static fn ($d) => $d['State'] === 'bloqueado'),
            'late'           => $count(static fn ($d) => $d['State'] !== 'terminado'
                && (string) ($d['EstimatedDate'] ?: $d['CommittedDate']) > $last),
            'eligible'       => $eligible,
            'met'            => $met,
            'notMet'         => $eligible - $met,
            'pct'            => $eligible > 0 ? (int) round($met / $eligible * 100) : 0,
            'milestonesDue'  => $due,
            'milestonesDone' => $doneMs,
            'milestonePct'   => $due > 0 ? (int) round($doneMs / $due * 100) : 0,
        ];
    }

    /**
     * Estadística guardada al cerrar un mes, con las mismas llaves de summary().
     *
     * @param array<string, mixed> $closure
     *
     * @return array<string, int>
     */
    public static function closureSummary(array $closure): array
    {
        $eligible = (int) ($closure['Eligible'] ?? 0);
        $met      = (int) ($closure['Met'] ?? 0);
        $due      = (int) ($closure['MilestonesDue'] ?? 0);
        $doneMs   = (int) ($closure['MilestonesDone'] ?? 0);

        return [
            'total'          => (int) $closure['Committed'],
            'done'           => (int) $closure['Delivered'],
            'unassigned'     => (int) ($closure['Unassigned'] ?? 0),
            'notStarted'     => (int) ($closure['NotStarted'] ?? 0),
            'inProgress'     => (int) ($closure['InProgress'] ?? 0),
            'open'           => (int) $closure['CarriedOver'],
            'blocked'        => 0,
            'late'           => 0,
            'eligible'       => $eligible,
            'met'            => $met,
            'notMet'         => $eligible - $met,
            'pct'            => $eligible > 0 ? (int) round($met / $eligible * 100) : 0,
            'milestonesDue'  => $due,
            'milestonesDone' => $doneMs,
            'milestonePct'   => $due > 0 ? (int) round($doneMs / $due * 100) : 0,
        ];
    }

    /**
     * Detalle por desarrollo guardado en el cierre de un mes.
     *
     * @return list<array<string, mixed>>
     */
    public function closureItems(string $month): array
    {
        if (! self::milestonesAvailable()) {
            return [];
        }

        $rows = $this->db->table('DevelopmentMonthClosureItems')
            ->where('PlanMonth', $month)
            ->orderBy('IdDevelopment')
            ->get()
            ->getResultArray();

        return array_map(static function (array $r): array {
            $r['Code']          = self::code((int) $r['IdDevelopment']);
            $r['Progress']      = (int) $r['ProjectPct'];
            $r['MonthDue']      = (int) $r['MilestonesDue'];
            $r['MonthDone']     = (int) $r['MilestonesDone'];
            $r['Eligible']      = (bool) $r['Eligible'];
            $r['Met']           = (bool) $r['Met'];
            $r['GoalPct']       = $r['GoalPct'] === null ? null : (int) $r['GoalPct'];
            $r['CommittedDate'] = self::dateOnly($r['CommittedDate']);

            return $r;
        }, $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function closure(string $month): ?array
    {
        return $this->db->table('DevelopmentMonthClosures')->where('PlanMonth', $month)->get()->getRowArray();
    }

    // ------------------------------------------------------------------
    // Reglas
    // ------------------------------------------------------------------

    /**
     * Agrega avance, estado y retraso a una fila de builder().
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function decorate(array $row): array
    {
        $total = (float) ($row['HoursTotal'] ?? 0);
        $done  = (float) ($row['HoursDone'] ?? 0);

        $row['Progress'] = $total > 0 ? (int) round($done / $total * 100) : 0;
        $row['Code']     = self::code((int) $row['IdDevelopment']);
        $row['HasRequirement'] = (int) ($row['HasRequirement'] ?? 0);
        $row['DelayDays'] = 0;

        $committed = self::dateOnly($row['CommittedDate'] ?? null);
        $estimated = self::dateOnly($row['EstimatedDate'] ?? null);
        $row['CommittedDate'] = $committed;
        $row['EstimatedDate'] = $estimated;

        if ($committed !== null && $estimated !== null && $estimated > $committed) {
            $row['DelayDays'] = self::daysBetween($committed, $estimated);
        }

        $row['State'] = match (true) {
            $row['ApprovedAt'] !== null       => 'terminado',
            $row['PlanMonth'] === null        => 'por_planear',
            (int) $row['OpenBlocking'] > 0    => 'bloqueado',
            $total > 0 && $done >= $total     => 'en_pruebas',
            $done > 0                         => 'en_desarrollo',
            default                           => 'planeado',
        };

        return $row;
    }

    public static function code(int $id): string
    {
        return 'DEV-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * ¿El usuario puede actualizar el desarrollo? (el administrador o el
     * programador asignado, mientras no esté aprobado).
     *
     * @param array<string, mixed> $dev
     */
    public static function canWork(array $dev): bool
    {
        return $dev['ApprovedAt'] === null
            && (panel_is_admin() || (string) ($dev['AssignedUserId'] ?? '') === panel_user_id());
    }

    // ------------------------------------------------------------------
    // Fechas y meses
    // ------------------------------------------------------------------

    public static function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(config('Tickets')->displayTimezone)))->format('Y-m-d');
    }

    public static function currentMonth(): string
    {
        return substr(self::today(), 0, 7);
    }

    public static function validMonth(?string $month): ?string
    {
        return is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : null;
    }

    public static function monthName(?string $month): string
    {
        if ($month === null) {
            return '—';
        }

        [$y, $m] = array_map('intval', explode('-', $month));

        return self::MONTHS[$m] . ' ' . $y;
    }

    public static function shiftMonth(string $month, int $delta): string
    {
        return (new \DateTimeImmutable($month . '-01'))->modify(($delta >= 0 ? '+' : '') . $delta . ' month')->format('Y-m');
    }

    public static function lastDay(string $month): string
    {
        return (new \DateTimeImmutable($month . '-01'))->format('Y-m-t');
    }

    public static function validDate(?string $date): ?string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date);

        return $d !== false && $d->format('Y-m-d') === $date ? $date : null;
    }

    public static function formatDate(?string $date): string
    {
        $date = self::dateOnly($date);

        return $date === null ? '—' : implode('/', array_reverse(explode('-', $date)));
    }

    public static function daysBetween(string $from, string $to): int
    {
        return (int) (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->format('%r%a');
    }

    public static function dateOnly(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : substr((string) $value, 0, 10);
    }
}
