<?php

namespace App\Controllers\Panel;

use App\Libraries\CatalogAdmin;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Administración de catálogos: compañías, sucursales, categorías y
 * subcategorías. El comportamiento de cada uno se define en
 * App\Libraries\CatalogAdmin.
 */
class Admin extends BasePanelController
{
    private const PER_PAGE = 25;

    /**
     * GET /panel/admin
     */
    public function index()
    {
        return redirect()->to(site_url('panel/admin/companies'));
    }

    /**
     * GET /panel/admin/{catalogo}
     */
    public function list(string $key)
    {
        $def = $this->definition($key);
        $db  = db_connect();

        $q      = trim((string) ($this->request->getGet('q') ?? ''));
        $parent = $def['parent'] ?? null;
        $parentValue = $parent ? trim((string) ($this->request->getGet($parent['param']) ?? '')) : '';

        $builder = $db->table($def['table'] . ' x')->select('x.*');

        if ($parent !== null) {
            $parentDef = CatalogAdmin::get($parent['catalog']);

            $builder->select("p.{$parentDef['name']} AS ParentName")
                ->join("{$parentDef['table']} p", "p.{$parentDef['pk']} = x.{$parent['field']}", 'left');

            if ($parentValue !== '') {
                $builder->where("x.{$parent['field']}", $parentValue);
            }
        }

        if ($q !== '') {
            $builder->groupStart();
            foreach ($def['search'] as $i => $column) {
                $i === 0 ? $builder->like("x.{$column}", $q) : $builder->orLike("x.{$column}", $q);
            }
            $builder->groupEnd();
        }

        $total = (clone $builder)->countAllResults();
        $page  = max(1, (int) ($this->request->getGet('page') ?? 1));
        $rows  = $builder
            ->orderBy("x.{$def['order']}", 'ASC')
            ->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)
            ->get()
            ->getResultArray();

        $editId = $this->request->getGet('edit');
        $edit   = null;

        if ($editId !== null && $editId !== '') {
            $edit = $db->table($def['table'])->where($def['pk'], $editId)->get()->getRowArray();
        }

        $options = [];
        foreach ($def['fields'] as $field) {
            if (($field['type'] ?? '') === 'select') {
                $options[$field['options']] = CatalogAdmin::options($field['options']);
            }
        }

