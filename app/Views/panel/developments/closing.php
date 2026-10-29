<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Cierre de mes: comprometidos vs. realizados y compromisos que pasan al
 * mes siguiente.
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
    $people[$name] ??= ['done' => 0, 'total' => 0];
    $people[$name]['total']++;
    if ($d['State'] === 'terminado' && $d['PlanMonth'] === $month) {
        $people[$name]['done']++;
    }
}
$maxTotal = max([1, ...array_column($people, 'total')]);

$byDev     = array_column($all, null, 'IdDevelopment');
$lostDays  = array_sum(array_map(static fn ($i) => (int) $i['ImpactDays'], $issues));
?>
<a class="back-link" href="<?= site_url('panel/desarrollos?mes=' . $month) ?>"><?= icon('arrow-left') ?> Plan de <?= esc(Board::monthName($month)) ?></a>

<div class="page-head">
    <div>
        <h1>Cierre de <?= esc(Board::monthName($month)) ?></h1>
        <p class="muted">Lo que se comprometió, lo que se entregó y lo que pasa a <?= esc(Board::monthName($next)) ?>.</p>
    </div>
    <div class="month-nav">
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/cierre?mes=' . Board::shiftMonth($month, -1)) ?>" aria-label="Mes anterior">‹</a>
        <strong><?= esc(Board::monthName($month)) ?></strong>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/cierre?mes=' . $next) ?>" aria-label="Mes siguiente">›</a>
        <?php if ($closure !== null): ?>
            <span class="chip">Cerrado el <?= format_date($closure['ClosedAt'], 'd/m/Y') ?> por <?= esc($closure['ClosedByName']) ?></span>
        <?php elseif (panel_is_admin()): ?>
            <form method="post" action="<?= site_url('panel/desarrollos/cierre') ?>"
                  data-confirm="¿Cerrar <?= esc(Board::monthName($month), 'attr') ?>? Los <?= count($pending) ?> pendientes pasarán a <?= esc(Board::monthName($next), 'attr') ?>.">
                <?= csrf_field() ?>
                <input type="hidden" name="PlanMonth" value="<?= esc($month, 'attr') ?>">
                <button type="submit" class="btn btn-primary btn-sm" <?= $all === [] ? 'disabled' : '' ?>>Cerrar mes y pasar <?= count($pending) ?> pendiente<?= count($pending) === 1 ? '' : 's' ?></button>
            </form>
        <?php endif ?>
    </div>
</div>

<section class="dev-kpis">
    <div class="card kpi"><div class="kpi-head"><span>Comprometidos</span></div><div class="kpi-value"><?= $summary['total'] ?></div></div>
    <div class="card kpi"><div class="kpi-head"><span>Realizados</span></div><div class="kpi-value dev-good"><?= $summary['done'] ?></div></div>
    <div class="card kpi"><div class="kpi-head"><span><?= $closure ? 'Pasaron' : 'Pendientes' ?> a <?= esc(Board::monthName($next)) ?></span></div><div class="kpi-value dev-warn"><?= $summary['open'] ?></div></div>
    <div class="card kpi">
        <div class="kpi-head"><span>Cumplimiento</span></div>
        <div class="kpi-value"><?= $summary['pct'] ?>%</div>
        <div class="dev-progress <?= $summary['pct'] === 100 ? 'is-done' : '' ?>"><span style="width: <?= $summary['pct'] ?>%"></span></div>
    </div>
    <div class="card kpi"><div class="kpi-head"><span>Días perdidos por inconvenientes</span></div><div class="kpi-value"><?= $lostDays ?></div><div class="kpi-foot"><?= count($issues) ?> reportados</div></div>
</section>

<div class="dev-layout" style="margin-top:20px">
    <div>
        <section class="card table-card">
            <div class="card-head" style="padding:20px 20px 0"><div><h2>Realizados</h2><p class="muted">Entregas aprobadas con evidencia.</p></div></div>
            <?php if ($done === []): ?>
                <p class="empty" style="padding:0 20px 20px">Ninguno todavía.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Desarrollo</th><th>Programador</th><th>Aprobado</th><th>Evidencia</th></tr></thead>
                        <tbody>
                            <?php foreach ($done as $d): ?>
                                <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>">
                                    <td class="cell-main"><span class="mono muted small"><?= esc($d['Code']) ?> · <?= ticket_code($d['IdTicket']) ?></span><span class="cell-title"><?= esc($d['Subject']) ?></span></td>
                                    <td><?= esc($d['ProgrammerName'] ?? 'Sin asignar') ?></td>
                                    <td class="mono"><?= format_date($d['ApprovedAt'], 'd/m/Y') ?></td>
                                    <td><?= (int) $d['EvidenceCount'] ?> imagen<?= (int) $d['EvidenceCount'] === 1 ? '' : 'es' ?></td>
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
                    <p class="muted"><?= $closure ? 'Pasaron al plan del mes siguiente con su avance.' : 'Al cerrar el mes pasan al plan del mes siguiente con su avance.' ?></p>
                </div>
            </div>
            <?php if ($pending === []): ?>
                <p class="empty" style="padding:0 20px 20px">Todo lo comprometido se entregó.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Desarrollo</th><th>Programador</th><th>Avance</th><th>Nueva fecha</th><th>Motivo</th></tr></thead>
                        <tbody>
                            <?php foreach ($pending as $d):
                                $reason = null;
                                foreach ($issues as $i) {
                                    if ((int) $i['IdDevelopment'] === (int) $d['IdDevelopment'] && $i['ResolvedAt'] === null) { $reason = $i; break; }
                                }
                            ?>
                                <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>">
                                    <td class="cell-main"><span class="mono muted small"><?= esc($d['Code']) ?></span><span class="cell-title"><?= esc($d['Subject']) ?></span></td>
                                    <td><?= esc($d['ProgrammerName'] ?? 'Sin asignar') ?></td>
                                    <td><?= dev_progress($d) ?></td>
                                    <td class="mono"><?= Board::formatDate($d['EstimatedDate'] ?? $d['CommittedDate']) ?></td>
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
                <div class="dev-legend"><span><i style="background:var(--good)"></i>Realizados</span><span><i style="background:var(--warning)"></i>Pendientes</span></div>
                <div class="dev-bars">
                    <?php foreach ($people as $name => $p): ?>
                        <div class="dev-bar-row">
                            <span><?= esc($name) ?></span>
                            <div class="dev-bar-track" style="width: <?= round($p['total'] / $maxTotal * 100) ?>%">
                                <span class="b-done" style="width: <?= round($p['done'] / $p['total'] * 100) ?>%"></span>
                                <span class="b-open" style="width: <?= round(($p['total'] - $p['done']) / $p['total'] * 100) ?>%"></span>
                            </div>
                            <span class="small" style="font-variant-numeric:tabular-nums"><?= $p['done'] ?> de <?= $p['total'] ?></span>
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
