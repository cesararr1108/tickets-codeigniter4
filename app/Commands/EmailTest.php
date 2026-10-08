<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Muestra qué configuración de correo lee la aplicación desde el .env y envía un mensaje de prueba.
 *
 *   php spark email:test destino@correo.com
 */
class EmailTest extends BaseCommand
{
    protected $group       = 'Tickets';
    protected $name        = 'email:test';
    protected $description = 'Revisa la configuración de correo saliente y envía un mensaje de prueba.';
    protected $usage       = 'email:test <destino>';
    protected $arguments   = [
        'destino' => 'Correo al que se envía la prueba.',
    ];

    public function run(array $params)
    {
        $c = config(\Config\Email::class);

        CLI::write('Configuración que lee la aplicación (.env):', 'yellow');
        CLI::write('  fromEmail  : ' . ($c->fromEmail !== '' ? $c->fromEmail : '(VACÍO)'));
        CLI::write('  fromName   : ' . ($c->fromName !== '' ? $c->fromName : '(vacío)'));
        CLI::write('  protocol   : ' . $c->protocol);
        CLI::write('  SMTPHost   : ' . ($c->SMTPHost !== '' ? $c->SMTPHost : '(VACÍO)'));
        CLI::write('  SMTPUser   : ' . ($c->SMTPUser !== '' ? $c->SMTPUser : '(VACÍO)'));
        CLI::write('  SMTPPass   : ' . ($c->SMTPPass !== '' ? str_repeat('*', min(8, strlen($c->SMTPPass))) . ' (' . strlen($c->SMTPPass) . ' caracteres)' : '(VACÍA)'));
        CLI::write('  SMTPPort   : ' . $c->SMTPPort);
        CLI::write('  SMTPCrypto : ' . $c->SMTPCrypto);
        CLI::newLine();

        if ($c->fromEmail === '') {
            CLI::error('email.fromEmail llega vacío: el .env que lee PHP no tiene esa línea (o está comentada con #, o es otro archivo).');
            CLI::write('Archivo .env esperado: ' . ROOTPATH . '.env');
            CLI::write('¿Existe? ' . (is_file(ROOTPATH . '.env') ? 'sí' : 'NO'));

            return EXIT_ERROR;
        }

        $to = $params[0] ?? CLI::prompt('Correo destino de la prueba', null, 'required|valid_email');

        $email = service('email');
        $email->setFrom($c->fromEmail, $c->fromName !== '' ? $c->fromName : 'Mesa de Ayuda');
        $email->setTo($to);
        $email->setSubject('Prueba de correo · Mesa de Ayuda');
        $email->setMessage("Si recibes este mensaje, el correo saliente está bien configurado.\n");

        if ($email->send(false)) {
            CLI::write('Enviado a ' . $to . '. Revisa la bandeja (y el spam).', 'green');

            return EXIT_SUCCESS;
        }

        CLI::error('No se pudo enviar. Detalle del servidor SMTP:');
        CLI::write($email->printDebugger(['headers']));

        return EXIT_ERROR;
    }
}
