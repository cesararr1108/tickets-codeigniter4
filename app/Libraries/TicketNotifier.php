<?php

namespace App\Libraries;

use App\Models\NotificationRouteModel;

/**
 * Push al panel cuando entra un ticket nuevo.
 *
 * Destinatarios:
 *  1. Si la compañía del ticket tiene reglas (Panel > Notificaciones): los
 *     usuarios activos de esos roles.
 *  2. Si no tiene reglas: el responsable asignado o, si no hay, todos los
 *     usuarios activos del panel.
 *
 * Los correos se resuelven contra t_fcm_tokens. Un fallo nunca interrumpe
 * la creación del ticket.
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

            $this->push->sendToEmails(
                $this->recipients($ticket, $actorId),
                'Nuevo ticket ' . ticket_code($id),
                mb_strlen($subject) > 140 ? mb_substr($subject, 0, 137) . '…' : $subject,
                ['type' => 'ticket_created', 'ticketId' => $id],
                site_url('panel/tickets/' . $id),
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
        $users = db_connect()->table('Users')->select('IdUser, Email')->where('IsActive', 1);
        $roles = NotificationRouteModel::rolesFor((string) ($ticket['CodCompanies'] ?? ''));

        if ($roles !== []) {
            $users->whereIn('RoleId', $roles);
        } elseif (($assigned = (string) ($ticket['AssignedUserId'] ?? '')) !== '') {
            $users->where('IdUser', $assigned);
        }

        $emails = [];

        foreach ($users->get()->getResultArray() as $user) {
            if ((string) $user['IdUser'] !== (string) $actorId) {
                $emails[] = $user['Email'];
            }
        }

        return $emails;
    }
}
