<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;

/**
 * Compras (diademas, mouse, etc.): tickets que se crean a partir de un ticket
 * original (Tickets.ParentTicketId) y se gestionan en la pestaña "Compras".
 * No cuentan en el dashboard ni en los reportes.
 *
 * Requiere app/Database/sql/OrdenDesarrollosYCompras.sql; sin él todo sigue
 * funcionando igual que antes.
 */
class Purchases
{
    public static function available(): bool
    {
        static $exists = null;

        return $exists ??= db_connect()->fieldExists('IsPurchase', 'Tickets')
            && db_connect()->fieldExists('ParentTicketId', 'Tickets');
    }

    /**
     * Excluye las compras de una consulta sobre Tickets ($alias = 't' o '').
     */
    public static function exclude(BaseBuilder $builder, string $alias = ''): BaseBuilder
    {
        if (self::available()) {
            $builder->where(($alias !== '' ? $alias . '.' : '') . 'IsPurchase', 0);
        }

        return $builder;
    }

    /**
     * Compras de un ticket original (más recientes primero).
     *
     * @return list<array<string, mixed>>
     */
    public static function forTicket(int $ticketId): array
    {
        if (! self::available()) {
            return [];
        }

        return db_connect()->table('Tickets')
            ->select('IdTicket, Subject, Status, CreatedAt')
            ->where('ParentTicketId', $ticketId)
            ->where('IsPurchase', 1)
            ->orderBy('IdTicket', 'DESC')
            ->get()->getResultArray();
    }
}
