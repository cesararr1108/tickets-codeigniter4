<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Model;

/**
 * Controlador base para los recursos de la API. Todas las rutas que lo
 * usan pasan primero por el filtro 'apiauth' (ver Config/Filters.php),
 * que valida el token y la clave en los headers de la petición.
 */
abstract class BaseApiController extends BaseController
{
    use ResponseTrait;

    /**
     * Fully qualified class name del modelo asociado al recurso.
     */
    protected string $modelName;

    private ?Model $model = null;

    protected function model(): Model
    {
        if ($this->model === null) {
            $this->model = model($this->modelName);
        }

        return $this->model;
    }

    /**
     * GET /recurso
     */
    public function index()
    {
        return $this->respond($this->model()->findAll());
    }

    /**
     * GET /recurso/{id}
     */
    public function show($id = null)
    {
        $row = $this->model()->find($id);

        if ($row === null) {
            return $this->failNotFound('Registro no encontrado.');
        }

        return $this->respond($row);
    }

    /**
     * POST /recurso
     */
    public function create()
    {
        $model = $this->model();
        $data  = $this->getPayload();

        if (! $model->insert($data)) {
            return $this->failValidationErrors($model->errors());
        }

        $id  = $model->getInsertID();
        $row = $id ? $model->find($id) : $data;

        return $this->respondCreated($row);
    }

    /**
     * PUT/PATCH /recurso/{id}
     */
    public function update($id = null)
    {
        $model = $this->model();

        if ($model->find($id) === null) {
            return $this->failNotFound('Registro no encontrado.');
        }

        $data = $this->getPayload();

        if (! $model->update($id, $data)) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respond($model->find($id));
    }

    /**
     * DELETE /recurso/{id}
     */
    public function delete($id = null)
    {
        $model = $this->model();

        if ($model->find($id) === null) {
            return $this->failNotFound('Registro no encontrado.');
        }

        $model->delete($id);

        return $this->respondDeleted(['id' => $id]);
    }

    /**
     * Obtiene el body de la petición como arreglo, ya sea JSON o
     * form-urlencoded (incluyendo PUT/PATCH).
     */
protected function getPayload(): array
{
    try {
        $json = $this->request->getJSON(true);

        if (is_array($json)) {
            return $json;
        }
    } catch (\Throwable $e) {
        // JSON inválido, continuamos con otros métodos de entrada
    }

    $raw = $this->request->getRawInput();

    if (is_array($raw)) {
        return $raw;
    }

    return [];
}
}
