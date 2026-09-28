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
     * Campos que llena el área de TI desde el panel (el solicitante no los
     * ve en el widget), por formulario (TicketFormAnswers.FormKey).
     * Se guardan en TicketFormAnswers junto a las respuestas del formulario.
     * Estados tomados del Excel de requerimientos (hoja "Estados").
     *
     * type: select | text | textarea
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    public array $areaFields = [
        'proyecto' => [
            'estado_proyecto' => [
                'label'   => 'Estado del proyecto',
                'type'    => 'select',
                'options' => [
                    'Propuesto', 'En evaluación', 'Pendiente de aprobación', 'Aprobado',
                    'Planificación', 'En ejecución', 'En espera', 'En pruebas',
                    'Pendiente de aceptación', 'Implementado', 'Cerrado', 'Cancelado', 'Rechazado',
                ],
            ],
        ],
        'requerimiento' => [
            'estado_requerimiento' => [
                'label'   => 'Estado del requerimiento',
                'type'    => 'select',
                'options' => [
                    'Nuevo', 'En validación', 'Pendiente de aprobación', 'Aprobado', 'Asignado',
                    'En ejecución', 'En espera', 'Atendido', 'Cerrado', 'Rechazado', 'Cancelado',
                ],
            ],
        ],
        'incidente' => [
            'estado_incidente' => [
                'label'   => 'Estado del incidente',
                'type'    => 'select',
                'options' => ['Nuevo', 'Asignado', 'En atención', 'En espera', 'Resuelto', 'Cerrado', 'Cancelado'],
            ],
            'diagnostico' => ['label' => 'Diagnóstico', 'type' => 'textarea'],
            'solucion'    => ['label' => 'Solución', 'type' => 'textarea'],
        ],
    ];

    /**
     * Tickets por página en el listado.
     */
    public int $perPage = 20;

    /**
     * Cada cuántos segundos el chat consulta mensajes nuevos.
     */
    public int $chatPollSeconds = 10;
}
