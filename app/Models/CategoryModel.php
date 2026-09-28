<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table            = 'Category';
    protected $primaryKey       = 'IdCategory';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'Category',
        'Description',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'Category'    => 'required|max_length[40]',
        'Description' => 'permit_empty|max_length[155]',
    ];
}
