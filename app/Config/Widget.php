<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración de la API pública del widget (/widget/*).
 *
 * Todo se define en el .env del servidor:
 *
 *   widget.secret  = <cadena aleatoria de 32+ caracteres>
 *   widget.origins = 'https://intranet.empresa.com,https://portal.empresa.com'
 *
 * El secreto se comparte SOLO con el servidor de la página anfitriona, que
 * lo usa para firmar los tokens. Nunca debe aparecer en JavaScript.
 */
class Widget extends BaseConfig
{
    /**
     * Secreto para firmar y verificar los tokens (HS256). Mínimo 32 caracteres.
     * Genera uno con:  php -r 'echo bin2hex(random_bytes(32));'
     */
    public string $secret = '';

    /**
     * Dominios (origin) autorizados a usar la API desde un navegador,
     * separados por coma. Vacío = ningún dominio externo (CORS cerrado).
     */
    public string $origins = '';

    /**
     * Vigencia máxima aceptada para un token, en segundos. Un token cuyo
     * exp supere este límite se rechaza, aunque esté bien firmado.
     */
    public int $maxTokenTtl = 3600;

    /**
     * Peticiones por minuto y por IP a toda la API del widget.
     */
    public int $requestsPerMinute = 90;

    /**
     * Tickets que puede crear una misma IP (y un mismo correo) por hora.
     */
    public int $ticketsPerHour = 10;

    /**
     * Adjuntos: tamaño máximo (MB) y extensiones permitidas.
     */
    public int $maxUploadMb = 5;

    /**
     * @var list<string>
     */
    public array $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx'];

    /**
     * @return list<string>
     */
    public function allowedOrigins(): array
    {
        $origins = array_map(
            static fn (string $origin) => rtrim(trim($origin), '/'),
            explode(',', $this->origins)
        );

        return array_values(array_filter($origins));
    }
}
