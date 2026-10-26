<?php

namespace App\Libraries;

use Config\PushApi as PushApiConfig;

/**
 * Cliente de la API de notificaciones push (notificaciones-push-roma).
 *
 *   POST   /tokens          registrar el token de un navegador
 *   DELETE /tokens          dar de baja un token
 *   POST   /notificaciones  enviar a correos y/o roles
 *
 * Nunca lanza excepciones: un fallo de push no debe romper el flujo del ticket.
 */
class PushApiClient
{
    private PushApiConfig $config;

    public function __construct(?PushApiConfig $config = null)
    {
        $this->config = $config ?? config(PushApiConfig::class);
    }

    public function enabled(): bool
    {
        return $this->problem() === null;
    }

    /**
     * Motivo por el que el envío no está disponible (null si todo está bien).
     */
    public function problem(): ?string
    {
        if (trim($this->config->baseURL) === '') {
            return 'Falta pushapi.baseURL en el .env (URL de la API de notificaciones, terminada en /api/v1).';
        }

        if (trim($this->config->apiKey) === '') {
            return 'Falta pushapi.apiKey en el .env (llave creada con php spark apikey:create en la API de notificaciones).';
        }

        return null;
    }

    /**
     * Registra (o actualiza) el token de un navegador.
     *
     * @param array{token: string, email: string, nombre?: string|null, oficina?: string|null, rol?: string|null, usuario?: string|null, navegador?: string|null} $data
     *
     * @return array{ok: bool, status: int, error: string}
     */
    public function registrarToken(array $data): array
    {
        $email = strtolower(trim($data['email']));
        $name  = trim(strip_tags((string) ($data['nombre'] ?? '')));

        $body = [
            'oficina'        => $this->oficina($data['oficina'] ?? null),
            'nombre_usuario' => mb_substr($name !== '' ? $name : strstr($email, '@', true), 0, 150),
            'email'          => $email,
            'rol'            => self::rol($data['rol'] ?? null) ?? $this->config->rolWidget,
            'token'          => trim($data['token']),
            'navegador'      => self::clip($data['navegador'] ?? null, 100),
            'dispositivo'    => 'web',
        ];

        $usuario = trim((string) ($data['usuario'] ?? ''));

        if ($usuario !== '' && preg_match('/^[A-Za-z0-9._@\-]{1,190}$/', $usuario)) {
            $body['usuario'] = $usuario;
        }

        return $this->summary($this->request('POST', 'tokens', array_filter($body, static fn ($v) => $v !== null)));
    }

    /**
     * Da de baja un token (cierre de sesión o el usuario lo quitó). Un 404 cuenta como hecho.
     *
     * @return array{ok: bool, status: int, error: string}
     */
    public function eliminarToken(string $token): array
    {
        $result = $this->summary($this->request('DELETE', 'tokens', ['token' => trim($token)]));

        return $result['status'] === 404 ? ['ok' => true, 'status' => 404, 'error' => ''] : $result;
    }

    /**
     * Envía una notificación a todos los dispositivos de esos correos.
     *
     * @param list<string>          $emails
     * @param array<string, scalar> $data
     *
     * @return array<string, mixed>|null Resultado de la API (total, exitosos, fallidos…) o null si falló
     */
    public function sendToEmails(array $emails, string $title, string $body, array $data = [], ?string $link = null): ?array
    {
        $emails = array_values(array_unique(array_filter(
            array_map(static fn ($e) => strtolower(trim((string) $e)), $emails),
            static fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false,
        )));

        return $emails === [] ? null : $this->send(['emails' => $emails], $title, $body, $data, $link);
    }

    /**
     * Envía a todos los navegadores registrados desde el panel.
     *
     * @param array<string, scalar> $data
     *
     * @return array<string, mixed>|null
     */
    public function sendToPanel(string $title, string $body, array $data = [], ?string $link = null): ?array
    {
        return $this->send(['roles' => [$this->config->rolPanel]], $title, $body, $data, $link);
    }

    /**
     * @param array<string, mixed>  $destinatarios
     * @param array<string, scalar> $data
     *
     * @return array<string, mixed>|null
     */
    public function send(array $destinatarios, string $title, string $body, array $data = [], ?string $link = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $data = array_map(static fn ($v) => (string) $v, $data);

        $notificacion = [
            'titulo' => mb_substr($title, 0, 150),
            'cuerpo' => mb_substr($body, 0, 1000),
        ];

        if ($link !== null && $link !== '') {
            // El service worker abre data.url al tocar la notificación; la API solo acepta https en notificacion.url.
            $data['url'] = $link;

            if (str_starts_with($link, 'https://')) {
                $notificacion['url'] = $link;
            }
        }

        $payload = [
            'destinatarios' => $destinatarios,
            'notificacion'  => $notificacion,
            'data'          => $data === [] ? new \stdClass() : $data,
            'prioridad'     => 'high',
            'asincrono'     => $this->config->asincrono,
        ];

        $response = $this->request('POST', 'notificaciones', $payload);

        if ($response['status'] !== 200 && $response['status'] !== 202) {
            log_message('warning', '[PushApi] Envío "' . $title . '" falló: ' . $this->summary($response)['error']);

            return null;
        }

        return is_array($response['json']['data'] ?? null) ? $response['json']['data'] : [];
    }

