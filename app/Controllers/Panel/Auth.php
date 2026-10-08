<?php

namespace App\Controllers\Panel;

use App\Models\UserModel;

/**
 * Inicio y cierre de sesión del panel contra la tabla Users.
 */
class Auth extends BasePanelController
{
    public function login()
    {
        if ($this->user !== null) {
            return redirect()->to(site_url('panel'));
        }

        return view('auth/login', [
            'success' => session()->getFlashdata('success'),
            'error' => session()->getFlashdata('error'),
            'email' => old('email'),
        ]);
    }

    public function attempt()
    {
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');

        $user = model(UserModel::class)
            ->select('
             Users.IdUser,
             Users.FullName, 
             Users.Email, 
             Users.PasswordHash, 
             Users.IsActive,
             Users.RoleId, Users.CodCompanies')
            ->where('Email', $email)
            ->first();
            
        if ($user === null || ! password_verify($password, (string) $user['PasswordHash'])) {
            return redirect()->back()->withInput()->with('error', 'Correo o contraseña incorrectos.');
        }

        if (! (int) $user['IsActive']) {
            return redirect()->back()->withInput()->with('error', 'Tu usuario está inactivo.');
        }

        $role = db_connect()->table('Roles')->select('Descripcion')->where('Id', $user['RoleId'])->get()->getRowArray();

        session()->regenerate(true);
        session()->set('panelUser', [
            'id'      => $user['IdUser'],
            'name'    => $user['FullName'],
            'email'   => $user['Email'],
            'roleId'  => (int) $user['RoleId'],
            'role'    => $role['Descripcion'] ?? '',
            'company' => $user['CodCompanies'],
        ]);

        $target = session('redirectAfterLogin');
        session()->remove('redirectAfterLogin');

        return redirect()->to(is_string($target) && str_starts_with($target, site_url('panel')) ? $target : site_url('panel'));
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to(site_url('login'));
    }

    /** Máximo de solicitudes de recuperación por usuario y por hora. */
    private const RESET_MAX_PER_HOUR = 3;

    /** Vigencia del enlace de recuperación (minutos). */
    private const RESET_MINUTES = 60;

    /**
     * GET /recuperar
     */
    public function forgot()
    {
        if ($this->user !== null) {
            return redirect()->to(site_url('panel'));
        }

        return view('auth/forgot', ['error' => session()->getFlashdata('error'), 'sent' => session()->getFlashdata('sent')]);
    }

    /**
     * POST /recuperar
     * Envía el enlace por correo. La respuesta es siempre la misma, exista o no el correo.
     */
    public function sendReset()
    {
        $email = mb_strtolower(trim((string) $this->request->getPost('email')));
        $db    = db_connect();

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', 'Escribe un correo válido.');
        }

        if (! $db->tableExists('PasswordResets')) {
            log_message('error', '[Auth] Falta la tabla PasswordResets (app/Database/sql/SedesYRecuperacion.sql).');

            return redirect()->back()->with('error', 'La recuperación de contraseña aún no está disponible. Pide al administrador que la restablezca.');
        }

        $user = $db->table('Users')->select('IdUser, FullName, Email, IsActive')->where('Email', $email)->get()->getRowArray();

        if ($user !== null && (int) $user['IsActive']) {
            $recent = $db->table('PasswordResets')
                ->where('IdUser', $user['IdUser'])
                ->where('CreatedAt >', gmdate('Y-m-d H:i:s', time() - 3600))
                ->countAllResults();

            if ($recent < self::RESET_MAX_PER_HOUR) {
                $token = bin2hex(random_bytes(32));

                // Los enlaces anteriores sin usar dejan de servir.
                $db->table('PasswordResets')->where('IdUser', $user['IdUser'])->where('UsedAt', null)->update(['UsedAt' => gmdate('Y-m-d H:i:s')]);

                $db->table('PasswordResets')->insert([
                    'IdUser'    => $user['IdUser'],
                    'TokenHash' => hash('sha256', $token),
                    'ExpiresAt' => gmdate('Y-m-d H:i:s', time() + self::RESET_MINUTES * 60),
                    'RequestIp' => substr((string) $this->request->getIPAddress(), 0, 45),
                ]);

                $this->mailReset($user, site_url('restablecer/' . $token));
            }
        }

        return redirect()->to(site_url('recuperar'))->with('sent', true);
    }

