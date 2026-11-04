<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Informe de fin de mes de desarrollos y proyectos (para imprimir o PDF).
 * Con el mes cerrado sale de lo guardado en el cierre; si no, en vivo.
 *
 * @var string $month
 * @var array|null $closure
 * @var array $summary
 * @var list<array> $rows Desarrollos del mes (withMonthGoal o closureItems)
 * @var array<int, array> $live Datos actuales de cada desarrollo (decorate)
 * @var list<array> $issues Incidentes creados en el mes o aún abiertos
 * @var list<array> $reschedules Reprogramaciones registradas en el mes
 */
use App\Libraries\DevelopmentBoard as Board;

$next  = Board::shiftMonth($month, 1);
$kind  = static fn (array $d) => ! empty($d['IsProject']) || ($live[$d['IdDevelopment']]['IsProject'] ?? false);
$byDev = [];
foreach ($rows as $d) {
    $byDev[$d['IdDevelopment']] = $d;
}

// Resumen por programador.
$people = [];
foreach ($rows as $d) {
    $name = $d['ProgrammerName'] ?? 'Sin asignar';
    $people[$name] ??= ['total' => 0, 'completo' => 0, 'en_curso' => 0, 'sin_empezar' => 0, 'sin_asignar' => 0, 'eligible' => 0, 'met' => 0, 'due' => 0, 'doneMs' => 0];
    $p = &$people[$name];
    $p['total']++;
    $p[$d['Bucket']] = ($p[$d['Bucket']] ?? 0) + 1;
    if ($d['Eligible']) {
        $p['eligible']++;
        $p['met']    += $d['Met'] ? 1 : 0;
        $p['due']    += (int) $d['MonthDue'];
        $p['doneMs'] += (int) $d['MonthDone'];
    }
    unset($p);
}
ksort($people);

$projectRows = array_values(array_filter($rows, $kind));
$lostDays    = array_sum(array_map(static fn ($i) => (int) $i['ImpactDays'], $issues));
?>
<div class="no-print">
    <a class="back-link" href="<?= site_url('panel/desarrollos/cierre?mes=' . $month) ?>"><?= icon('arrow-left') ?> Cierre de <?= esc(Board::monthName($month)) ?></a>
</div>

<div class="page-head">
    <div>
        <div class="eyebrow">Informe de fin de mes</div>
        <h1>Desarrollos y proyectos · <?= esc(Board::monthName($month)) ?></h1>
        <p class="muted">
            <?= $closure !== null
                ? 'Mes cerrado el ' . format_date($closure['ClosedAt'], 'd/m/Y') . ' por ' . esc($closure['ClosedByName']) . '.'
                : 'Mes abierto: cifras al ' . Board::formatDate(Board::today()) . '. Se fijan al cerrar el mes.' ?>
            <?= panel_is_admin() ? '' : ' Solo tus desarrollos.' ?>
        </p>
    </div>
    <div class="month-nav no-print">
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/cierre/informe?mes=' . Board::shiftMonth($month, -1)) ?>" aria-label="Mes anterior">‹</a>
        <strong><?= esc(Board::monthName($month)) ?></strong>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/cierre/informe?mes=' . $next) ?>" aria-label="Mes siguiente">›</a>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/cierre/reporte?mes=' . $month) ?>"><?= icon('download') ?> Excel</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Imprimir / PDF</button>
    </div>
</div>

<?= view('panel/developments/_kpis', ['summary' => $summary, 'month' => $month, 'closed' => $closure !== null]) ?>

