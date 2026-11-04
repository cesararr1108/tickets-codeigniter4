<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Reporte de cómo van los proyectos.
 *
 * @var string $scope abiertos | todos
 * @var list<array> $rows Proyectos (DevelopmentBoard::list(..., 'proyecto'))
 * @var array<int, array> $next Próxima actividad pendiente por proyecto
 * @var array<int, array> $open Último incidente abierto por proyecto
 * @var array $summary
 */
use App\Libraries\DevelopmentBoard as Board;

$s     = $summary;
$query = $scope === 'todos' ? 'estado=todos&' : '';
$execPct = $s['planned'] > 0 ? (int) round($s['spent'] / $s['planned'] * 100) : 0;
?>
<a class="back-link no-print" href="<?= site_url('panel/desarrollos') ?>"><?= icon('arrow-left') ?> Plan del mes</a>

<div class="page-head">
    <div>
        <h1>Reporte de proyectos</h1>
        <p class="muted">Cómo van los proyectos al <?= Board::formatDate(Board::today()) ?>: avance, actividades, presupuesto, incidentes y reprogramaciones de la entrega.</p>
    </div>
    <div class="month-nav no-print">
        <a class="btn btn-sm <?= $scope === 'abiertos' ? 'btn-primary' : 'btn-outline' ?>" href="<?= site_url('panel/desarrollos/proyectos') ?>">En curso</a>
        <a class="btn btn-sm <?= $scope === 'todos' ? 'btn-primary' : 'btn-outline' ?>" href="<?= site_url('panel/desarrollos/proyectos?estado=todos') ?>">Todos</a>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/proyectos?' . $query . 'formato=csv') ?>"><?= icon('download') ?> Excel</a>
        <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">Imprimir / PDF</button>
    </div>
</div>

<section class="dev-kpis">
    <div class="card kpi">
        <div class="kpi-head"><span>Proyectos</span></div>
        <div class="kpi-value"><?= $s['total'] ?></div>
        <div class="kpi-foot">avance promedio <?= $s['progress'] ?>%</div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>En tiempo</span></div>
        <div class="kpi-value dev-good"><?= $s['health']['en_tiempo'] ?></div>
        <div class="kpi-foot"><?= $s['health']['entregado'] ?> entregado<?= $s['health']['entregado'] === 1 ? '' : 's' ?></div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>En riesgo</span></div>
        <div class="kpi-value <?= $s['health']['en_riesgo'] > 0 ? 'dev-warn' : '' ?>"><?= $s['health']['en_riesgo'] ?></div>
        <div class="kpi-foot">incidentes abiertos o reprogramados</div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>Atrasados</span></div>
        <div class="kpi-value <?= $s['health']['atrasado'] > 0 ? 'dev-bad' : '' ?>"><?= $s['health']['atrasado'] ?></div>
        <div class="kpi-foot">vencidos, bloqueados o con actividades vencidas</div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>Reprogramados</span></div>
        <div class="kpi-value"><?= $s['rescheduled'] ?></div>
        <div class="kpi-foot"><?= $s['impact'] ?> días de impacto por incidentes</div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>Presupuesto</span></div>
        <div class="kpi-value" style="font-size:20px"><?= Board::money($s['planned']) ?></div>
        <div class="dev-progress <?= $s['spent'] > $s['planned'] ? 'is-blocked' : '' ?>"><span style="width: <?= min(100, $execPct) ?>%"></span></div>
        <div class="kpi-foot">ejecutado <?= Board::money($s['spent']) ?> (<?= $execPct ?>%)</div>
    </div>
</section>

