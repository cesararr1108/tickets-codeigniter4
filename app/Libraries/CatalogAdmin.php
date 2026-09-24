<?php

namespace App\Libraries;

use App\Models\BranchModel;
use App\Models\CategoryModel;
use App\Models\CompanyModel;
use App\Models\SubCategoryModel;

/**
 * Definición de los catálogos que se administran desde el panel.
 *
 * Cada catálogo describe su tabla, clave, campos del formulario y las
 * tablas que lo referencian (para mostrar su uso y evitar borrar
 * registros que están en uso). Para agregar un catálogo nuevo basta
 * con añadir una entrada aquí.
 */
class CatalogAdmin
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'companies' => [
                'title'    => 'Compañías',
                'singular' => 'compañía',
                'icon'     => 'building',
                'model'    => CompanyModel::class,
                'table'    => 'Companies',
                'pk'       => 'CodCompanies',
                'autoPk'   => false,
                'name'     => 'Companies',
                'order'    => 'Companies',
                'search'   => ['CodCompanies', 'Companies'],
                'fields'   => [
                    'CodCompanies' => ['label' => 'Código', 'type' => 'text', 'max' => 5, 'required' => true, 'hint' => 'Hasta 5 caracteres. No se puede cambiar después.'],
                    'Companies'    => ['label' => 'Nombre', 'type' => 'text', 'max' => 150, 'required' => true],
                ],
                'usage' => [
                    ['table' => 'Branches', 'column' => 'CodCompanies', 'label' => 'sucursales', 'one' => 'sucursal', 'link' => 'panel/admin/branches?company='],
                    ['table' => 'Tickets', 'column' => 'CodCompanies', 'label' => 'tickets', 'one' => 'ticket', 'link' => 'panel/tickets?company='],
                    ['table' => 'Users', 'column' => 'CodCompanies', 'label' => 'usuarios', 'one' => 'usuario'],
                ],
            ],

            'branches' => [
                'title'    => 'Sucursales',
                'singular' => 'sucursal',
                'icon'     => 'building',
                'model'    => BranchModel::class,
                'table'    => 'Branches',
                'pk'       => 'CodBranches',
                'autoPk'   => false,
                'name'     => 'Branches',
                'order'    => 'Branches',
                'search'   => ['CodBranches', 'Branches'],
                'parent'   => ['field' => 'CodCompanies', 'param' => 'company', 'catalog' => 'companies', 'label' => 'Compañía'],
                'fields'   => [
                    'CodBranches'  => ['label' => 'Código', 'type' => 'text', 'max' => 5, 'required' => true, 'hint' => 'Hasta 5 caracteres, único entre todas las compañías. No se puede cambiar después.'],
                    'Branches'     => ['label' => 'Nombre', 'type' => 'text', 'max' => 150, 'required' => true],
                    'CodCompanies' => ['label' => 'Compañía', 'type' => 'select', 'options' => 'companies', 'required' => true],
                ],
                'usage' => [
                    ['table' => 'Tickets', 'column' => 'CodBranches', 'label' => 'tickets', 'one' => 'ticket', 'link' => 'panel/tickets?branch='],
                    ['table' => 'Users', 'column' => 'CodBranches', 'label' => 'usuarios', 'one' => 'usuario'],
                ],
            ],

            'categories' => [
                'title'    => 'Categorías',
                'singular' => 'categoría',
                'icon'     => 'tag',
                'model'    => CategoryModel::class,
                'table'    => 'Category',
                'pk'       => 'IdCategory',
                'autoPk'   => true,
                'name'     => 'Category',
                'order'    => 'Category',
                'search'   => ['Category', 'Description'],
                'fields'   => [
                    'Category'    => ['label' => 'Nombre', 'type' => 'text', 'max' => 40, 'required' => true],
                    'Description' => ['label' => 'Descripción', 'type' => 'textarea', 'max' => 155, 'required' => false],
                ],
                'usage' => [
                    ['table' => 'SubCategory', 'column' => 'IdCategory', 'label' => 'subcategorías', 'one' => 'subcategoría', 'link' => 'panel/admin/subcategories?category='],
                    ['table' => 'Tickets', 'column' => 'IdCategory', 'label' => 'tickets', 'one' => 'ticket', 'link' => 'panel/tickets?category='],
                ],
            ],

            'subcategories' => [
                'title'    => 'Subcategorías',
                'singular' => 'subcategoría',
                'icon'     => 'tag',
                'model'    => SubCategoryModel::class,
                'table'    => 'SubCategory',
                'pk'       => 'IdSubCategory',
                'autoPk'   => true,
                'name'     => 'SubCategory',
                'order'    => 'SubCategory',
                'search'   => ['SubCategory'],
                'parent'   => ['field' => 'IdCategory', 'param' => 'category', 'catalog' => 'categories', 'label' => 'Categoría'],
                'fields'   => [
                    'SubCategory' => ['label' => 'Nombre', 'type' => 'text', 'max' => 100, 'required' => true],
                    'IdCategory'  => ['label' => 'Categoría', 'type' => 'select', 'options' => 'categories', 'required' => true],
                ],
                'usage' => [
                    ['table' => 'Tickets', 'column' => 'IdSubCategory', 'label' => 'tickets', 'one' => 'ticket'],
                ],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        $definition = self::definitions()[$key] ?? null;

        return $definition === null ? null : $definition + ['key' => $key];
    }

    /**
     * Opciones [valor => etiqueta] de un catálogo, para los selects.
     *
     * @return array<string, string>
     */
    public static function options(string $key): array
    {
        $def = self::get($key);

        if ($def === null) {
            return [];
        }

        $rows = db_connect()->table($def['table'])
            ->select("{$def['pk']}, {$def['name']}")
            ->orderBy($def['order'])
            ->get()
            ->getResultArray();

        $options = [];

        foreach ($rows as $row) {
            $options[(string) $row[$def['pk']]] = (string) $row[$def['name']];
        }

        return $options;
    }

    /**
     * Cuántos registros de otras tablas usan cada id.
     *
     * @param list<int|string> $ids
     *
     * @return array<string, array<int, int>> [id => [índice de usage => total]]
     */
    public static function usageCounts(array $def, array $ids): array
    {
        $counts = [];

        if ($ids === []) {
            return $counts;
        }

        $db = db_connect();

        foreach ($def['usage'] as $index => $usage) {
            $rows = $db->table($usage['table'])
                ->select($usage['column'])
                ->selectCount($usage['column'], 'total')
                ->whereIn($usage['column'], $ids)
                ->groupBy($usage['column'])
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $counts[(string) $row[$usage['column']]][$index] = (int) $row['total'];
            }
        }

        return $counts;
    }
}
