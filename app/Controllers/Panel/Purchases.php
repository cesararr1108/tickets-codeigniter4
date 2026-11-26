<?php

namespace App\Controllers\Panel;

use App\Libraries\Purchases as PurchaseLib;
use App\Libraries\TicketRepository;

/**
 * Pestaña "Compras": tickets de compra (diademas, mouse, etc.) creados a
 * partir de un ticket original. No cuentan en dashboard ni reportes.
 */
class Purchases extends BasePanelController
{
    /**
     * GET /panel/compras?status=&q=&page=
     */
    public function index()
    {
        if (! PurchaseLib::available()) {
            return redirect()->to(site_url('panel'))->with('error', 'Falta ejecutar app/Database/sql/OrdenDesarrollosYCompras.sql (compras).');
        }

        $filters = [
            'purchases' => '1',
            'status'    => trim((string) ($this->request->getGet('status') ?? 'pendientes')),
            'q'         => trim((string) ($this->request->getGet('q') ?? '')),
            'sort'      => 'fecha',
        ];

        if ($filters['status'] !== '' && $filters['status'] !== 'pendientes' && ! isset($this->config->statuses[$filters['status']])) {
            $filters['status'] = 'pendientes';
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->config->perPage;
        $result  = (new TicketRepository())->paginate($filters, $page, $perPage, $this->user['id'] ?? null);

        $parents = [];
        $ids     = array_values(array_unique(array_filter(array_column($result['rows'], 'ParentTicketId'))));

        if ($ids !== []) {
            foreach (db_connect()->table('Tickets')->select('IdTicket, Subject')->whereIn('IdTicket', $ids)->get()->getResultArray() as $p) {
                $parents[$p['IdTicket']] = $p['Subject'];
            }
        }

        return $this->render('purchases', [
            'active'  => 'purchases',
            'title'   => 'Compras',
            'filters' => $filters,
            'rows'    => $result['rows'],
            'parents' => $parents,
            'total'   => $result['total'],
            'page'    => $page,
            'pages'   => max(1, (int) ceil($result['total'] / $perPage)),
        ]);
    }
}
