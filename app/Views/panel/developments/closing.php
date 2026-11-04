<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Cierre de mes: estadística (completos, sin asignar, sin empezar, en curso),
 * cumplimiento de la meta del mes y desarrollos que pasan al mes siguiente.
 * Si el mes está cerrado, todo sale de lo guardado en el cierre.
 *
 * @var string $month
 * @var array|null $closure
 * @var array $summary
 * @var list<array> $done
 * @var list<array> $pending
 * @var list<array> $all
 * @var list<array> $issues
 */
use App\Libraries\DevelopmentBoard as Board;

$next = Board::shiftMonth($month, 1);

// Entregas por programador.
$people = [];
foreach (array_merge($done, $pending) as $d) {
    $name = $d['ProgrammerName'] ?? 'Sin asignar';
    $people[$name] ??= ['done' => 0, 'total' => 0, 'eligible' => 0, 'met' => 0];
    $people[$name]['total']++;
    if ($d['State'] === 'terminado') {
        $people[$name]['done']++;
    }
    if ($d['Eligible']) {
        $people[$name]['eligible']++;
        $people[$name]['met'] += $d['Met'] ? 1 : 0;
    }
}
$maxTotal = max([1, ...array_column($people, 'total')]);
$counted  = array_values(array_filter($all, static fn ($d) => $d['Eligible']));

$byDev     = array_column($all, null, 'IdDevelopment');
$lostDays  = array_sum(array_map(static fn ($i) => (int) $i['ImpactDays'], $issues));
?>
<a class="back-link" href="<?= site_url('panel/desarrollos?mes=' . $month) ?>"><?= icon('arrow-left') ?> Plan de <?= esc(Board::monthName($month)) ?></a>

<div class="page-head">
    <div>
        <h1>Cierre de <?= esc(Board::monthName($month)) ?></h1>
        <p class="muted">Completos, sin asignar y sin empezar; cumplimiento de la meta del mes y lo que pasa a <?= esc(Board::monthName($next)) ?>.</p>
    </div>
    <div class="month-nav">
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/cierre?mes=' . Board::shiftMonth($month, -1)) ?>" aria-label="Mes anterior">‹</a>
        <strong><?= esc(Board::monthName($month)) ?></strong>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/cierre?mes=' . $next) ?>" aria-label="Mes siguiente">›</a>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/cierre/reporte?mes=' . $month) ?>"><?= icon('download') ?> Reporte (Excel)</a>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/cierre/informe?mes=' . $month) ?>">Informe (imprimir / PDF)</a>
        <?php if ($closure !== null): ?>
            <span class="chip">Cerrado el <?= format_date($closure['ClosedAt'], 'd/m/Y') ?> por <?= esc($closure['ClosedByName']) ?></span>
        <?php elseif (panel_is_admin()): ?>
            <form method="post" action="<?= site_url('panel/desarrollos/cierre') ?>"
                  data-confirm="¿Cerrar <?= esc(Board::monthName($month), 'attr') ?>? Se guarda la estadística y los <?= count($pending) ?> desarrollos abiertos pasan a <?= esc(Board::monthName($next), 'attr') ?>.">
                <?= csrf_field() ?>
                <input type="hidden" name="PlanMonth" value="<?= esc($month, 'attr') ?>">
                <button type="submit" class="btn btn-primary btn-sm" <?= $all === [] ? 'disabled' : '' ?>>Cerrar mes y pasar <?= count($pending) ?> pendiente<?= count($pending) === 1 ? '' : 's' ?></button>
            </form>
        <?php endif ?>
    </div>
</div>

<?= view('panel/developments/_kpis', ['summary' => $summary, 'month' => $month, 'closed' => $closure !== null]) ?>

