<?php
/** @var list<int|float> $values @var string $color */
$w = 200; $h = 36;
$max = max(1, ...$values); $min = min(...$values);
$range = max(1, $max - $min);
$n = max(1, count($values) - 1);
$pts = [];
foreach ($values as $i => $v) {
    $pts[] = round($w * $i / $n, 1) . ',' . round(4 + ($h - 8) * (1 - ($v - $min) / $range), 1);
}
?>
<svg class="sparkline" viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="none" aria-hidden="true">
    <polyline fill="none" stroke="<?= $color ?>" stroke-width="2" vector-effect="non-scaling-stroke" points="<?= implode(' ', $pts) ?>"/>
</svg>
