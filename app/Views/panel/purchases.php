<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var array $filters
 * @var list<array> $rows
 * @var array<int, string> $parents Asunto del ticket original por id
 */
$statusOptions = ['' => 'Todos los estados', 'pendientes' => 'Pendientes'] + $config->statuses;
?>
<div class="page-head">
    <div>
        <h1>Compras</h1>
        <p class="muted"><?= number_format($total) ?> compra<?= $total === 1 ? '' : 's' ?> · diademas, mouse y otros insumos. Nacen de un incidente o requerimiento, quedan como compra pendiente y no cuentan en la métrica del mes, dashboard ni reportes.</p>
    </div>
</div>

<form class="card filters" method="get" action="<?= current_url() ?>" data-autosubmit>
    <label class="search-field">
        <?= icon('search') ?>
        <input type="search" name="q" value="<?= esc($filters['q']) ?>" placeholder="Compra, correo o número…">
    </label>

    <select name="status" class="select">
        <?php foreach ($statusOptions as $value => $label): ?>
            <option value="<?= esc($value, 'attr') ?>" <?= $filters['status'] === (string) $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach ?>
    </select>

    <noscript><button class="btn btn-outline" type="submit">Filtrar</button></noscript>
</form>

<div class="card table-card">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <?= icon('cart', 'icon icon-xl') ?>
            <strong>No hay compras con estos filtros</strong>
            <span class="muted">Se crean con "Crear compra" desde el ticket original.</span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Compra</th>
                        <th>Ticket original</th>
                        <th>Solicitante</th>
                        <th>Compañía</th>
                        <th>Estado</th>
                        <th>Responsable</th>
                        <th>Creada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $t): $url = site_url('panel/tickets/' . $t['IdTicket']); ?>
                        <tr data-href="<?= $url ?>">
                            <td class="cell-main">
                                <span class="mono muted small"><a href="<?= $url ?>"><?= ticket_code($t['IdTicket']) ?></a></span>
                                <span class="cell-title"><?= esc($t['Subject']) ?></span>
                            </td>
                            <td>
                                <?php if (! empty($t['ParentTicketId'])): ?>
                                    <a class="mono" href="<?= site_url('panel/tickets/' . $t['ParentTicketId']) ?>"><?= ticket_code($t['ParentTicketId']) ?></a>
                                    <span class="muted small"><?= esc($parents[$t['ParentTicketId']] ?? '') ?></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif ?>
                            </td>
                            <td><?= esc($t['RequesterEmail']) ?></td>
                            <td>
                                <span><?= esc($t['Companies'] ?? $t['CodCompanies']) ?></span>
                                <span class="muted small"><?= esc($t['Branches'] ?? $t['CodBranches']) ?></span>
                            </td>
                            <td><?= status_badge($t['Status']) ?></td>
                            <td><?= $t['AssignedName'] ? esc($t['AssignedName']) : '<span class="muted">Sin asignar</span>' ?></td>
                            <td><span title="<?= format_date($t['CreatedAt']) ?>"><?= format_age($t['CreatedAt']) ?></span></td>
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
