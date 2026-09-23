<?php

namespace App\Models;

use CodeIgniter\Model;

class SubCategoryModel extends Model
{
    protected $table            = 'SubCategory';
    protected $primaryKey       = 'IdSubCategory';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'SubCategory',
        'IdCategory',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'SubCategory' => 'required|max_length[100]',
        'IdCategory'  => 'required|is_natural_no_zero',
    ];
}
