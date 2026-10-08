<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Usuarios del panel.
 *
 * @var list<array> $rows
 * @var array|null $edit
 * @var list<array> $roles
 * @var list<array> $companies
 * @var list<string> $adminRoles
 * @var array $errors
 */
$isEdit  = $edit !== null;
$baseUrl = site_url('panel/admin/usuarios');
$action  = $isEdit ? $baseUrl . '/update/' . rawurlencode((string) $edit['IdUser']) : $baseUrl;
$value   = static fn (string $name, $default = '') => old($name) ?? ($isEdit ? ($edit[$name] ?? $default) : $default);
$err     = static fn (string $name) => isset($errors[$name]) ? '<span class="field-error">' . esc($errors[$name]) . '</span>' : '';
$isAdminRole = static fn (?string $name) => in_array(mb_strtolower((string) $name), $adminRoles, true);

// Rol sugerido al crear: Técnico.
$defaultRole = '';
foreach ($roles as $r) {
    if (mb_strtolower($r['Descripcion']) === mb_strtolower($config->technicianRole)) {
        $defaultRole = (string) $r['Id'];
    }
}
$activeValue = old('IsActive') !== null ? (bool) old('IsActive') : ($isEdit ? (bool) $edit['IsActive'] : true);
?>
<div class="page-head">
    <div>
        <h1>Usuarios</h1>
        <p class="muted">
            <strong>Administrador:</strong> administra usuarios y catálogos, asigna tickets a cualquiera y atiende escalamientos.
            <strong>Técnico:</strong> toma tickets para sí mismo, conversa con el solicitante y puede escalar.
        </p>
    </div>
</div>

