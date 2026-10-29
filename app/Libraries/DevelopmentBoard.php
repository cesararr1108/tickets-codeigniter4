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
 *  - La fecha comprometida la fija el administrador; el programador mueve la
 *    estimada. La diferencia es el retraso.
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

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /**
     * Desarrollos con datos del ticket, programador y totales.
     */
    public function builder(): BaseBuilder
    {
        $sub = static fn (string $sql) => "({$sql})";

        return $this->db->table('Developments d')
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
     * @return list<array<string, mixed>>
     */
    public function list(?string $month = null, ?string $userId = null): array
    {
        $builder = $this->builder();

        if ($month !== null) {
            $builder->where('d.PlanMonth', $month);
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
     * Totales de un mes para los indicadores y el cierre.
     *
     * @param list<array<string, mixed>> $rows Desarrollos del mes
     *
     * @return array<string, int>
     */
    public static function summary(array $rows, string $month): array
    {
        $done    = count(array_filter($rows, static fn ($d) => $d['State'] === 'terminado'));
        $blocked = count(array_filter($rows, static fn ($d) => $d['State'] === 'bloqueado'));
        $last    = self::lastDay($month);
        $late    = count(array_filter($rows, static fn ($d) => $d['State'] !== 'terminado'
            && (string) ($d['EstimatedDate'] ?: $d['CommittedDate']) > $last));
        $total   = count($rows);

        return [
            'total'   => $total,
            'done'    => $done,
            'open'    => $total - $done,
            'blocked' => $blocked,
            'late'    => $late,
            'pct'     => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
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

    private static function dateOnly(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : substr((string) $value, 0, 10);
    }
}
