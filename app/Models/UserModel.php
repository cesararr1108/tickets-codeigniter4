<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'Users';
    protected $primaryKey       = 'IdUser';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'IdUser',
        'CodCompanies',
        'CodBranches',
        'FullName',
        'Email',
        'PasswordHash',
        'IsActive',
        'RoleId',
    ];

    // La BD asigna CreatedAt por defecto (sysutcdatetime()); no gestionamos
    // UpdatedAt porque la tabla no lo tiene.
    protected $useTimestamps = false;

    protected $validationRules = [
        'IdUser'       => 'required|max_length[20]',
        'CodCompanies' => 'required|max_length[5]',
        'CodBranches'  => 'required|max_length[5]',
        'FullName'     => 'required|max_length[50]',
        'Email'        => 'required|valid_email|max_length[180]|is_unique[Users.Email,IdUser,{IdUser}]',
        'PasswordHash' => 'required|max_length[255]',
        'IsActive'     => 'permit_empty|in_list[0,1]',
        'RoleId'       => 'required|is_natural_no_zero',
    ];

    /**
     * Oculta el hash de contraseña al serializar resultados hacia la API.
     */
    public function findAllSafe(): array
    {
        return $this->select('IdUser, CodCompanies, CodBranches, FullName, Email, IsActive, RoleId, CreatedAt')
            ->findAll();
    }
}