<div class="admin-layout">
    <section class="card table-card">
        <form class="admin-toolbar" method="get" action="<?= $baseUrl ?>" data-autosubmit>
            <label class="search-field">
                <?= icon('search') ?>
                <input type="search" name="q" value="<?= esc($q) ?>" placeholder="Buscar por usuario, nombre o correo…">
            </label>

            <select name="role" class="select">
                <option value="">Todos los roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int) $r['Id'] ?>" <?= $role === (string) $r['Id'] ? 'selected' : '' ?>><?= esc($r['Descripcion']) ?></option>
                <?php endforeach ?>
            </select>

            <span class="muted small admin-count"><?= $total ?> usuario<?= $total === 1 ? '' : 's' ?></span>
        </form>

        <?php if ($rows === []): ?>
            <div class="empty-state">
                <?= icon('users', 'icon icon-xl') ?>
                <strong><?= $q !== '' || $role !== '' ? 'Sin resultados' : 'Aún no hay usuarios' ?></strong>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table users-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Rol</th>
                            <th>Compañía / sucursal</th>
                            <th>Estado</th>
                            <th class="actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr class="<?= $isEdit && $edit['IdUser'] === $row['IdUser'] ? 'row-selected' : '' ?> <?= (int) $row['IsActive'] ? '' : 'user-off' ?>">
                                <td class="mono"><?= esc($row['IdUser']) ?></td>
                                <td class="cell-main">
                                    <span class="cell-title"><?= esc($row['FullName']) ?><?= $row['IdUser'] === ($user['id'] ?? null) ? ' (yo)' : '' ?></span>
                                    <span class="muted small"><?= esc($row['Email']) ?></span>
                                </td>
                                <td><span class="chip <?= $isAdminRole($row['RoleName']) ? 'role-admin' : 'role-tech' ?>"><?= esc($row['RoleName'] ?? 'Sin rol') ?></span></td>
                                <td>
                                    <?= esc($row['Companies'] ?? $row['CodCompanies']) ?>
                                    <span class="muted small" style="display:block"><?= esc($row['Branches'] ?? $row['CodBranches']) ?></span>
                                    <?php $more = $extras[(string) $row['IdUser']] ?? []; if ($more !== []): ?>
                                        <span class="muted small" style="display:block" title="<?= esc(implode(', ', array_map(static fn ($c) => $branchNames[$c] ?? $c, $more)), 'attr') ?>">+ <?= count($more) ?> sede<?= count($more) === 1 ? '' : 's' ?> más</span>
                                    <?php endif ?>
                                </td>
                                <td><?= (int) $row['IsActive'] ? '<span class="badge badge-cerrado">Activo</span>' : '<span class="badge">Inactivo</span>' ?></td>
                                <td class="actions-col">
                                    <a class="btn btn-outline btn-sm" href="<?= $baseUrl . '?' . http_build_query(array_filter(['q' => $q, 'role' => $role, 'edit' => $row['IdUser']])) ?>#formulario">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="pager">
                    <a class="btn btn-outline btn-sm <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= query_with(['page' => $page - 1, 'edit' => null]) ?>">Anterior</a>
                    <span class="muted">Página <?= $page ?> de <?= $pages ?></span>
                    <a class="btn btn-outline btn-sm <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= query_with(['page' => $page + 1, 'edit' => null]) ?>">Siguiente</a>
                </nav>
            <?php endif ?>
        <?php endif ?>
    </section>

    <aside class="card admin-form <?= $isEdit ? 'is-edit' : '' ?>" id="formulario">
        <h2><?= $isEdit ? 'Editar usuario' : 'Nuevo usuario' ?></h2>

        <?php if ($errors !== []): ?>
            <div class="flash flash-error"><?= icon('alert') ?> Revisa los campos marcados.</div>
        <?php endif ?>

        <form method="post" action="<?= $action ?>" autocomplete="off">
            <?= csrf_field() ?>

            <label class="field">
                <span class="field-label">Usuario</span>
                <input type="text" name="IdUser" class="input" maxlength="20" value="<?= esc($value('IdUser'), 'attr') ?>" <?= $isEdit ? 'readonly' : 'required' ?> placeholder="ej. jperez">
                <?= $err('IdUser') ?: ($isEdit ? '<span class="muted small">El usuario no se puede cambiar.</span>' : '') ?>
            </label>

            <label class="field">
                <span class="field-label">Nombre completo</span>
                <input type="text" name="FullName" class="input" maxlength="50" required value="<?= esc($value('FullName'), 'attr') ?>">
                <?= $err('FullName') ?>
            </label>

            <label class="field">
                <span class="field-label">Correo</span>
                <input type="email" name="Email" class="input" maxlength="180" required value="<?= esc($value('Email'), 'attr') ?>">
                <?= $err('Email') ?>
            </label>

            <label class="field">
                <span class="field-label">Contraseña <?= $isEdit ? '<span class="muted">(déjala vacía para no cambiarla)</span>' : '' ?></span>
                <input type="password" name="Password" class="input" minlength="8" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                <?= $err('Password') ?: '<span class="muted small">Mínimo 8 caracteres.</span>' ?>
            </label>

            <label class="field">
                <span class="field-label">Rol</span>
                <select name="RoleId" class="select" required>
                    <option value="">Selecciona…</option>
                    <?php $roleValue = (string) $value('RoleId', $defaultRole); foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['Id'] ?>" <?= $roleValue === (string) $r['Id'] ? 'selected' : '' ?>><?= esc($r['Descripcion']) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $err('RoleId') ?>
            </label>

            <label class="field">
                <span class="field-label">Compañía</span>
                <select name="CodCompanies" class="select" required data-dependent-source="branches">
                    <option value="">Selecciona…</option>
                    <?php $companyValue = (string) $value('CodCompanies'); foreach ($companies as $c): ?>
                        <option value="<?= esc($c['CodCompanies'], 'attr') ?>" <?= $companyValue === $c['CodCompanies'] ? 'selected' : '' ?>><?= esc($c['Companies']) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $err('CodCompanies') ?>
            </label>

            <label class="field">
                <span class="field-label">Sucursal</span>
                <select name="CodBranches" class="select" required
                        data-dependent="branches"
                        data-url="<?= site_url('panel/lookups/branches') ?>"
                        data-param="company"
                        data-selected="<?= esc((string) $value('CodBranches'), 'attr') ?>"
                        data-placeholder="Selecciona primero una compañía">
                </select>
                <?= $err('CodBranches') ?>
            </label>

            <?php if ($extrasAvailable): ?>
                <?php
                $extraSelected = old('ExtraBranches') !== null ? (array) old('ExtraBranches') : ($isEdit ? ($edit['ExtraBranches'] ?? []) : []);
                $extraSelected = array_map('strval', $extraSelected);
                ?>
                <fieldset class="field" style="border:0;padding:0;margin:0">
                    <span class="field-label">Sedes adicionales <span class="muted">(opcional)</span></span>
                    <div style="max-height:180px;overflow:auto;border:1px solid var(--border);border-radius:8px;padding:8px 10px">
                        <?php foreach ($allBranches as $b): ?>
                            <label style="display:flex;gap:8px;align-items:center;padding:3px 0;cursor:pointer">
                                <input type="checkbox" name="ExtraBranches[]" value="<?= esc($b['CodBranches'], 'attr') ?>" <?= in_array((string) $b['CodBranches'], $extraSelected, true) ? 'checked' : '' ?>>
                                <span><?= esc($b['Branches']) ?> <span class="muted small">· <?= esc($b['Companies'] ?? '') ?></span></span>
                            </label>
                        <?php endforeach ?>
                    </div>
                    <?= $err('ExtraBranches') ?: '<span class="muted small">Además de la sucursal principal. Los avisos de tickets llegan por todas las sedes asignadas.</span>' ?>
                </fieldset>
            <?php else: ?>
                <p class="muted small">Para asignar más de una sede, ejecuta <code>app/Database/sql/SedesYRecuperacion.sql</code>.</p>
            <?php endif ?>

            <label class="field field-check">
                <input type="checkbox" name="IsActive" value="1" <?= $activeValue ? 'checked' : '' ?>>
                <span>Usuario activo (puede iniciar sesión)</span>
                <?= $err('IsActive') ?>
            </label>

            <div class="form-actions">
                <?php if ($isEdit): ?>
                    <a href="<?= $baseUrl ?>" class="btn btn-outline">Cancelar</a>
                <?php endif ?>
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Guardar cambios' : icon('plus') . ' Crear usuario' ?></button>
            </div>
        </form>
    </aside>
</div>
<?= $this->endSection() ?>
