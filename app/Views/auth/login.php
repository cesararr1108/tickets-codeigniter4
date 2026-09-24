<?php helper(['url', 'form', 'panel']); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Mesa de Ayuda</title>
    <script>
        try {
            const theme = localStorage.getItem('panel-theme');
            if (theme) document.documentElement.dataset.theme = theme;
        } catch (e) {}
    </script>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('favicon.svg') ?>">
    <link rel="alternate icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('panel/panel.css') ?>">
</head>
<body class="login-page">
    <form class="login-card" method="post" action="<?= site_url('login') ?>">
        <?= csrf_field() ?>

        <div class="brand">
            <span class="brand-mark"><?= brand_mark() ?></span>
            <span class="brand-name">Mesa de Ayuda <b>TI</b></span>
        </div>

        <h1>Iniciar sesión</h1>
        <p class="muted">Ingresa con tu usuario del sistema de tickets.</p>

        <?php if (! empty($error)): ?>
            <div class="flash flash-error"><?= icon('alert') ?> <?= esc($error) ?></div>
        <?php endif ?>

        <label class="field">
            <span class="field-label">Correo</span>
            <input class="input" type="email" name="email" value="<?= esc($email ?? '') ?>" required autofocus autocomplete="username">
        </label>

        <label class="field">
            <span class="field-label">Contraseña</span>
            <input class="input" type="password" name="password" required autocomplete="current-password">
        </label>

        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
</body>
</html>
