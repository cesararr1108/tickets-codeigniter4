<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

/**
 * Consultas de tickets para el panel (listado con filtros y detalle).
 *
 * Solo usa Query Builder para funcionar igual en SQL Server (SQLSRV)
 * y en otros motores.
 */
class TicketRepository
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Tickets con los nombres de compañía, sucursal, categoría y agente.
     */
    public function builder(): BaseBuilder
    {
        return $this->db->table('Tickets t')
            ->select('t.IdTicket, t.CodCompanies, t.CodBranches, t.AssignedUserId, t.RequesterEmail,
                t.Subject, t.IdCategory, t.IdSubCategory, t.Priority, t.Status, t.CreatedAt,
                c.Companies, b.Branches, cat.Category, sc.SubCategory, u.FullName AS AssignedName')
            ->join('Companies c', 'c.CodCompanies = t.CodCompanies', 'left')
            ->join('Branches b', 'b.CodBranches = t.CodBranches', 'left')
            ->join('Category cat', 'cat.IdCategory = t.IdCategory', 'left')
            ->join('SubCategory sc', 'sc.IdSubCategory = t.IdSubCategory', 'left')
            ->join('Users u', 'u.IdUser = t.AssignedUserId', 'left');
    }

    /**
     * Aplica los filtros del listado.
     *
     * @param array<string, string> $filters
     */
    public function applyFilters(BaseBuilder $builder, array $filters, ?string $currentUserId = null): BaseBuilder
    {
        $status = $filters['status'] ?? '';

        if ($status === 'pendientes') {
            $builder->whereIn('t.Status', ['abierto', 'en_progreso']);
        } elseif ($status !== '') {
            $builder->where('t.Status', $status);
        }

        foreach (['priority' => 't.Priority', 'company' => 't.CodCompanies', 'branch' => 't.CodBranches', 'category' => 't.IdCategory'] as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $builder->where($column, $filters[$key]);
            }
        }

        $assigned = $filters['assigned'] ?? '';

        if ($assigned === 'none') {
            $builder->where('t.AssignedUserId', null);
        } elseif ($assigned === 'me' && $currentUserId !== null) {
            $builder->where('t.AssignedUserId', $currentUserId);
        } elseif ($assigned !== '' && $assigned !== 'me') {
            $builder->where('t.AssignedUserId', $assigned);
        }

        $q = trim($filters['q'] ?? '');

        if ($q !== '') {
            $number = (int) preg_replace('/\D/', '', $q);

            $builder->groupStart()
                ->like('t.Subject', $q)
                ->orLike('t.RequesterEmail', $q);

            if ($number > 0) {
                $builder->orWhere('t.IdTicket', $number);
            }

            $builder->groupEnd();
        }

        return $builder;
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage, ?string $currentUserId = null): array
    {
        $total = $this->applyFilters($this->builder(), $filters, $currentUserId)
            ->countAllResults();

        $builder = $this->applyFilters($this->builder(), $filters, $currentUserId);

        $this->applySort($builder, $filters['sort'] ?? '');

        $rows = $builder
            ->limit($perPage, max(0, ($page - 1) * $perPage))
            ->get()
            ->getResultArray();

        return ['rows' => $rows, 'total' => $total];
    }

    public function applySort(BaseBuilder $builder, string $sort): void
    {
        match ($sort) {
            'antiguos'  => $builder->orderBy('t.CreatedAt', 'ASC'),
            'prioridad' => $builder
                ->orderBy(self::priorityOrderSql('t.Priority'), '', false)
                ->orderBy('t.CreatedAt', 'ASC'),
            default     => $builder->orderBy('t.CreatedAt', 'DESC'),
        };
    }

    /**
     * Expresión para ordenar alta > media > baja.
     */
    public static function priorityOrderSql(string $column): string
    {
        return "CASE {$column} WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END";
    }

    public function find(int $id): ?array
    {
        return $this->builder()
            ->where('t.IdTicket', $id)
            ->get()
            ->getRowArray();
    }
}
