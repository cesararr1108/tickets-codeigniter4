<?php

namespace App\Libraries;

/**
 * Compañías en las que se ofrece cada categoría (tabla CategoryCompanies,
 * ver app/Database/sql/CategoriasPorCompania.sql).
 *
 * Una categoría sin compañías asignadas se ofrece en todas. Si la tabla aún
 * no existe, todas las categorías se ofrecen en todas las compañías.
 */
class CategoryCompanies
{
    public static function available(): bool
    {
        static $exists = null;

        return $exists ??= db_connect()->tableExists('CategoryCompanies');
    }

    /**
     * Compañías asignadas a varias categorías.
     *
     * @param list<int|string> $categoryIds
     *
     * @return array<string, list<string>> IdCategory => códigos de compañía
     */
    public static function forCategories(array $categoryIds): array
    {
        if ($categoryIds === [] || ! self::available()) {
            return [];
        }

        $out = [];

        $rows = db_connect()->table('CategoryCompanies')
            ->select('IdCategory, CodCompanies')
            ->whereIn('IdCategory', $categoryIds)
            ->orderBy('CodCompanies')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $out[(string) $row['IdCategory']][] = (string) $row['CodCompanies'];
        }

        return $out;
    }

    /**
     * Reemplaza las compañías de una categoría.
     *
     * @param list<string> $companies
     */
    public static function save(int $categoryId, array $companies): void
    {
        if (! self::available()) {
            return;
        }

        $db = db_connect();
        $db->table('CategoryCompanies')->where('IdCategory', $categoryId)->delete();

        foreach (array_unique($companies) as $company) {
            $db->table('CategoryCompanies')->insert(['IdCategory' => $categoryId, 'CodCompanies' => $company]);
        }
    }

    /**
     * Limita un query de categorías (alias de la tabla Category) a las que se
     * ofrecen en la compañía: asignadas a ella o sin compañías asignadas.
     */
    public static function scope($builder, string $company, string $alias = 'Category')
    {
        if (! self::available()) {
            return $builder;
        }

        $db      = db_connect();
        $company = $db->escape($company);

        return $builder->where(
            "(EXISTS (SELECT 1 FROM CategoryCompanies cc WHERE cc.IdCategory = {$alias}.IdCategory AND cc.CodCompanies = {$company})"
            . " OR NOT EXISTS (SELECT 1 FROM CategoryCompanies cc WHERE cc.IdCategory = {$alias}.IdCategory))",
            null,
            false
        );
    }

    /**
     * Categorías que se ofrecen en una compañía.
     *
     * @return list<array<string, mixed>>
     */
    public static function categoriesFor(string $company): array
    {
        $builder = db_connect()->table('Category')->orderBy('Category');

        return self::scope($builder, $company)->get()->getResultArray();
    }

    /**
     * ¿La categoría se ofrece en la compañía?
     */
    public static function allows(int $categoryId, string $company): bool
    {
        $builder = db_connect()->table('Category')->where('IdCategory', $categoryId);

        return self::scope($builder, $company)->countAllResults() > 0;
    }
}
