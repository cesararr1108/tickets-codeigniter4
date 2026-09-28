<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
$hour  = (int) (new DateTime('now', new DateTimeZone($config->displayTimezone)))->format('G');
$hello = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
$first = explode(' ', trim($user['name'] ?? ''))[0] ?? '';
$fmt   = new IntlDateFormatter('es', IntlDateFormatter::FULL, IntlDateFormatter::NONE, $config->displayTimezone);
?>
<div class="page-head">
    <div>
        <h1><?= esc($hello) ?>, <?= esc($first) ?></h1>
        <p class="muted">Panorama de la mesa de ayuda — <?= esc($fmt->format(time())) ?>.</p>
    </div>
</div>

<section class="kpis">
    <a class="card kpi" href="<?= site_url('panel/tickets?status=pendientes') ?>">
        <div class="kpi-head"><span>Tickets pendientes</span><span class="kpi-icon tone-primary"><?= icon('inbox') ?></span></div>
        <div class="kpi-value"><?= $kpis['pending'] ?></div>
        <div class="kpi-foot"><?= $kpis['open'] ?> abiertos · <?= $kpis['inProgress'] ?> en progreso</div>
        <?= view('panel/partials/sparkline', ['values' => array_column($trend, 'created'), 'color' => 'var(--primary)']) ?>
    </a>

    <div class="card kpi">
        <div class="kpi-head"><span>Dentro de la meta de atención</span><span class="kpi-icon tone-good"><?= icon('check') ?></span></div>
        <div class="kpi-value"><?= $kpis['onTarget'] === null ? '—' : $kpis['onTarget'] . '%' ?></div>
        <div class="kpi-foot"><?= $kpis['late'] ?> pendientes fuera de meta</div>
        <div class="meter"><span style="width: <?= (int) ($kpis['onTarget'] ?? 0) ?>%"></span></div>
    </div>

    <a class="card kpi" href="<?= site_url('panel/tickets?assigned=none&status=pendientes') ?>">
        <div class="kpi-head"><span>Sin asignar</span><span class="kpi-icon tone-warning"><?= icon('user') ?></span></div>
        <div class="kpi-value"><?= $kpis['unassigned'] ?></div>
        <div class="kpi-foot">Esperan a un agente</div>
    </a>

    <a class="card kpi" href="<?= site_url('panel/tickets?priority=alta&status=pendientes') ?>">
        <div class="kpi-head"><span>Prioridad alta pendiente</span><span class="kpi-icon tone-critical"><?= icon('flag') ?></span></div>
        <div class="kpi-value"><?= $kpis['highPending'] ?></div>
        <div class="kpi-foot">
            <?= $kpis['closeRate30'] === null ? 'Sin tickets en 30 días' : $kpis['closeRate30'] . '% cerrados de ' . $kpis['created30'] . ' creados (30 días)' ?>
        </div>
    </a>
</section>

<section class="grid-2">
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Pendientes por categoría</h2>
                <p class="muted">Tickets abiertos y en progreso</p>
            </div>
        </div>
        <?= view('panel/partials/bars', [
            'rows'  => array_map(static fn ($r) => $r + ['url' => site_url('panel/tickets?status=pendientes&category=' . $r['id'])], $byCategory),
            'empty' => 'No hay tickets pendientes.',
        ]) ?>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2>Top 5 más urgentes</h2>
                <p class="muted">Por prioridad y antigüedad</p>
            </div>
            <a class="link" href="<?= site_url('panel/tickets?status=pendientes&sort=prioridad') ?>">Ver todos</a>
        </div>

        <?php if ($topPending === []): ?>
            <p class="empty">No hay tickets pendientes. 🎉</p>
        <?php else: ?>
            <ol class="rank">
                <?php foreach ($topPending as $i => $t): ?>
                    <li>
                        <span class="rank-num"><?= $i + 1 ?></span>
                        <a class="rank-body" href="<?= site_url('panel/tickets/' . $t['IdTicket']) ?>">
                            <span class="rank-title"><?= esc($t['Subject']) ?></span>
                            <span class="rank-meta">
                                <?= ticket_code($t['IdTicket']) ?> · <?= esc($t['Companies'] ?? $t['CodCompanies']) ?>
                                · <span class="age age-<?= target_state($t) ?>"><?= icon('clock') ?> <?= format_age($t['CreatedAt']) ?></span>
                            </span>
                        </a>
                        <?= priority_badge($t['Priority']) ?>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    </div>
</section>

<section class="grid-2">
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Tendencia semanal</h2>
                <p class="muted">Tickets creados por semana — últimas 8 semanas</p>
            </div>
        </div>
        <?= view('panel/partials/line_chart', [
            'ariaLabel' => 'Tickets creados y cerrados por semana',
            'labels'    => array_column($trend, 'label'),
            'series'    => [
                ['name' => 'Creados', 'color' => '#2f6fdf', 'values' => array_column($trend, 'created'), 'area' => true],
                ['name' => 'Ya cerrados', 'color' => '#1faa7a', 'values' => array_column($trend, 'closed')],
            ],
        ]) ?>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2>Actividad reciente</h2>
                <p class="muted">Tickets nuevos y mensajes del chat</p>
            </div>
        </div>

        <?php if ($activity === []): ?>
            <p class="empty">Sin actividad todavía.</p>
        <?php else: ?>
            <ul class="activity">
                <?php foreach ($activity as $a): ?>
                    <li>
                        <?php if ($a['type'] === 'ticket'): ?>
                            <span class="activity-dot prio-<?= esc($a['Priority'], 'attr') ?>"></span>
                            <div>
                                <a href="<?= site_url('panel/tickets/' . $a['IdTicket']) ?>"><b><?= ticket_code($a['IdTicket']) ?></b> — <?= esc($a['Subject']) ?></a>
                                <span class="muted small">Nuevo ticket de <?= esc($a['RequesterEmail']) ?> · <?= time_ago($a['CreatedAt']) ?></span>
                            </div>
                        <?php else: ?>
                            <span class="activity-dot msg-<?= esc($a['SenderType'], 'attr') ?>"></span>
                            <div>
                                <a href="<?= site_url('panel/tickets/' . $a['IdTicket']) ?>#chat"><b><?= esc($a['SenderName']) ?></b> en <?= ticket_code($a['IdTicket']) ?></a>
                                <span class="muted small">“<?= esc(mb_strimwidth($a['Message'], 0, 90, '…')) ?>” · <?= time_ago($a['CreatedAt']) ?></span>
                            </div>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>
