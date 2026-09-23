<?php

namespace App\Controllers\Api;

use App\Models\UserModel;

class UserController extends BaseApiController
{
    protected string $modelName = UserModel::class;

    public function index()
    {
        return $this->respond($this->model()->findAllSafe());
    }

    public function show($id = null)
    {
        $row = $this->model()
            ->select('IdUser, CodCompanies, CodBranches, FullName, Email, IsActive, RoleId, CreatedAt')
            ->find($id);

        if ($row === null) {
            return $this->failNotFound('Usuario no encontrado.');
        }

        return $this->respond($row);
    }

    public function create()
    {
        $model = $this->model();
        $data  = $this->getPayload();

        if (isset($data['Password'])) {
            $data['PasswordHash'] = password_hash($data['Password'], PASSWORD_DEFAULT);
            unset($data['Password']);
        }

        if (! $model->insert($data)) {
            return $this->failValidationErrors($model->errors());
        }

        $id  = $model->getInsertID() ?: ($data['IdUser'] ?? null);
        $row = $id ? $model->find($id) : $data;
        unset($row['PasswordHash']);

        return $this->respondCreated($row);
    }

    public function update($id = null)
    {
        $model = $this->model();

        if ($model->find($id) === null) {
            return $this->failNotFound('Usuario no encontrado.');
        }

        $data = $this->getPayload();

        if (isset($data['Password'])) {
            $data['PasswordHash'] = password_hash($data['Password'], PASSWORD_DEFAULT);
            unset($data['Password']);
        }

        if (! $model->update($id, $data)) {
            return $this->failValidationErrors($model->errors());
        }

        $row = $model->find($id);
        unset($row['PasswordHash']);

        return $this->respond($row);
    }
}
