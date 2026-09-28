<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/** @var array $ticket @var array $messages @var array $attachments @var array $lookups */
$state     = target_state($ticket);
$target    = $config->targetHours[$ticket['Priority']] ?? 72;
$stateText = ['ok' => 'Dentro de la meta', 'warn' => 'Por vencer', 'late' => 'Fuera de meta', 'done' => 'Cerrado'][$state];
$lastId    = $messages === [] ? 0 : (int) end($messages)['MessageId'];

// Formulario adicional: respuestas del solicitante y campos que llena el área.
$formKey    = $formAnswers[0]['FormKey'] ?? null;
$areaDefs   = $formKey !== null ? ($config->areaFields[$formKey] ?? []) : [];
$areaValues = [];
foreach ($formAnswers as $a) {
    if (isset($areaDefs[$a['FieldKey']])) {
        $areaValues[$a['FieldKey']] = (string) $a['Value'];
    }
}
$formAnswers = array_values(array_filter($formAnswers, static fn ($a) => ! isset($areaDefs[$a['FieldKey']])));
?>
<a class="back-link" href="<?= site_url('panel/tickets?status=pendientes') ?>"><?= icon('arrow-left') ?> Tickets</a>

<div class="page-head">
    <div>
        <div class="eyebrow mono"><?= ticket_code($ticket['IdTicket']) ?></div>
        <h1><?= esc($ticket['Subject']) ?></h1>
        <div class="head-badges">
            <?= status_badge($ticket['Status']) ?>
            <?= priority_badge($ticket['Priority']) ?>
            <span class="age age-<?= $state ?>"><?= icon('clock') ?> <?= format_age($ticket['CreatedAt']) ?> · <?= $stateText ?> (<?= $target ?> h)</span>
        </div>
    </div>
</div>

