<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Ficha de un desarrollo.
 *
 * @var array $dev
 * @var bool $canWork Puede actualizarlo (admin o programador asignado, sin aprobar)
 * @var bool $isAdmin
 * @var list<array> $tasks
 * @var list<array> $resources
 * @var list<array> $issues
 * @var list<array> $evidence
 * @var list<array> $agents
 */
use App\Libraries\DevelopmentBoard as Board;

$id        = (int) $dev['IdDevelopment'];
$base      = site_url('panel/desarrollos/' . $id);
$approved  = $dev['ApprovedAt'] !== null;
$hoursAll  = array_sum(array_map(static fn ($t) => (float) $t['Hours'], $tasks));
$hoursDone = array_sum(array_map(static fn ($t) => $t['DoneAt'] !== null ? (float) $t['Hours'] : 0, $tasks));
$hours     = static fn ($h) => rtrim(rtrim(number_format((float) $h, 1, ',', ''), '0'), ',');
$pendingRes = count(array_filter($resources, static fn ($r) => $r['ReadyAt'] === null));
$daysLeft  = $dev['EstimatedDate'] !== null && ! $approved ? max(0, Board::daysBetween(Board::today(), $dev['EstimatedDate'])) : null;
?>
<a class="back-link" href="<?= site_url('panel/desarrollos' . ($dev['PlanMonth'] ? '?mes=' . $dev['PlanMonth'] : '')) ?>"><?= icon('arrow-left') ?> Plan del mes</a>

<div class="page-head">
    <div>
        <div class="eyebrow mono"><?= esc($dev['Code']) ?> · nace del ticket <a href="<?= site_url('panel/tickets/' . $dev['IdTicket']) ?>"><?= ticket_code($dev['IdTicket']) ?></a> · <?= esc($dev['Companies'] ?? $dev['CodCompanies']) ?></div>
        <h1><?= esc($dev['Subject']) ?></h1>
        <div class="head-badges">
            <?= dev_state_badge($dev) ?>
            <span class="chip"><?= $dev['PlanMonth'] ? 'Plan de ' . esc(Board::monthName($dev['PlanMonth'])) : 'Sin mes' ?></span>
            <?php if ($dev['CarriedFromMonth']): ?><span class="chip chip-carry">Viene de <?= esc(Board::monthName($dev['CarriedFromMonth'])) ?></span><?php endif ?>
            <?php if ($dev['DelayDays'] > 0 && ! $approved): ?><span class="chip chip-late">Retraso de <?= $dev['DelayDays'] ?> días</span><?php endif ?>
        </div>
    </div>

    <?php if ($isAdmin && ! $approved && $dev['State'] === 'en_pruebas'): ?>
        <form method="post" action="<?= $base . '/aprobar' ?>" data-confirm="¿Aprobar la entrega? El desarrollo quedará en solo lectura y se cerrará el ticket <?= ticket_code($dev['IdTicket']) ?>.">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-accent" <?= (int) $dev['EvidenceCount'] === 0 ? 'disabled title="Falta subir evidencia"' : '' ?>><?= icon('check') ?> Aprobar entrega</button>
        </form>
    <?php endif ?>
</div>

<?php if ($approved): ?>
    <div class="dev-note" style="margin-bottom:16px">Entrega aprobada por <?= esc($dev['ApprovedByName']) ?> el <?= format_date($dev['ApprovedAt']) ?>. El desarrollo queda en solo lectura.</div>
<?php elseif (! $canWork): ?>
    <div class="dev-note" style="margin-bottom:16px">Solo el programador asignado o el administrador pueden actualizar este desarrollo.</div>
<?php elseif ($isAdmin && $dev['State'] === 'en_pruebas' && (int) $dev['EvidenceCount'] === 0): ?>
    <div class="dev-note" style="margin-bottom:16px">Todas las tareas están terminadas. Falta subir evidencia para poder aprobar la entrega.</div>
<?php endif ?>

