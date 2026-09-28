<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Tickets;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Indicadores y series para el dashboard y los reportes.
 *
 * Las agregaciones por fecha se hacen en PHP para no depender de
 * funciones de fecha propias de cada motor de BD.
 */
class TicketStats
{
    private BaseConnection $db;
    private Tickets $config;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db     = $db ?? db_connect();
        $this->config = config(Tickets::class);
    }

    /**
     * Límite inferior (UTC) para un periodo en días. null = todo.
     */
    public static function sinceUtc(?int $days): ?string
    {
        return $days ? gmdate('Y-m-d H:i:s', time() - $days * 86400) : null;
    }

    /**
     * Conteo por estado: ['abierto' => n, 'en_progreso' => n, 'cerrado' => n].
     *
     * @return array<string, int>
     */
    public function countByStatus(?string $sinceUtc = null): array
    {
        $builder = $this->db->table('Tickets')
            ->select('Status')
            ->selectCount('IdTicket', 'total')
            ->groupBy('Status');

        if ($sinceUtc !== null) {
            $builder->where('CreatedAt >=', $sinceUtc);
        }

        $counts = array_fill_keys(array_keys($this->config->statuses), 0);

        foreach ($builder->get()->getResultArray() as $row) {
            $counts[$row['Status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Indicadores principales del dashboard.
     *
     * @return array<string, int|float|null>
     */
    public function kpis(): array
    {
        $status  = $this->countByStatus();
        $pending = $status['abierto'] + $status['en_progreso'];

        $unassigned = $this->pendingBuilder()
            ->where('AssignedUserId', null)
            ->countAllResults();

        $highPending = $this->pendingBuilder()
            ->where('Priority', 'alta')
            ->countAllResults();

        $late = $this->countLate();

        $last30  = $this->countByStatus(self::sinceUtc(30));
        $total30 = array_sum($last30);

        return [
            'pending'     => $pending,
            'open'        => $status['abierto'],
            'inProgress'  => $status['en_progreso'],
            'closed'      => $status['cerrado'],
            'unassigned'  => $unassigned,
            'highPending' => $highPending,
            'late'        => $late,
            'onTarget'    => $pending > 0 ? round(100 * ($pending - $late) / $pending) : null,
            'created30'   => $total30,
            'closed30'    => $last30['cerrado'],
            'closeRate30' => $total30 > 0 ? round(100 * $last30['cerrado'] / $total30) : null,
        ];
    }

    /**
     * Tickets abiertos o en progreso que superaron la meta de su prioridad.
     */
    public function countLate(?string $companyId = null): int
    {
        $total = 0;

        foreach ($this->config->targetHours as $priority => $hours) {
            $builder = $this->pendingBuilder()
                ->where('Priority', $priority)
                ->where('CreatedAt <', gmdate('Y-m-d H:i:s', time() - $hours * 3600));

            if ($companyId !== null) {
                $builder->where('CodCompanies', $companyId);
            }

            $total += $builder->countAllResults();
        }

        return $total;
    }

    private function pendingBuilder()
    {
        return $this->db->table('Tickets')
            ->whereIn('Status', ['abierto', 'en_progreso']);
    }

    /**
     * Tickets por categoría.
     *
     * @return list<array{id: int, name: string, total: int}>
     */
    public function byCategory(bool $onlyPending = true, ?string $sinceUtc = null): array
    {
        $builder = $this->db->table('Tickets t')
            ->select('t.IdCategory, cat.Category')
            ->selectCount('t.IdTicket', 'total')
            ->join('Category cat', 'cat.IdCategory = t.IdCategory', 'left')
            ->groupBy('t.IdCategory, cat.Category')
            ->orderBy('total', 'DESC');

        if ($onlyPending) {
            $builder->whereIn('t.Status', ['abierto', 'en_progreso']);
        }

        if ($sinceUtc !== null) {
            $builder->where('t.CreatedAt >=', $sinceUtc);
        }

        return array_map(static fn ($row) => [
            'id'    => (int) $row['IdCategory'],
            'name'  => $row['Category'] ?? 'Sin categoría',
            'total' => (int) $row['total'],
        ], $builder->get()->getResultArray());
    }

    /**
     * Tickets creados y cerrados por semana (últimas N semanas).
     * "Cerrados" se estima por la semana de creación de los tickets
     * que hoy están cerrados (la tabla no guarda fecha de cierre).
     *
     * @return list<array{label: string, created: int, closed: int}>
     */
    public function weeklyTrend(int $weeks = 8): array
    {
        $tz    = new DateTimeZone($this->config->displayTimezone);
        $today = new DateTimeImmutable('today', $tz);
        $start = $today->modify('monday this week')->modify('-' . ($weeks - 1) . ' weeks');

        $series = [];

        for ($i = 0; $i < $weeks; $i++) {
            $week = $start->modify("+{$i} weeks");

            $series[$week->format('Y-m-d')] = [
                'label'   => $week->format('d/m'),
                'created' => 0,
                'closed'  => 0,
            ];
        }

        $rows = $this->db->table('Tickets')
            ->select('CreatedAt, Status')
            ->where('CreatedAt >=', $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $local = (new DateTimeImmutable($row['CreatedAt'], new DateTimeZone('UTC')))->setTimezone($tz);
            $key   = $local->modify('monday this week')->format('Y-m-d');

            if (! isset($series[$key])) {
                continue;
            }

            $series[$key]['created']++;

            if ($row['Status'] === 'cerrado') {
                $series[$key]['closed']++;
            }
        }

        return array_values($series);
    }

    /**
     * Pendientes más urgentes: prioridad alta primero y luego los más antiguos.
     */
    public function topPending(int $limit = 5): array
    {
        $repo    = new TicketRepository($this->db);
        $builder = $repo->builder()->whereIn('t.Status', ['abierto', 'en_progreso']);

        $repo->applySort($builder, 'prioridad');

        return $builder->limit($limit)->get()->getResultArray();
    }

    /**
     * Últimos movimientos: tickets creados y mensajes del chat.
     *
     * @return list<array<string, mixed>>
     */
    public function recentActivity(int $limit = 8): array
    {
        $tickets = $this->db->table('Tickets')
            ->select('IdTicket, Subject, Status, Priority, RequesterEmail, CreatedAt')
            ->orderBy('CreatedAt', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        $messages = $this->db->table('TicketMessages m')
            ->select('m.MessageId, m.IdTicket, m.SenderType, m.SenderName, m.Message, m.CreatedAt, t.Subject')
            ->join('Tickets t', 't.IdTicket = m.IdTicket', 'left')
            ->orderBy('m.CreatedAt', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        $items = [];

        foreach ($tickets as $ticket) {
            $items[] = ['type' => 'ticket'] + $ticket;
        }

        foreach ($messages as $message) {
            $items[] = ['type' => 'message'] + $message;
        }

        usort($items, static fn ($a, $b) => strcmp((string) $b['CreatedAt'], (string) $a['CreatedAt']));

        return array_slice($items, 0, $limit);
    }

    /**
     * Conteo por estado agrupado por una columna (compañía, agente, etc.).
     *
     * @return list<array<string, mixed>>
     */
    public function statusMatrix(string $groupColumn, string $nameColumn, string $joinTable, string $joinOn, ?string $sinceUtc = null): array
    {
        $builder = $this->db->table('Tickets t')
            ->select("{$groupColumn} AS groupId, {$nameColumn} AS groupName, t.Status")
            ->selectCount('t.IdTicket', 'total')
            ->join($joinTable, $joinOn, 'left')
            ->groupBy("{$groupColumn}, {$nameColumn}, t.Status");

        if ($sinceUtc !== null) {
            $builder->where('t.CreatedAt >=', $sinceUtc);
        }

        $groups = [];

        foreach ($builder->get()->getResultArray() as $row) {
            $key = (string) ($row['groupId'] ?? '');

            $groups[$key] ??= [
                'id'          => $row['groupId'],
                'name'        => $row['groupName'],
                'abierto'     => 0,
                'en_progreso' => 0,
                'cerrado'     => 0,
                'total'       => 0,
            ];

            $groups[$key][$row['Status']] = (int) $row['total'];
            $groups[$key]['total'] += (int) $row['total'];
        }

        usort($groups, static fn ($a, $b) => $b['total'] <=> $a['total']);

        return $groups;
    }

    /**
     * @return array<string, int>
     */
    public function byPriority(?string $sinceUtc = null): array
    {
        $builder = $this->db->table('Tickets')
            ->select('Priority')
            ->selectCount('IdTicket', 'total')
            ->groupBy('Priority');

        if ($sinceUtc !== null) {
            $builder->where('CreatedAt >=', $sinceUtc);
        }

        $counts = array_fill_keys(array_keys($this->config->priorities), 0);

        foreach ($builder->get()->getResultArray() as $row) {
            $counts[$row['Priority']] = (int) $row['total'];
        }

        return $counts;
    }
}
