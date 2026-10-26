<?php

namespace App\Commands;

use App\Libraries\PushApiClient;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Revisa la conexión con la API de notificaciones y, opcionalmente, envía
 * una notificación de prueba a todos los dispositivos de un correo.
 *
 *   php spark fcm:test                                 (revisa URL, API y la llave del .env)
 *   php spark fcm:test --company 1000                  (revisa la llave de la compañía 1000)
 *   php spark fcm:test ana@empresa.com --company 1000  (además envía una notificación a ese correo)
 */
class FcmTest extends BaseCommand
{
    protected $group       = 'Tickets';
    protected $name        = 'fcm:test';
    protected $description = 'Revisa la conexión con la API de notificaciones y envía una prueba a un correo.';
    protected $usage       = 'fcm:test [email] [--company <CodCompanies>]';
    protected $arguments   = [
        'email' => 'Correo con dispositivos registrados (opcional).',
    ];
    protected $options = [
        '--company' => 'Compañía cuya API key se usa (por defecto la del .env).',
    ];

    public function run(array $params)
    {
        $company = CLI::getOption('company');
        $push    = is_string($company) && $company !== '' ? PushApiClient::forCompany($company) : new PushApiClient();
        $ok   = true;

        foreach ($push->diagnose() as $step) {
            $line = ($step['ok'] ? '[OK]   ' : '[FALLA] ') . $step['step'] . ': ' . preg_replace('/\s+/', ' ', $step['detail']);
            $step['ok'] ? CLI::write($line, 'green') : CLI::error($line);
            $ok = $ok && $step['ok'];
        }

        if (! $ok) {
            return EXIT_ERROR;
        }

        $email = $params[0] ?? null;

        if ($email === null) {
            CLI::write('Configuración correcta. Para enviar una prueba: php spark fcm:test <correo>', 'yellow');

            return EXIT_SUCCESS;
        }

        $result = $push->sendToEmails([$email], 'Notificación de prueba', 'Enviada con php spark fcm:test', ['type' => 'test']);

        if ($result === null) {
            CLI::error('La API rechazó el envío (detalle en writable/logs).');

            return EXIT_ERROR;
        }

        CLI::write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return EXIT_SUCCESS;
    }
}
