<?php

namespace App\Models;

use CodeIgniter\Model;

class BranchModel extends Model
{
    protected $table            = 'Branches';
    protected $primaryKey       = 'CodBranches';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'CodBranches',
        'Branches',
        'CodCompanies',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'CodBranches'  => 'required|max_length[5]',
        'Branches'     => 'required|max_length[150]',
        'CodCompanies' => 'required|max_length[5]',
    ];
}
