<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/** @var array $filters @var array $lookups */
$statusOptions = ['' => 'Todos los estados', 'pendientes' => 'Pendientes'] + $config->statuses;
?>
<div class="page-head">
    <div>
        <h1><?= esc($title) ?></h1>
        <p class="muted"><?= number_format($total) ?> ticket<?= $total === 1 ? '' : 's' ?> encontrados</p>
    </div>
    <a href="<?= site_url('panel/tickets/nuevo') ?>" class="btn btn-primary"><?= icon('plus') ?> Nueva solicitud</a>
</div>

<form class="card filters" method="get" action="<?= current_url() ?>" data-autosubmit>
    <label class="search-field">
        <?= icon('search') ?>
        <input type="search" name="q" value="<?= esc($filters['q']) ?>" placeholder="Asunto, correo o número…">
    </label>

    <select name="status" class="select">
        <?php foreach ($statusOptions as $value => $label): ?>
            <option value="<?= esc($value, 'attr') ?>" <?= $filters['status'] === (string) $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach ?>
    </select>

    <select name="priority" class="select">
        <option value="">Toda prioridad</option>
        <?php foreach ($config->priorities as $value => $label): ?>
            <option value="<?= $value ?>" <?= $filters['priority'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach ?>
    </select>

    <select name="company" class="select">
        <option value="">Todas las compañías</option>
        <?php foreach ($lookups['companies'] as $c): ?>
            <option value="<?= esc($c['CodCompanies'], 'attr') ?>" <?= $filters['company'] === $c['CodCompanies'] ? 'selected' : '' ?>><?= esc($c['Companies']) ?></option>
        <?php endforeach ?>
    </select>

    <select name="category" class="select">
        <option value="">Todas las categorías</option>
        <?php foreach ($lookups['categories'] as $c): ?>
            <option value="<?= (int) $c['IdCategory'] ?>" <?= $filters['category'] === (string) $c['IdCategory'] ? 'selected' : '' ?>><?= esc($c['Category']) ?></option>
        <?php endforeach ?>
    </select>

    <select name="assigned" class="select">
        <option value="">Cualquier agente</option>
        <option value="me" <?= $filters['assigned'] === 'me' ? 'selected' : '' ?>>Asignados a mí</option>
        <option value="none" <?= $filters['assigned'] === 'none' ? 'selected' : '' ?>>Sin asignar</option>
        <?php foreach ($lookups['agents'] as $a): ?>
            <option value="<?= esc($a['IdUser'], 'attr') ?>" <?= $filters['assigned'] === $a['IdUser'] ? 'selected' : '' ?>><?= esc($a['FullName']) ?></option>
        <?php endforeach ?>
    </select>

    <select name="sort" class="select">
        <option value="">Más recientes</option>
        <option value="antiguos" <?= $filters['sort'] === 'antiguos' ? 'selected' : '' ?>>Más antiguos</option>
        <option value="prioridad" <?= $filters['sort'] === 'prioridad' ? 'selected' : '' ?>>Prioridad</option>
    </select>

    <?php if ($filters['branch'] !== ''): ?>
        <input type="hidden" name="branch" value="<?= esc($filters['branch'], 'attr') ?>">
    <?php endif ?>

    <noscript><button class="btn btn-outline" type="submit">Filtrar</button></noscript>
    <?php if (array_filter($filters)): ?>
        <a class="link" href="<?= current_url() ?>">Limpiar</a>
    <?php endif ?>
</form>

<div class="card table-card">
    <?php if ($tickets === []): ?>
        <div class="empty-state">
            <?= icon('inbox', 'icon icon-xl') ?>
            <strong>No hay tickets con estos filtros</strong>
            <span class="muted">Prueba cambiando o limpiando los filtros.</span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Asunto</th>
                        <th>Compañía / sucursal</th>
                        <th>Categoría</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Agente</th>
                        <th>Antigüedad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): $url = site_url('panel/tickets/' . $t['IdTicket']); ?>
                        <tr data-href="<?= $url ?>">
                            <td class="mono"><a href="<?= $url ?>"><?= ticket_code($t['IdTicket']) ?></a></td>
                            <td class="cell-main">
                                <span class="cell-title"><?= esc($t['Subject']) ?></span>
                                <span class="muted small"><?= esc($t['RequesterEmail']) ?></span>
                            </td>
                            <td>
                                <span><?= esc($t['Companies'] ?? $t['CodCompanies']) ?></span>
                                <span class="muted small"><?= esc($t['Branches'] ?? $t['CodBranches']) ?></span>
                            </td>
                            <td>
                                <span><?= esc($t['Category'] ?? '—') ?></span>
                                <?php if (! empty($t['SubCategory'])): ?><span class="muted small"><?= esc($t['SubCategory']) ?></span><?php endif ?>
                            </td>
                            <td><?= priority_badge($t['Priority']) ?></td>
                            <td><?= status_badge($t['Status']) ?></td>
                            <td>
                                <?php if ($t['AssignedName']): ?>
                                    <span class="agent"><span class="avatar avatar-sm"><?= esc(initials($t['AssignedName'])) ?></span><?= esc($t['AssignedName']) ?></span>
                                <?php else: ?>
                                    <span class="muted">Sin asignar</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <span class="age age-<?= target_state($t) ?>" title="Creado el <?= format_date($t['CreatedAt']) ?>"><?= icon('clock') ?> <?= format_age($t['CreatedAt']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="pager">
                <a class="btn btn-outline btn-sm <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= query_with(['page' => $page - 1]) ?>">Anterior</a>
                <span class="muted">Página <?= $page ?> de <?= $pages ?></span>
                <a class="btn btn-outline btn-sm <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= query_with(['page' => $page + 1]) ?>">Siguiente</a>
            </nav>
        <?php endif ?>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
