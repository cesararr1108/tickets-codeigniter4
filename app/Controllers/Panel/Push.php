<?php

namespace App\Controllers\Panel;

use App\Libraries\PushApiClient;

/**
 * POST /panel/push/token
 * Registra el token FCM del navegador del usuario con sesión iniciada en la
 * API de notificaciones (con el rol del panel, pushapi.rolPanel).
 */
class Push extends BasePanelController
{
    public function register()
    {
        $token = trim((string) $this->request->getPost('token'));

        if ($token === '' || strlen($token) > 4096 || $this->user === null) {
            return $this->response->setStatusCode(422)->setJSON(['message' => 'Token inválido.', 'csrf' => csrf_hash()]);
        }

        // La sucursal no va en la sesión: se lee del usuario.
        $account = db_connect()->table('Users')->select('CodBranches')->where('IdUser', $this->user['id'])->get()->getRowArray();

        $result = (new PushApiClient())->registrarToken([
            'token'     => $token,
            'email'     => (string) $this->user['email'],
            'nombre'    => (string) ($this->user['name'] ?? ''),
            'oficina'   => $account['CodBranches'] ?? null,
            'rol'       => config(\Config\PushApi::class)->rolPanel,
            'navegador' => 'Panel ' . $this->request->getUserAgent()->getBrowser(),
        ]);

        if (! $result['ok']) {
            log_message('warning', '[PushApi] Registro de token del panel: ' . $result['error']);

            return $this->response->setStatusCode(502)->setJSON(['message' => 'No se pudo registrar el navegador en la API de notificaciones.', 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /**
     * POST /panel/push/test
     * Revisa la conexión con la API y envía una notificación de prueba al usuario con sesión.
     * Deja un informe (flashdata "pushReport") que muestra Panel > Notificaciones.
     */
    public function test()
    {
        $push   = new PushApiClient();
        $email  = (string) $this->user['email'];
        $report = ['steps' => $push->diagnose(), 'email' => $email];

        $configured = array_reduce($report['steps'], static fn ($ok, $step) => $ok && $step['ok'], true);

        if ($configured) {
            $report['result'] = $push->sendToEmails(
                [$email],
                'Notificación de prueba',
                'Si ves esto, las notificaciones del panel funcionan.',
                ['type' => 'test'],
                site_url('panel'),
            );
        }

        return redirect()->back()->with('pushReport', $report);
    }
}
