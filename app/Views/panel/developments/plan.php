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
 * @var string $responsable Filtro por programador ('' | 'none' | IdUser)
 * @var bool $canReorder
 */
use App\Libraries\DevelopmentBoard as Board;

$isAdmin = panel_is_admin();
$prev    = Board::shiftMonth($month, -1);
$next    = Board::shiftMonth($month, 1);
$keep    = $responsable !== '' ? '&responsable=' . rawurlencode($responsable) : '';
$canPrio = static fn (array $d) => $d['TicketStatus'] !== 'cerrado' && ($isAdmin || (string) ($d['AssignedUserId'] ?? '') === panel_user_id());
?>
<div class="page-head">
    <div>
        <h1>Plan de desarrollos · <?= esc(Board::monthName($month)) ?></h1>
        <p class="muted"><?= $isAdmin ? 'Elige qué desarrollos se comprometen este mes y sigue su avance.' : 'Tus desarrollos comprometidos este mes.' ?></p>
    </div>
    <div class="month-nav">
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos?mes=' . $prev . $keep) ?>" aria-label="Mes anterior">‹</a>
        <strong><?= esc(Board::monthName($month)) ?></strong>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos?mes=' . $next . $keep) ?>" aria-label="Mes siguiente">›</a>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/todos') ?>">Todos</a>
        <?php if (Board::projectsAvailable()): ?>
            <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/proyectos') ?>">Proyectos</a>
        <?php endif ?>
        <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/cierre/informe?mes=' . $month) ?>">Informe del mes</a>
        <a class="btn btn-primary btn-sm" href="<?= site_url('panel/desarrollos/cierre?mes=' . $month) ?>">Cierre de mes</a>
    </div>
</div>

<?= view('panel/developments/_kpis', ['summary' => $summary, 'month' => $month, 'closed' => false]) ?>

<?php if ($closure !== null): ?>
    <div class="dev-note" style="margin-top:16px">
        <?= esc(Board::monthName($closure['PlanMonth'])) ?> se cerró el <?= format_date($closure['ClosedAt'], 'd/m/Y') ?>:
        <?= (int) $closure['Delivered'] ?> de <?= (int) $closure['Committed'] ?> completos,
        cumplimiento <?= Board::closureSummary($closure)['pct'] ?>% y
        <?= (int) $closure['CarriedOver'] ?> pendiente<?= (int) $closure['CarriedOver'] === 1 ? '' : 's' ?> pasaron a <?= esc(Board::monthName(Board::shiftMonth($closure['PlanMonth'], 1))) ?>.
    </div>
<?php endif ?>

