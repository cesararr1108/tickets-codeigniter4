<?php

namespace App\Controllers\Panel;

use App\Models\UserModel;

/**
 * Usuarios del panel (solo administradores).
 *
 * Roles (tabla Roles): Administrador y Técnico. Ver Config\Tickets::$adminRoles.
 * Los usuarios no se eliminan (sus tickets los referencian): se inactivan.
 */
class Users extends BasePanelController
{
    private const PER_PAGE = 25;

    /**
     * GET /panel/admin/usuarios[?q=&role=&edit=IdUser]
     */
    public function index()
    {
        $db   = db_connect();
        $q    = trim((string) ($this->request->getGet('q') ?? ''));
        $role = trim((string) ($this->request->getGet('role') ?? ''));

        $builder = $db->table('Users u')
            ->select('u.IdUser, u.FullName, u.Email, u.IsActive, u.RoleId, u.CodCompanies, u.CodBranches, r.Descripcion AS RoleName, c.Companies, b.Branches')
            ->join('Roles r', 'r.Id = u.RoleId', 'left')
            ->join('Companies c', 'c.CodCompanies = u.CodCompanies', 'left')
            ->join('Branches b', 'b.CodBranches = u.CodBranches', 'left');

        if ($q !== '') {
            $builder->groupStart()->like('u.IdUser', $q)->orLike('u.FullName', $q)->orLike('u.Email', $q)->groupEnd();
        }

        if ($role !== '') {
            $builder->where('u.RoleId', (int) $role);
        }

        $total = (clone $builder)->countAllResults();
        $page  = max(1, (int) ($this->request->getGet('page') ?? 1));
        $rows  = $builder->orderBy('u.IsActive', 'DESC')
            ->orderBy('u.FullName', 'ASC')
            ->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)
            ->get()
            ->getResultArray();

        $editId = (string) ($this->request->getGet('edit') ?? '');
        $edit   = $editId === '' ? null : $db->table('Users')
            ->select('IdUser, FullName, Email, IsActive, RoleId, CodCompanies, CodBranches')
            ->where('IdUser', $editId)
            ->get()
            ->getRowArray();

