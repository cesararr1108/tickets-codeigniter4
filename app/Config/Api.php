<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Credenciales exigidas por app/Filters/ApiAuthFilter.php para autenticar
 * las peticiones a la API mediante headers.
 *
 * Se definen en el archivo .env como:
 *   api.token = ...
 *   api.key   = ...
 */
class Api extends BaseConfig
{
    /**
     * Nombre del header que debe traer el token (formato "Bearer {token}").
     */
    public string $tokenHeader = 'Authorization';

    /**
     * Nombre del header que debe traer la clave de la API.
     */
    public string $keyHeader = 'X-Api-Key';

    /**
     * Token esperado. Se define en .env como api.token.
     */
    public string $token = '';

    /**
     * Clave esperada. Se define en .env como api.key.
     */
    public string $key = '';
}
