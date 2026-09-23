<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1>Catálogo de servicios</h1>
        <p class="muted">Categorías y subcategorías disponibles para registrar tickets.</p>
    </div>
</div>

<?php if ($categories === []): ?>
    <div class="card empty-state"><?= icon('grid', 'icon icon-xl') ?><strong>No hay categorías registradas</strong></div>
<?php else: ?>
    <div class="catalog">
        <?php foreach ($categories as $i => $c): ?>
            <article class="card catalog-card">
                <div class="catalog-head">
                    <span class="catalog-icon" style="background: <?= chart_color($i) ?>"><?= esc(initials($c['Category'])) ?></span>
                    <div>
                        <h2><?= esc($c['Category']) ?></h2>
                        <?php if (! empty($c['Description'])): ?><p class="muted"><?= esc($c['Description']) ?></p><?php endif ?>
                    </div>
                </div>

                <?php if ($c['subcategories'] !== []): ?>
                    <div class="chips">
                        <?php foreach ($c['subcategories'] as $s): ?><span class="chip"><?= esc($s['SubCategory']) ?></span><?php endforeach ?>
                    </div>
                <?php else: ?>
                    <p class="muted small">Sin subcategorías.</p>
                <?php endif ?>

                <div class="catalog-foot">
                    <a href="<?= site_url('panel/tickets?status=pendientes&category=' . $c['IdCategory']) ?>"><?= $c['pending'] ?> pendientes</a>
                    <span class="muted"><?= $c['total'] ?> en total</span>
                </div>
            </article>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
