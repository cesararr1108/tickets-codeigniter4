<?php

namespace App\Controllers\Panel;

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
