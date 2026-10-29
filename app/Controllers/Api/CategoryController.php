<?php

namespace App\Controllers\Api;

use App\Libraries\CategoryCompanies;
use App\Models\CategoryModel;

class CategoryController extends BaseApiController
{
    protected string $modelName = CategoryModel::class;

    /**
     * GET /categories?company={CodCompanies}
     * Con company, solo las categorías que se ofrecen en esa compañía.
     */
    public function index()
    {
        $company = trim((string) $this->request->getGet('company'));

        if ($company === '') {
            return parent::index();
        }

        return $this->respond(CategoryCompanies::categoriesFor($company));
    }
}
