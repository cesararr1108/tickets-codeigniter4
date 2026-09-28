<?php

namespace App\Models;

use CodeIgniter\Model;

class TicketMessageModel extends Model
{
    protected $table            = 'TicketMessages';
    protected $primaryKey       = 'MessageId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'IdTicket',
        'SenderType',
        'SenderName',
        'Message',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'IdTicket'   => 'required|is_natural_no_zero',
        'SenderType' => 'required|in_list[agente,cliente]',
        'SenderName' => 'required|max_length[150]',
        'Message'    => 'required',
    ];

    public function forTicket(int $idTicket): array
    {
        return $this->where('IdTicket', $idTicket)
            ->orderBy('CreatedAt', 'ASC')
            ->findAll();
    }
}
