<?php
/**
 * Gráfico de líneas SVG (sin librerías).
 *
 * @var list<string> $labels
 * @var list<array{name: string, color: string, values: list<int|float>, area?: bool}> $series
 */
$w = 640; $h = 240;
$pad = ['l' => 34, 'r' => 24, 't' => 14, 'b' => 28];
$max = 0;
foreach ($series as $s) { $max = max($max, ...$s['values']); }
$max  = max(4, (int) ceil($max / 4) * 4);
$n    = max(1, count($labels) - 1);
$x    = static fn ($i) => $pad['l'] + ($w - $pad['l'] - $pad['r']) * $i / $n;
$y    = static fn ($v) => $pad['t'] + ($h - $pad['t'] - $pad['b']) * (1 - $v / $max);
?>
<div class="chart">
    <svg viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-label="<?= esc($ariaLabel ?? 'Gráfico', 'attr') ?>">
        <?php for ($g = 0; $g <= 4; $g++): $gv = $max * $g / 4; ?>
            <line class="grid" x1="<?= $pad['l'] ?>" x2="<?= $w - $pad['r'] ?>" y1="<?= $y($gv) ?>" y2="<?= $y($gv) ?>"/>
            <text class="axis" x="<?= $pad['l'] - 8 ?>" y="<?= $y($gv) + 4 ?>" text-anchor="end"><?= (int) $gv ?></text>
        <?php endfor ?>

        <?php foreach ($labels as $i => $label): ?>
            <text class="axis" x="<?= $x($i) ?>" y="<?= $h - 8 ?>" text-anchor="middle"><?= esc($label) ?></text>
        <?php endforeach ?>

        <?php foreach ($series as $s):
            $points = [];
            foreach ($s['values'] as $i => $v) { $points[] = round($x($i), 1) . ',' . round($y($v), 1); }
        ?>
            <?php if (! empty($s['area'])): ?>
                <polygon fill="<?= $s['color'] ?>" opacity=".1"
                    points="<?= $x(0) ?>,<?= $y(0) ?> <?= implode(' ', $points) ?> <?= $x(count($points) - 1) ?>,<?= $y(0) ?>"/>
            <?php endif ?>
            <polyline fill="none" stroke="<?= $s['color'] ?>" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" points="<?= implode(' ', $points) ?>"/>
            <?php foreach ($s['values'] as $i => $v): ?>
                <circle cx="<?= $x($i) ?>" cy="<?= $y($v) ?>" r="3.5" fill="var(--surface)" stroke="<?= $s['color'] ?>" stroke-width="2">
                    <title><?= esc($s['name']) ?> · <?= esc($labels[$i]) ?>: <?= $v ?></title>
                </circle>
            <?php endforeach ?>
        <?php endforeach ?>
    </svg>

    <div class="legend">
        <?php foreach ($series as $s): ?>
            <span><i style="background: <?= $s['color'] ?>"></i><?= esc($s['name']) ?></span>
        <?php endforeach ?>
    </div>
</div>
