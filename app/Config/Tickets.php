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
     * Tickets por página en el listado.
     */
    public int $perPage = 20;

    /**
     * Cada cuántos segundos el chat consulta mensajes nuevos.
     */
    public int $chatPollSeconds = 10;
}
