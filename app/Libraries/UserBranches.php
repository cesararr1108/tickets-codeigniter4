<?php

namespace App\Libraries;

/**
 * Sedes de un usuario: la principal (Users.CodBranches) más las adicionales
 * (tabla UserBranches, ver app/Database/sql/SedesYRecuperacion.sql).
 */
class UserBranches
{
    /** ¿Existe la tabla de sedes adicionales? Si no, solo cuenta la sede principal. */
    public static function available(): bool
    {
        static $exists = null;

        return $exists ??= db_connect()->tableExists('UserBranches');
    }

    /**
     * Sedes adicionales de varios usuarios.
     *
     * @param list<string> $userIds
     *
     * @return array<string, list<string>> IdUser => códigos de sede
     */
    public static function extrasFor(array $userIds): array
    {
        if ($userIds === [] || ! self::available()) {
            return [];
        }

        $out = [];

        foreach (db_connect()->table('UserBranches')->select('IdUser, CodBranches')->whereIn('IdUser', $userIds)->get()->getResultArray() as $row) {
            $out[(string) $row['IdUser']][] = (string) $row['CodBranches'];
        }

        return $out;
    }

    /**
     * Reemplaza las sedes adicionales de un usuario.
     *
     * @param list<string> $branches
     */
    public static function saveExtras(string $userId, array $branches): void
    {
        if (! self::available()) {
            return;
        }

        $db = db_connect();
        $db->table('UserBranches')->where('IdUser', $userId)->delete();

        foreach (array_unique($branches) as $branch) {
            $db->table('UserBranches')->insert(['IdUser' => $userId, 'CodBranches' => $branch]);
        }
    }

    /**
     * Usuarios (IdUser) que tienen asignada una sede, ya sea como principal o adicional.
     *
     * @return list<string>
     */
    public static function usersWithBranch(string $branch): array
    {
        $db  = db_connect();
        $ids = array_column($db->table('Users')->select('IdUser')->where('CodBranches', $branch)->get()->getResultArray(), 'IdUser');

        if (self::available()) {
            $ids = array_merge($ids, array_column($db->table('UserBranches')->select('IdUser')->where('CodBranches', $branch)->get()->getResultArray(), 'IdUser'));
        }

        return array_values(array_unique(array_map('strval', $ids)));
    }
}
