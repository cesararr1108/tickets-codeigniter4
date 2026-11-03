<?php
/**
 * Estadística de un mes (DevelopmentBoard::summary() / closureSummary()).
 *
 * @var array  $summary
 * @var string $month
 * @var bool   $closed
 */
use App\Libraries\DevelopmentBoard as Board;

$s = $summary;
?>
<section class="dev-kpis">
    <div class="card kpi">
        <div class="kpi-head"><span>Desarrollos del mes</span></div>
        <div class="kpi-value"><?= $s['total'] ?></div>
        <div class="kpi-foot"><?= $s['open'] ?> <?= $closed ? 'pasaron' : 'pasan' ?> a <?= esc(Board::monthName(Board::shiftMonth($month, 1))) ?> si no se completan</div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>Completos</span></div>
        <div class="kpi-value dev-good"><?= $s['done'] ?></div>
        <div class="kpi-foot">entregas aprobadas</div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>Sin asignar</span></div>
        <div class="kpi-value <?= $s['unassigned'] > 0 ? 'dev-warn' : '' ?>"><?= $s['unassigned'] ?></div>
        <div class="kpi-foot">sin programador</div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>Sin empezar</span></div>
        <div class="kpi-value <?= $s['notStarted'] > 0 ? 'dev-warn' : '' ?>"><?= $s['notStarted'] ?></div>
        <div class="kpi-foot">ninguna tarea terminada</div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>En curso</span></div>
        <div class="kpi-value"><?= $s['inProgress'] ?></div>
        <div class="kpi-foot"><?= $closed ? 'al cierre' : $s['blocked'] . ' bloqueado' . ($s['blocked'] === 1 ? '' : 's') ?></div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>Cumplimiento</span></div>
        <div class="kpi-value"><?= $s['pct'] ?>%</div>
        <div class="dev-progress <?= $s['pct'] === 100 ? 'is-done' : '' ?>"><span style="width: <?= $s['pct'] ?>%"></span></div>
        <div class="kpi-foot">
            <?= $s['met'] ?> cumplido<?= $s['met'] === 1 ? '' : 's' ?> · <?= $s['notMet'] ?> no ·
            hitos <?= $s['milestonesDone'] ?>/<?= $s['milestonesDue'] ?>
        </div>
    </div>
</section>
<p class="muted small" style="margin:8px 0 0">
    El cumplimiento solo cuenta los desarrollos asignados con hitos (tareas que vencen en el mes) o con requerimiento cuya entrega vence en el mes
    (<?= $s['eligible'] ?> de <?= $s['total'] ?>).
</p>
