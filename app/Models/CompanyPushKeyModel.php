<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * API key de la API de notificaciones de cada compañía (tabla CompanyPushKeys).
 * Ver app/Database/sql/PushApiKeys.sql. La llave nunca se devuelve al navegador.
 */
class CompanyPushKeyModel extends Model
{
    protected $table            = 'CompanyPushKeys';
    protected $primaryKey       = 'CodCompanies';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = ['CodCompanies', 'ApiKey', 'UpdatedAt'];

    /** Formato de las llaves de la API: pk_<prefijo de 12>.<secreto de 64>. */
    public const KEY_PATTERN = '/^pk_[a-f0-9]{12}\.[a-f0-9]{64}$/';

    /** @var array<string, string|null> Llaves ya consultadas en esta petición. */
    private static array $cache = [];

    /** ¿Existe la tabla? Si no, solo se usa la llave del .env (pushapi.apiKey). */
    public static function available(): bool
    {
        static $exists = null;

        return $exists ??= db_connect()->tableExists('CompanyPushKeys');
    }

    /**
     * Llave de una compañía (null si no tiene).
     */
    public static function keyFor(?string $company): ?string
    {
        $company = trim((string) $company);

        if ($company === '' || ! self::available()) {
            return null;
        }

        if (! array_key_exists($company, self::$cache)) {
            $row = db_connect()->table('CompanyPushKeys')->select('ApiKey')->where('CodCompanies', $company)->get()->getRowArray();
            $key = trim((string) ($row['ApiKey'] ?? ''));

            self::$cache[$company] = $key === '' ? null : $key;
        }

        return self::$cache[$company];
    }

    /**
     * Compañías que tienen llave.
     *
     * @return list<string>
     */
    public static function companies(): array
    {
        if (! self::available()) {
            return [];
        }

        return array_column(db_connect()->table('CompanyPushKeys')->select('CodCompanies')->get()->getResultArray(), 'CodCompanies');
    }

    public function saveKey(string $company, string $key): void
    {
        $row = ['ApiKey' => trim($key), 'UpdatedAt' => gmdate('Y-m-d H:i:s')];

        if ($this->where('CodCompanies', $company)->countAllResults() > 0) {
            $this->builder()->where('CodCompanies', $company)->update($row);
        } else {
            $this->builder()->insert(['CodCompanies' => $company] + $row);
        }

        unset(self::$cache[$company]);
    }

    public function removeKey(string $company): void
    {
        $this->builder()->where('CodCompanies', $company)->delete();
        unset(self::$cache[$company]);
    }

    /**
     * Parte pública de la llave (pk_<prefijo>) para mostrarla sin revelar el secreto.
     */
    public static function mask(string $key): string
    {
        return (strstr($key, '.', true) ?: substr($key, 0, 6)) . '.…';
    }
}
