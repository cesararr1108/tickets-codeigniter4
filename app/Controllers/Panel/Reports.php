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
        'custom' => 'Entre fechas…',
    ];

    public function index()
    {
        $period = (string) ($this->request->getGet('period') ?? '90');
        [$from, $to] = $this->dateRange();

        // Con fechas en la URL el periodo es el rango elegido.
        if ($from !== null || $to !== null) {
            $period = 'custom';
        }

        $period = array_key_exists($period, self::PERIODS) ? $period : '90';
        $since  = $period === 'custom' || $period === 'all' ? null : TicketStats::sinceUtc((int) $period);

        $stats = (new TicketStats())->between($period === 'custom' ? $from : null, $period === 'custom' ? $to : null);

        return $this->render('reports', [
            'active'     => 'reports',
            'title'      => 'Reportes',
            'periods'    => self::PERIODS,
            'period'     => $period,
            'from'       => $period === 'custom' ? $from : null,
            'to'         => $period === 'custom' ? $to : null,
            'status'     => $stats->countByStatus($since),
            'byPriority' => $stats->byPriority($since),
            'byCategory' => $stats->byCategory(false, $since),
            'byCompany'  => $stats->statusMatrix('t.CodCompanies', 'c.Companies', 'Companies c', 'c.CodCompanies = t.CodCompanies', $since),
            'byBranch'   => $stats->statusMatrix('t.CodBranches', 'b.Branches', 'Branches b', 'b.CodBranches = t.CodBranches', $since),
            'byCategoryMatrix'    => $stats->statusMatrix('t.IdCategory', 'cat.Category', 'Category cat', 'cat.IdCategory = t.IdCategory', $since),
            'bySubCategoryMatrix' => $stats->statusMatrix('t.IdSubCategory', 'sc.SubCategory', 'SubCategory sc', 'sc.IdSubCategory = t.IdSubCategory', $since),
            'byArea'     => $stats->areaMatrix($since),
            'timeByType' => [
                'incidente'     => ['Incidentes', $stats->timeByUser($since, 'incidente')],
                'requerimiento' => ['Requerimientos', $stats->timeByUser($since, 'requerimiento')],
            ],
            'byAgent'    => $stats->statusMatrix('t.AssignedUserId', 'u.FullName', 'Users u', 'u.IdUser = t.AssignedUserId', $since),
            'trend'      => $stats->weeklyTrend(12),
        ]);
    }
}
