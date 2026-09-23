<?php

namespace App\Controllers\Api;

use App\Models\SubCategoryModel;

class SubCategoryController extends BaseApiController
{
    protected string $modelName = SubCategoryModel::class;

    /**
     * GET /subcategories/category/{idCategory}
     */
    public function byCategory($idCategory = null)
    {
        $rows = $this->model()
            ->where('IdCategory', $idCategory)
            ->findAll();

        return $this->respond($rows);
    }
}
