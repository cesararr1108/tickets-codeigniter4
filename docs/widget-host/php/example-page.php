<?php
/**
 * Ejemplo de página anfitriona (PHP) con el widget de tickets.
 */

require __DIR__ . '/widget_token.php';

session_start();

// Solo se muestra el widget a usuarios que iniciaron sesión.
$usuario = $_SESSION['usuario'] ?? null;

$token = $usuario ? widget_token([
    'email'   => $usuario['email'],
    'name'    => $usuario['nombre'],
    'phone'   => $usuario['telefono'] ?? null,
    'company' => $usuario['cod_compania'],
    'branch'  => $usuario['cod_sucursal'],
], 600) : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mi sistema</title>
</head>
<body>
    <h1>Mi sistema</h1>

    <?php if ($usuario): ?>
        <div id="tickets-widget"></div>

        <script
            src="https://tu-servidor:8081/widgets/tickets.js"
            data-container="tickets-widget"
            data-token="<?= htmlspecialchars($token, ENT_QUOTES) ?>"
            data-token-url="/token-endpoint.php"
            data-version="1"></script>
    <?php endif ?>
</body>
</html>