    /**
     * GET /restablecer/{token}
     */
    public function resetForm(string $token)
    {
        if ($this->findReset($token) === null) {
            return view('auth/reset', ['invalid' => true, 'token' => '', 'error' => null]);
        }

        return view('auth/reset', ['invalid' => false, 'token' => $token, 'error' => session()->getFlashdata('error')]);
    }

    /**
     * POST /restablecer/{token}
     */
    public function resetSave(string $token)
    {
        $reset = $this->findReset($token);

        if ($reset === null) {
            return view('auth/reset', ['invalid' => true, 'token' => '', 'error' => null]);
        }

        $password = (string) $this->request->getPost('password');
        $confirm  = (string) $this->request->getPost('password_confirm');

        if (mb_strlen($password) < 8) {
            return redirect()->back()->with('error', 'La contraseña debe tener al menos 8 caracteres.');
        }

        if ($password !== $confirm) {
            return redirect()->back()->with('error', 'Las contraseñas no coinciden.');
        }

        $db = db_connect();
        $db->table('Users')->where('IdUser', $reset['IdUser'])->update(['PasswordHash' => password_hash($password, PASSWORD_DEFAULT)]);
        $db->table('PasswordResets')->where('IdUser', $reset['IdUser'])->where('UsedAt', null)->update(['UsedAt' => gmdate('Y-m-d H:i:s')]);

        return redirect()->to(site_url('login'))->with('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }

    /**
     * Solicitud vigente (sin usar y sin vencer) para ese enlace, o null.
     *
     * @return array<string, mixed>|null
     */
    private function findReset(string $token): ?array
    {
        $db = db_connect();

        if (! preg_match('/^[a-f0-9]{64}$/', $token) || ! $db->tableExists('PasswordResets')) {
            return null;
        }

        return $db->table('PasswordResets r')
            ->select('r.Id, r.IdUser')
            ->join('Users u', 'u.IdUser = r.IdUser')
            ->where('r.TokenHash', hash('sha256', $token))
            ->where('r.UsedAt', null)
            ->where('r.ExpiresAt >', gmdate('Y-m-d H:i:s'))
            ->where('u.IsActive', 1)
            ->get()
            ->getRowArray();
    }

    /**
     * @param array<string, mixed> $user
     */
    private function mailReset(array $user, string $link): void
    {
        try {
            $email  = service('email');
            $config = config(\Config\Email::class);

            if ($config->fromEmail === '') {
                log_message('error', '[Auth] Falta configurar el correo saliente (email.fromEmail en .env); no se envió el enlace de recuperación.');

                return;
            }

            $email->setFrom($config->fromEmail, $config->fromName !== '' ? $config->fromName : 'Mesa de Ayuda');
            $email->setTo($user['Email']);
            $email->setSubject('Recuperar tu contraseña · Mesa de Ayuda');
            $email->setMessage(
                'Hola ' . $user['FullName'] . ",\n\n"
                . "Recibimos una solicitud para restablecer tu contraseña. Usa este enlace (vale " . self::RESET_MINUTES . " minutos y solo sirve una vez):\n\n"
                . $link . "\n\n"
                . "Si no fuiste tú, ignora este mensaje: tu contraseña no cambia.\n"
            );

            if (! $email->send(false)) {
                log_message('error', '[Auth] No se pudo enviar el correo de recuperación: ' . $email->printDebugger(['headers']));
            }
        } catch (\Throwable $e) {
            log_message('error', '[Auth] Correo de recuperación: ' . $e->getMessage());
        }
    }
}
