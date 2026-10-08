<?php helper(['url', 'form', 'panel']); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva contraseña · Mesa de Ayuda</title>
    <script>
        try {
            const theme = localStorage.getItem('panel-theme');
            if (theme) document.documentElement.dataset.theme = theme;
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="<?= base_url('assets/panel/panel.css') ?>">
</head>
<body class="login-page">
    <form class="login-card" method="post" action="<?= $invalid ? site_url('recuperar') : site_url('restablecer/' . $token) ?>">
        <?= csrf_field() ?>

        <div class="brand">
            <span class="brand-mark"><?= icon('chat') ?></span>
            <span class="brand-name">Mesa de Ayuda <b>TI</b></span>
        </div>

        <?php if ($invalid): ?>
            <h1>Enlace no válido</h1>
            <div class="flash flash-error"><?= icon('alert') ?> El enlace venció o ya se usó. Solicita uno nuevo.</div>
            <a href="<?= site_url('recuperar') ?>" class="btn btn-primary btn-block">Solicitar otro enlace</a>
        <?php else: ?>
            <h1>Nueva contraseña</h1>
            <p class="muted">Elige una contraseña de al menos 8 caracteres.</p>

            <?php if (! empty($error)): ?>
                <div class="flash flash-error"><?= icon('alert') ?> <?= esc($error) ?></div>
            <?php endif ?>

            <label class="field">
                <span class="field-label">Contraseña nueva</span>
                <input class="input" type="password" name="password" minlength="8" required autofocus autocomplete="new-password">
            </label>

            <label class="field">
                <span class="field-label">Repite la contraseña</span>
                <input class="input" type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
            </label>

            <button type="submit" class="btn btn-primary btn-block">Guardar contraseña</button>
        <?php endif ?>
    </form>
</body>
</html>
