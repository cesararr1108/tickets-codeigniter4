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
     * Envía a todos los tokens de los correos indicados.
     *
     * @param list<string>          $emails
     * @param array<string, scalar> $data   Datos extra (todos se envían como string)
     */
    public function sendToEmails(array $emails, string $title, string $body, array $data = [], ?string $link = null): void
    {
        try {
            if (! $this->enabled()) {
                return;
            }

            $tokens = model(FcmTokenModel::class)->tokensForEmails($emails);

            foreach ($tokens as $token) {
                $this->sendToToken($token, $title, $body, $data, $link);
            }
        } catch (\Throwable $e) {
            log_message('error', '[FCM] ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, scalar> $data
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [], ?string $link = null): bool
    {
        $accessToken = $this->accessToken();

        if ($accessToken === null) {
            return false;
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

        $response = $this->http()->post(
            'https://fcm.googleapis.com/v1/projects/' . $this->credentials()['project_id'] . '/messages:send',
            [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
                'body'    => json_encode(['message' => $message], JSON_UNESCAPED_UNICODE),
            ],
        );

        $status = $response->getStatusCode();

        if ($status === 200) {
            return true;
        }

        // Token caducado o inválido: se elimina para no reintentarlo.
        if ($status === 404 || str_contains($response->getBody(), 'UNREGISTERED')) {
            model(FcmTokenModel::class)->forget($token);
        }

        log_message('warning', '[FCM] HTTP ' . $status . ': ' . substr($response->getBody(), 0, 300));

        return false;
    }

    private function accessToken(): ?string
    {
        $cached = cache(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->credentials();

        if ($credentials === null) {
            return null;
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

            return null;
        }

        $response = $this->http()->post($claims['aud'], [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $unsigned . '.' . $this->b64($signature),
            ],
        ]);

        $json = json_decode($response->getBody(), true);

        if (empty($json['access_token'])) {
            log_message('error', '[FCM] OAuth falló: ' . substr($response->getBody(), 0, 300));

            return null;
        }

        cache()->save(self::CACHE_KEY, $json['access_token'], max(60, (int) ($json['expires_in'] ?? 3600) - 120));

        return $json['access_token'];
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
