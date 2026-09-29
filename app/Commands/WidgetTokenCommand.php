<?php

namespace App\Commands;

use App\Libraries\WidgetToken;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Herramientas del widget:
 *
 *   php spark widget:token secret
 *   php spark widget:token sign -e juan@empresa.com -c 01 -b B02 -n "Juan Pérez" -p 0991234567
 */
class WidgetTokenCommand extends BaseCommand
{
    protected $group       = 'Tickets';
    protected $name        = 'widget:token';
    protected $description = 'Genera un secreto para el widget o un token de prueba firmado.';
    protected $usage       = 'widget:token <secret|sign> [opciones]';
    protected $arguments   = [
        'secret' => 'Imprime un secreto aleatorio para widget.secret del .env.',
        'sign'   => 'Imprime un token firmado con widget.secret (para probar).',
    ];
    protected $options = [
        '-e' => 'Correo del solicitante',
        '-n' => 'Nombre',
        '-p' => 'Teléfono',
        '-c' => 'Compañía (CodCompanies)',
        '-b' => 'Sucursal (CodBranches)',
        '-t' => 'Vigencia en segundos (por defecto 900)',
    ];

    public function run(array $params)
    {
        $action = $params[0] ?? 'secret';

        if ($action === 'secret') {
            CLI::write('Agrega esta línea al .env del servidor:', 'yellow');
            CLI::write('widget.secret = ' . bin2hex(random_bytes(32)));
            CLI::newLine();
            CLI::write('El mismo valor debe usarse en la página anfitriona para firmar los tokens.');

            return EXIT_SUCCESS;
        }

        if ($action !== 'sign') {
            CLI::error('Usa: php spark widget:token secret   |   php spark widget:token sign [opciones]');

            return EXIT_ERROR;
        }

        if (! WidgetToken::isConfigured()) {
            CLI::error('widget.secret no está configurado en el .env (mínimo 32 caracteres). Ejecuta: php spark widget:token secret');

            return EXIT_ERROR;
        }

        $token = WidgetToken::sign([
            'email'   => CLI::getOption('e'),
            'name'    => CLI::getOption('n'),
            'phone'   => CLI::getOption('p'),
            'company' => CLI::getOption('c'),
            'branch'  => CLI::getOption('b'),
        ], (int) (CLI::getOption('t') ?: 900));

        CLI::write($token);

        return EXIT_SUCCESS;
    }
}
