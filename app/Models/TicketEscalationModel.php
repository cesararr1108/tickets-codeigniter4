<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Escalamientos de tickets al administrador.
 * Ver app/Database/sql/RolesYEscalamientos.sql.
 */
class TicketEscalationModel extends Model
{
    protected $table            = 'TicketEscalations';
    protected $primaryKey       = 'IdEscalation';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'IdTicket',
        'EscalatedBy',
        'EscalatedByName',
        'Reason',
        'ResolvedAt',
        'ResolvedBy',
        'ResolvedByName',
        'ResolutionNote',
    ];

    protected $useTimestamps = false;

    /**
     * ¿Existe la tabla? (si aún no se ejecutó el script, el resto del
     * sistema sigue funcionando sin escalamientos).
     */
    public static function available(): bool
    {
        static $exists = null;

        return $exists ??= db_connect()->tableExists('TicketEscalations');
    }

    /**
     * Escalamiento activo del ticket, o null.
     *
     * @return array<string, mixed>|null
     */
    public function activeFor(int $idTicket): ?array
    {
        if (! self::available()) {
            return null;
        }

        return $this->where('IdTicket', $idTicket)
            ->where('ResolvedAt', null)
            ->orderBy('IdEscalation', 'DESC')
            ->first();
    }

    /**
     * Historial de escalamientos del ticket (más reciente primero).
     *
     * @return list<array<string, mixed>>
     */
    public function historyFor(int $idTicket): array
    {
        if (! self::available()) {
            return [];
        }

        return $this->where('IdTicket', $idTicket)
            ->orderBy('IdEscalation', 'DESC')
            ->findAll();
    }

    public function countActive(): int
    {
        if (! self::available()) {
            return 0;
        }

        return $this->builder()
            ->join('Tickets t', 't.IdTicket = TicketEscalations.IdTicket')
            ->where('TicketEscalations.ResolvedAt', null)
            ->whereIn('t.Status', ['abierto', 'en_progreso'])
            ->countAllResults();
    }
}
