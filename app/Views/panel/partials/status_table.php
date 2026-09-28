<?php
/**
 * Tabla de conteos por estado con barra apilada.
 *
 * @var list<array> $rows
 * @var string $label
 * @var string $filterKey
 */
?>
<?php if ($rows === []): ?>
    <p class="empty">Sin datos en el periodo.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table table-compact">
            <thead>
                <tr>
                    <th><?= esc($label) ?></th>
                    <th class="num">Abiertos</th>
                    <th class="num">En progreso</th>
                    <th class="num">Cerrados</th>
                    <th class="num">Total</th>
                    <th class="stack-col">Distribución</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <?php if ($r['id'] !== null && $r['id'] !== ''): ?>
                                <a href="<?= site_url('panel/tickets?' . http_build_query([$filterKey => $r['id']])) ?>"><?= esc($r['name'] ?? $r['id']) ?></a>
                            <?php else: ?>
                                <span class="muted"><?= esc($emptyName ?? 'Sin dato') ?></span>
                            <?php endif ?>
                        </td>
                        <td class="num"><?= $r['abierto'] ?></td>
                        <td class="num"><?= $r['en_progreso'] ?></td>
                        <td class="num"><?= $r['cerrado'] ?></td>
                        <td class="num"><b><?= $r['total'] ?></b></td>
                        <td class="stack-col">
                            <span class="stack" title="<?= $r['abierto'] ?> abiertos · <?= $r['en_progreso'] ?> en progreso · <?= $r['cerrado'] ?> cerrados">
                                <?php foreach (['abierto', 'en_progreso', 'cerrado'] as $s): if ($r[$s] > 0): ?>
                                    <span class="stack-<?= $s ?>" style="flex: <?= $r[$s] ?>"></span>
                                <?php endif; endforeach ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
