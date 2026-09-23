<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table            = 'Roles';
    protected $primaryKey       = 'Id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'Descripcion',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'Descripcion' => 'required|max_length[50]',
    ];
}
