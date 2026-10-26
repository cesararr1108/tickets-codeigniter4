<?php

namespace App\Controllers\Api;

use App\Libraries\PushApiClient;

/**
 * Registro de tokens FCM desde el anfitrión del widget (o cualquier cliente).
 * Se reenvían a la API de notificaciones; la API key nunca llega al navegador.
 *
 * POST /api/fcm-tokens
 *   Body JSON o form: { Email, Token, Companies, Name?, Navigator?, branches?, Rol?, User? }
 *   Se registra con la API key de la compañía (Companies); sin llave propia se usa la del .env.
 *   Crea el token o, si ya existe, lo actualiza (un token pertenece a un solo correo).
 *
 * POST /api/fcm-tokens/delete
 *   Body: { Token, Companies }   Da de baja el token (por ejemplo al cerrar sesión).
 */
class FcmTokenController extends BaseApiController
{
    public function create()
    {
        $payload = $this->request->getPost() ?: $this->getPayload();

        $email = trim((string) ($payload['Email'] ?? $payload['email'] ?? ''));
        $token = trim((string) ($payload['Token'] ?? $payload['token'] ?? ''));

        $errors = [];

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors['Email'] = 'Indica un correo válido (máx. 190 caracteres).';
        }

        if ($token === '' || strlen($token) > 4096 || ! preg_match('/^[A-Za-z0-9_:\-]+$/', $token)) {
            $errors['Token'] = 'Indica un token FCM válido.';
        }

        if ($errors !== []) {
            return $this->failValidationErrors($errors);
        }

        $company = trim((string) ($payload['Companies'] ?? $payload['companies'] ?? ''));
        $push    = PushApiClient::forCompany($company);

        if (! $push->enabled()) {
            return $this->fail('Las notificaciones no están configuradas para la compañía ' . $company . '.', 503);
        }

        $rol = PushApiClient::rol($payload['Rol'] ?? $payload['rol'] ?? null);

        // El rol del panel queda reservado para los navegadores del panel.
        if ($rol === config(\Config\PushApi::class)->rolPanel) {
            $rol = null;
        }

        $result = $push->registrarToken([
            'token'     => $token,
            'email'     => $email,
            'nombre'    => $payload['Name'] ?? $payload['name'] ?? null,
            'oficina'   => $payload['branches'] ?? $payload['Branches'] ?? null,
            'rol'       => $rol,
            'usuario'   => $payload['User'] ?? $payload['user'] ?? null,
            'navegador' => $payload['Navigator'] ?? $payload['navigator'] ?? $this->request->getUserAgent()->getBrowser(),
        ]);

        if (! $result['ok']) {
            log_message('error', '[API fcm-tokens] ' . $result['error']);

            return $this->fail('No fue posible registrar el token.', 502);
        }

        return $this->respondCreated(['registered' => true]);
    }

    public function remove()
    {
        $payload = $this->request->getPost() ?: $this->getPayload();
        $token   = trim((string) ($payload['Token'] ?? $payload['token'] ?? ''));

        if ($token === '') {
            return $this->failValidationErrors(['Token' => 'Indica el token FCM.']);
        }

        $result = PushApiClient::forCompany((string) ($payload['Companies'] ?? $payload['companies'] ?? ''))->eliminarToken($token);

        if (! $result['ok']) {
            log_message('error', '[API fcm-tokens] ' . $result['error']);

            return $this->fail('No fue posible dar de baja el token.', 502);
        }

        return $this->respond(['removed' => true]);
    }
}
