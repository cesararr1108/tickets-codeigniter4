<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var bool $available
 * @var list<array> $rows
 * @var list<array> $companies
 * @var list<array> $roles
 * @var bool $keysAvailable
 * @var array<string, array> $keys       Llaves por compañía (solo la parte pública)
 * @var bool $defaultKey                 ¿Hay pushapi.apiKey en el .env?
 */
?>
<div class="page-head">
    <div>
        <h1>Notificaciones</h1>
        <p class="muted">Define a qué rol le llega el aviso push cuando se crea un ticket de cada compañía. Una compañía puede avisar a varios roles. Si una compañía no tiene reglas, el aviso va al responsable asignado o, si no hay, a todos los usuarios activos.</p>
    </div>
    <form method="post" action="<?= site_url('panel/push/test') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline"><?= icon('bell') ?> Enviarme una prueba</button>
    </form>
</div>

<?php $fcmCfg = config(\Config\Fcm::class); ?>
<?php if (! $fcmCfg->useRoutes): ?>
    <div class="flash flash-error" style="background:#fff7e6;color:#8a5a00"><?= icon('alert') ?>
        Las reglas de esta pantalla están desactivadas por ahora (agrega <code>fcm.useRoutes = true</code> al .env para aplicarlas).
        <?php if ($fcmCfg->filterByBranch): ?>
            Mientras tanto, cada ticket nuevo avisa a los usuarios que tienen asignada <strong>la sede del ticket</strong> (sede principal o adicional).
        <?php else: ?>
            Cada ticket nuevo avisa a <strong>todos los dispositivos registrados desde el panel</strong>.
        <?php endif ?>
    </div>
<?php elseif ($fcmCfg->filterByBranch): ?>
    <div class="flash" style="background:#eef6ff;color:#1d4f91"><?= icon('check') ?> Además de estas reglas, solo se avisa a los usuarios que tienen asignada la sede del ticket.</div>
<?php endif ?>

<?php if ($report = session()->getFlashdata('pushReport')): ?>
    <section class="card" style="padding:16px;margin-bottom:16px">
        <h2 style="margin-top:0">Resultado de la prueba</h2>
        <table class="table">
            <?php foreach ($report['steps'] as $step): ?>
                <tr>
                    <td><?= $step['ok'] ? '✅' : '❌' ?> <strong><?= esc($step['step']) ?></strong></td>
                    <td class="muted small" style="word-break:break-all"><?= esc($step['detail']) ?></td>
                </tr>
            <?php endforeach ?>
        </table>

        <?php if (array_key_exists('result', $report)): $r = $report['result']; ?>
            <?php if ($r === null): ?>
                <p>❌ La API rechazó el envío a <?= esc($report['email']) ?> (el detalle queda en <code>writable/logs</code>).</p>
            <?php elseif ((int) ($r['total'] ?? 0) === 0 && ! in_array($r['estado'] ?? '', ['pendiente', 'procesando'], true)): ?>
                <p>⚠️ <?= esc($report['email']) ?> no tiene dispositivos registrados en la API. Pulsa "Activar notificaciones" en este navegador (o recarga el panel si ya diste permiso) y repite la prueba.</p>
            <?php else: ?>
                <p>
                    <?= (int) ($r['exitosos'] ?? 0) > 0 || in_array($r['estado'] ?? '', ['pendiente', 'procesando'], true) ? '✅' : '❌' ?>
                    Envío <code><?= esc($r['id'] ?? '') ?></code> a <?= esc($report['email']) ?> · estado <strong><?= esc($r['estado'] ?? '?') ?></strong>
                </p>
                <p class="muted small">
                    Dispositivos: <?= (int) ($r['total'] ?? 0) ?> · enviados: <?= (int) ($r['exitosos'] ?? 0) ?> ·
                    fallidos: <?= (int) ($r['fallidos'] ?? 0) ?> · caducados (dados de baja): <?= (int) ($r['invalidos'] ?? 0) ?>
                </p>
            <?php endif ?>
        <?php endif ?>
    </section>
<?php endif ?>

<?php if (! $available): ?>
    <div class="flash flash-error"><?= icon('alert') ?> Falta crear la tabla en SQL Server: ejecuta <code>app/Database/sql/NotificationRoutes.sql</code>.</div>
<?php endif ?>