<div class="<?= $isAdmin ? 'dev-layout' : '' ?>" style="margin-top:20px">
    <div>
        <section class="card table-card">
            <div class="card-head" style="padding:20px 20px 0">
                <div>
                    <h2>Desarrollos del mes</h2>
                    <p class="muted">Meta del mes: tareas con fecha de cumplimiento en el mes. Proyecto: horas de todas las tareas terminadas.<?= $canReorder ? ' Arrastra ⠿ (o usa las flechas) para ordenarlos.' : '' ?></p>
                </div>
                <?php if ($isAdmin): ?>
                    <form method="get" class="period" data-autosubmit>
                        <input type="hidden" name="mes" value="<?= esc($month, 'attr') ?>">
                        <select name="responsable" class="select" aria-label="Filtrar por responsable">
                            <option value="">Todos los responsables</option>
                            <option value="none" <?= $responsable === 'none' ? 'selected' : '' ?>>Sin asignar</option>
                            <?php foreach ($agents as $a): ?>
                                <option value="<?= esc($a['IdUser'], 'attr') ?>" <?= $responsable === (string) $a['IdUser'] ? 'selected' : '' ?>><?= esc($a['FullName']) ?></option>
                            <?php endforeach ?>
                        </select>
                        <noscript><button class="btn btn-outline btn-sm">Filtrar</button></noscript>
                    </form>
                <?php endif ?>
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
                                <?php if ($canReorder): ?><th aria-label="Orden"></th><?php endif ?>
                                <th>Desarrollo</th>
                                <th>Prioridad</th>
                                <th>Programador</th>
                                <th>Estado</th>
                                <th>Meta del mes</th>
                                <th>Proyecto</th>
                                <th>Entrega</th>
                                <?php if ($isAdmin): ?><th></th><?php endif ?>
                            </tr>
                        </thead>
                        <tbody<?= $canReorder ? ' data-reorder data-url="' . site_url('panel/desarrollos/orden') . '" data-month="' . esc($month, 'attr') . '"' : '' ?>>
                            <?php foreach ($rows as $i => $d): ?>
                                <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>" data-id="<?= (int) $d['IdDevelopment'] ?>">
                                    <?php if ($canReorder): ?>
                                        <td class="order-cell">
                                            <span class="drag-handle" data-drag-handle title="Arrastra para reordenar" aria-hidden="true">⠿</span>
                                            <form method="post" action="<?= site_url('panel/desarrollos/' . $d['IdDevelopment'] . '/orden') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="mes" value="<?= esc($month, 'attr') ?>">
                                                <input type="hidden" name="responsable" value="<?= esc($responsable, 'attr') ?>">
                                                <button type="submit" name="dir" value="up" class="order-btn" title="Subir" aria-label="Subir" <?= $i === 0 ? 'disabled' : '' ?>>▲</button>
                                                <button type="submit" name="dir" value="down" class="order-btn" title="Bajar" aria-label="Bajar" <?= $i === count($rows) - 1 ? 'disabled' : '' ?>>▼</button>
                                            </form>
                                        </td>
                                    <?php endif ?>
                                    <td class="cell-main">
                                        <span class="mono muted small"><?= esc($d['Code']) ?> · <?= ticket_code($d['IdTicket']) ?> · Cía <?= esc($d['CodCompanies']) ?></span>
                                        <span class="cell-title"><?= esc($d['Subject']) ?></span>
                                        <?php if ($d['IsProject'] || $d['CarriedFromMonth']): ?>
                                            <div>
                                                <?= dev_kind_chip($d) ?>
                                                <?php if ($d['CarriedFromMonth']): ?><span class="chip chip-carry">Viene de <?= esc(Board::monthName($d['CarriedFromMonth'])) ?></span><?php endif ?>
                                            </div>
                                        <?php endif ?>
                                    </td>
                                    <td>
                                        <?php if ($canPrio($d)): ?>
                                            <button type="button" class="prio-btn" data-prio-open data-action="<?= site_url('panel/desarrollos/' . $d['IdDevelopment'] . '/prioridad') ?>"
                                                    data-title="<?= esc($d['Code'] . ' · ' . $d['Subject'], 'attr') ?>" title="Cambiar prioridad (impacto × urgencia)"><?= priority_badge($d['Priority']) ?></button>
                                        <?php else: ?>
                                            <?= priority_badge($d['Priority']) ?>
                                        <?php endif ?>
                                    </td>
                                    <td><?= esc($d['ProgrammerName'] ?? 'Sin asignar') ?></td>
                                    <td><div><?= dev_state_badge($d) ?></div></td>
                                    <td><?= dev_goal($d) ?></td>
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
            <p class="dev-sub">Tickets de desarrollo y de proyecto sin mes. Al agregarlo, el programador queda como responsable del ticket. La entrega puede quedar en este mes o en uno posterior: el desarrollo aparece en cada mes hasta su entrega.</p>

            <?php if ($closedMonth): ?>
                <p class="muted small">Este mes ya está cerrado. Planea en <a href="<?= site_url('panel/desarrollos?mes=' . $next) ?>"><?= esc(Board::monthName($next)) ?></a>.</p>
            <?php elseif ($backlog === []): ?>
                <p class="muted small">No hay desarrollos pendientes de planear.</p>
            <?php else: ?>
                <ul class="dev-backlog">
                    <?php foreach ($backlog as $b): $key = $b['IdDevelopment'] ? 'd' . $b['IdDevelopment'] : 't' . $b['IdTicket']; ?>
                        <li>
                            <span class="mono muted small"><?= $b['IdDevelopment'] ? esc($b['Code']) . ' · ' : '' ?><?= ticket_code($b['IdTicket']) ?> · Cía <?= esc($b['CodCompanies']) ?></span>
                            <div><a href="<?= site_url('panel/tickets/' . $b['IdTicket']) ?>"><strong><?= esc($b['Subject']) ?></strong></a> <?= dev_kind_chip($b) ?></div>
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
                                    <input type="date" name="CommittedDate" class="input" id="due-<?= $key ?>" required aria-label="Entrega del proyecto"
                                           min="<?= $month ?>-01" value="<?= Board::lastDay($month) ?>" title="Entrega del proyecto: en este mes o en uno posterior">
                                </div>
                                <?php if (Board::projectsAvailable()): ?>
                                    <select name="Kind" class="select" aria-label="Tipo">
                                        <?php foreach (Board::KINDS as $k => $label): ?>
                                            <option value="<?= $k ?>" <?= ($b['Kind'] ?? 'desarrollo') === $k ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach ?>
                                    </select>
                                <?php endif ?>
                                <button type="submit" class="btn btn-primary btn-sm">Agregar a <?= esc(Board::monthName($month)) ?></button>
                            </form>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </aside>
    <?php endif ?>
</div>
<?= view('panel/partials/priority_matrix') ?>
<?= $this->endSection() ?>
