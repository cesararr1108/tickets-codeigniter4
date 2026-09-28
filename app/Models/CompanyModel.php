<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * La tabla Companies es referenciada por FK desde Branches, Users y
 * Tickets pero su DDL no fue provisto; se asume la misma convención
 * de columnas usada en Branches (CodCompanies + nombre en la columna
 * homónima a la tabla). Ajustar allowedFields/validationRules si la
 * estructura real difiere.
 */
class CompanyModel extends Model
{
    protected $table            = 'Companies';
    protected $primaryKey       = 'CodCompanies';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'CodCompanies',
        'Companies',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'CodCompanies' => 'required|max_length[5]',
        'Companies'    => 'required|max_length[150]',
    ];
}
