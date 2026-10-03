<?php

namespace App\Controllers\Api;

use App\Models\FcmTokenModel;

/**
 * Registro de tokens FCM desde el anfitrión del widget (o cualquier cliente).
 *
 * POST /api/fcm-tokens
 *   Body JSON o form: { Email, Token, Navigator?, Companies?, Rol?, branches? }
 *   Crea el token o, si ya existe, lo actualiza (un token pertenece a un solo correo).
 *
 * POST /api/fcm-tokens/delete
 *   Body: { Token }   Da de baja el token (por ejemplo al cerrar sesión).
 */
class FcmTokenController extends BaseApiController
{
    protected string $modelName = FcmTokenModel::class;

    public function create()
    {
        $payload = $this->request->getPost() ?: $this->getPayload();

        $email = trim((string) ($payload['Email'] ?? $payload['email'] ?? ''));
        $token = trim((string) ($payload['Token'] ?? $payload['token'] ?? ''));

        $errors = [];

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
            $errors['Email'] = 'Indica un correo válido (máx. 120 caracteres).';
        }

        if ($token === '' || strlen($token) > 255) {
            $errors['Token'] = 'Indica el token FCM (máx. 255 caracteres).';
        }

        if ($errors !== []) {
            return $this->failValidationErrors($errors);
        }

        try {
            model(FcmTokenModel::class)->register([
                'Email'     => $email,
                'Token'     => $token,
                'Navigator' => $payload['Navigator'] ?? $payload['navigator'] ?? $this->request->getUserAgent()->getBrowser(),
                'Companies' => $payload['Companies'] ?? $payload['companies'] ?? null,
                'Rol'       => $payload['Rol'] ?? $payload['rol'] ?? null,
                'branches'  => $payload['branches'] ?? $payload['Branches'] ?? null,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[API fcm-tokens] ' . $e->getMessage());

            return $this->failServerError('No fue posible registrar el token.');
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

        model(FcmTokenModel::class)->forget($token);

        return $this->respond(['removed' => true]);
    }
}
