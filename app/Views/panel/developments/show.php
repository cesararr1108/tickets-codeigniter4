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
 * @var array $goal Desarrollo con la meta del mes del plan (withMonthGoal)
 * @var bool $projects ¿Existe Proyectos.sql? (tipo, presupuesto, responsables, reprogramaciones)
 * @var list<array> $budget Rubros del presupuesto
 * @var list<array> $reschedules Cambios de la entrega comprometida
 * @var list<array> $answers Formulario del ticket (lo que pidió el solicitante)
 */
use App\Libraries\DevelopmentBoard as Board;

$id        = (int) $dev['IdDevelopment'];
$base      = site_url('panel/desarrollos/' . $id);
$approved  = $dev['ApprovedAt'] !== null;
$hoursAll  = array_sum(array_map(static fn ($t) => (float) $t['Hours'], $tasks));
$hoursDone = array_sum(array_map(static fn ($t) => $t['DoneAt'] !== null ? (float) $t['Hours'] : 0, $tasks));
$hours     = static fn ($h) => rtrim(rtrim(number_format((float) $h, 1, ',', ''), '0'), ',');
$pendingRes = count(array_filter($resources, static fn ($r) => $r['ReadyAt'] === null));
$today     = Board::today();
$minDue    = $dev['PlanMonth'] !== null ? $dev['PlanMonth'] . '-01' : '';
$maxDue    = $dev['CommittedDate'] ?? '';
$daysLeft  = $dev['EstimatedDate'] !== null && ! $approved ? max(0, Board::daysBetween(Board::today(), $dev['EstimatedDate'])) : null;
$isProject = $projects && $dev['IsProject'];
$word      = $isProject ? 'proyecto' : 'desarrollo';
$usedIssues = array_flip(array_filter(array_map(static fn ($r) => (int) $r['IdIssue'], $reschedules)));
$budgetPlanned = array_sum(array_map(static fn ($b) => (float) $b['Planned'], $budget));
$budgetSpent   = array_sum(array_map(static fn ($b) => (float) $b['Spent'], $budget));
$budgetPct     = $budgetPlanned > 0 ? (int) round($budgetSpent / $budgetPlanned * 100) : 0;
$requested     = null;
foreach ($answers as $a) {
    if ($a['FieldKey'] === 'presupuesto') {
        $requested = $a['Value'];
    }
}
?>
<?php if ($projects): ?>
    <datalist id="dev-people">
        <?php foreach ($agents as $a): ?><option value="<?= esc($a['FullName'], 'attr') ?>"></option><?php endforeach ?>
    </datalist>
<?php endif ?>
<a class="back-link" href="<?= site_url('panel/desarrollos' . ($dev['PlanMonth'] ? '?mes=' . $dev['PlanMonth'] : '')) ?>"><?= icon('arrow-left') ?> Plan del mes</a>

<div class="page-head">
    <div>
        <div class="eyebrow mono"><?= esc($dev['Code']) ?> · nace del ticket <a href="<?= site_url('panel/tickets/' . $dev['IdTicket']) ?>"><?= ticket_code($dev['IdTicket']) ?></a> · <?= esc($dev['Companies'] ?? $dev['CodCompanies']) ?></div>
        <h1><?= esc($dev['Subject']) ?></h1>
        <div class="head-badges">
            <?= dev_state_badge($dev) ?>
            <?= $isProject ? dev_kind_chip($dev) . ' ' . dev_health_badge($dev) : '' ?>
            <span class="chip"><?= $dev['PlanMonth'] ? 'Plan de ' . esc(Board::monthName($dev['PlanMonth'])) : 'Sin mes' ?></span>
            <?php if ($dev['CarriedFromMonth']): ?><span class="chip chip-carry">Viene de <?= esc(Board::monthName($dev['CarriedFromMonth'])) ?></span><?php endif ?>
            <?php if ($dev['DelayDays'] > 0 && ! $approved): ?><span class="chip chip-late">Retraso de <?= $dev['DelayDays'] ?> días</span><?php endif ?>
            <?php if ($dev['RescheduledDays'] > 0): ?><span class="chip chip-carry">Entrega reprogramada +<?= $dev['RescheduledDays'] ?> días</span><?php endif ?>
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
    <div class="dev-note" style="margin-bottom:16px">Solo el responsable asignado o el administrador pueden actualizar este <?= $word ?>.</div>
