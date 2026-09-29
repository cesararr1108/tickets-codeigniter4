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
     * Roles (Roles.Descripcion) de ADMINISTRADOR: administran catálogos y
     * usuarios, asignan tickets a cualquiera y atienden los escalamientos.
     * Los demás usuarios del panel son TÉCNICOS: solo pueden tomar tickets
     * para sí mismos, gestionar los suyos y escalarlos.
     *
     * Mientras ningún usuario activo tenga uno de estos roles, todos se
     * tratan como administradores (para no quedar sin acceso al instalar).
     *
     * @var list<string>
     */
    public array $adminRoles = ['Administrador'];

    /**
     * Rol que se sugiere al crear usuarios técnicos.
     */
    public string $technicianRole = 'Técnico';

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
