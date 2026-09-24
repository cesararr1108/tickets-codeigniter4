<?php

namespace App\Controllers\Panel;

/**
 * Catálogo de categorías y subcategorías con su volumen de tickets.
 */
class Catalog extends BasePanelController
{
    public function index()
    {
        $db = db_connect();

        $categories = $db->table('Category')->orderBy('Category')->get()->getResultArray();
        $subs       = $db->table('SubCategory')->orderBy('SubCategory')->get()->getResultArray();

        $counts = [];

        $rows = $db->table('Tickets')
            ->select('IdCategory, Status')
            ->selectCount('IdTicket', 'total')
            ->groupBy('IdCategory, Status')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $id = (int) $row['IdCategory'];
            $counts[$id] ??= ['total' => 0, 'pending' => 0];
            $counts[$id]['total'] += (int) $row['total'];

            if ($row['Status'] !== 'cerrado') {
                $counts[$id]['pending'] += (int) $row['total'];
            }
        }

        foreach ($categories as &$category) {
            $id = (int) $category['IdCategory'];

            $category['subcategories'] = array_values(array_filter(
                $subs,
                static fn ($sub) => (int) $sub['IdCategory'] === $id
            ));
            $category['total']   = $counts[$id]['total'] ?? 0;
            $category['pending'] = $counts[$id]['pending'] ?? 0;
        }
        unset($category);

        return $this->render('catalog', [
            'active'     => 'catalog',
            'title'      => 'Catálogo de servicios',
            'categories' => $categories,
        ]);
    }
}