<?php elseif ($isAdmin && $dev['State'] === 'en_pruebas' && (int) $dev['EvidenceCount'] === 0): ?>
    <div class="dev-note" style="margin-bottom:16px">Todas las tareas están terminadas. Falta subir evidencia para poder aprobar la entrega.</div>
<?php endif ?>

<div class="dev-layout">
    <div>
        <section class="card" id="tareas">
            <div class="card-head">
                <div>
                    <h2><?= $isProject ? 'Actividades a realizar' : 'Qué se va a hacer' ?></h2>
                    <p class="muted"><?= $isProject
                        ? 'Cada actividad con su responsable, horas y fecha de entrega. La meta de cada mes son las actividades que vencen en él.'
                        : 'Tareas con horas y fecha de cumplimiento (hito). La meta de cada mes son las tareas que vencen en él.' ?></p>
                </div>
                <span class="muted small"><?= $hours($hoursDone) ?> de <?= $hours($hoursAll) ?> h</span>
            </div>

            <div class="dev-goal-grid">
                <div>
                    <span class="muted small">Avance del proyecto</span>
                    <?= dev_progress($dev) ?>
                </div>
                <?php if ($dev['PlanMonth'] !== null && ! $approved): ?>
                    <div>
                        <span class="muted small">Meta de <?= esc(Board::monthName($dev['PlanMonth'])) ?></span>
                        <?= dev_goal($goal) ?>
                    </div>
                <?php endif ?>
            </div>

            <?php if ($tasks === []): ?>
                <p class="empty" style="margin-top:12px"><?= $isProject ? 'Aún no hay actividades. Registra el plan de trabajo con sus responsables y fechas de entrega.' : 'Aún no hay tareas. El programador debe registrar el plan de trabajo.' ?></p>
            <?php else: ?>
                <ul class="dev-tasks">
                    <?php foreach ($tasks as $t): $done = $t['DoneAt'] !== null; ?>
                        <li class="<?= $done ? 'is-done' : '' ?>">
                            <form method="post" action="<?= $base . '/tareas/' . $t['IdTask'] . '/marcar' ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="dev-check <?= $done ? 'is-on' : '' ?>" <?= $canWork ? '' : 'disabled' ?>
                                        aria-label="<?= $done ? 'Marcar como pendiente' : 'Marcar como terminada' ?>"><?= $done ? icon('check') : '' ?></button>
                            </form>
                            <div class="task-main">
                                <span class="task-text"><?= esc($t['Description']) ?></span>
                                <div class="task-due">
                                    <?php if ($t['DueDate'] === null): ?>
                                        <span class="chip chip-late">Sin fecha de cumplimiento</span>
                                    <?php else: $late = $done === false && Board::dateOnly($t['DueDate']) < $today; ?>
                                        <span class="<?= $late ? 'dev-warn' : 'muted' ?>"><?= $isProject ? 'Entrega' : 'Cumple' ?> <?= Board::formatDate($t['DueDate']) ?><?= $late ? ' · vencida' : '' ?></span>
                                    <?php endif ?>
                                    <?php if ($projects && ($isProject || ! empty($t['ResponsibleName']))): ?>
                                        <span class="muted"><?= icon('user') ?> <?= esc($t['ResponsibleName'] ?: 'Sin responsable') ?></span>
                                        <?php if ($canWork && ! $done): ?>
                                            <details class="task-date-edit">
                                                <summary class="link-btn small">Cambiar responsable</summary>
                                                <form method="post" action="<?= $base . '/tareas/' . $t['IdTask'] . '/responsable' ?>">
                                                    <?= csrf_field() ?>
                                                    <input class="input" name="ResponsibleName" list="dev-people" maxlength="150" value="<?= esc($t['ResponsibleName'] ?? '', 'attr') ?>" aria-label="Responsable">
                                                    <button type="submit" class="btn btn-outline btn-sm">Guardar</button>
                                                </form>
                                            </details>
                                        <?php endif ?>
                                    <?php endif ?>
                                    <?php if (! empty($t['CarriedFromMonth'])): ?>
                                        <span class="chip chip-carry" title="Fecha original <?= Board::formatDate($t['OriginalDueDate']) ?>">Pasó de <?= esc(Board::monthName($t['CarriedFromMonth'])) ?></span>
                                    <?php endif ?>
                                    <?php if ($canWork && ! $done): ?>
                                        <details class="task-date-edit">
                                            <summary class="link-btn small">Cambiar fecha</summary>
                                            <form method="post" action="<?= $base . '/tareas/' . $t['IdTask'] . '/fecha' ?>">
                                                <?= csrf_field() ?>
                                                <input type="date" class="input" name="DueDate" required min="<?= $minDue ?>" max="<?= $maxDue ?>"
                                                       value="<?= esc(Board::dateOnly($t['DueDate']) ?? '', 'attr') ?>" aria-label="Fecha de cumplimiento">
                                                <button type="submit" class="btn btn-outline btn-sm">Guardar</button>
                                            </form>
                                        </details>
                                    <?php endif ?>
                                </div>
                            </div>
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
                <form class="dev-inline dev-inline-task <?= $isProject ? 'has-responsible' : '' ?>" method="post" action="<?= $base . '/tareas' ?>">
                    <?= csrf_field() ?>
                    <input class="input" name="Description" id="task-text" maxlength="255" required placeholder="<?= $isProject ? 'Nueva actividad (ej. Levantamiento con el área)' : 'Nueva tarea (ej. Pruebas con el usuario)' ?>" aria-label="<?= $isProject ? 'Actividad' : 'Tarea' ?>">
                    <?php if ($isProject): ?>
                        <input class="input" name="ResponsibleName" id="task-responsible" list="dev-people" maxlength="150" placeholder="Responsable" aria-label="Responsable"
                               value="<?= esc($dev['ProgrammerName'] ?? '', 'attr') ?>">
                    <?php endif ?>
                    <input class="input" name="DueDate" id="task-due" type="date" required min="<?= $minDue ?>" max="<?= $maxDue ?>" aria-label="<?= $isProject ? 'Fecha de entrega' : 'Fecha de cumplimiento' ?>" title="<?= $isProject ? 'Fecha de entrega de la actividad' : 'Fecha de cumplimiento (hito)' ?>">
                    <input class="input" name="Hours" id="task-hours" type="number" min="0.5" max="999" step="0.5" required placeholder="Horas" aria-label="Horas">
                    <button type="submit" class="btn btn-outline">Agregar</button>
                </form>
            <?php endif ?>
        </section>

        <?php if ($isProject): ?>
            <section class="card" id="requiere">
                <h2>Lo que se requiere</h2>
                <p class="dev-sub">Alcance, entregables y requisitos acordados para el proyecto. Los recursos puntuales (accesos, licencias, apoyo) se piden en "Recursos que necesita".</p>

                <?php if ($canWork): ?>
                    <form method="post" action="<?= $base ?>" class="dev-issue-form" style="margin-top:0">
                        <?= csrf_field() ?>
                        <textarea class="input" name="Requirements" id="dev-requirements" rows="5" maxlength="4000"
                                  placeholder="Ej. Módulo de cotizaciones integrado a SAP; reportes en Power BI; capacitación a 10 usuarios"><?= esc($dev['Requirements'] ?? '') ?></textarea>
                        <div class="row"><button type="submit" class="btn btn-outline btn-sm" style="margin-left:auto">Guardar</button></div>
                    </form>
                <?php elseif (! empty($dev['Requirements'])): ?>
                    <p style="white-space:pre-line;margin:0"><?= esc($dev['Requirements']) ?></p>
                <?php else: ?>
                    <p class="empty">Sin registrar.</p>
                <?php endif ?>

                <?php if ($answers !== []): ?>
                    <details class="dev-answers" style="margin-top:14px">
                        <summary class="link-btn small">Lo que pidió el solicitante (formulario del ticket)</summary>
                        <dl class="details">
                            <?php foreach ($answers as $a): ?>
                                <div><dt><?= esc($a['Label']) ?></dt><dd style="white-space:pre-line"><?= esc($a['Value']) ?></dd></div>
                            <?php endforeach ?>
                        </dl>
                    </details>
                <?php endif ?>
            </section>

            <section class="card" id="presupuesto">
                <div class="card-head">
                    <div>
                        <h2>Presupuesto</h2>
                        <p class="muted">Rubros del proyecto: lo presupuestado y lo ejecutado a la fecha.<?= $requested !== null ? ' El solicitante estimó ' . esc($requested) . '.' : '' ?></p>
                    </div>
                    <span class="muted small"><?= Board::money($budgetSpent) ?> de <?= Board::money($budgetPlanned) ?></span>
                </div>

                <?php if ($budgetPlanned > 0): ?>
                    <div class="dev-progress-row" style="margin-bottom:12px">
                        <div class="dev-progress <?= $budgetSpent > $budgetPlanned ? 'is-blocked' : '' ?>"><span style="width: <?= min(100, $budgetPct) ?>%"></span></div><b><?= $budgetPct ?>%</b>
                    </div>
                    <?php if ($budgetSpent > $budgetPlanned): ?>
                        <p class="small dev-warn" style="margin:0 0 12px">Ejecutado supera lo presupuestado en <?= Board::money($budgetSpent - $budgetPlanned) ?>.</p>
                    <?php endif ?>
                <?php endif ?>

                <?php if ($budget === []): ?>
                    <p class="empty">Aún no hay rubros.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table dev-budget">
                            <thead><tr><th>Rubro</th><th class="num">Presupuestado</th><th class="num">Ejecutado</th><th class="num">Saldo</th><?= $canWork ? '<th></th>' : '' ?></tr></thead>
                            <tbody>
                                <?php foreach ($budget as $b): $formId = 'budget-' . $b['IdBudgetItem']; ?>
                                    <tr>
                                        <td><?= esc($b['Concept']) ?></td>
                                        <?php if ($canWork): ?>
                                            <td class="num"><input form="<?= $formId ?>" class="input" name="Planned" inputmode="decimal" value="<?= esc(number_format((float) $b['Planned'], 0, ',', '.'), 'attr') ?>" aria-label="Presupuestado"></td>
                                            <td class="num"><input form="<?= $formId ?>" class="input" name="Spent" inputmode="decimal" value="<?= esc(number_format((float) $b['Spent'], 0, ',', '.'), 'attr') ?>" aria-label="Ejecutado"></td>
                                        <?php else: ?>
                                            <td class="num mono"><?= Board::money($b['Planned']) ?></td>
                                            <td class="num mono"><?= Board::money($b['Spent']) ?></td>
                                        <?php endif ?>
                                        <td class="num mono <?= (float) $b['Spent'] > (float) $b['Planned'] ? 'dev-warn' : '' ?>"><?= Board::money((float) $b['Planned'] - (float) $b['Spent']) ?></td>
                                        <?php if ($canWork): ?>
                                            <td class="nowrap">
                                                <form id="<?= $formId ?>" method="post" action="<?= $base . '/presupuesto/' . $b['IdBudgetItem'] ?>" style="display:inline"><?= csrf_field() ?><button type="submit" class="link-btn small">Guardar</button></form>
                                                <form method="post" action="<?= $base . '/presupuesto/' . $b['IdBudgetItem'] . '/eliminar' ?>" style="display:inline" data-confirm="¿Quitar el rubro &quot;<?= esc($b['Concept'], 'attr') ?>&quot;?"><?= csrf_field() ?><button type="submit" class="link-btn small">Quitar</button></form>
                                            </td>
                                        <?php endif ?>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                            <tfoot>
                                <tr><th>Total</th><th class="num mono"><?= Board::money($budgetPlanned) ?></th><th class="num mono"><?= Board::money($budgetSpent) ?></th><th class="num mono"><?= Board::money($budgetPlanned - $budgetSpent) ?></th><?= $canWork ? '<th></th>' : '' ?></tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif ?>

                <?php if ($canWork): ?>
                    <form class="dev-inline dev-inline-budget" method="post" action="<?= $base . '/presupuesto' ?>">
                        <?= csrf_field() ?>
                        <input class="input" name="Concept" id="budget-concept" maxlength="255" required placeholder="Rubro (ej. Licencias, proveedor, hardware)" aria-label="Rubro">
                        <input class="input" name="Planned" id="budget-planned" inputmode="decimal" required placeholder="Presupuestado" aria-label="Presupuestado">
                        <input class="input" name="Spent" id="budget-spent" inputmode="decimal" placeholder="Ejecutado" aria-label="Ejecutado">
                        <button type="submit" class="btn btn-outline">Agregar</button>
                    </form>
                <?php endif ?>
            </section>
        <?php endif ?>

        <section class="card" id="inconvenientes">
            <h2><?= $isProject ? 'Incidentes del proyecto' : 'Inconvenientes' ?></h2>
            <p class="dev-sub"><?= $isProject
                ? 'Lo que retrasa la entrega. Un incidente bloqueante abierto marca el proyecto como bloqueado; con sus días de impacto el administrador puede reprogramar la entrega.'
                : 'Lo que frena el avance. Un inconveniente bloqueante abierto marca el desarrollo como bloqueado hasta que el administrador lo resuelve.' ?></p>

            <div class="dev-issues">
                <?php if ($issues === []): ?>
                    <p class="empty"><?= $isProject ? 'Sin incidentes reportados.' : 'Sin inconvenientes reportados.' ?></p>
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
                        <?php if ($projects && (int) $i['ImpactDays'] > 0): ?>
                            <?php if (isset($usedIssues[(int) $i['IdIssue']])): ?>
                                <span class="small muted">Reprogramó la entrega +<?= (int) $i['ImpactDays'] ?> días.</span>
                            <?php elseif ($isAdmin && ! $approved && $dev['CommittedDate'] !== null): $newDate = (new DateTimeImmutable($dev['CommittedDate']))->modify('+' . (int) $i['ImpactDays'] . ' day')->format('Y-m-d'); ?>
                                <form method="post" action="<?= $base . '/inconvenientes/' . $i['IdIssue'] . '/reprogramar' ?>"
                                      data-confirm="¿Reprogramar la entrega del <?= Board::formatDate($dev['CommittedDate']) ?> al <?= Board::formatDate($newDate) ?> por este incidente?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-btn small">Reprogramar entrega +<?= (int) $i['ImpactDays'] ?> días (al <?= Board::formatDate($newDate) ?>)</button>
                                </form>
                            <?php endif ?>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>

            <?php if ($canWork): ?>
                <form class="dev-issue-form" method="post" action="<?= $base . '/inconvenientes' ?>">
                    <?= csrf_field() ?>
                    <textarea class="input" name="Description" id="issue-text" rows="2" maxlength="2000" required placeholder="<?= $isProject ? 'Qué pasó, cómo afecta la entrega y qué se necesita' : 'Qué pasa y qué necesitas para seguir' ?>"></textarea>
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

        <?php if ($reschedules !== []): ?>
            <section class="card" id="reprogramaciones">
                <h2>Reprogramaciones de la entrega</h2>
                <p class="dev-sub">Línea base: <?= Board::formatDate($dev['OriginalCommittedDate']) ?> · vigente: <?= Board::formatDate($dev['CommittedDate']) ?>.</p>
                <ul class="dev-resources">
                    <?php foreach ($reschedules as $r): ?>
                        <li style="align-items:flex-start;flex-direction:column;gap:2px">
                            <span class="mono small"><?= Board::formatDate($r['PreviousDate']) ?> → <strong><?= Board::formatDate($r['NewDate']) ?></strong>
                                <?php if ($r['PreviousDate'] !== null): $diff = Board::daysBetween(Board::dateOnly($r['PreviousDate']), Board::dateOnly($r['NewDate'])); ?>
                                    (<?= $diff > 0 ? '+' : '' ?><?= $diff ?> días)
                                <?php endif ?>
                            </span>
                            <span class="small"><?= esc($r['Reason']) ?></span>
                            <span class="muted small"><?= esc($r['ChangedByName']) ?> · <?= format_date($r['CreatedAt'], 'd/m/Y') ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            </section>
        <?php endif ?>

        <section class="card" id="evidencia">
            <h2>Evidencia de cierre</h2>
            <p class="dev-sub">Capturas del <?= $word ?> terminado. Se exige al menos una para aprobar la entrega.</p>

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
                <div><span class="k">Entrega del <?= $word ?></span><span class="v"><?= Board::formatDate($dev['CommittedDate']) ?></span></div>
                <div><span class="k">Estimada</span><span class="v <?= $dev['DelayDays'] > 0 ? 'late' : '' ?>"><?= Board::formatDate($dev['EstimatedDate']) ?></span></div>
                <div><span class="k">Faltan</span><span class="v"><?= $daysLeft === null ? '—' : $daysLeft . ' días' ?></span></div>
            </div>
            <?php if ($dev['RescheduledDays'] > 0): ?>
                <p class="small" style="margin:-6px 0 14px">Entrega original: <strong class="mono"><?= Board::formatDate($dev['OriginalCommittedDate']) ?></strong> · reprogramada <?= (int) $dev['RescheduleCount'] ?> vez<?= (int) $dev['RescheduleCount'] === 1 ? '' : 'es' ?> (+<?= $dev['RescheduledDays'] ?> días). <a href="#reprogramaciones">Ver historial</a></p>
            <?php endif ?>

            <?php if ($canWork && $dev['PlanMonth'] !== null): ?>
                <form method="post" action="<?= $base ?>">
                    <?= csrf_field() ?>
                    <?php if ($isAdmin): ?>
                        <label class="field">
                            <span class="field-label"><?= $isProject ? 'Responsable del proyecto' : 'Programador' ?></span>
                            <select name="AssignedUserId" class="select" id="dev-programmer">
                                <option value="">Sin asignar</option>
                                <?php foreach ($agents as $a): ?>
                                    <option value="<?= esc($a['IdUser'], 'attr') ?>" <?= (string) $dev['AssignedUserId'] === (string) $a['IdUser'] ? 'selected' : '' ?>><?= esc($a['FullName']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </label>
                        <?php if ($projects): ?>
                            <label class="field">
                                <span class="field-label">Tipo</span>
                                <select name="Kind" class="select" id="dev-kind">
                                    <?php foreach (Board::KINDS as $k => $label): ?>
                                        <option value="<?= $k ?>" <?= $dev['Kind'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach ?>
                                </select>
                            </label>
                        <?php endif ?>
                        <label class="field">
                            <span class="field-label">Entrega del <?= $word ?> (comprometida)</span>
                            <input type="date" name="CommittedDate" id="dev-committed" class="input" min="<?= $minDue ?>" value="<?= esc($dev['CommittedDate'] ?? '', 'attr') ?>">
                        </label>
                        <?php if ($projects): ?>
                            <label class="field">
                                <span class="field-label">Motivo si cambias la entrega<?= $isProject ? '' : ' (opcional)' ?></span>
                                <input type="text" name="RescheduleReason" id="dev-reschedule-reason" class="input" maxlength="1000" placeholder="Ej. El proveedor entregó tarde el acceso">
                            </label>
                        <?php endif ?>
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
