<?php

namespace App\Controllers\Panel;

use App\Libraries\FcmPush;
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

        // La sucursal no va en la sesión: se lee del usuario.
        $account = db_connect()->table('Users')->select('CodBranches, CodCompanies')->where('IdUser', $this->user['id'])->get()->getRowArray();

        model(FcmTokenModel::class)->register([
            'Email'     => $this->user['email'],
            'Token'     => $token,
            'Navigator' => $this->request->getUserAgent()->getBrowser(),
            'Companies' => $account['CodCompanies'] ?? $this->user['company'] ?? null,
            'branches'  => $account['CodBranches'] ?? null,
            'Rol'       => $this->user['role'] ?? 'panel',
        ]);

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /**
     * POST /panel/push/test
     * Envía una notificación de prueba al usuario con sesión.
     */
    public function test()
    {
        $push = new FcmPush();

        if (! $push->enabled()) {
            return redirect()->back()->with('error', 'El envío no está configurado: falta fcm.credentialsPath en .env.');
        }

        $tokens = model(FcmTokenModel::class)->tokensForEmails([(string) $this->user['email']]);

        if ($tokens === []) {
            return redirect()->back()->with('error', 'Este navegador aún no tiene notificaciones activadas. Pulsa "Activar notificaciones" primero.');
        }

        $push->sendToEmails(
            [(string) $this->user['email']],
            'Notificación de prueba',
            'Si ves esto, las notificaciones del panel funcionan.',
            ['type' => 'test'],
            site_url('panel'),
        );

        return redirect()->back()->with('success', 'Prueba enviada a ' . count($tokens) . ' dispositivo(s).');
    }
}