        return $this->render('admin/users', [
            'active'    => 'users',
            'title'     => 'Usuarios',
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'pages'     => max(1, (int) ceil($total / self::PER_PAGE)),
            'q'         => $q,
            'role'      => $role,
            'edit'      => $edit,
            'roles'     => $db->table('Roles')->orderBy('Descripcion')->get()->getResultArray(),
            'companies' => $db->table('Companies')->orderBy('Companies')->get()->getResultArray(),
            'adminRoles' => array_map('mb_strtolower', $this->config->adminRoles),
            'errors'    => session()->getFlashdata('errors') ?? [],
        ]);
    }

    /**
     * POST /panel/admin/usuarios
     */
    public function create()
    {
        $data   = $this->payload();
        $errors = $this->validateUser($data, null);

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        model(UserModel::class)->skipValidation(true)->insert([
            'IdUser'       => $data['IdUser'],
            'FullName'     => $data['FullName'],
            'Email'        => $data['Email'],
            'PasswordHash' => password_hash($data['Password'], PASSWORD_DEFAULT),
            'RoleId'       => $data['RoleId'],
            'CodCompanies' => $data['CodCompanies'],
            'CodBranches'  => $data['CodBranches'],
            'IsActive'     => $data['IsActive'],
        ]);

        return redirect()->to(site_url('panel/admin/usuarios'))->with('success', 'Usuario ' . $data['IdUser'] . ' creado.');
    }

    /**
     * POST /panel/admin/usuarios/update/{IdUser}
     */
    public function update(string $id)
    {
        $model    = model(UserModel::class);
        $existing = $model->find($id);

        if ($existing === null) {
            return redirect()->to(site_url('panel/admin/usuarios'))->with('error', 'El usuario no existe.');
        }

        $data   = $this->payload();
        $errors = $this->validateUser($data, $existing);

        if ($errors !== []) {
            return redirect()->to(site_url('panel/admin/usuarios?edit=' . rawurlencode($id)))->withInput()->with('errors', $errors);
        }

        $update = [
            'FullName'     => $data['FullName'],
            'Email'        => $data['Email'],
            'RoleId'       => $data['RoleId'],
            'CodCompanies' => $data['CodCompanies'],
            'CodBranches'  => $data['CodBranches'],
            'IsActive'     => $data['IsActive'],
        ];

        if ($data['Password'] !== '') {
            $update['PasswordHash'] = password_hash($data['Password'], PASSWORD_DEFAULT);
        }

        $model->skipValidation(true)->update($id, $update);

        return redirect()->to(site_url('panel/admin/usuarios'))->with('success', 'Usuario ' . $id . ' actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $post = $this->request->getPost();

        return [
            'IdUser'       => trim((string) ($post['IdUser'] ?? '')),
            'FullName'     => trim((string) ($post['FullName'] ?? '')),
            'Email'        => mb_strtolower(trim((string) ($post['Email'] ?? ''))),
            'Password'     => (string) ($post['Password'] ?? ''),
            'RoleId'       => (int) ($post['RoleId'] ?? 0),
            'CodCompanies' => trim((string) ($post['CodCompanies'] ?? '')),
            'CodBranches'  => trim((string) ($post['CodBranches'] ?? '')),
            'IsActive'     => empty($post['IsActive']) ? 0 : 1,
        ];
    }

    /**
     * @param array<string, mixed>      $data
     * @param array<string, mixed>|null $existing null al crear
     *
     * @return array<string, string>
     */
    private function validateUser(array $data, ?array $existing): array
    {
        $db     = db_connect();
        $errors = [];
        $isNew  = $existing === null;

        if ($isNew) {
            if (! preg_match('/^[A-Za-z0-9._-]{3,20}$/', $data['IdUser'])) {
                $errors['IdUser'] = 'Entre 3 y 20 caracteres: letras, números, punto, guion o guion bajo.';
            } elseif ($db->table('Users')->where('IdUser', $data['IdUser'])->countAllResults() > 0) {
                $errors['IdUser'] = 'Ya existe un usuario con ese nombre.';
            }
        }

        if ($data['FullName'] === '' || mb_strlen($data['FullName']) > 50) {
            $errors['FullName'] = 'Escribe el nombre (máximo 50 caracteres).';
        }

        if (! filter_var($data['Email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['Email']) > 180) {
            $errors['Email'] = 'Correo no válido.';
        } else {
            $dupe = $db->table('Users')->where('Email', $data['Email']);
            if (! $isNew) {
                $dupe->where('IdUser !=', $existing['IdUser']);
            }
            if ($dupe->countAllResults() > 0) {
                $errors['Email'] = 'Ese correo ya lo usa otro usuario.';
            }
        }

        if ($isNew || $data['Password'] !== '') {
            if (mb_strlen($data['Password']) < 8) {
                $errors['Password'] = 'Mínimo 8 caracteres.';
            }
        }

        $role = $db->table('Roles')->where('Id', $data['RoleId'])->get()->getRowArray();

        if ($role === null) {
            $errors['RoleId'] = 'Selecciona un rol.';
        }

        $branchOk = $db->table('Branches')
            ->where('CodBranches', $data['CodBranches'])
            ->where('CodCompanies', $data['CodCompanies'])
            ->countAllResults() > 0;

        if ($data['CodCompanies'] === '') {
            $errors['CodCompanies'] = 'Selecciona la compañía.';
        } elseif (! $branchOk) {
            $errors['CodBranches'] = 'Selecciona una sucursal de la compañía.';
        }

        // Evita que el administrador se quite el acceso a sí mismo.
        if (! $isNew && (string) $existing['IdUser'] === panel_user_id()) {
            $adminRoles = array_map('mb_strtolower', $this->config->adminRoles);

            if (! $data['IsActive']) {
                $errors['IsActive'] = 'No puedes inactivar tu propio usuario.';
            }

            if ($role !== null && $adminRoles !== [] && ! in_array(mb_strtolower($role['Descripcion']), $adminRoles, true) && panel_admins_exist()) {
                $errors['RoleId'] = 'No puedes quitarte el rol de administrador.';
            }
        }

        return $errors;
    }
}
