<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var bool $available
 * @var list<array> $rows
 * @var list<array> $companies
 * @var list<array> $roles
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

<?php if ($report = session()->getFlashdata('pushReport')):
    $mask = static fn (string $t) => ($report['debug'] ?? false) ? $t : substr($t, 0, 12) . '…';
?>
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

        <?php if (isset($report['devices'])): ?>
            <h3>Dispositivos de <?= esc($report['email']) ?> registrados desde el panel: <?= count($report['devices']) ?></h3>
            <?php if ($report['devices'] === []): ?>
                <p class="muted">Ninguno. Pulsa "Activar notificaciones" en este navegador (o recarga el panel si ya diste permiso) y repite la prueba.</p>
            <?php endif ?>
            <?php foreach ($report['results'] as $r): ?>
                <p class="small" style="word-break:break-all">
                    <?= $r['ok'] ? '✅ Enviado' : '❌ Falló' ?> · HTTP <?= (int) $r['status'] ?> · <?= esc($r['navigator']) ?><br>
                    <span class="muted">Token: <?= esc($mask($r['token'])) ?></span>
                    <?php if (! $r['ok'] || ($report['debug'] ?? false)): ?><br><span class="muted">Respuesta de FCM: <?= esc(substr($r['response'], 0, 500)) ?></span><?php endif ?>
                </p>
            <?php endforeach ?>
        <?php endif ?>
        <?php if (! ($report['debug'] ?? false)): ?>
            <p class="muted small">Para ver los tokens completos y registrar cada envío en <code>writable/logs</code>, agrega <code>fcm.debug = true</code> al .env (solo en desarrollo).</p>
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
<?= $this->endSection() ?>
