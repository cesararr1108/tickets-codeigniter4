<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Notificaciones push con Firebase Cloud Messaging.
 * Los valores web (webConfig y vapidKey) son públicos; pueden sobrescribirse en .env (fcm.vapidKey).
 */
class Fcm extends BaseConfig
{
    /** Ruta absoluta al JSON de la cuenta de servicio (fuera del webroot). Vacío = no se envían push. */
    public string $credentialsPath = '';

    /** Modo diagnóstico (solo desarrollo): fcm.debug = true en .env. Muestra tokens y respuestas de FCM. */
    public bool $debug = false;

    /**
     * Aplicar las reglas de Panel > Notificaciones (compañía -> rol -> sucursal).
     * false (por ahora): cada ticket nuevo avisa a todos los dispositivos registrados desde el panel.
     * Se activa con fcm.useRoutes = true en .env.
     */
    public bool $useRoutes = false;

    /** Página que se abre al tocar una notificación dirigida al solicitante (la que aloja el widget). fcm.widgetUrl en .env. */
    public string $widgetUrl = 'https://www.pwmultiroma.com/calidad/views/Menu.php';

    /** Timeout (segundos) de las llamadas a Google. */
    public int $timeout = 5;

    /** Clave pública VAPID (la usa el panel para obtener su token). */
    public string $vapidKey = 'BOMCZUnVmsZv4nuhWXALeGC2m5AsebnR3tP15yDPIji51boQPr66bGEjZ_ZHfWbu68aPNJhOiqcNDeLXb6Olfvw';

    /**
     * Configuración web de Firebase (pública, es la misma que va en el navegador).
     *
     * @var array<string, string>
     */
    public array $webConfig = [
        'apiKey'            => 'AIzaSyCRaATvfSIIBR3DtGri3vL8tMMwj4AzECc',
        'authDomain'        => 'fcm-multiroma.firebaseapp.com',
        'projectId'         => 'fcm-multiroma',
        'storageBucket'     => 'fcm-multiroma.firebasestorage.app',
        'messagingSenderId' => '221123124298',
        'appId'             => '1:221123124298:web:8f61d4371e9bc3083f9ab3',
    ];
}
