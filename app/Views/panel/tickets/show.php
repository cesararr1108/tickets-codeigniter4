<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/** @var array $ticket @var array $messages @var array $attachments @var array $lookups */
$state     = target_state($ticket);
$target    = $config->targetHours[$ticket['Priority']] ?? 72;
$stateText = ['ok' => 'Dentro de la meta', 'warn' => 'Por vencer', 'late' => 'Fuera de meta', 'done' => 'Cerrado'][$state];
$lastId    = $messages === [] ? 0 : (int) end($messages)['MessageId'];
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
                    <?php foreach ($attachments as $f): $isUrl = preg_match('#^https?://#i', $f['FilePath']); ?>
                        <li>
                            <?= icon('paperclip') ?>
                            <?php if ($isUrl): ?>
                                <a href="<?= esc($f['FilePath'], 'attr') ?>" target="_blank" rel="noopener"><?= esc($f['FileName']) ?></a>
                            <?php else: ?>
                                <span title="<?= esc($f['FilePath'], 'attr') ?>"><?= esc($f['FileName']) ?></span>
                            <?php endif ?>
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