    /**
     * Revisa paso a paso la conexión con la API (sin exponer la llave).
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

        if (! $add('Configuración (.env)', $problem === null, $problem ?? rtrim($this->config->baseURL, '/') . ' · llave ' . (strstr(trim($this->config->apiKey), '.', true) ?: substr(trim($this->config->apiKey), 0, 6)) . '.…')) {
            return $steps;
        }

        $health = $this->request('GET', 'health', null, false);

        if (! $add('API disponible (GET /health)', $health['status'] === 200, $health['status'] === 200 ? 'en línea' : $this->summary($health)['error'])) {
            return $steps;
        }

        // Un id que no existe: 404 = la llave es válida y tiene el permiso "notificaciones".
        $probe = $this->request('GET', 'notificaciones/00000000-0000-0000-0000-000000000000');
        $ok    = $probe['status'] === 404;

        $add('API key (permiso "notificaciones")', $ok, $ok ? 'válida' : $this->summary($probe)['error']);

        return $steps;
    }

    /**
     * Convierte el código de sede (varchar(5)) en una oficina de la API ([A-Z0-9]{4}).
     */
    public function oficina(?string $branch): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $branch) ?? '');

        if ($code === '') {
            return $this->config->oficinaDefault;
        }

        return strlen($code) > 4 ? substr($code, -4) : str_pad($code, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Rol en el formato de la API ([A-Z0-9_-], máx. 30). null si queda vacío.
     */
    public static function rol(?string $role): ?string
    {
        $role = (string) $role;

        if (function_exists('iconv')) {
            $role = (string) @iconv('UTF-8', 'ASCII//TRANSLIT', $role);
        }

        $role = trim((string) preg_replace('/[^A-Z0-9_\-]+/', '_', strtoupper(trim($role))), '_');

        return $role === '' ? null : substr($role, 0, 30);
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array{status: int, json: array<string, mixed>|null, raw: string}
     */
    private function request(string $method, string $path, ?array $body = null, bool $auth = true): array
    {
        if ($this->problem() !== null) {
            return ['status' => 0, 'json' => null, 'raw' => (string) $this->problem()];
        }

        if ($body !== null && $this->config->organizacion !== '' && $path !== 'health') {
            $body['organizacion'] = strtoupper($this->config->organizacion);
        }

        $headers = ['Accept' => 'application/json'];

        if ($auth) {
            $headers['Authorization'] = 'Bearer ' . trim($this->config->apiKey);
        }

        $options = ['headers' => $headers];

        if ($body !== null) {
            $options['headers']['Content-Type'] = 'application/json';
            $options['body']                    = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        try {
            // Instancia nueva por llamada: la compartida arrastra cabeceras y cuerpo de la anterior.
            $response = \Config\Services::curlrequest(['timeout' => $this->config->timeout, 'http_errors' => false], null, null, false)
                ->request($method, rtrim($this->config->baseURL, '/') . '/' . $path, $options);
        } catch (\Throwable $e) {
            log_message('error', '[PushApi] ' . $method . ' ' . $path . ': ' . $e->getMessage());

            return ['status' => 0, 'json' => null, 'raw' => 'No se pudo conectar con la API: ' . $e->getMessage()];
        }

        $raw  = (string) $response->getBody();
        $json = json_decode($raw, true);

        return ['status' => $response->getStatusCode(), 'json' => is_array($json) ? $json : null, 'raw' => $raw];
    }

    /**
     * @param array{status: int, json: array<string, mixed>|null, raw: string} $response
     *
     * @return array{ok: bool, status: int, error: string}
     */
    private function summary(array $response): array
    {
        $status = $response['status'];

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'status' => $status, 'error' => ''];
        }

        $json  = $response['json'] ?? [];
        $error = is_array($json['messages'] ?? null)
            ? implode(' ', array_map('strval', $json['messages']))
            : (string) ($json['error'] ?? $json['message'] ?? $json['messages'] ?? '');

        if ($error === '') {
            $error = $status === 0 ? $response['raw'] : 'HTTP ' . $status . ': ' . mb_substr(strip_tags($response['raw']), 0, 200);
        } else {
            $error = 'HTTP ' . $status . ': ' . $error;
        }

        return ['ok' => false, 'status' => $status, 'error' => $error];
    }

    private static function clip(mixed $value, int $max): ?string
    {
        $value = trim(strip_tags((string) ($value ?? '')));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