<section class="card table-card" style="margin-top:20px">
    <div class="card-head" style="padding:20px 20px 0">
        <div>
            <h2>Cumplimiento de la meta del mes</h2>
            <p class="muted">Solo desarrollos asignados con hitos en el mes o con requerimiento. La meta puede estar al 100 % aunque el proyecto no.</p>
        </div>
        <span class="muted small"><?= $lostDays ?> día<?= $lostDays === 1 ? '' : 's' ?> perdidos por <?= count($issues) ?> inconveniente<?= count($issues) === 1 ? '' : 's' ?></span>
    </div>
    <?php if ($counted === []): ?>
        <p class="empty" style="padding:0 20px 20px">Ningún desarrollo tiene hitos ni requerimiento con entrega en este mes.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Desarrollo</th><th>Programador</th><th>Meta del mes</th><th>Proyecto</th><th>Entrega del proyecto</th></tr></thead>
                <tbody>
                    <?php foreach ($counted as $d): ?>
                        <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>">
                            <td class="cell-main"><span class="mono muted small"><?= esc($d['Code']) ?> · <?= ticket_code($d['IdTicket']) ?></span><span class="cell-title"><?= esc($d['Subject']) ?></span></td>
                            <td><?= esc($d['ProgrammerName'] ?? 'Sin asignar') ?></td>
                            <td><?= dev_goal($d) ?></td>
                            <td><?= dev_progress($d) ?></td>
                            <td class="mono"><?= Board::formatDate($d['CommittedDate']) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<div class="dev-layout" style="margin-top:20px">
    <div>
        <section class="card table-card">
            <div class="card-head" style="padding:20px 20px 0"><div><h2>Completos</h2><p class="muted">Entregas aprobadas con evidencia.</p></div></div>
            <?php if ($done === []): ?>
                <p class="empty" style="padding:0 20px 20px">Ninguno todavía.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Desarrollo</th><th>Programador</th><th>Aprobado</th></tr></thead>
                        <tbody>
                            <?php foreach ($done as $d): ?>
                                <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>">
                                    <td class="cell-main"><span class="mono muted small"><?= esc($d['Code']) ?> · <?= ticket_code($d['IdTicket']) ?></span><span class="cell-title"><?= esc($d['Subject']) ?></span></td>
                                    <td><?= esc($d['ProgrammerName'] ?? 'Sin asignar') ?></td>
                                    <td class="mono"><?= format_date($d['ApprovedAt'], 'd/m/Y') ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>

        <section class="card table-card">
            <div class="card-head" style="padding:20px 20px 0">
                <div>
                    <h2>Compromisos para <?= esc(Board::monthName($next)) ?></h2>
                    <p class="muted"><?= $closure ? 'Pasaron al mes siguiente con su avance; los hitos vencidos sin terminar pasaron con él.' : 'Al cerrar el mes todos los abiertos pasan al mes siguiente con su avance; los hitos vencidos sin terminar pasan con ellos.' ?></p>
                </div>
            </div>
            <?php if ($pending === []): ?>
                <p class="empty" style="padding:0 20px 20px">Todo lo comprometido se entregó.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Desarrollo</th><th>Situación</th><th>Programador</th><th>Proyecto</th><th>Entrega</th><th>Motivo</th></tr></thead>
                        <tbody>
                            <?php foreach ($pending as $d):
                                $reason = null;
                                foreach ($issues as $i) {
                                    if ((int) $i['IdDevelopment'] === (int) $d['IdDevelopment'] && $i['ResolvedAt'] === null) { $reason = $i; break; }
                                }
                            ?>
                                <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>">
                                    <td class="cell-main"><span class="mono muted small"><?= esc($d['Code']) ?></span><span class="cell-title"><?= esc($d['Subject']) ?></span><?= ! empty($d['IsProject']) ? '<div><span class="chip chip-project">Proyecto</span></div>' : '' ?></td>
                                    <td><span class="chip"><?= esc(Board::BUCKETS[$d['Bucket']] ?? $d['Bucket']) ?></span></td>
                                    <td><?= esc($d['ProgrammerName'] ?? 'Sin asignar') ?></td>
                                    <td><?= dev_progress($d) ?></td>
                                    <td class="mono"><?= Board::formatDate($d['CommittedDate']) ?></td>
                                    <td class="small" style="min-width:200px"><?= $reason ? esc($reason['Description']) : '<span class="muted">Sin inconvenientes; faltan tareas</span>' ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>
    </div>

    <div>
        <section class="card">
            <h2>Entregas por programador</h2>
            <?php if ($people === []): ?>
                <p class="empty">Sin desarrollos en este mes.</p>
            <?php else: ?>
                <div class="dev-legend"><span><i style="background:var(--good)"></i>Completos</span><span><i style="background:var(--warning)"></i>Abiertos</span></div>
                <div class="dev-bars">
                    <?php foreach ($people as $name => $p): ?>
                        <div class="dev-bar-row">
                            <span><?= esc($name) ?></span>
                            <div class="dev-bar-track" style="width: <?= round($p['total'] / $maxTotal * 100) ?>%">
                                <span class="b-done" style="width: <?= round($p['done'] / $p['total'] * 100) ?>%"></span>
                                <span class="b-open" style="width: <?= round(($p['total'] - $p['done']) / $p['total'] * 100) ?>%"></span>
                            </div>
                            <span class="small" style="font-variant-numeric:tabular-nums" title="Completos de total · metas cumplidas de las que cuentan"><?= $p['done'] ?> de <?= $p['total'] ?><?= $p['eligible'] > 0 ? ' · meta ' . $p['met'] . '/' . $p['eligible'] : '' ?></span>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </section>

        <section class="card">
            <h2>Inconvenientes del mes</h2>
            <div class="dev-issues">
                <?php if ($issues === []): ?>
                    <p class="empty">Sin inconvenientes.</p>
                <?php endif ?>
                <?php foreach ($issues as $i): $open = $i['ResolvedAt'] === null; $d = $byDev[$i['IdDevelopment']] ?? null; ?>
                    <div class="dev-issue <?= $open ? 'is-' . esc($i['IssueType'], 'attr') : 'is-resolved' ?>">
                        <div class="dev-issue-head">
                            <span><?= esc($d['Code'] ?? '') ?> · <?= format_date($i['CreatedAt'], 'd/m/Y') ?></span>
                            <span><?= $open ? 'Abierto' : 'Resuelto' ?> · <?= (int) $i['ImpactDays'] ?> d</span>
                        </div>
                        <p class="small"><?= esc($i['Description']) ?></p>
                    </div>
                <?php endforeach ?>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
