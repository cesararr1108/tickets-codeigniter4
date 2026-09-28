<?php

namespace App\Controllers\Panel;

use App\Libraries\TicketStats;

class Reports extends BasePanelController
{
    private const PERIODS = [
        '30'  => 'Últimos 30 días',
        '90'  => 'Últimos 90 días',
        '365' => 'Último año',
        'all' => 'Todo el historial',
    ];

    public function index()
    {
        $period = (string) ($this->request->getGet('period') ?? '90');
        $period = array_key_exists($period, self::PERIODS) ? $period : '90';
        $since  = TicketStats::sinceUtc($period === 'all' ? null : (int) $period);

        $stats = new TicketStats();

        return $this->render('reports', [
            'active'     => 'reports',
            'title'      => 'Reportes',
            'periods'    => self::PERIODS,
            'period'     => $period,
            'status'     => $stats->countByStatus($since),
            'byPriority' => $stats->byPriority($since),
            'byCategory' => $stats->byCategory(false, $since),
            'byCompany'  => $stats->statusMatrix('t.CodCompanies', 'c.Companies', 'Companies c', 'c.CodCompanies = t.CodCompanies', $since),
            'byAgent'    => $stats->statusMatrix('t.AssignedUserId', 'u.FullName', 'Users u', 'u.IdUser = t.AssignedUserId', $since),
            'trend'      => $stats->weeklyTrend(12),
        ]);
    }
}
