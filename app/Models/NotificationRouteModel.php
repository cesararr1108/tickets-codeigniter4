<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Reglas "tickets de la compañía X -> notificar al rol Y".
 * Ver app/Database/sql/NotificationRoutes.sql.
 */
class NotificationRouteModel extends Model
{
    protected $table            = 'TicketNotificationRoutes';
    protected $primaryKey       = 'Id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = ['CodCompanies', 'RoleId'];

    /** ¿Existe la tabla? Si no, el sistema funciona sin reglas. */
    public static function available(): bool
    {
        static $exists = null;

        return $exists ??= db_connect()->tableExists('TicketNotificationRoutes');
    }

    /**
     * Ids de rol que deben ser notificados por los tickets de una compañía.
     *
     * @return list<int>
     */
    public static function rolesFor(string $company): array
    {
        if (! self::available()) {
            return [];
        }

        $rows = db_connect()->table('TicketNotificationRoutes')
            ->select('RoleId')
            ->where('CodCompanies', $company)
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($rows, 'RoleId'));
    }
}
