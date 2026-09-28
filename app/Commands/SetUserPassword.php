<?php

namespace App\Commands;

use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Asigna la contraseña de un usuario para entrar al panel.
 *
 *   php spark user:password karen@empresa.com
 */
class SetUserPassword extends BaseCommand
{
    protected $group       = 'Tickets';
    protected $name        = 'user:password';
    protected $description = 'Asigna la contraseña (PasswordHash) de un usuario de la tabla Users.';
    protected $usage       = 'user:password <email> [password]';
    protected $arguments   = [
        'email'    => 'Correo del usuario (Users.Email).',
        'password' => 'Nueva contraseña. Si se omite, se pide por consola.',
    ];

    public function run(array $params)
    {
        $email = $params[0] ?? CLI::prompt('Correo del usuario', null, 'required');
        $model = model(UserModel::class);
        $user  = $model->where('Email', $email)->first();

        if ($user === null) {
            CLI::error("No existe un usuario con el correo {$email}.");

            return EXIT_ERROR;
        }

        $password = $params[1] ?? CLI::prompt('Nueva contraseña', null, 'required|min_length[8]');

        if (strlen($password) < 8) {
            CLI::error('La contraseña debe tener al menos 8 caracteres.');

            return EXIT_ERROR;
        }

        $model->skipValidation(true)->update($user['IdUser'], [
            'PasswordHash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        CLI::write("Contraseña actualizada para {$user['FullName']} ({$email}).", 'green');

        if (! (int) $user['IsActive']) {
            CLI::write('Atención: el usuario está inactivo (IsActive = 0) y no podrá entrar.', 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
