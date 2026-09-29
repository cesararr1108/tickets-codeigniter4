<?php
/**
 * Firma el token que el widget necesita (JWT HS256, sin librerías).
 *
 * Va en el SERVIDOR de tu sitio (PHP). El secreto NUNCA debe salir de aquí:
 * ni en HTML, ni en JavaScript, ni en el repositorio público.
 *
 * WIDGET_SECRET debe ser idéntico a `widget.secret` del .env del servidor de tickets.
 */

function widget_b64url(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * @param array $datos email, name, phone, company (CodCompanies), branch (CodBranches)
 * @param int   $vigenciaSegundos entre 60 y 3600 (por defecto 10 min)
 */
function widget_token(array $datos, int $vigenciaSegundos = 600): string
{
    $secret = getenv('WIDGET_SECRET');   // o la forma en que guardes secretos en tu sitio

    if (! $secret || strlen($secret) < 32) {
        throw new RuntimeException('WIDGET_SECRET no está configurado.');
    }

    $ahora   = time();
    $claims  = array_filter($datos, fn ($v) => $v !== null && $v !== '') + [
        'iat' => $ahora,
        'exp' => $ahora + max(60, min(3600, $vigenciaSegundos)),
    ];

    $header  = widget_b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = widget_b64url(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $firma   = widget_b64url(hash_hmac('sha256', "$header.$payload", $secret, true));

    return "$header.$payload.$firma";
}
