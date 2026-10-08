<?php helper(['url', 'form', 'panel']); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña · Mesa de Ayuda</title>
    <script>
        try {
            const theme = localStorage.getItem('panel-theme');
            if (theme) document.documentElement.dataset.theme = theme;
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="<?= base_url('assets/panel/panel.css') ?>">
</head>
<body class="login-page">
    <form class="login-card" method="post" action="<?= site_url('recuperar') ?>">
        <?= csrf_field() ?>

        <div class="brand">
            <span class="brand-mark"><?= icon('chat') ?></span>
            <span class="brand-name">Mesa de Ayuda <b>TI</b></span>
        </div>

        <h1>Recuperar contraseña</h1>

        <?php if (! empty($sent)): ?>
            <div class="flash flash-success"><?= icon('check') ?> Si el correo está registrado, te enviamos un enlace para crear una contraseña nueva. Revisa tu bandeja de entrada (y el spam). El enlace vale 60 minutos.</div>
            <a href="<?= site_url('login') ?>" class="btn btn-outline btn-block">Volver a iniciar sesión</a>
        <?php else: ?>
            <p class="muted">Escribe el correo de tu usuario y te enviaremos un enlace para crear una contraseña nueva.</p>

            <?php if (! empty($error)): ?>
                <div class="flash flash-error"><?= icon('alert') ?> <?= esc($error) ?></div>
            <?php endif ?>

            <label class="field">
                <span class="field-label">Correo</span>
                <input class="input" type="email" name="email" required autofocus autocomplete="username">
            </label>

            <button type="submit" class="btn btn-primary btn-block">Enviar enlace</button>
            <p style="text-align:center;margin-top:16px"><a href="<?= site_url('login') ?>">Volver a iniciar sesión</a></p>
        <?php endif ?>
    </form>
</body>
</html>
