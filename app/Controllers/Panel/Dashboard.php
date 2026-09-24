<?php

namespace App\Controllers\Panel;

use App\Libraries\TicketStats;

class Dashboard extends BasePanelController
{
    public function index()
    {
        $stats = new TicketStats();

        return $this->render('dashboard', [
            'active'     => 'dashboard',
            'title'      => 'Inicio',
            'kpis'       => $stats->kpis(),
            'byCategory' => $stats->byCategory(true),
            'trend'      => $stats->weeklyTrend(8),
            'topPending' => $stats->topPending(5),
            'activity'   => $stats->recentActivity(8),
            'byPriority' => $stats->byPriority(),
        ]);
    }
}