        return $this->render('admin/catalog', [
            'active'      => 'admin',
            'title'       => $def['title'],
            'def'         => $def,
            'catalogs'    => CatalogAdmin::definitions(),
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'pages'       => max(1, (int) ceil($total / self::PER_PAGE)),
            'q'           => $q,
            'parentValue' => $parentValue,
            'usage'       => CatalogAdmin::usageCounts($def, array_column($rows, $def['pk'])),
            'edit'        => $edit,
            'options'     => $options,
            'errors'      => session()->getFlashdata('errors') ?? [],
        ]);
    }

    /**
     * POST /panel/admin/{catalogo}
     */
    public function create(string $key)
    {
        $def  = $this->definition($key);
        $data = $this->collect($def, true);

        $errors = $this->validateFields($def, $data) + $this->checkReferences($def, $data);

        if (! $def['autoPk'] && ($data[$def['pk']] ?? '') !== '' && $this->exists($def['table'], $def['pk'], $data[$def['pk']])) {
            $errors[$def['pk']] = 'Ya existe un registro con ese código.';
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $model = model($def['model']);

        try {
            if (! $model->insert($data)) {
                return redirect()->back()->withInput()->with('errors', $model->errors());
            }
        } catch (DatabaseException $e) {
            log_message('error', '[Admin] ' . $e->getMessage());

            return redirect()->back()->withInput()->with('error', 'No se pudo guardar. Revisa los datos e intenta de nuevo.');
        }

        return redirect()->to($this->listUrl($def))
            ->with('success', ucfirst($def['singular']) . ' "' . $data[$def['name']] . '" creada correctamente.');
    }

    /**
     * POST /panel/admin/{catalogo}/update/{id}
     */
    public function update(string $key, string $id)
    {
        $def = $this->definition($key);
        $id  = rawurldecode($id);

        if (! $this->exists($def['table'], $def['pk'], $id)) {
            return redirect()->to($this->listUrl($def))->with('error', 'El registro ya no existe.');
        }

        // La clave primaria no se modifica (la usan otras tablas).
        $data   = $this->collect($def, false);
        $errors = $this->validateFields($def, $data) + $this->checkReferences($def, $data);

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $model = model($def['model']);

        try {
            if (! $model->update($id, $data)) {
                return redirect()->back()->withInput()->with('errors', $model->errors());
            }
        } catch (DatabaseException $e) {
            log_message('error', '[Admin] ' . $e->getMessage());

            return redirect()->back()->withInput()->with('error', 'No se pudo guardar. Revisa los datos e intenta de nuevo.');
        }

        return redirect()->to($this->listUrl($def))
            ->with('success', 'Cambios guardados en "' . $data[$def['name']] . '".');
    }

    /**
     * POST /panel/admin/{catalogo}/delete/{id}
     */
    public function delete(string $key, string $id)
    {
        $def = $this->definition($key);
        $id  = rawurldecode($id);
        $db  = db_connect();

        $row = $db->table($def['table'])->where($def['pk'], $id)->get()->getRowArray();

        if ($row === null) {
            return redirect()->to($this->listUrl($def))->with('error', 'El registro ya no existe.');
        }

        $inUse = [];
        $usage = CatalogAdmin::usageCounts($def, [$id])[(string) $id] ?? [];

        foreach ($usage as $index => $count) {
            if ($count > 0) {
                $inUse[] = $count . ' ' . ($count === 1 ? $def['usage'][$index]['one'] : $def['usage'][$index]['label']);
            }
        }

        if ($inUse !== []) {
            return redirect()->back()->with(
                'error',
                'No se puede eliminar "' . $row[$def['name']] . '" porque tiene ' . implode(', ', $inUse) . '.'
            );
        }

        try {
            model($def['model'])->delete($id);
        } catch (DatabaseException $e) {
            log_message('error', '[Admin] ' . $e->getMessage());

            return redirect()->back()->with('error', 'No se pudo eliminar: el registro está en uso.');
        }

        return redirect()->to($this->listUrl($def))
            ->with('success', ucfirst($def['singular']) . ' "' . $row[$def['name']] . '" eliminada.');
    }

    private function definition(string $key): array
    {
        $def = CatalogAdmin::get($key);

        if ($def === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $def;
    }

    /**
     * Toma del POST solo los campos definidos para el catálogo.
     */
    private function collect(array $def, bool $withPk): array
    {
        $data = [];

        foreach ($def['fields'] as $name => $field) {
            if ($name === $def['pk'] && ! $withPk) {
                continue;
            }

            $value = trim((string) ($this->request->getPost($name) ?? ''));

            $data[$name] = $value === '' && empty($field['required']) ? null : $value;
        }

        return $data;
    }

    /**
     * Obligatorios y largo máximo, con mensajes en español.
     *
     * @return array<string, string>
     */
    private function validateFields(array $def, array $data): array
    {
        $errors = [];

        foreach ($def['fields'] as $name => $field) {
            if (! array_key_exists($name, $data)) {
                continue;
            }

            $value = (string) ($data[$name] ?? '');

            if ($value === '' && ! empty($field['required'])) {
                $errors[$name] = 'Este campo es obligatorio.';
            } elseif (isset($field['max']) && mb_strlen($value) > $field['max']) {
                $errors[$name] = "Máximo {$field['max']} caracteres.";
            }
        }

        return $errors;
    }

    /**
     * Verifica que los valores de los selects existan (claves foráneas).
     *
     * @return array<string, string>
     */
    private function checkReferences(array $def, array $data): array
    {
        $errors = [];

        foreach ($def['fields'] as $name => $field) {
            if (($field['type'] ?? '') !== 'select' || ($data[$name] ?? '') === '') {
                continue;
            }

            $ref = CatalogAdmin::get($field['options']);

            if (! $this->exists($ref['table'], $ref['pk'], $data[$name])) {
                $errors[$name] = 'Selecciona una opción válida.';
            }
        }

        return $errors;
    }

    private function exists(string $table, string $column, string $value): bool
    {
        return db_connect()->table($table)->where($column, $value)->countAllResults() > 0;
    }

    private function listUrl(array $def): string
    {
        $query = [];
        $parent = $def['parent'] ?? null;

        if ($parent !== null) {
            $value = $this->request->getPost($parent['field']);

            if ($value !== null && $value !== '') {
                $query[$parent['param']] = $value;
            }
        }

        return site_url('panel/admin/' . $def['key']) . ($query ? '?' . http_build_query($query) : '');
    }
}
