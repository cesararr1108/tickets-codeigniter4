<?php
/**
 * Modal con la matriz impacto × urgencia para cambiar la prioridad de un
 * desarrollo. Se abre con cualquier botón [data-prio-open] (data-action =
 * URL del POST, data-title = nombre del desarrollo). Lógica en panel.js.
 */
$matrix = [
    'alto'  => ['alta' => 'alta',  'media' => 'alta',  'baja' => 'media'],
    'medio' => ['alta' => 'alta',  'media' => 'media', 'baja' => 'baja'],
    'bajo'  => ['alta' => 'media', 'media' => 'baja',  'baja' => 'baja'],
];
?>
<dialog class="prio-modal" data-prio-modal aria-label="Cambiar prioridad">
    <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="Priority" value="">
        <h2>Impacto en la venta y urgencia</h2>
        <p class="muted" data-prio-title></p>
        <p class="muted small">Selecciona una celda: define si el desarrollo es urgente.</p>

        <div class="matrix" data-dev-matrix>
            <span></span>
            <span class="matrix-col">Urgencia alta</span>
            <span class="matrix-col">Media</span>
            <span class="matrix-col">Baja</span>
            <?php foreach ($matrix as $impact => $row): ?>
                <span class="matrix-row">Impacto en la venta <?= $impact ?></span>
                <?php foreach ($row as $urgency => $prio): ?>
                    <button type="button" class="matrix-cell prio-<?= $prio ?>" data-priority="<?= $prio ?>"
                            title="Impacto en la venta <?= $impact ?> × urgencia <?= $urgency ?>"><?= priority_label($prio) ?></button>
                <?php endforeach ?>
            <?php endforeach ?>
        </div>

        <div class="prio-actions">
            <button type="button" class="btn btn-outline btn-sm" data-prio-cancel>Cancelar</button>
            <button type="submit" class="btn btn-primary btn-sm" data-prio-save disabled>Guardar prioridad</button>
        </div>
    </form>
</dialog>