<div class="admin-layout">
    <section class="card table-card">
        <?php if ($rows === []): ?>
            <div class="empty-state">
                <?= icon('bell', 'icon icon-xl') ?>
                <strong>Aún no hay reglas</strong>
                <span class="muted">Usa el formulario para agregar la primera.</span>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Compañía</th><th>Rol que recibe el aviso</th><th>Usuarios activos</th><th class="actions-col"></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="cell-main"><span class="cell-title"><?= esc($row['Companies'] ?? $row['CodCompanies']) ?></span><span class="muted small"><?= esc($row['CodCompanies']) ?></span></td>
                                <td><span class="chip"><?= esc($row['Role'] ?? $row['RoleId']) ?></span></td>
                                <td><?= (int) $row['Users'] ?></td>
                                <td class="actions-col">
                                    <form method="post" action="<?= site_url('panel/admin/notificaciones/delete/' . (int) $row['Id']) ?>"
                                          data-confirm="¿Eliminar esta regla?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <aside class="card admin-form">
        <h2>Nueva regla</h2>
        <form method="post" action="<?= site_url('panel/admin/notificaciones') ?>">
            <?= csrf_field() ?>
            <label class="field">
                <span class="field-label">Los tickets de la compañía</span>
                <select name="CodCompanies" class="select" required>
                    <option value="">Selecciona…</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= esc($c['CodCompanies'], 'attr') ?>"><?= esc($c['Companies']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <label class="field">
                <span class="field-label">Se notifican al rol</span>
                <select name="RoleId" class="select" required>
                    <option value="">Selecciona…</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['Id'] ?>"><?= esc($r['Descripcion']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= icon('plus') ?> Agregar</button>
            </div>
        </form>
    </aside>
</div>

<h2 style="margin:28px 0 6px">API keys de notificaciones por compañía</h2>
<p class="muted">La API de notificaciones entrega una llave por organización (<code>php spark apikey:create</code>). Cada usuario del panel registra su navegador y recibe los avisos con la llave de su compañía; el solicitante, con la de la compañía del ticket.
    <?= $defaultKey ? 'Las compañías sin llave usan la del .env (<code>pushapi.apiKey</code>).' : 'Las compañías sin llave no reciben notificaciones.' ?></p>

<?php if (! $keysAvailable): ?>
    <div class="flash flash-error"><?= icon('alert') ?> Falta crear la tabla en SQL Server: ejecuta <code>app/Database/sql/PushApiKeys.sql</code>.</div>
<?php else: ?>
    <div class="admin-layout">
        <section class="card table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Compañía</th><th>API key</th><th>Actualizada</th><th class="actions-col"></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $c): $k = $keys[$c['CodCompanies']] ?? null; ?>
                            <tr>
                                <td class="cell-main"><span class="cell-title"><?= esc($c['Companies']) ?></span><span class="muted small"><?= esc($c['CodCompanies']) ?></span></td>
                                <td>
                                    <?php if ($k !== null): ?>
                                        <span class="chip"><?= esc($k['Masked']) ?></span>
                                    <?php elseif ($defaultKey): ?>
                                        <span class="muted small">Usa la del .env</span>
                                    <?php else: ?>
                                        <span class="small" style="color:#b91c1c">Sin llave</span>
                                    <?php endif ?>
                                </td>
                                <td class="muted small"><?= $k !== null ? esc($k['UpdatedAt']) . ' UTC' : '' ?></td>
                                <td class="actions-col">
                                    <?php if ($k !== null): ?>
                                        <form method="post" action="<?= site_url('panel/admin/notificaciones/llaves/delete/' . rawurlencode($c['CodCompanies'])) ?>"
                                              data-confirm="¿Eliminar la llave de esta compañía? Dejará de recibir notificaciones<?= $defaultKey ? ' propias (usará la del .env)' : '' ?>.">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                        </form>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="card admin-form">
            <h2>Guardar llave</h2>
            <form method="post" action="<?= site_url('panel/admin/notificaciones/llaves') ?>" autocomplete="off">
                <?= csrf_field() ?>
                <label class="field">
                    <span class="field-label">Compañía</span>
                    <select name="CodCompanies" class="select" required>
                        <option value="">Selecciona…</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= esc($c['CodCompanies'], 'attr') ?>"><?= esc($c['CodCompanies'] . ' · ' . $c['Companies']) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <label class="field">
                    <span class="field-label">API key (reemplaza la anterior)</span>
                    <input type="password" name="ApiKey" class="input" required placeholder="pk_xxxxxxxxxxxx.xxxx…" autocomplete="new-password" spellcheck="false">
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= icon('check') ?> Guardar y verificar</button>
                </div>
            </form>
        </aside>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
