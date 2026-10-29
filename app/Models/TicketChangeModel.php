<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Historial de cambios de cada ticket (quién cambió qué y cuándo).
 * Ver app/Database/sql/CategoriasPorCompania.sql.
 */
class TicketChangeModel extends Model
{
    protected $table            = 'TicketChanges';
    protected $primaryKey       = 'IdChange';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'IdTicket',
        'IdUser',
        'UserName',
        'Field',
        'OldValue',
        'NewValue',
    ];

    protected $useTimestamps = false;

    /** Nombre visible de cada campo del historial. */
    public const LABELS = [
        'CodCompanies'   => 'Compañía',
        'CodBranches'    => 'Sucursal',
        'IdCategory'     => 'Categoría',
        'IdSubCategory'  => 'Subcategoría',
        'Subject'        => 'Asunto',
        'RequesterEmail' => 'Solicitante',
        'Status'         => 'Estado',
        'Priority'       => 'Prioridad',
        'AssignedUserId' => 'Responsable',
    ];

    /**
     * ¿Existe la tabla? (sin el script el panel funciona sin historial).
     */
    public static function available(): bool
    {
        static $exists = null;

        return $exists ??= db_connect()->tableExists('TicketChanges');
    }

    /**
     * Registra los campos de $after que cambiaron respecto a $before.
     * Los valores se guardan ya legibles (nombres, no códigos).
     *
     * @param array<string, mixed>  $before Ticket antes del cambio
     * @param array<string, mixed>  $after  Campos nuevos
     * @param array<string, string> $user   ['id' => ..., 'name' => ...]
     */
    public function record(int $idTicket, array $before, array $after, array $user): void
    {
        if (! self::available()) {
            return;
        }

        foreach ($after as $field => $value) {
            if (! isset(self::LABELS[$field]) || (string) ($before[$field] ?? '') === (string) ($value ?? '')) {
                continue;
            }

            $this->insert([
                'IdTicket' => $idTicket,
                'IdUser'   => (string) $user['id'],
                'UserName' => mb_substr((string) ($user['name'] ?? $user['id']), 0, 150),
                'Field'    => $field,
                'OldValue' => $this->display($field, $before[$field] ?? null),
                'NewValue' => $this->display($field, $value),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forTicket(int $idTicket): array
    {
        if (! self::available()) {
            return [];
        }

        return $this->where('IdTicket', $idTicket)->orderBy('IdChange', 'DESC')->findAll();
    }

    private function display(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $db = db_connect();

        $name = match ($field) {
            'CodCompanies'   => $db->table('Companies')->select('Companies AS n')->where('CodCompanies', $value)->get()->getRowArray()['n'] ?? null,
            'CodBranches'    => $db->table('Branches')->select('Branches AS n')->where('CodBranches', $value)->get()->getRowArray()['n'] ?? null,
            'IdCategory'     => $db->table('Category')->select('Category AS n')->where('IdCategory', $value)->get()->getRowArray()['n'] ?? null,
            'IdSubCategory'  => $db->table('SubCategory')->select('SubCategory AS n')->where('IdSubCategory', $value)->get()->getRowArray()['n'] ?? null,
            'AssignedUserId' => $db->table('Users')->select('FullName AS n')->where('IdUser', $value)->get()->getRowArray()['n'] ?? null,
            'Status'         => config('Tickets')->statuses[$value] ?? null,
            'Priority'       => config('Tickets')->priorities[$value] ?? null,
            default          => null,
        };

        return mb_substr((string) ($name ?? $value), 0, 255);
    }
}
