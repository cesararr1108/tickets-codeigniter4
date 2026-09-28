<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Vista genérica de administración de un catálogo.
 *
 * @var array $def Definición (App\Libraries\CatalogAdmin)
 * @var array $catalogs
 * @var list<array> $rows
 * @var array|null $edit
 * @var array $options
 * @var array $errors
 */
$pk       = $def['pk'];
$parent   = $def['parent'] ?? null;
$isEdit   = $edit !== null;
$baseUrl  = site_url('panel/admin/' . $def['key']);
$action   = $isEdit ? $baseUrl . '/update/' . rawurlencode((string) $edit[$pk]) : $baseUrl;
$value    = static fn (string $name) => old($name) ?? ($isEdit ? ($edit[$name] ?? '') : '');
$keepQuery = array_filter(['q' => $q, $parent['param'] ?? 'x' => $parentValue]);
?>
<div class="page-head">
    <div>
        <h1>Administración</h1>
        <p class="muted">Compañías, sucursales, categorías y subcategorías que se usan en los tickets.</p>
    </div>
</div>

<nav class="tabs">
    <?php foreach ($catalogs as $key => $c): ?>
        <a href="<?= site_url('panel/admin/' . $key) ?>" class="tab <?= $key === $def['key'] ? 'active' : '' ?>"><?= icon($c['icon']) ?> <?= esc($c['title']) ?></a>
    <?php endforeach ?>
</nav>

