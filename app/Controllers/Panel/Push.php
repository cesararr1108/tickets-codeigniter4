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
            'Navigator' => FcmTokenModel::PANEL_TAG . ' ' . $this->request->getUserAgent()->getBrowser(),
            'Companies' => $account['CodCompanies'] ?? $this->user['company'] ?? null,
            'branches'  => $account['CodBranches'] ?? null,
            'Rol'       => $this->user['role'] ?? 'panel',
        ]);

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /**
     * POST /panel/push/test
     * Revisa la configuración y envía una notificación de prueba al usuario con sesión.
     * Deja un informe (flashdata "pushReport") que muestra Panel > Notificaciones.
     */
    public function test()
    {
        $push   = new FcmPush();
        $debug  = (bool) config(\Config\Fcm::class)->debug;
        $email  = (string) $this->user['email'];
        $report = ['steps' => $push->diagnose(), 'results' => [], 'debug' => $debug, 'email' => $email];

        $configured = array_reduce($report['steps'], static fn ($ok, $step) => $ok && $step['ok'], true);

        if ($configured) {
            $report['devices'] = model(FcmTokenModel::class)->rowsForEmails([$email], true);

            if ($report['devices'] !== []) {
                $report['results'] = $push->sendToEmails(
                    [$email],
                    'Notificación de prueba',
                    'Si ves esto, las notificaciones del panel funcionan.',
                    ['type' => 'test'],
                    site_url('panel'),
                    true,
                );
            }
        }

        return redirect()->back()->with('pushReport', $report);
    }
}