<div class="dev-layout">
    <div>
        <section class="card" id="tareas">
            <div class="card-head">
                <div>
                    <h2>Qué se va a hacer</h2>
                    <p class="muted">El programador divide el desarrollo en tareas con horas estimadas.</p>
                </div>
                <span class="muted small"><?= $hours($hoursDone) ?> de <?= $hours($hoursAll) ?> h</span>
            </div>

            <?= dev_progress($dev) ?>

            <?php if ($tasks === []): ?>
                <p class="empty" style="margin-top:12px">Aún no hay tareas. El programador debe registrar el plan de trabajo.</p>
            <?php else: ?>
                <ul class="dev-tasks">
                    <?php foreach ($tasks as $t): $done = $t['DoneAt'] !== null; ?>
                        <li class="<?= $done ? 'is-done' : '' ?>">
                            <form method="post" action="<?= $base . '/tareas/' . $t['IdTask'] . '/marcar' ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="dev-check <?= $done ? 'is-on' : '' ?>" <?= $canWork ? '' : 'disabled' ?>
                                        aria-label="<?= $done ? 'Marcar como pendiente' : 'Marcar como terminada' ?>"><?= $done ? icon('check') : '' ?></button>
                            </form>
                            <span class="task-text"><?= esc($t['Description']) ?></span>
                            <span class="task-hours"><?= $hours($t['Hours']) ?> h</span>
                            <?php if ($canWork): ?>
                                <form method="post" action="<?= $base . '/tareas/' . $t['IdTask'] . '/eliminar' ?>" data-confirm="¿Quitar la tarea &quot;<?= esc($t['Description'], 'attr') ?>&quot;?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-btn small">Quitar</button>
                                </form>
                            <?php else: ?>
                                <span></span>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>

            <?php if ($canWork): ?>
                <form class="dev-inline" method="post" action="<?= $base . '/tareas' ?>">
                    <?= csrf_field() ?>
                    <input class="input" name="Description" id="task-text" maxlength="255" required placeholder="Nueva tarea (ej. Pruebas con el usuario)" aria-label="Tarea">
                    <input class="input" name="Hours" id="task-hours" type="number" min="0.5" max="999" step="0.5" required placeholder="Horas" aria-label="Horas">
                    <button type="submit" class="btn btn-outline">Agregar</button>
                </form>
            <?php endif ?>
        </section>

        <section class="card" id="inconvenientes">
            <h2>Inconvenientes</h2>
            <p class="dev-sub">Lo que frena el avance. Un inconveniente bloqueante abierto marca el desarrollo como bloqueado hasta que el administrador lo resuelve.</p>

            <div class="dev-issues">
                <?php if ($issues === []): ?>
                    <p class="empty">Sin inconvenientes reportados.</p>
                <?php endif ?>
                <?php foreach ($issues as $i): $open = $i['ResolvedAt'] === null; ?>
                    <div class="dev-issue <?= $open ? 'is-' . esc($i['IssueType'], 'attr') : 'is-resolved' ?>">
                        <div class="dev-issue-head">
                            <span><?= format_date($i['CreatedAt'], 'd/m/Y') ?> · <?= $i['IssueType'] === 'bloqueante' ? 'Bloqueante' : 'Riesgo' ?> · impacto <?= (int) $i['ImpactDays'] ?> día<?= (int) $i['ImpactDays'] === 1 ? '' : 's' ?> · <?= esc($i['ReportedByName']) ?></span>
                            <?php if (! $open): ?>
                                <span>Resuelto por <?= esc($i['ResolvedByName']) ?></span>
                            <?php elseif ($isAdmin && ! $approved): ?>
                                <form method="post" action="<?= $base . '/inconvenientes/' . $i['IdIssue'] . '/resolver' ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-btn small">Marcar resuelto</button>
                                </form>
                            <?php else: ?>
                                <strong>Abierto</strong>
                            <?php endif ?>
                        </div>
                        <p><?= esc($i['Description']) ?></p>
                    </div>
                <?php endforeach ?>
            </div>

            <?php if ($canWork): ?>
                <form class="dev-issue-form" method="post" action="<?= $base . '/inconvenientes' ?>">
                    <?= csrf_field() ?>
                    <textarea class="input" name="Description" id="issue-text" rows="2" maxlength="2000" required placeholder="Qué pasa y qué necesitas para seguir"></textarea>
                    <div class="row">
                        <select class="select" name="IssueType" id="issue-type" style="width:auto" aria-label="Tipo">
                            <option value="riesgo">Riesgo</option>
                            <option value="bloqueante">Bloqueante</option>
                        </select>
                        <input class="input" type="number" name="ImpactDays" id="issue-days" min="0" max="365" value="1" style="width:100px" aria-label="Días de impacto">
                        <span class="muted small">días de impacto</span>
                        <button type="submit" class="btn btn-outline" style="margin-left:auto">Reportar</button>
                    </div>
                </form>
            <?php endif ?>
        </section>

        <section class="card" id="evidencia">
            <h2>Evidencia de cierre</h2>
            <p class="dev-sub">Capturas del desarrollo terminado. Se exige al menos una para aprobar la entrega.</p>

            <?php if ($evidence === []): ?>
                <p class="empty">Aún no hay imágenes.</p>
            <?php else: ?>
                <div class="dev-gallery">
                    <?php foreach ($evidence as $e): $url = $base . '/evidencia/' . $e['IdEvidence']; ?>
                        <a href="<?= $url ?>" target="_blank" rel="noopener">
                            <img src="<?= $url ?>" alt="<?= esc($e['FileName'], 'attr') ?>" loading="lazy">
                            <span title="<?= esc($e['FileName'], 'attr') ?>"><?= esc($e['FileName']) ?></span>
                        </a>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <?php if ($canWork): ?>
                <form class="dev-upload" method="post" enctype="multipart/form-data" action="<?= $base . '/evidencia' ?>">
                    <?= csrf_field() ?>
                    <input type="file" name="images[]" id="evidence-files" accept="image/png,image/jpeg,image/gif,image/webp" multiple required aria-label="Imágenes">
                    <button type="submit" class="btn btn-outline btn-sm"><?= icon('paperclip') ?> Subir</button>
                    <span class="muted small" style="flex-basis:100%">PNG, JPG, GIF o WEBP · máximo 10 MB por imagen.</span>
                </form>
            <?php endif ?>
        </section>
    </div>

    <div>
        <section class="card">
            <h2>Responsable y entrega</h2>

            <div class="dev-dates">
                <div><span class="k">Comprometida</span><span class="v"><?= Board::formatDate($dev['CommittedDate']) ?></span></div>
                <div><span class="k">Estimada</span><span class="v <?= $dev['DelayDays'] > 0 ? 'late' : '' ?>"><?= Board::formatDate($dev['EstimatedDate']) ?></span></div>
                <div><span class="k">Faltan</span><span class="v"><?= $daysLeft === null ? '—' : $daysLeft . ' días' ?></span></div>
            </div>

            <?php if ($canWork && $dev['PlanMonth'] !== null): ?>
                <form method="post" action="<?= $base ?>">
                    <?= csrf_field() ?>
                    <?php if ($isAdmin): ?>
                        <label class="field">
                            <span class="field-label">Programador</span>
                            <select name="AssignedUserId" class="select" id="dev-programmer">
                                <option value="">Sin asignar</option>
                                <?php foreach ($agents as $a): ?>
                                    <option value="<?= esc($a['IdUser'], 'attr') ?>" <?= (string) $dev['AssignedUserId'] === (string) $a['IdUser'] ? 'selected' : '' ?>><?= esc($a['FullName']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </label>
                        <label class="field">
                            <span class="field-label">Fecha comprometida</span>
                            <input type="date" name="CommittedDate" id="dev-committed" class="input" value="<?= esc($dev['CommittedDate'] ?? '', 'attr') ?>">
                        </label>
                    <?php else: ?>
                        <p class="small" style="margin:0 0 12px">Programador: <strong><?= esc($dev['ProgrammerName'] ?? 'Sin asignar') ?></strong></p>
                    <?php endif ?>
                    <label class="field">
                        <span class="field-label">Fecha estimada de entrega</span>
                        <input type="date" name="EstimatedDate" id="dev-estimated" class="input" value="<?= esc($dev['EstimatedDate'] ?? '', 'attr') ?>">
                        <span class="muted small">El programador la actualiza; si pasa de la comprometida, se cuenta como retraso.</span>
                    </label>
                    <button type="submit" class="btn btn-primary btn-block">Guardar</button>
                </form>
            <?php else: ?>
                <p class="small" style="margin:0">Programador: <strong><?= esc($dev['ProgrammerName'] ?? 'Sin asignar') ?></strong></p>
            <?php endif ?>
        </section>

        <section class="card" id="recursos">
            <h2>Recursos que necesita</h2>
            <p class="dev-sub"><?= $resources === [] ? 'No se han pedido recursos.' : ($pendingRes > 0 ? $pendingRes . ' pendiente' . ($pendingRes === 1 ? '' : 's') . ' por conseguir.' : 'Todos disponibles.') ?></p>

            <?php if ($resources !== []): ?>
                <ul class="dev-resources">
                    <?php foreach ($resources as $r): ?>
                        <li>
                            <span><?= esc($r['Description']) ?></span>
                            <?php if ($r['ReadyAt'] !== null): ?>
                                <span class="ok">Disponible</span>
                            <?php elseif ($isAdmin && ! $approved): ?>
                                <form method="post" action="<?= $base . '/recursos/' . $r['IdResource'] . '/disponible' ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline btn-sm">Marcar disponible</button>
                                </form>
                            <?php else: ?>
                                <span class="wait">Solicitado</span>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>

            <?php if ($canWork): ?>
                <form class="dev-inline" style="grid-template-columns:minmax(0,1fr) auto" method="post" action="<?= $base . '/recursos' ?>">
                    <?= csrf_field() ?>
                    <input class="input" name="Description" id="resource-text" maxlength="255" required placeholder="Ej. Acceso a QAS, licencia, apoyo del usuario" aria-label="Recurso">
                    <button type="submit" class="btn btn-outline">Pedir</button>
                </form>
            <?php endif ?>
        </section>

        <section class="card">
            <h2>Del ticket original</h2>
            <dl class="details">
                <div><dt><?= icon('mail') ?> Solicitante</dt><dd><?= esc($dev['RequesterEmail']) ?></dd></div>
                <div><dt><?= icon('tag') ?> Ticket</dt><dd><a href="<?= site_url('panel/tickets/' . $dev['IdTicket']) ?>"><?= ticket_code($dev['IdTicket']) ?></a> · <?= esc(status_label($dev['TicketStatus'])) ?><span class="muted small"><?= esc($dev['Category'] ?? '') ?></span></dd></div>
            </dl>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
