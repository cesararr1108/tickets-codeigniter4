<?php

namespace App\Models;

use CodeIgniter\Model;

class TicketFormModel extends Model
{
    protected $table            = 'TicketForms';
    protected $primaryKey       = 'IdForm';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'NameForm',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'NameForm' => 'required|max_length[100]',
    ];
}
