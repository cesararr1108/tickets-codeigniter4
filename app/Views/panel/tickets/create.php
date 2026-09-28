<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/** @var array $lookups @var array $errors */
$err = static fn (string $field) => isset($errors[$field]) ? '<span class="field-error">' . esc($errors[$field]) . '</span>' : '';
// Matriz impacto × urgencia -> prioridad (la BD solo admite alta/media/baja).
$matrix = [
    'alto'  => ['alta' => 'alta',  'media' => 'alta',  'baja' => 'media'],
    'medio' => ['alta' => 'alta',  'media' => 'media', 'baja' => 'baja'],
    'bajo'  => ['alta' => 'media', 'media' => 'baja',  'baja' => 'baja'],
];
$oldPriority = old('Priority');
?>
<div class="page-head">
    <div>
        <h1>Nueva solicitud</h1>
        <p class="muted">Registra un ticket en nombre de un solicitante.</p>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="flash flash-error"><?= icon('alert') ?> Revisa los campos marcados.</div>
<?php endif ?>

<form class="create-layout" method="post" action="<?= site_url('panel/tickets') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <h2>Detalle</h2>

        <div class="form-grid">
            <label class="field">
                <span class="field-label">Compañía</span>
                <select name="CodCompanies" class="select" required data-dependent-source="branches">
                    <option value="">Selecciona…</option>
                    <?php foreach ($lookups['companies'] as $c): ?>
                        <option value="<?= esc($c['CodCompanies'], 'attr') ?>" <?= old('CodCompanies') === $c['CodCompanies'] ? 'selected' : '' ?>><?= esc($c['Companies']) ?></option>
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
                        data-selected="<?= esc(old('CodBranches') ?? '', 'attr') ?>"
                        data-placeholder="Selecciona primero una compañía">
                    <option value="">Selecciona primero una compañía</option>
                </select>
                <?= $err('CodBranches') ?>
            </label>

            <label class="field">
                <span class="field-label">Categoría</span>
                <select name="IdCategory" class="select" required data-dependent-source="subcategories">
                    <option value="">Selecciona…</option>
                    <?php foreach ($lookups['categories'] as $c): ?>
                        <option value="<?= (int) $c['IdCategory'] ?>" <?= old('IdCategory') === (string) $c['IdCategory'] ? 'selected' : '' ?>><?= esc($c['Category']) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $err('IdCategory') ?>
            </label>

            <label class="field">
                <span class="field-label">Subcategoría <span class="muted">(opcional)</span></span>
                <select name="IdSubCategory" class="select"
                        data-dependent="subcategories"
                        data-url="<?= site_url('panel/lookups/subcategories') ?>"
                        data-param="category"
                        data-selected="<?= esc(old('IdSubCategory') ?? '', 'attr') ?>"
                        data-placeholder="Sin subcategoría">
                    <option value="">Sin subcategoría</option>
                </select>
            </label>

            <label class="field">
                <span class="field-label">Correo del solicitante</span>
                <input class="input" type="email" name="RequesterEmail" maxlength="180" required value="<?= esc(old('RequesterEmail') ?? '') ?>">
                <?= $err('RequesterEmail') ?>
            </label>

            <label class="field">
                <span class="field-label">Asignar a <span class="muted">(opcional)</span></span>
                <select name="AssignedUserId" class="select">
                    <option value="">Sin asignar</option>
                    <?php foreach ($lookups['agents'] as $a): ?>
                        <option value="<?= esc($a['IdUser'], 'attr') ?>" <?= old('AssignedUserId') === $a['IdUser'] ? 'selected' : '' ?>><?= esc($a['FullName']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
        </div>

        <label class="field">
            <span class="field-label">Asunto</span>
            <input class="input" type="text" name="Subject" maxlength="150" required value="<?= esc(old('Subject') ?? '') ?>" placeholder="Resumen corto del problema">
            <?= $err('Subject') ?>
        </label>

        <label class="field">
            <span class="field-label">Descripción <span class="muted">(se guarda como primer mensaje del chat)</span></span>
            <textarea class="input" name="Description" rows="5" placeholder="¿Qué ocurre? ¿Desde cuándo? ¿A quién afecta?"><?= esc(old('Description') ?? '') ?></textarea>
        </label>
    </div>

    <div class="card">
        <h2>Impacto y urgencia</h2>
        <p class="muted">Selecciona una celda para calcular la prioridad.</p>

        <div class="matrix" data-matrix>
            <span></span>
            <span class="matrix-col">Urgencia alta</span>
            <span class="matrix-col">Media</span>
            <span class="matrix-col">Baja</span>
            <?php foreach ($matrix as $impact => $row): ?>
                <span class="matrix-row">Impacto <?= $impact ?></span>
                <?php foreach ($row as $urgency => $prio): ?>
                    <button type="button" class="matrix-cell prio-<?= $prio ?>" data-priority="<?= $prio ?>"
                            title="Impacto <?= $impact ?> × urgencia <?= $urgency ?>"><?= priority_label($prio) ?></button>
                <?php endforeach ?>
            <?php endforeach ?>
        </div>

        <div class="priority-result" data-priority-result>
            <span class="field-label">Prioridad calculada</span>
            <div class="priority-options">
                <?php foreach ($config->priorities as $value => $label): ?>
                    <label class="priority-pill prio-<?= $value ?>">
                        <input type="radio" name="Priority" value="<?= $value ?>" required <?= $oldPriority === $value ? 'checked' : '' ?>>
                        <span><?= esc($label) ?></span>
                    </label>
                <?php endforeach ?>
            </div>
            <p class="muted small" data-priority-target>
                Meta de atención:
                <?php foreach ($config->targetHours as $p => $h): ?>
                    <span data-target-for="<?= $p ?>"><?= priority_label($p) ?> <?= $h ?> h</span><?= $p !== array_key_last($config->targetHours) ? ' · ' : '' ?>
                <?php endforeach ?>
            </p>
            <?= $err('Priority') ?>
        </div>

        <div class="form-actions">
            <a href="<?= site_url('panel') ?>" class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-primary">Crear ticket</button>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