<div class="ticket-layout">
    <div class="ticket-main">
    <?php if (! empty($formAnswers)): ?>
        <?php
        $formTitles = [
            'proyecto'      => 'Solicitud de proyecto / desarrollo',
            'requerimiento' => 'Requerimiento',
            'incidente'     => 'Incidente / soporte',
        ];
        ?>
        <section class="card form-answers">
            <details open>
                <summary class="card-head">
                    <div>
                        <h2><?= icon('list') ?> <?= esc($formTitles[$formKey] ?? ucfirst($formKey)) ?></h2>
                        <p class="muted">Información registrada por el solicitante al crear el ticket.</p>
                    </div>
                </summary>

                <dl class="answers">
                    <?php foreach ($formAnswers as $a): $value = trim((string) $a['Value']); $long = mb_strlen($value) > 70 || str_contains($value, "\n"); ?>
                        <div class="<?= $long ? 'answer-wide' : '' ?>">
                            <dt><?= esc($a['Label']) ?></dt>
                            <dd class="<?= $value === '' ? 'muted' : '' ?>"><?= $value === '' ? '—' : nl2br(esc($value)) ?></dd>
                        </div>
                    <?php endforeach ?>
                </dl>
            </details>
        </section>
    <?php endif ?>

    <section class="card chat" id="chat">
        <div class="card-head">
            <div>
                <h2>Conversación</h2>
                <p class="muted"><?= count($messages) ?> mensaje<?= count($messages) === 1 ? '' : 's' ?> con <?= esc($ticket['RequesterEmail']) ?></p>
            </div>
        </div>

        <div class="chat-thread" data-chat
             data-poll-url="<?= site_url('panel/tickets/' . $ticket['IdTicket'] . '/messages') ?>"
             data-poll-seconds="<?= (int) $config->chatPollSeconds ?>"
             data-last-id="<?= $lastId ?>">
            <?php if ($messages === []): ?>
                <p class="empty chat-empty">Aún no hay mensajes. Escribe el primero.</p>
            <?php endif ?>
            <?php foreach ($messages as $m): ?>
                <?= view('panel/partials/message', ['m' => $m]) ?>
            <?php endforeach ?>
        </div>

        <form class="chat-composer" method="post" action="<?= site_url('panel/tickets/' . $ticket['IdTicket'] . '/messages') ?>" data-chat-form>
            <?= csrf_field() ?>
            <textarea name="Message" rows="2" placeholder="Escribe una respuesta… (Enter para enviar, Shift+Enter para salto de línea)" required></textarea>
            <button type="submit" class="btn btn-primary" aria-label="Enviar"><?= icon('send') ?> <span class="hide-mobile">Enviar</span></button>
        </form>
    </section>
    </div>

    <aside class="ticket-side">
        <form class="card" method="post" action="<?= site_url('panel/tickets/' . $ticket['IdTicket']) ?>">
            <?= csrf_field() ?>
            <h2>Gestión</h2>

            <label class="field">
                <span class="field-label">Estado</span>
                <div class="segmented">
                    <?php foreach ($config->statuses as $value => $label): ?>
                        <label>
                            <input type="radio" name="Status" value="<?= $value ?>" <?= $ticket['Status'] === $value ? 'checked' : '' ?>>
                            <span><?= esc($label) ?></span>
                        </label>
                    <?php endforeach ?>
                </div>
            </label>

            <label class="field">
                <span class="field-label">Prioridad</span>
                <select name="Priority" class="select">
                    <?php foreach ($config->priorities as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $ticket['Priority'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </label>

            <label class="field">
                <span class="field-label">Agente asignado</span>
                <select name="AssignedUserId" class="select">
                    <option value="">Sin asignar</option>
                    <?php foreach ($lookups['agents'] as $a): ?>
                        <option value="<?= esc($a['IdUser'], 'attr') ?>" <?= $ticket['AssignedUserId'] === $a['IdUser'] ? 'selected' : '' ?>>
                            <?= esc($a['FullName']) ?><?= $a['IdUser'] === ($user['id'] ?? null) ? ' (yo)' : '' ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </label>

            <button type="submit" class="btn btn-primary btn-block">Guardar cambios</button>
        </form>

        <?php if ($areaDefs !== []): ?>
            <form class="card" method="post" action="<?= site_url('panel/tickets/' . $ticket['IdTicket'] . '/seguimiento') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="FormKey" value="<?= esc($formKey, 'attr') ?>">
                <h2>Seguimiento del área</h2>
                <p class="muted small">Solo lo ve TI; el solicitante no llena estos campos.</p>

                <?php foreach ($areaDefs as $key => $def): $value = $areaValues[$key] ?? ''; ?>
                    <label class="field">
                        <span class="field-label"><?= esc($def['label']) ?></span>
                        <?php if (($def['type'] ?? 'text') === 'select'): ?>
                            <select name="Area[<?= esc($key, 'attr') ?>]" class="select">
                                <?php foreach ($def['options'] as $option): ?>
                                    <option value="<?= esc($option, 'attr') ?>" <?= $value === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                                <?php endforeach ?>
                            </select>
                        <?php elseif ($def['type'] === 'textarea'): ?>
                            <textarea name="Area[<?= esc($key, 'attr') ?>]" class="input" rows="3"><?= esc($value) ?></textarea>
                        <?php else: ?>
                            <input type="text" name="Area[<?= esc($key, 'attr') ?>]" class="input" value="<?= esc($value, 'attr') ?>">
                        <?php endif ?>
                    </label>
                <?php endforeach ?>

                <button type="submit" class="btn btn-primary btn-block">Guardar seguimiento</button>
            </form>
        <?php endif ?>

        <div class="card">
            <h2>Detalle</h2>
            <dl class="details">
                <div><dt><?= icon('mail') ?> Solicitante</dt><dd><a href="mailto:<?= esc($ticket['RequesterEmail'], 'attr') ?>"><?= esc($ticket['RequesterEmail']) ?></a></dd></div>
                <div><dt><?= icon('building') ?> Compañía</dt><dd><?= esc($ticket['Companies'] ?? $ticket['CodCompanies']) ?><span class="muted small"><?= esc($ticket['Branches'] ?? $ticket['CodBranches']) ?></span></dd></div>
                <div><dt><?= icon('tag') ?> Categoría</dt><dd><?= esc($ticket['Category'] ?? '—') ?><?php if (! empty($ticket['SubCategory'])): ?><span class="muted small"><?= esc($ticket['SubCategory']) ?></span><?php endif ?></dd></div>
                <div><dt><?= icon('clock') ?> Creado</dt><dd><?= format_date($ticket['CreatedAt']) ?><span class="muted small"><?= time_ago($ticket['CreatedAt']) ?></span></dd></div>
            </dl>
        </div>

        <div class="card">
            <h2>Adjuntos</h2>
            <?php if ($attachments === []): ?>
                <p class="empty">Sin archivos adjuntos.</p>
            <?php else: ?>
                <ul class="files">
                    <?php foreach ($attachments as $f): ?>
                        <li>
                            <?= icon('paperclip') ?>
                            <a href="<?= site_url('panel/tickets/' . $ticket['IdTicket'] . '/adjuntos/' . $f['AttachmentId']) ?>" target="_blank" rel="noopener"><?= esc($f['FileName']) ?></a>
                            <span class="muted small"><?= format_date($f['CreatedAt'], 'd/m/Y') ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>

        <?php if ($history !== []): ?>
            <div class="card">
                <h2>Otros tickets del solicitante</h2>
                <ul class="mini-list">
                    <?php foreach ($history as $h): ?>
                        <li>
                            <a href="<?= site_url('panel/tickets/' . $h['IdTicket']) ?>"><?= ticket_code($h['IdTicket']) ?> — <?= esc($h['Subject']) ?></a>
                            <?= status_badge($h['Status']) ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>
    </aside>
</div>
<?= $this->endSection() ?>
