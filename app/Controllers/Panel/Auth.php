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
            'error' => session()->getFlashdata('error'),
            'email' => old('email'),
        ]);
    }

    public function attempt()
    {
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');

        $user = model(UserModel::class)
            ->select('Users.IdUser, Users.FullName, Users.Email, Users.PasswordHash, Users.IsActive, Users.RoleId, Users.CodCompanies')
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
}