<section class="card table-card report-block" style="margin-top:20px">
    <div class="card-head" style="padding:20px 20px 0"><div><h2>Resumen por programador</h2><p class="muted">Clasificación del mes y cumplimiento de la meta (hitos que vencían en el mes).</p></div></div>
    <?php if ($people === []): ?>
        <p class="empty" style="padding:0 20px 20px">Sin desarrollos en el mes.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Programador</th><th class="num">Total</th><th class="num">Completos</th><th class="num">En curso</th><th class="num">Sin empezar</th><th class="num">Hitos del mes</th><th class="num">Meta cumplida</th></tr></thead>
                <tbody>
                    <?php foreach ($people as $name => $p): ?>
                        <tr>
                            <td><?= esc($name) ?></td>
                            <td class="num"><?= $p['total'] ?></td>
                            <td class="num"><?= $p['completo'] ?></td>
                            <td class="num"><?= $p['en_curso'] ?></td>
                            <td class="num"><?= $p['sin_empezar'] ?></td>
                            <td class="num"><?= $p['doneMs'] ?>/<?= $p['due'] ?></td>
                            <td class="num"><?= $p['eligible'] > 0 ? $p['met'] . ' de ' . $p['eligible'] . ' (' . (int) round($p['met'] / $p['eligible'] * 100) . '%)' : '—' ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<section class="card table-card report-block" style="margin-top:20px">
    <div class="card-head" style="padding:20px 20px 0"><div><h2>Detalle del mes</h2><p class="muted">Todo lo comprometido en <?= esc(Board::monthName($month)) ?> y si pasa a <?= esc(Board::monthName($next)) ?>.</p></div></div>
    <?php if ($rows === []): ?>
        <p class="empty" style="padding:0 20px 20px">Sin desarrollos en el mes.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Desarrollo</th><th>Programador</th><th>Clasificación</th><th>Meta del mes</th><th class="num">Avance</th><th>Entrega</th><th>Pasa al siguiente</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $d): ?>
                        <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>">
                            <td class="cell-main">
                                <span class="mono muted small"><?= esc($d['Code']) ?> · <?= ticket_code($d['IdTicket']) ?> · Cía <?= esc($d['CodCompanies']) ?></span>
                                <span class="cell-title"><?= esc($d['Subject']) ?></span>
                                <?php if ($kind($d)): ?><div><span class="chip chip-project">Proyecto</span></div><?php endif ?>
                            </td>
                            <td><?= esc($d['ProgrammerName'] ?? 'Sin asignar') ?></td>
                            <td><span class="chip"><?= esc(Board::BUCKETS[$d['Bucket']] ?? $d['Bucket']) ?></span></td>
                            <td class="small">
                                <?php if ($d['GoalPct'] === null): ?>
                                    <span class="muted">Sin hitos</span>
                                <?php else: ?>
                                    <?= (int) $d['GoalPct'] ?>% · <?= $d['Eligible'] ? ($d['Met'] ? '<span class="dev-good">cumplido</span>' : '<span class="dev-warn">no cumplido</span>') : '<span class="muted">no cuenta</span>' ?>
                                    <?php if ((int) $d['MonthDue'] > 0): ?><div class="muted"><?= (int) $d['MonthDone'] ?> de <?= (int) $d['MonthDue'] ?> hitos</div><?php endif ?>
                                <?php endif ?>
                            </td>
                            <td class="num"><?= (int) $d['Progress'] ?>%</td>
                            <td class="mono small"><?= Board::formatDate($d['CommittedDate']) ?></td>
                            <td><?= $d['State'] !== 'terminado' ? 'Sí' : 'No' ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<?php if ($projectRows !== []): ?>
    <section class="card table-card report-block" style="margin-top:20px">
        <div class="card-head" style="padding:20px 20px 0"><div><h2>Proyectos</h2><p class="muted">Presupuesto, entrega original y vigente, y estado actual de cada proyecto del mes.</p></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Proyecto</th><th>Semáforo hoy</th><th class="num">Avance</th><th>Entrega original</th><th>Entrega vigente</th><th class="num">Presupuesto</th><th class="num">Ejecutado</th><th class="num">Incidentes</th></tr></thead>
                <tbody>
                    <?php foreach ($projectRows as $d): $l = $live[$d['IdDevelopment']] ?? $d; ?>
                        <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>">
                            <td class="cell-main"><span class="mono muted small"><?= esc($d['Code']) ?></span><span class="cell-title"><?= esc($d['Subject']) ?></span></td>
                            <td><?= isset($l['Health']) ? dev_health_badge($l) : '—' ?></td>
                            <td class="num"><?= (int) ($l['Progress'] ?? $d['Progress']) ?>%</td>
                            <td class="mono small"><?= Board::formatDate($l['OriginalCommittedDate'] ?? null) ?></td>
                            <td class="mono small"><?= Board::formatDate($l['CommittedDate'] ?? null) ?><?= ($l['RescheduledDays'] ?? 0) > 0 ? ' <span class="chip chip-carry">+' . (int) $l['RescheduledDays'] . ' d</span>' : '' ?></td>
                            <td class="num mono small"><?= Board::money($l['BudgetPlanned'] ?? 0) ?></td>
                            <td class="num mono small"><?= Board::money($l['BudgetSpent'] ?? 0) ?></td>
                            <td class="num"><?= (int) ($l['OpenIssues'] ?? 0) ?> abiertos / <?= (int) ($l['IssuesTotal'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif ?>

<div class="dev-layout report-two" style="margin-top:20px">
    <div>
        <section class="card report-block">
            <h2>Incidentes e inconvenientes</h2>
            <p class="dev-sub">Reportados en el mes o aún abiertos · <?= $lostDays ?> día<?= $lostDays === 1 ? '' : 's' ?> de impacto.</p>
            <div class="dev-issues">
                <?php if ($issues === []): ?>
                    <p class="empty">Sin incidentes.</p>
                <?php endif ?>
                <?php foreach ($issues as $i): $open = $i['ResolvedAt'] === null; $d = $byDev[$i['IdDevelopment']] ?? null; ?>
                    <div class="dev-issue <?= $open ? 'is-' . esc($i['IssueType'], 'attr') : 'is-resolved' ?>">
                        <div class="dev-issue-head">
                            <span><?= esc($d['Code'] ?? Board::code((int) $i['IdDevelopment'])) ?> · <?= format_date($i['CreatedAt'], 'd/m/Y') ?> · <?= $i['IssueType'] === 'bloqueante' ? 'Bloqueante' : 'Riesgo' ?></span>
                            <span><?= $open ? 'Abierto' : 'Resuelto' ?> · <?= (int) $i['ImpactDays'] ?> d</span>
                        </div>
                        <p class="small"><?= esc($i['Description']) ?></p>
                    </div>
                <?php endforeach ?>
            </div>
        </section>
    </div>
    <div>
        <section class="card report-block">
            <h2>Reprogramaciones del mes</h2>
            <?php if ($reschedules === []): ?>
                <p class="empty">No se movió ninguna entrega.</p>
            <?php else: ?>
                <ul class="dev-resources">
                    <?php foreach ($reschedules as $r): $d = $byDev[$r['IdDevelopment']] ?? null; ?>
                        <li style="align-items:flex-start;flex-direction:column;gap:2px">
                            <span class="mono small"><?= esc($d['Code'] ?? Board::code((int) $r['IdDevelopment'])) ?> · <?= Board::formatDate($r['PreviousDate']) ?> → <strong><?= Board::formatDate($r['NewDate']) ?></strong></span>
                            <span class="small"><?= esc($r['Reason']) ?></span>
                            <span class="muted small"><?= esc($r['ChangedByName']) ?> · <?= format_date($r['CreatedAt'], 'd/m/Y') ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </div>
</div>

<p class="muted small" style="margin-top:16px">Generado el <?= Board::formatDate(Board::today()) ?>.</p>
<?= $this->endSection() ?>
