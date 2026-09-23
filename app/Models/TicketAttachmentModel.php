<?php

namespace App\Models;

use CodeIgniter\Model;

class TicketAttachmentModel extends Model
{
    protected $table            = 'TicketAttachments';
    protected $primaryKey       = 'AttachmentId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'TicketId',
        'FileName',
        'FilePath',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'TicketId' => 'required|is_natural_no_zero',
        'FileName' => 'required|max_length[255]',
        'FilePath' => 'required|max_length[500]',
    ];
}
