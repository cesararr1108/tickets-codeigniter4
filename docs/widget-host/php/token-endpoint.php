<?php
/**
 * Endpoint opcional que renueva el token: <script data-token-url="/tickets-token.php">
 *
 * El widget lo llama solo cuando el token está por vencer o ya venció.
 * Como responde con los datos de la SESIÓN del usuario, solo lo puede
 * obtener alguien que haya iniciado sesión en tu sitio.
 */

require __DIR__ . '/widget_token.php';

session_start();

// Adapta esto a cómo tu sistema guarda al usuario autenticado.
$usuario = $_SESSION['usuario'] ?? null;

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (! $usuario) {
    http_response_code(401);
    echo json_encode(['message' => 'No autenticado']);
    exit;
}

echo json_encode([
    'token' => widget_token([
        'email'   => $usuario['email'],
        'name'    => $usuario['nombre'],
        'phone'   => $usuario['telefono'] ?? null,
        'company' => $usuario['cod_compania'],   // CodCompanies de tu base de tickets
        'branch'  => $usuario['cod_sucursal'],   // CodBranches
    ], 600),
]);
