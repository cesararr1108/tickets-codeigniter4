<?php

namespace App\Controllers\Panel;

use App\Models\FcmTokenModel;

/**
 * POST /panel/push/token
 * Registra el token FCM del navegador del usuario con sesión iniciada.
 */
class Push extends BasePanelController
{
    public function register()
    {
        $token = trim((string) $this->request->getPost('token'));

        if ($token === '' || strlen($token) > 255 || $this->user === null) {
            return $this->response->setStatusCode(422)->setJSON(['message' => 'Token inválido.', 'csrf' => csrf_hash()]);
        }

        model(FcmTokenModel::class)->register([
            'Email'     => $this->user['email'],
            'Token'     => $token,
            'Navigator' => $this->request->getUserAgent()->getBrowser(),
            'Companies' => $this->user['company'] ?? null,
            'Rol'       => $this->user['role'] ?? 'panel',
        ]);

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }
}
