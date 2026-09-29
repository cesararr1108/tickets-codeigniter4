<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración del panel de tickets.
 */
class Tickets extends BaseConfig
{
    /**
     * Estados válidos (deben coincidir con CK_Tickets_Status).
     *
     * @var array<string, string>
     */
    public array $statuses = [
        'abierto'     => 'Abierto',
        'en_progreso' => 'En progreso',
        'cerrado'     => 'Cerrado',
    ];

    /**
     * Prioridades válidas (deben coincidir con CK_Tickets_Priority).
     *
     * @var array<string, string>
     */
    public array $priorities = [
        'alta'  => 'Alta',
        'media' => 'Media',
        'baja'  => 'Baja',
    ];

    /**
     * Meta de atención en horas por prioridad. Un ticket abierto o en
     * progreso que supera su meta se considera "fuera de meta".
     * La tabla Tickets no guarda SLA, así que se define aquí.
     *
     * @var array<string, int>
     */
    public array $targetHours = [
        'alta'  => 24,
        'media' => 72,
        'baja'  => 120,
    ];

    /**
     * Zona horaria para mostrar fechas. La BD guarda CreatedAt en UTC
     * (sysutcdatetime()).
     */
    public string $displayTimezone = 'America/Guayaquil';

    /**
     * Roles (Roles.Descripcion) que pueden administrar compañías, sucursales,
     * categorías y subcategorías. Vacío = cualquier usuario con sesión.
     *
     * Ejemplo: ['Administrador']
     *
     * @var list<string>
     */
    public array $adminRoles = [];

    /**
     * Redes desde las que se puede abrir /login y /panel (IPs o CIDR separados
     * por coma). Vacío = cualquier red. Ejemplo en el .env:
     *
     *   tickets.panelNetworks = '192.168.0.0/16,10.0.0.0/8'
     */
    public string $panelNetworks = '';

    /**
     * @return list<string>
     */
    public function allowedNetworks(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->panelNetworks))));
    }

    /**
     * Tickets por página en el listado.
     */
    public int $perPage = 20;

    /**
     * Cada cuántos segundos el chat consulta mensajes nuevos.
     */
    public int $chatPollSeconds = 10;
}
