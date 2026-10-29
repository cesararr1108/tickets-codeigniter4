<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Plan de desarrollos de un mes.
 *
 * @var string $month AAAA-MM
 * @var array $summary
 * @var list<array> $rows
 * @var list<array> $backlog
 * @var array|null $closure Cierre de este mes o del anterior
 * @var bool $closedMonth
 * @var list<array> $agents
 */
use App\Libraries\DevelopmentBoard as Board;

$isAdmin = panel_is_admin();
$prev    = Board::shiftMonth($month, -1);
$next    = Board::shiftMonth($month, 1);
?>
<div class="page-head">
    <div>
        <h1>Plan de desarrollos · <?= esc(Board::monthName($month)) ?></h1>
        <p class="muted"><?= $isAdmin ? 'Elige qué desarrollos se comprometen este mes y sigue su avance.' : 'Tus desarrollos comprometidos este mes.' ?></p>
    </div>
    <div class="month-nav">
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos?mes=' . $prev) ?>" aria-label="Mes anterior">‹</a>
        <strong><?= esc(Board::monthName($month)) ?></strong>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos?mes=' . $next) ?>" aria-label="Mes siguiente">›</a>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/todos') ?>">Todos</a>
        <a class="btn btn-primary btn-sm" href="<?= site_url('panel/desarrollos/cierre?mes=' . $month) ?>">Cierre de mes</a>
    </div>
</div>

<section class="dev-kpis">
    <div class="card kpi">
        <div class="kpi-head"><span>Comprometidos</span></div>
        <div class="kpi-value"><?= $summary['total'] ?></div>
        <div class="kpi-foot">en el plan del mes</div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>Terminados</span></div>
        <div class="kpi-value dev-good"><?= $summary['done'] ?></div>
        <div class="kpi-foot">aprobados por el administrador</div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>En curso</span></div>
        <div class="kpi-value"><?= $summary['open'] ?></div>
        <div class="kpi-foot"><?= $summary['blocked'] ?> bloqueado<?= $summary['blocked'] === 1 ? '' : 's' ?></div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>Pasarían al próximo mes</span></div>
        <div class="kpi-value dev-warn"><?= $summary['late'] ?></div>
        <div class="kpi-foot">entrega después del <?= Board::formatDate(Board::lastDay($month)) ?></div>
    </div>
    <div class="card kpi">
        <div class="kpi-head"><span>Cumplimiento</span></div>
        <div class="kpi-value"><?= $summary['pct'] ?>%</div>
        <div class="dev-progress <?= $summary['pct'] === 100 ? 'is-done' : '' ?>"><span style="width: <?= $summary['pct'] ?>%"></span></div>
    </div>
</section>

<?php if ($closure !== null): ?>
    <div class="dev-note" style="margin-top:16px">
        <?= esc(Board::monthName($closure['PlanMonth'])) ?> se cerró el <?= format_date($closure['ClosedAt'], 'd/m/Y') ?>:
        <?= (int) $closure['Delivered'] ?> de <?= (int) $closure['Committed'] ?> realizados y
        <?= (int) $closure['CarriedOver'] ?> pendiente<?= (int) $closure['CarriedOver'] === 1 ? '' : 's' ?> pasaron a <?= esc(Board::monthName(Board::shiftMonth($closure['PlanMonth'], 1))) ?>.
    </div>
<?php endif ?>