<div class="admin-layout">
    <section class="card table-card">
        <form class="admin-toolbar" method="get" action="<?= $baseUrl ?>" data-autosubmit>
            <label class="search-field">
                <?= icon('search') ?>
                <input type="search" name="q" value="<?= esc($q) ?>" placeholder="Buscar <?= esc(mb_strtolower($def['title'])) ?>…">
            </label>

            <?php if ($parent !== null): ?>
                <select name="<?= $parent['param'] ?>" class="select">
                    <option value="">Todas las <?= esc(mb_strtolower($catalogs[$parent['catalog']]['title'])) ?></option>
                    <?php foreach ($options[$parent['catalog']] ?? [] as $id => $label): ?>
                        <option value="<?= esc($id, 'attr') ?>" <?= $parentValue === (string) $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            <?php endif ?>

            <span class="muted small admin-count"><?= $total ?> registro<?= $total === 1 ? '' : 's' ?></span>
        </form>

        <?php if ($rows === []): ?>
            <div class="empty-state">
                <?= icon($def['icon'], 'icon icon-xl') ?>
                <strong><?= $q !== '' || $parentValue !== '' ? 'Sin resultados' : 'Aún no hay ' . esc(mb_strtolower($def['title'])) ?></strong>
                <span class="muted">Usa el formulario para agregar <?= $q !== '' ? 'una nueva' : 'la primera' ?>.</span>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= $def['autoPk'] ? 'Id' : 'Código' ?></th>
                            <th>Nombre</th>
                            <?php if ($parent !== null): ?><th><?= esc($parent['label']) ?></th><?php endif ?>
                            <th>En uso</th>
                            <th class="actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $id     = (string) $row[$pk];
                            $counts = $usage[$id] ?? [];
                            $used   = array_sum($counts) > 0;
                        ?>
                            <tr class="<?= $isEdit && (string) $edit[$pk] === $id ? 'row-selected' : '' ?>">
                                <td class="mono"><?= esc($id) ?></td>
                                <td class="cell-main">
                                    <span class="cell-title"><?= esc($row[$def['name']]) ?></span>
                                    <?php if (! empty($row['Description'])): ?><span class="muted small"><?= esc($row['Description']) ?></span><?php endif ?>
                                </td>
                                <?php if ($parent !== null): ?>
                                    <td><?= esc($row['ParentName'] ?? $row[$parent['field']]) ?></td>
                                <?php endif ?>
                                <td>
                                    <?php if (! $used): ?>
                                        <span class="muted small">Sin uso</span>
                                    <?php else: ?>
                                        <span class="usage">
                                            <?php foreach ($def['usage'] as $i => $u): if (! empty($counts[$i])): ?>
                                                <?php if (! empty($u['link'])): ?>
                                                    <a class="chip" href="<?= site_url($u['link'] . rawurlencode($id)) ?>"><?= $counts[$i] ?> <?= esc($counts[$i] === 1 ? $u['one'] : $u['label']) ?></a>
                                                <?php else: ?>
                                                    <span class="chip"><?= $counts[$i] ?> <?= esc($counts[$i] === 1 ? $u['one'] : $u['label']) ?></span>
                                                <?php endif ?>
                                            <?php endif; endforeach ?>
                                        </span>
                                    <?php endif ?>
                                </td>
                                <td class="actions-col">
                                    <a class="btn btn-outline btn-sm" href="<?= $baseUrl . '?' . http_build_query($keepQuery + ['edit' => $id, 'page' => $page > 1 ? $page : null]) ?>">Editar</a>
                                    <form method="post" action="<?= $baseUrl . '/delete/' . rawurlencode($id) ?>"
                                          data-confirm="¿Eliminar <?= esc($def['singular'], 'attr') ?> &quot;<?= esc($row[$def['name']], 'attr') ?>&quot;?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-danger btn-sm" <?= $used ? 'disabled title="Está en uso: no se puede eliminar"' : '' ?>>Eliminar</button>
                                    </form>
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
        <h2><?= $isEdit ? 'Editar ' . esc($def['singular']) : 'Nueva ' . esc($def['singular']) ?></h2>

        <?php if ($errors !== []): ?>
            <div class="flash flash-error"><?= icon('alert') ?> Revisa los campos marcados.</div>
        <?php endif ?>

        <form method="post" action="<?= $action ?>">
            <?= csrf_field() ?>

            <?php foreach ($def['fields'] as $name => $field):
                $readonly = $isEdit && $name === $pk;
                $val      = (string) ($readonly ? $edit[$pk] : $value($name));
                $required = ! empty($field['required']);
                // Al crear, se preselecciona el padre filtrado (ej. la compañía de la lista).
                if (! $isEdit && $val === '' && $parent !== null && $name === $parent['field']) {
                    $val = $parentValue;
                }
            ?>
                <label class="field">
                    <span class="field-label">
                        <?= esc($field['label']) ?>
                        <?php if (! $required): ?><span class="muted">(opcional)</span><?php endif ?>
                    </span>

                    <?php if ($field['type'] === 'select'): ?>
                        <select name="<?= $name ?>" class="select" <?= $required ? 'required' : '' ?>>
                            <option value="">Selecciona…</option>
                            <?php foreach ($options[$field['options']] ?? [] as $id => $label): ?>
                                <option value="<?= esc($id, 'attr') ?>" <?= $val === (string) $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach ?>
                        </select>
                    <?php elseif ($field['type'] === 'textarea'): ?>
                        <textarea name="<?= $name ?>" class="input" rows="3" maxlength="<?= (int) $field['max'] ?>" <?= $required ? 'required' : '' ?>><?= esc($val) ?></textarea>
                    <?php else: ?>
                        <input type="text" name="<?= $name ?>" class="input" value="<?= esc($val, 'attr') ?>"
                               maxlength="<?= (int) $field['max'] ?>" <?= $required ? 'required' : '' ?> <?= $readonly ? 'readonly' : '' ?>>
                    <?php endif ?>

                    <?php if (isset($errors[$name])): ?>
                        <span class="field-error"><?= esc($errors[$name]) ?></span>
                    <?php elseif (! empty($field['hint']) && ! $readonly): ?>
                        <span class="muted small"><?= esc($field['hint']) ?></span>
                    <?php elseif ($readonly): ?>
                        <span class="muted small">El código no se puede cambiar porque lo usan otras tablas.</span>
                    <?php endif ?>
                </label>
            <?php endforeach ?>

            <div class="form-actions">
                <?php if ($isEdit): ?>
                    <a href="<?= $baseUrl . ($keepQuery ? '?' . http_build_query($keepQuery) : '') ?>" class="btn btn-outline">Cancelar</a>
                <?php endif ?>
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Guardar cambios' : icon('plus') . ' Agregar' ?></button>
            </div>
        </form>
    </aside>
</div>
<?= $this->endSection() ?>
