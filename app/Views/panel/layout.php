<?php
/**
 * Layout del panel. Las vistas hijas definen la sección "content".
 *
 * @var array $nav
 * @var array|null $user
 * @var string $title
 */
$navItems = [
    ['key' => 'dashboard',  'url' => 'panel',                           'icon' => 'dashboard', 'label' => 'Inicio'],
    ['key' => 'new',        'url' => 'panel/tickets/nuevo',             'icon' => 'plus',      'label' => 'Nueva solicitud'],
    ['key' => 'tickets',    'url' => 'panel/tickets?status=pendientes', 'icon' => 'list',      'label' => 'Tickets',       'count' => $nav['pending']],
    ['key' => 'mine',       'url' => 'panel/tickets?assigned=me&status=pendientes', 'icon' => 'user', 'label' => 'Mis asignados', 'count' => $nav['mine']],
    ['key' => 'unassigned', 'url' => 'panel/tickets?assigned=none&status=pendientes', 'icon' => 'inbox', 'label' => 'Sin asignar', 'count' => $nav['unassigned'], 'alert' => true],
    ['key' => 'reports',    'url' => 'panel/reportes',                  'icon' => 'chart',     'label' => 'Reportes'],
    ['key' => 'catalog',    'url' => 'panel/catalogo',                  'icon' => 'grid',      'label' => 'Catálogo'],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token-name" content="<?= csrf_token() ?>">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <title><?= esc($title ?? 'Panel') ?> · Mesa de Ayuda</title>
    <script>
        // Aplica el tema guardado antes de pintar para evitar parpadeo.
        try {
            const theme = localStorage.getItem('panel-theme');
            if (theme) document.documentElement.dataset.theme = theme;
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="<?= base_url('panel/panel.css') ?>">
</head>
<body>
<div class="app">

    <header class="topbar">
        <button type="button" class="icon-btn only-mobile" data-toggle-sidebar aria-label="Menú"><?= icon('menu') ?></button>

        <a href="<?= site_url('panel') ?>" class="brand">
            <span class="brand-mark"><?= icon('chat') ?></span>
            <span class="brand-name">Mesa de Ayuda <b>TI</b></span>
        </a>

        <form class="topbar-search" action="<?= site_url('panel/tickets') ?>" method="get" role="search">
            <?= icon('search') ?>
            <input type="search" name="q" placeholder="Buscar por ticket, asunto o solicitante…" value="<?= esc(service('request')->getGet('q') ?? '') ?>">
        </form>

        <div class="topbar-actions">
            <a href="<?= site_url('panel/tickets/nuevo') ?>" class="btn btn-accent hide-mobile"><?= icon('plus') ?> Nueva solicitud</a>

            <div class="user-menu">
                <button type="button" class="avatar" data-toggle-menu aria-label="Cuenta"><?= esc(initials($user['name'] ?? '')) ?></button>
                <div class="menu" hidden>
                    <div class="menu-head">
                        <strong><?= esc($user['name'] ?? '') ?></strong>
                        <span><?= esc($user['email'] ?? '') ?></span>
                        <?php if (! empty($user['role'])): ?><span class="chip"><?= esc($user['role']) ?></span><?php endif ?>
                    </div>
                    <a href="<?= site_url('logout') ?>" class="menu-item"><?= icon('logout') ?> Cerrar sesión</a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <nav>
            <?php foreach ($navItems as $item): ?>
                <a href="<?= site_url($item['url']) ?>" class="nav-item <?= $nav['active'] === $item['key'] ? 'active' : '' ?>">
                    <?= icon($item['icon']) ?>
                    <span class="nav-label"><?= esc($item['label']) ?></span>
                    <?php if (! empty($item['count'])): ?>
                        <span class="nav-count <?= ! empty($item['alert']) ? 'alert' : '' ?>"><?= (int) $item['count'] ?></span>
                    <?php endif ?>
                </a>
            <?php endforeach ?>
        </nav>

        <div class="sidebar-foot">
            <span>Tema</span>
            <button type="button" class="icon-btn" data-toggle-theme aria-label="Cambiar tema">
                <span class="theme-dark"><?= icon('moon') ?></span>
                <span class="theme-light"><?= icon('sun') ?></span>
            </button>
        </div>
    </aside>
    <div class="sidebar-backdrop" data-toggle-sidebar></div>

    <main class="main">
        <?php if ($msg = session()->getFlashdata('success')): ?>
            <div class="flash flash-success" data-flash><?= icon('check') ?> <?= esc($msg) ?></div>
        <?php endif ?>
        <?php if ($msg = session()->getFlashdata('error')): ?>
            <div class="flash flash-error" data-flash><?= icon('alert') ?> <?= esc($msg) ?></div>
        <?php endif ?>

        <?= $this->renderSection('content') ?>
    </main>
</div>

<script src="<?= base_url('panel/panel.js') ?>" defer></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
