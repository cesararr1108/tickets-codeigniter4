<?php

namespace App\Controllers\Panel;

use App\Libraries\CategoryCompanies;

/**
 * Listas dependientes para los formularios del panel (JSON).
 */
class Lookups extends BasePanelController
{
    public function branches()
    {
        $rows = db_connect()->table('Branches')
            ->select('CodBranches AS id, Branches AS name')
            ->where('CodCompanies', (string) $this->request->getGet('company'))
            ->orderBy('Branches')
            ->get()
            ->getResultArray();

        return $this->response->setJSON($rows);
    }

    /**
     * Categorías que se ofrecen en la compañía.
     */
    public function categories()
    {
        $rows = array_map(static fn ($c) => [
            'id'   => (int) $c['IdCategory'],
            'name' => $c['Category'],
        ], CategoryCompanies::categoriesFor((string) $this->request->getGet('company')));

        return $this->response->setJSON($rows);
    }

    public function subcategories()
    {
        $rows = db_connect()->table('SubCategory')
            ->select('IdSubCategory AS id, SubCategory AS name')
            ->where('IdCategory', (int) $this->request->getGet('category'))
            ->orderBy('SubCategory')
            ->get()
            ->getResultArray();

        return $this->response->setJSON($rows);
    }
}
