<?php

namespace App\Libraries;

use App\Models\FcmTokenModel;
use Config\Fcm as FcmConfig;

/**
 * Envío de notificaciones por FCM HTTP v1 usando la cuenta de servicio
 * (JWT RS256 -> access token OAuth2). Sin dependencias externas.
 * Nunca lanza excepciones: un fallo de push no debe romper el flujo del ticket.
 */
class FcmPush
{
    private const SCOPE     = 'https://www.googleapis.com/auth/firebase.messaging';
    private const CACHE_KEY = 'fcm_access_token';

    private FcmConfig $config;

    /** @var array<string, mixed>|null */
    private ?array $credentials = null;

    public function __construct(?FcmConfig $config = null)
    {
        $this->config = $config ?? config(FcmConfig::class);
    }

    public function enabled(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * Motivo por el que el envío no está disponible (null si todo está bien).
     */
    public function problem(): ?string
    {
        $path = $this->config->credentialsPath;

        if ($path === '') {
            return 'PHP no recibe fcm.credentialsPath. Revisa que la línea esté en el .env de la raíz del proyecto, sin # al inicio, y que CI_ENVIRONMENT no use otro .env.';
        }

        if (! file_exists($path)) {
            return 'No se encuentra el archivo en "' . $path . '" (o PHP no puede entrar a esa carpeta: revisa nombre, permisos de la carpeta y open_basedir).';
        }

        if (! is_readable($path)) {
            return 'El archivo "' . $path . '" existe pero PHP no tiene permiso de lectura (usuario: ' . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . ').';
        }

        if ($this->credentials() === null) {
            return 'El archivo se leyó pero no es un JSON de cuenta de servicio válido (¿se copió completo?).';
        }

        return null;
    }

    /**
     * Envía a todos los tokens de los correos indicados.
     *
     * @param list<string>          $emails
     * @param array<string, scalar> $data   Datos extra (todos se envían como string)
     * @param bool|null             $panel  true = solo navegadores registrados desde el panel
     *
     * @return list<array{email: string, token: string, navigator: string, ok: bool, status: int, response: string}>
     */
    public function sendToEmails(array $emails, string $title, string $body, array $data = [], ?string $link = null, ?bool $panel = null): array
    {
        $results = [];

        try {
            if (! $this->enabled()) {
                return [];
            }

            foreach (model(FcmTokenModel::class)->rowsForEmails($emails, $panel) as $row) {
                $result    = $this->sendToToken($row['Token'], $title, $body, $data, $link);
                $results[] = $result + [
                    'email'     => (string) $row['Email'],
                    'token'     => (string) $row['Token'],
                    'navigator' => (string) ($row['Navigator'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            log_message('error', '[FCM] ' . $e->getMessage());
        }

        if ($this->config->debug) {
            log_message('error', '[FCM debug] "' . $title . '" -> ' . json_encode(
                array_map(static fn ($r) => [$r['email'], $r['navigator'], $r['ok'], $r['status'], $r['token']], $results),
                JSON_UNESCAPED_UNICODE,
            ));
        }

        return $results;
    }

    /**
     * @param array<string, scalar> $data
     *
     * @return array{ok: bool, status: int, response: string}
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [], ?string $link = null): array
    {
        [$accessToken, $status, $raw] = $this->requestAccessToken(true);

        if ($accessToken === null) {
            return ['ok' => false, 'status' => $status, 'response' => 'OAuth: ' . $raw];
        }

        $data = array_map(static fn ($v) => (string) $v, $data);

        if ($link !== null && $link !== '') {
            $data['url'] = $link;
        }

        $message = [
            'token'        => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => $data === [] ? new \stdClass() : $data,
        ];

        if ($link !== null && str_starts_with($link, 'https://')) {
            $message['webpush'] = ['fcm_options' => ['link' => $link]];
        }

        try {
            $response = $this->http()->post(
                'https://fcm.googleapis.com/v1/projects/' . $this->credentials()['project_id'] . '/messages:send',
                [
                    'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
                    'body'    => json_encode(['message' => $message], JSON_UNESCAPED_UNICODE),
                ],
            );
        } catch (\Throwable $e) {
            log_message('error', '[FCM] ' . $e->getMessage());

            return ['ok' => false, 'status' => 0, 'response' => $e->getMessage()];
        }

        $status = $response->getStatusCode();
        $raw    = (string) $response->getBody();

        if ($status === 200) {
            return ['ok' => true, 'status' => 200, 'response' => $raw];
        }

        // Token caducado o inválido: se elimina para no reintentarlo.
        if ($status === 404 || str_contains($raw, 'UNREGISTERED')) {
            model(FcmTokenModel::class)->forget($token);
        }

        log_message('warning', '[FCM] HTTP ' . $status . ': ' . substr($raw, 0, 300));

        return ['ok' => false, 'status' => $status, 'response' => $raw];
    }

    /**
     * Revisa paso a paso la configuración (sin exponer secretos).
     *
     * @return list<array{step: string, ok: bool, detail: string}>
     */
    public function diagnose(): array
    {
        $steps = [];
        $add   = static function (string $step, bool $ok, string $detail) use (&$steps): bool {
            $steps[] = ['step' => $step, 'ok' => $ok, 'detail' => $detail];

            return $ok;
        };

        $problem = $this->problem();

        if (! $add('Archivo de credenciales (.json)', $problem === null, $problem ?? $this->config->credentialsPath)) {
            return $steps;
        }

        $credentials = $this->credentials();

        $add('Proyecto Firebase', true, $credentials['project_id'] . ' · cuenta ' . $credentials['client_email']);

        $key = @openssl_pkey_get_private($credentials['private_key']);

        if (! $add('Llave privada (OpenSSL)', $key !== false, $key !== false ? 'válida' : 'no se pudo leer: ¿el JSON está completo? ' . (string) openssl_error_string())) {
            return $steps;
        }

        $add('Hora del servidor', true, gmdate('Y-m-d H:i:s') . ' UTC (si difiere más de unos minutos de la real, Google rechaza el token)');

        [$token, $status, $raw] = $this->requestAccessToken(false);

        $add(
            'Token OAuth de Google (HTTP ' . $status . ')',
            $token !== null,
            $token !== null ? 'obtenido' : substr($raw, 0, 400),
        );

        return $steps;
    }

    /**
     * @return array{0: string|null, 1: int, 2: string} [access token, HTTP status, cuerpo/mensaje de error]
     */
    private function requestAccessToken(bool $useCache): array
    {
        if ($useCache) {
            $cached = cache(self::CACHE_KEY);

            if (is_string($cached) && $cached !== '') {
                return [$cached, 200, ''];
            }
        }

        $credentials = $this->credentials();

        if ($credentials === null) {
            return [null, 0, 'Sin credenciales.'];
        }

        $now    = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $unsigned = $this->b64(json_encode($header)) . '.' . $this->b64(json_encode($claims));

        if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            log_message('error', '[FCM] No se pudo firmar el JWT (revisa la private_key).');

            return [null, 0, 'No se pudo firmar el JWT (revisa la private_key).'];
        }

        try {
            $response = $this->http()->post($claims['aud'], [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $unsigned . '.' . $this->b64($signature),
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[FCM] OAuth: ' . $e->getMessage());

            return [null, 0, 'No se pudo conectar con Google: ' . $e->getMessage()];
        }

        $raw  = (string) $response->getBody();
        $json = json_decode($raw, true);

        if (empty($json['access_token'])) {
            log_message('error', '[FCM] OAuth falló: ' . substr($raw, 0, 300));

            return [null, $response->getStatusCode(), $raw];
        }

        cache()->save(self::CACHE_KEY, $json['access_token'], max(60, (int) ($json['expires_in'] ?? 3600) - 120));

        return [$json['access_token'], 200, ''];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = $this->config->credentialsPath;

        if ($path === '' || ! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || empty($json['private_key']) || empty($json['client_email']) || empty($json['project_id'])) {
            log_message('error', '[FCM] El JSON de credenciales no es válido.');

            return null;
        }

        return $this->credentials = $json;
    }

    private function http(): \CodeIgniter\HTTP\CURLRequest
    {
        return service('curlrequest', ['timeout' => $this->config->timeout, 'http_errors' => false]);
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
