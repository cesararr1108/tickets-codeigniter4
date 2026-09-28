<?php

namespace App\Models;

use CodeIgniter\Model;

class TicketModel extends Model
{
    protected $table            = 'Tickets';
    protected $primaryKey       = 'IdTicket';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'CodCompanies',
        'CodBranches',
        'AssignedUserId',
        'RequesterEmail',
        'Subject',
        'IdCategory',
        'IdSubCategory',
        'Priority',
        'Status',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'CodCompanies'   => 'required|max_length[5]',
        'CodBranches'    => 'required|max_length[5]',
        'AssignedUserId' => 'permit_empty|max_length[20]',
        'RequesterEmail' => 'required|valid_email|max_length[180]',
        'Subject'        => 'required|max_length[150]',
        'IdCategory'     => 'required|is_natural_no_zero',
        'IdSubCategory'  => 'permit_empty|is_natural_no_zero',
        'Priority'       => 'required|in_list[alta,media,baja]',
        'Status'         => 'required|in_list[abierto,en_progreso,cerrado]',
    ];
}
