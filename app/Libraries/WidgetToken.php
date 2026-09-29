<?php

namespace App\Libraries;

use Config\Widget;

/**
 * Token firmado (JWT HS256) que la página anfitriona entrega al widget.
 *
 * Claims:
 *   exp      (obligatorio) expiración, timestamp Unix
 *   iat      (opcional)    emisión
 *   email    (opcional)    correo verificado del solicitante
 *   name     (opcional)    nombre
 *   phone    (opcional)    teléfono
 *   company  (opcional)    CodCompanies fijo
 *   branch   (opcional)    CodBranches fijo
 *
 * Lo que venga en el token manda: el navegador no puede cambiarlo.
 */
class WidgetToken
{
    /**
     * Claims del token validado en la petición actual.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $current = null;

    /**
     * @return array<string, mixed>
     */
    public static function claims(): array
    {
        return self::$current ?? [];
    }

    public static function setCurrent(?array $claims): void
    {
        self::$current = $claims;
    }

    public static function isConfigured(): bool
    {
        return strlen(config(Widget::class)->secret) >= 32;
    }

    /**
     * Genera un token. Se usa en pruebas y en el comando spark widget:token.
     *
     * @param array<string, mixed> $claims
     */
    public static function sign(array $claims, int $ttl = 900): string
    {
        $now    = time();
        $claims = array_filter($claims, static fn ($v) => $v !== null && $v !== '') + ['iat' => $now, 'exp' => $now + $ttl];

        $header  = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $payload = self::b64(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return "{$header}.{$payload}." . self::b64(self::mac("{$header}.{$payload}"));
    }

    /**
     * Valida firma, algoritmo y vigencia.
     *
     * @return array{claims: ?array<string, mixed>, error: ?string}
     */
    public static function verify(string $token): array
    {
        $parts = explode('.', trim($token));

        if (count($parts) !== 3) {
            return ['claims' => null, 'error' => 'Token con formato inválido.'];
        }

        [$h, $p, $s] = $parts;

        $header = json_decode((string) self::unb64($h), true);

        // Solo HS256: evita ataques con alg=none o cambio de algoritmo.
        if (! is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return ['claims' => null, 'error' => 'Algoritmo de token no permitido.'];
        }

        if (! hash_equals(self::mac("{$h}.{$p}"), (string) self::unb64($s))) {
            return ['claims' => null, 'error' => 'Firma de token inválida.'];
        }

        $claims = json_decode((string) self::unb64($p), true);

        if (! is_array($claims) || ! isset($claims['exp']) || ! is_numeric($claims['exp'])) {
            return ['claims' => null, 'error' => 'Token sin expiración.'];
        }

        $now = time();

        if ($claims['exp'] < $now) {
            return ['claims' => null, 'error' => 'El token expiró.'];
        }

        if ($claims['exp'] - $now > config(Widget::class)->maxTokenTtl + 60) {
            return ['claims' => null, 'error' => 'El token dura más de lo permitido.'];
        }

        if (isset($claims['iat']) && is_numeric($claims['iat']) && $claims['iat'] > $now + 120) {
            return ['claims' => null, 'error' => 'Token emitido en el futuro (revisa la hora del servidor).'];
        }

        $clean = [];

        foreach (['email', 'name', 'phone', 'company', 'branch'] as $key) {
            if (isset($claims[$key]) && is_scalar($claims[$key]) && trim((string) $claims[$key]) !== '') {
                $clean[$key] = trim((string) $claims[$key]);
            }
        }

        return ['claims' => $clean + ['exp' => (int) $claims['exp']], 'error' => null];
    }

    private static function mac(string $data): string
    {
        return hash_hmac('sha256', $data, config(Widget::class)->secret, true);
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $text): string|false
    {
        return base64_decode(strtr($text, '-_', '+/'), true);
    }
}
