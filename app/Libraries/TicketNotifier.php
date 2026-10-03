<?php

namespace App\Libraries;

/**
 * Push al panel cuando entra un ticket nuevo.
 *
 * Destinatarios: el responsable asignado o, si no hay, todos los usuarios
 * activos del panel (se resuelven por correo contra t_fcm_tokens).
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

            $db       = db_connect();
            $assigned = (string) ($ticket['AssignedUserId'] ?? '');
            $users    = $db->table('Users')->select('IdUser, Email')->where('IsActive', 1);

            if ($assigned !== '') {
                $users->where('IdUser', $assigned);
            }

            $emails = [];

            foreach ($users->get()->getResultArray() as $user) {
                if ((string) $user['IdUser'] !== (string) $actorId) {
                    $emails[] = $user['Email'];
                }
            }

            $id      = (int) $ticket['IdTicket'];
            $subject = trim((string) ($ticket['Subject'] ?? ''));

            $this->push->sendToEmails(
                $emails,
                'Nuevo ticket ' . ticket_code($id),
                mb_strlen($subject) > 140 ? mb_substr($subject, 0, 137) . '…' : $subject,
                ['type' => 'ticket_created', 'ticketId' => $id],
                site_url('panel/tickets/' . $id),
            );
        } catch (\Throwable $e) {
            log_message('error', '[Push] ' . $e->getMessage());
        }
    }
}
