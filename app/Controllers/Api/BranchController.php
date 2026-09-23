<?php

namespace App\Controllers\Api;

use App\Models\BranchModel;

class BranchController extends BaseApiController
{
    /**
     * GET /api/branches
     * GET /api/branches?company={codCompanies}
     */
    public function index()
    {
        $model   = model(BranchModel::class);
        $company = $this->request->getGet('company');

        if ($company !== null && $company !== '') {
            $model->where('CodCompanies', $company);
        }

        return $this->respond($model->findAll());
    }

    /**
     * GET /api/branches/{codBranches}
     */
    public function show($id = null)
    {
        $model = model(BranchModel::class);
        $row   = $model->find($id);

        if ($row === null) {
            return $this->failNotFound('Sucursal no encontrada.');
        }

        return $this->respond($row);
    }

    /**
     * POST /api/branches
     */
    public function create()
    {
        $model = model(BranchModel::class);
        $data  = $this->getPayload();

        if (! $model->insert($data)) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated($model->find($data['CodBranches'] ?? null));
    }

    /**
     * PUT/PATCH /api/branches/{codBranches}
     */
    public function update($id = null)
    {
        $model = model(BranchModel::class);

        if ($model->find($id) === null) {
            return $this->failNotFound('Sucursal no encontrada.');
        }

        $data = $this->getPayload();

        if (! $model->update($id, $data)) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respond($model->find($id));
    }

    /**
     * DELETE /api/branches/{codBranches}
     */
    public function delete($id = null)
    {
        $model = model(BranchModel::class);

        if ($model->find($id) === null) {
            return $this->failNotFound('Sucursal no encontrada.');
        }

        $model->delete($id);

        return $this->respondDeleted(['CodBranches' => $id]);
    }
}