<div class="<?= $isAdmin ? 'dev-layout' : '' ?>" style="margin-top:20px">
    <div>
        <section class="card table-card">
            <div class="card-head" style="padding:20px 20px 0">
                <div>
                    <h2>Desarrollos del mes</h2>
                    <p class="muted">Avance calculado con las horas de las tareas terminadas.</p>
                </div>
            </div>

            <?php if ($rows === []): ?>
                <div class="empty-state">
                    <?= icon('code', 'icon icon-xl') ?>
                    <strong>No hay desarrollos en este mes</strong>
                    <span class="muted"><?= $isAdmin ? 'Agrégalos desde "Por planear".' : 'El administrador aún no te asigna desarrollos.' ?></span>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Desarrollo</th>
                                <th>Programador</th>
                                <th>Estado</th>
                                <th>Avance</th>
                                <th>Entrega</th>
                                <?php if ($isAdmin): ?><th></th><?php endif ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $d): ?>
                                <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>">
                                    <td class="cell-main">
                                        <span class="mono muted small"><?= esc($d['Code']) ?> · <?= ticket_code($d['IdTicket']) ?> · Cía <?= esc($d['CodCompanies']) ?></span>
                                        <span class="cell-title"><?= esc($d['Subject']) ?></span>
                                        <?php if ($d['CarriedFromMonth']): ?><div><span class="chip chip-carry">Viene de <?= esc(Board::monthName($d['CarriedFromMonth'])) ?></span></div><?php endif ?>
                                    </td>
                                    <td><?= esc($d['ProgrammerName'] ?? 'Sin asignar') ?></td>
                                    <td><div><?= dev_state_badge($d) ?></div></td>
                                    <td><?= dev_progress($d) ?></td>
                                    <td class="mono">
                                        <?= Board::formatDate($d['CommittedDate']) ?>
                                        <?php if ($d['DelayDays'] > 0 && $d['State'] !== 'terminado'): ?><div><span class="chip chip-late">+<?= $d['DelayDays'] ?> días</span></div><?php endif ?>
                                    </td>
                                    <?php if ($isAdmin): ?>
                                        <td>
                                            <?php if ($d['State'] !== 'terminado' && ! $closedMonth): ?>
                                                <form method="post" action="<?= site_url('panel/desarrollos/' . $d['IdDevelopment'] . '/quitar-plan') ?>"
                                                      data-confirm="¿Quitar <?= esc($d['Code'], 'attr') ?> del plan? Volverá a &quot;Por planear&quot;.">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-outline btn-sm">Quitar</button>
                                                </form>
                                            <?php endif ?>
                                        </td>
                                    <?php endif ?>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>
    </div>

    <?php if ($isAdmin): ?>
        <aside class="card">
            <h2>Por planear</h2>
            <p class="dev-sub">Tickets de desarrollo sin mes. Al agregarlo, el programador queda como responsable del ticket.</p>

            <?php if ($closedMonth): ?>
                <p class="muted small">Este mes ya está cerrado. Planea en <a href="<?= site_url('panel/desarrollos?mes=' . $next) ?>"><?= esc(Board::monthName($next)) ?></a>.</p>
            <?php elseif ($backlog === []): ?>
                <p class="muted small">No hay desarrollos pendientes de planear.</p>
            <?php else: ?>
                <ul class="dev-backlog">
                    <?php foreach ($backlog as $b): $key = $b['IdDevelopment'] ? 'd' . $b['IdDevelopment'] : 't' . $b['IdTicket']; ?>
                        <li>
                            <span class="mono muted small"><?= $b['IdDevelopment'] ? esc($b['Code']) . ' · ' : '' ?><?= ticket_code($b['IdTicket']) ?> · Cía <?= esc($b['CodCompanies']) ?></span>
                            <div><a href="<?= site_url('panel/tickets/' . $b['IdTicket']) ?>"><strong><?= esc($b['Subject']) ?></strong></a></div>
                            <form method="post" action="<?= site_url('panel/desarrollos/planear') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="PlanMonth" value="<?= esc($month, 'attr') ?>">
                                <?php if ($b['IdDevelopment']): ?>
                                    <input type="hidden" name="IdDevelopment" value="<?= (int) $b['IdDevelopment'] ?>">
                                <?php else: ?>
                                    <input type="hidden" name="IdTicket" value="<?= (int) $b['IdTicket'] ?>">
                                <?php endif ?>
                                <div class="row2">
                                    <select name="AssignedUserId" class="select" id="prog-<?= $key ?>" required aria-label="Programador">
                                        <option value="">Programador…</option>
                                        <?php foreach ($agents as $a): ?>
                                            <option value="<?= esc($a['IdUser'], 'attr') ?>" <?= (string) ($b['AssignedUserId'] ?? '') === (string) $a['IdUser'] ? 'selected' : '' ?>><?= esc($a['FullName']) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    <input type="date" name="CommittedDate" class="input" id="due-<?= $key ?>" required aria-label="Fecha comprometida"
                                           value="<?= Board::lastDay($month) ?>">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Agregar a <?= esc(Board::monthName($month)) ?></button>
                            </form>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </aside>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
