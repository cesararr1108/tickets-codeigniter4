<?php

namespace App\Libraries;

use App\Models\NotificationRouteModel;

/**
 * Push al panel cuando entra un ticket nuevo.
 *
 * Destinatarios (usuarios activos del panel; no se avisa a quien creó el ticket,
 * salvo con fcm.debug = true para pruebas):
 *  1. Si la compañía del ticket tiene reglas (Panel > Notificaciones): los
 *     usuarios de esos roles que pertenecen a la sucursal del ticket. Si en
 *     esa sucursal no hay nadie con ese rol, se avisa a los de ese rol de
 *     cualquier sucursal para que el aviso no se pierda.
 *  2. Si no hay reglas: el responsable asignado o, si no hay, todos los
 *     usuarios activos.
 *
 * Solo se envía a los navegadores donde el usuario activó las notificaciones
 * desde el panel. Un fallo nunca interrumpe la creación del ticket.
 */
class TicketNotifier
{
    private FcmPush $push;

    public function __construct(?FcmPush $push = null)
    {
        $this->push = $push ?? new FcmPush();
    }

    /**
     * @param array<string, mixed> $ticket
     * @param string|null          $actorId Usuario que creó el ticket desde el panel (no se le avisa)
     */
    public function ticketCreated(array $ticket, ?string $actorId = null): void
    {
        try {
            if (! $this->push->enabled()) {
                return;
            }

            helper('panel');

            $id      = (int) $ticket['IdTicket'];
            $subject = trim((string) ($ticket['Subject'] ?? ''));

            $emails = $this->recipients($ticket, $actorId);

            if (config(\Config\Fcm::class)->debug) {
                log_message('error', '[FCM debug] Ticket ' . $id . ' (compañía ' . ($ticket['CodCompanies'] ?? '?') . ', sucursal ' . ($ticket['CodBranches'] ?? '?') . ') -> destinatarios: ' . json_encode($emails));
            }

            $this->push->sendToEmails(
                $emails,
                'Nuevo ticket ' . ticket_code($id),
                mb_strlen($subject) > 140 ? mb_substr($subject, 0, 137) . '…' : $subject,
                ['type' => 'ticket_created', 'ticketId' => $id],
                site_url('panel/tickets/' . $id),
                true,
            );
        } catch (\Throwable $e) {
            log_message('error', '[Push] ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $ticket
     *
     * @return list<string> Correos
     */
    private function recipients(array $ticket, ?string $actorId): array
    {
        $roles  = NotificationRouteModel::rolesFor((string) ($ticket['CodCompanies'] ?? ''));
        $branch = trim((string) ($ticket['CodBranches'] ?? ''));
        $users  = [];

        if ($roles !== []) {
            if ($branch !== '') {
                $users = $this->users($roles, $branch);
            }

            $users = $users ?: $this->users($roles);
        } elseif (($assigned = (string) ($ticket['AssignedUserId'] ?? '')) !== '') {
            $users = $this->users([], null, $assigned);
        } else {
            $users = $this->users();
        }

        $requester = strtolower(trim((string) ($ticket['RequesterEmail'] ?? '')));
        $emails    = [];

        // En modo debug (fcm.debug = true) también se avisa a quien creó el ticket, para poder probar con un solo usuario.
        $excludeCreator = ! config(\Config\Fcm::class)->debug;

        foreach ($users as $user) {
            if ($excludeCreator && ((string) $user['IdUser'] === (string) $actorId || strtolower(trim((string) $user['Email'])) === $requester)) {
                continue;
            }

            $emails[] = $user['Email'];
        }

        return $emails;
    }

    /**
     * @param list<int> $roles
     *
     * @return list<array<string, mixed>>
     */
    private function users(array $roles = [], ?string $branch = null, ?string $userId = null): array
    {
        $query = db_connect()->table('Users')->select('IdUser, Email')->where('IsActive', 1);

        if ($roles !== []) {
            $query->whereIn('RoleId', $roles);
        }

        if ($branch !== null) {
            $query->where('CodBranches', $branch);
        }

        if ($userId !== null) {
            $query->where('IdUser', $userId);
        }

        return $query->get()->getResultArray();
    }
}
