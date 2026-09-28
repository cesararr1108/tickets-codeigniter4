<?php
/**
 * Barras horizontales.
 *
 * @var list<array{name: string, total: int, url?: string}> $rows
 */
$max = max(1, ...array_column($rows, 'total') ?: [1]);
?>
<?php if ($rows === []): ?>
    <p class="empty"><?= esc($empty ?? 'Sin datos.') ?></p>
<?php else: ?>
    <ul class="bars">
        <?php foreach ($rows as $i => $row): ?>
            <li>
                <?php if (! empty($row['url'])): ?>
                    <a class="bars-label" href="<?= $row['url'] ?>" title="<?= esc($row['name'], 'attr') ?>"><?= esc($row['name']) ?></a>
                <?php else: ?>
                    <span class="bars-label" title="<?= esc($row['name'], 'attr') ?>"><?= esc($row['name']) ?></span>
                <?php endif ?>
                <span class="bars-track"><span class="bars-fill" style="width: <?= round(100 * $row['total'] / $max, 1) ?>%; background: <?= chart_color($i) ?>"></span></span>
                <span class="bars-value"><?= (int) $row['total'] ?></span>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
