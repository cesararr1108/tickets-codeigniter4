<?php

namespace App\Commands;

use App\Libraries\FcmPush;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Revisa la configuración de FCM y, opcionalmente, envía una notificación
 * de prueba a un token (el mismo que se usa en la consola de Firebase).
 *
 *   php spark fcm:test               (solo revisa credenciales y OAuth)
 *   php spark fcm:test <token>       (además envía una notificación a ese token)
 */
class FcmTest extends BaseCommand
{
    protected $group       = 'Tickets';
    protected $name        = 'fcm:test';
    protected $description = 'Revisa las credenciales de FCM y envía una notificación de prueba a un token.';
    protected $usage       = 'fcm:test [token]';
    protected $arguments   = [
        'token' => 'Token FCM del navegador (opcional).',
    ];

    public function run(array $params)
    {
        $push = new FcmPush();
        $ok   = true;

        foreach ($push->diagnose() as $step) {
            $line = ($step['ok'] ? '[OK]   ' : '[FALLA] ') . $step['step'] . ': ' . preg_replace('/\s+/', ' ', $step['detail']);
            $step['ok'] ? CLI::write($line, 'green') : CLI::error($line);
            $ok = $ok && $step['ok'];
        }

        if (! $ok) {
            return EXIT_ERROR;
        }

        $token = $params[0] ?? null;

        if ($token === null) {
            CLI::write('Configuración correcta. Para enviar una prueba: php spark fcm:test <token>', 'yellow');

            return EXIT_SUCCESS;
        }

        $result = $push->sendToToken($token, 'Notificación de prueba', 'Enviada con php spark fcm:test', ['type' => 'test'], null);

        CLI::write('HTTP ' . $result['status'] . ' · ' . ($result['ok'] ? 'ENVIADA' : 'FALLÓ'), $result['ok'] ? 'green' : 'red');
        CLI::write($result['response']);

        return $result['ok'] ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