<section class="card table-card" style="margin-top:20px">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <?= icon('code', 'icon icon-xl') ?>
            <strong>No hay proyectos <?= $scope === 'abiertos' ? 'en curso' : '' ?></strong>
            <span class="muted">Los tickets de proyecto aparecen en "Por planear" del plan del mes; al agregarlos como Proyecto quedan aquí.</span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table dev-report-table">
                <thead>
                    <tr>
                        <th>Proyecto</th>
                        <th>Semáforo</th>
                        <th>Avance</th>
                        <th>Actividades</th>
                        <th>Próxima actividad</th>
                        <th>Entrega</th>
                        <th>Presupuesto</th>
                        <th>Incidentes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $d): $n = $next[$d['IdDevelopment']] ?? null; $i = $open[$d['IdDevelopment']] ?? null; ?>
                        <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>">
                            <td class="cell-main">
                                <span class="mono muted small"><?= esc($d['Code']) ?> · <?= ticket_code($d['IdTicket']) ?> · Cía <?= esc($d['CodCompanies']) ?></span>
                                <span class="cell-title"><?= esc($d['Subject']) ?></span>
                                <span class="small muted"><?= esc($d['ProgrammerName'] ?? 'Sin responsable') ?> · <?= esc(Board::STATES[$d['State']]) ?></span>
                            </td>
                            <td><div><?= dev_health_badge($d) ?></div></td>
                            <td><?= dev_progress($d) ?></td>
                            <td class="small">
                                <?= $d['TasksDone'] ?> de <?= $d['TasksTotal'] ?>
                                <?php if ($d['OverdueTasks'] > 0): ?><div><span class="chip chip-late"><?= $d['OverdueTasks'] ?> vencida<?= $d['OverdueTasks'] === 1 ? '' : 's' ?></span></div><?php endif ?>
                            </td>
                            <td class="small" style="min-width:180px">
                                <?php if ($n === null): ?>
                                    <span class="muted"><?= $d['TasksTotal'] === 0 ? 'Sin plan de actividades' : 'Todas terminadas' ?></span>
                                <?php else: $late = $n['DueDate'] !== null && Board::dateOnly($n['DueDate']) < Board::today(); ?>
                                    <?= esc($n['Description']) ?>
                                    <div class="muted"><?= esc($n['ResponsibleName'] ?: 'Sin responsable') ?> · <span class="<?= $late ? 'dev-warn' : '' ?>"><?= Board::formatDate($n['DueDate']) ?></span></div>
                                <?php endif ?>
                            </td>
                            <td class="mono small">
                                <?= Board::formatDate($d['CommittedDate']) ?>
                                <?php if ($d['RescheduledDays'] > 0): ?>
                                    <div class="muted" title="Entrega original">orig. <?= Board::formatDate($d['OriginalCommittedDate']) ?></div>
                                    <div><span class="chip chip-carry">+<?= $d['RescheduledDays'] ?> días</span></div>
                                <?php endif ?>
                                <?php if ($d['DelayDays'] > 0 && $d['State'] !== 'terminado'): ?><div><span class="chip chip-late">est. +<?= $d['DelayDays'] ?> días</span></div><?php endif ?>
                            </td>
                            <td class="small nowrap">
                                <?php if ($d['BudgetPlanned'] > 0): ?>
                                    <?= Board::money($d['BudgetSpent']) ?>
                                    <div class="muted">de <?= Board::money($d['BudgetPlanned']) ?> (<?= $d['BudgetPct'] ?>%)</div>
                                <?php else: ?>
                                    <span class="muted">Sin presupuesto</span>
                                <?php endif ?>
                            </td>
                            <td class="small" style="min-width:160px">
                                <?php if ((int) $d['OpenIssues'] > 0): ?>
                                    <span class="chip chip-late"><?= (int) $d['OpenIssues'] ?> abierto<?= (int) $d['OpenIssues'] === 1 ? '' : 's' ?></span>
                                    <?php if ($i !== null): ?><div class="muted" style="margin-top:4px"><?= esc(mb_strimwidth((string) $i['Description'], 0, 90, '…')) ?></div><?php endif ?>
                                <?php else: ?>
                                    <span class="muted"><?= $d['IssuesTotal'] > 0 ? $d['IssuesTotal'] . ' resuelto' . ($d['IssuesTotal'] === 1 ? '' : 's') : 'Ninguno' ?></span>
                                <?php endif ?>
                                <?php if ($d['ImpactDaysTotal'] > 0): ?><div class="muted"><?= $d['ImpactDaysTotal'] ?> días de impacto</div><?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<p class="muted small">
    Semáforo: <strong>Atrasado</strong> si la entrega venció, está bloqueado, la fecha estimada pasa la comprometida o hay actividades vencidas;
    <strong>En riesgo</strong> si tiene incidentes abiertos, la entrega se reprogramó o el ejecutado supera el presupuesto; si no, <strong>En tiempo</strong>.
</p>
<?= $this->endSection() ?>
