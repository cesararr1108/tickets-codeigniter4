<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Sesiones del cronómetro de trabajo de cada ticket.
 * Ver app/Database/sql/TiempoDeTrabajo.sql.
 */
class TicketTimeLogModel extends Model
{
    protected $table            = 'TicketTimeLogs';
    protected $primaryKey       = 'IdLog';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'IdTicket',
        'IdUser',
        'UserName',
        'StartedAt',
        'EndedAt',
    ];

    protected $useTimestamps = false;

    /**
     * ¿Existe la tabla? (sin el script el panel funciona sin cronómetro).
     */
    public static function available(): bool
    {
        static $exists = null;

        return $exists ??= db_connect()->tableExists('TicketTimeLogs');
    }

    /**
     * Sesión corriendo del ticket, o null.
     *
     * @return array<string, mixed>|null
     */
    public function runningFor(int $idTicket): ?array
    {
        return $this->where('IdTicket', $idTicket)
            ->where('EndedAt', null)
            ->orderBy('IdLog', 'DESC')
            ->first();
    }

    public function start(int $idTicket, string $userId, string $userName): void
    {
        $this->insert([
            'IdTicket'  => $idTicket,
            'IdUser'    => $userId,
            'UserName'  => mb_substr($userName, 0, 150),
            'StartedAt' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Detiene las sesiones abiertas del ticket (si hay).
     */
    public function stop(int $idTicket): void
    {
        $this->builder()
            ->where('IdTicket', $idTicket)
            ->where('EndedAt', null)
            ->update(['EndedAt' => gmdate('Y-m-d H:i:s')]);
    }

    /**
     * Segundos acumulados del ticket (sesión corriendo incluida).
     */
    public function totalSeconds(int $idTicket): int
    {
        $total = 0;
        $now   = time();

        foreach ($this->where('IdTicket', $idTicket)->findAll() as $row) {
            $total += self::duration($row, $now);
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function duration(array $row, ?int $now = null): int
    {
        $start = strtotime($row['StartedAt'] . ' UTC');
        $end   = $row['EndedAt'] !== null ? strtotime($row['EndedAt'] . ' UTC') : ($now ?? time());

        return max(0, (int) $end - (int) $start);
    }
}
