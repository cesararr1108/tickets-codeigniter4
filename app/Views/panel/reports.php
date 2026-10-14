<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
$total    = array_sum($status);
$prioMax  = max(1, ...array_values($byPriority));
?>
<div class="page-head">
    <div>
        <h1>Reportes</h1>
        <p class="muted">Volumen, estado y tiempo de atención por usuario, área, categoría, oficina y organización.</p>
    </div>
    <form method="get" class="period" data-autosubmit>
        <select name="period" class="select">
            <?php foreach ($periods as $value => $label): ?>
                <option value="<?= $value ?>" <?= $period === (string) $value ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <noscript><button class="btn btn-outline">Ver</button></noscript>
    </form>
</div>

<section class="kpis">
    <div class="card kpi">
        <div class="kpi-head"><span>Tickets creados</span><span class="kpi-icon tone-primary"><?= icon('inbox') ?></span></div>
        <div class="kpi-value"><?= $total ?></div>
        <div class="kpi-foot"><?= esc($periods[$period]) ?></div>
    </div>
    <?php foreach (['abierto' => ['Abiertos', 'tone-warning', 'alert'], 'en_progreso' => ['En progreso', 'tone-primary', 'clock'], 'cerrado' => ['Cerrados', 'tone-good', 'check']] as $key => [$label, $tone, $ico]): ?>
        <div class="card kpi">
            <div class="kpi-head"><span><?= $label ?></span><span class="kpi-icon <?= $tone ?>"><?= icon($ico) ?></span></div>
            <div class="kpi-value"><?= $status[$key] ?></div>
            <div class="kpi-foot"><?= $total ? round(100 * $status[$key] / $total) : 0 ?>% del total</div>
        </div>
    <?php endforeach ?>
</section>

<section class="grid-2">
    <div class="card">
        <div class="card-head"><div><h2>Tendencia</h2><p class="muted">Tickets creados por semana — últimas 12 semanas</p></div></div>
        <?= view('panel/partials/line_chart', [
            'ariaLabel' => 'Tickets creados por semana',
            'labels'    => array_column($trend, 'label'),
            'series'    => [
                ['name' => 'Creados', 'color' => '#2f6fdf', 'values' => array_column($trend, 'created'), 'area' => true],
                ['name' => 'Ya cerrados', 'color' => '#1faa7a', 'values' => array_column($trend, 'closed')],
            ],
        ]) ?>
    </div>

    <div class="card">
        <div class="card-head"><div><h2>Por prioridad</h2><p class="muted"><?= esc($periods[$period]) ?></p></div></div>
        <ul class="bars">
            <?php foreach ($byPriority as $p => $n): ?>
                <li>
                    <span class="bars-label"><?= priority_badge($p) ?></span>
                    <span class="bars-track"><span class="bars-fill prio-bg-<?= $p ?>" style="width: <?= round(100 * $n / $prioMax, 1) ?>%"></span></span>
                    <span class="bars-value"><?= $n ?></span>
                </li>
            <?php endforeach ?>
        </ul>

        <h3 class="sub-title">Por categoría</h3>
        <?= view('panel/partials/bars', [
            'rows' => array_map(static fn ($r) => $r + ['url' => site_url('panel/tickets?category=' . $r['id'])], $byCategory),
        ]) ?>
    </div>
</section>

<section class="card">
    <div class="card-head"><div><h2>Tiempo de resolución por usuario</h2><p class="muted">Tiempo medido con el cronómetro (Iniciar / Finalizar) — <?= esc($periods[$period]) ?></p></div></div>
    <?php if ($timeByUser === []): ?>
        <p class="empty">Aún no hay tiempo registrado en el periodo. Los responsables lo registran con «Iniciar tiempo» en cada ticket.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th class="num">Tickets trabajados</th>
                        <th class="num">Cerrados</th>
                        <th class="num">Sesiones</th>
                        <th class="num">Tiempo total</th>
                        <th class="num">Promedio por ticket</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timeByUser as $r): ?>
                        <tr>
                            <td><a href="<?= site_url('panel/tickets?' . http_build_query(['assigned' => $r['id']])) ?>"><?= esc($r['name']) ?></a></td>
                            <td class="num"><?= $r['tickets'] ?></td>
                            <td class="num"><?= $r['closed'] ?></td>
                            <td class="num"><?= $r['sessions'] ?></td>
                            <td class="num"><b><?= format_duration($r['seconds']) ?></b></td>
                            <td class="num"><?= format_duration($r['average']) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<section class="card">
    <div class="card-head"><div><h2>Por organización</h2><p class="muted">Clic en una organización para ver sus tickets</p></div></div>
    <?= view('panel/partials/status_table', ['rows' => $byCompany, 'label' => 'Organización', 'filterKey' => 'company']) ?>
</section>

<section class="card">
    <div class="card-head"><div><h2>Por oficina</h2><p class="muted">Sede o sucursal del ticket</p></div></div>
    <?= view('panel/partials/status_table', ['rows' => $byBranch, 'label' => 'Oficina', 'filterKey' => 'branch']) ?>
</section>

<section class="card">
    <div class="card-head"><div><h2>Por área</h2><p class="muted">Área indicada por el solicitante en el formulario</p></div></div>
    <?= view('panel/partials/status_table', ['rows' => $byArea, 'label' => 'Área', 'filterKey' => '', 'emptyName' => 'Sin área']) ?>
</section>

<section class="card">
    <div class="card-head"><div><h2>Por categoría</h2><p class="muted">Clic en una categoría para ver sus tickets</p></div></div>
    <?= view('panel/partials/status_table', ['rows' => $byCategoryMatrix, 'label' => 'Categoría', 'filterKey' => 'category', 'emptyName' => 'Sin categoría']) ?>
</section>

<section class="card">
    <div class="card-head"><div><h2>Por subcategoría</h2><p class="muted">Clic en una subcategoría para ver sus tickets</p></div></div>
    <?= view('panel/partials/status_table', ['rows' => $bySubCategoryMatrix, 'label' => 'Subcategoría', 'filterKey' => 'subcategory', 'emptyName' => 'Sin subcategoría']) ?>
</section>

<section class="card">
    <div class="card-head"><div><h2>Carga por agente</h2><p class="muted">Tickets asignados a cada usuario</p></div></div>
    <?= view('panel/partials/status_table', ['rows' => $byAgent, 'label' => 'Agente', 'filterKey' => 'assigned', 'emptyName' => 'Sin asignar']) ?>
</section>
<?= $this->endSection() ?>
