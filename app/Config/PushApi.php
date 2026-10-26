<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * API de notificaciones push (repositorio notificaciones-push-roma).
 *
 * Este sistema ya no habla con Firebase directamente: registra los tokens de
 * los navegadores y envía los avisos a través de esa API.
 *
 * Valores en .env:
 *   pushapi.baseURL = https://push.tu-dominio.com/api/v1
 *   pushapi.apiKey  = pk_xxxxxxxxxxxx.yyyy...   (scopes: tokens,notificaciones)
 *
 * La API key es un secreto de servidor: el navegador (panel y widget) nunca la
 * recibe; registra su token en este backend y este lo reenvía a la API.
 */
class PushApi extends BaseConfig
{
    /** URL base de la API, incluida la versión (…/api/v1). Vacío = no se envían push. */
    public string $baseURL = '';

    /** API key de la organización (pk_<prefijo>.<secreto>). */
    public string $apiKey = '';

    /**
     * Organización (4 caracteres). Solo hace falta con llaves globales; con una
     * llave atada a una organización se deja vacío (la API la toma de la llave).
     */
    public string $organizacion = '';

    /** Oficina (4 caracteres [A-Z0-9]) para usuarios sin sede o con un código que no se pueda convertir. */
    public string $oficinaDefault = '0000';

    /** Rol con el que se registran los navegadores del panel (sirve para avisar "a todo el panel"). */
    public string $rolPanel = 'PANEL';

    /** Rol para los navegadores del widget cuando el anfitrión no envía data-rol. */
    public string $rolWidget = 'WIDGET';

    /**
     * true = la API encola el envío y responde de inmediato (requiere su worker:
     * php spark push:worker). false = la API envía en la misma petición si son
     * pocos destinatarios y devuelve el resultado.
     */
    public bool $asincrono = false;

    /** Timeout (segundos) de las llamadas a la API. */
    public int $timeout = 8;
}
