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

            $title = 'Nuevo ticket ' . ticket_code($id);
            $text  = mb_strlen($subject) > 140 ? mb_substr($subject, 0, 137) . '…' : $subject;
            $data  = ['type' => 'ticket_created', 'ticketId' => $id];
            $link  = site_url('panel/tickets/' . $id);

            $this->toStaff($ticket, $title, $text, $data, $link, $actorId);
        } catch (\Throwable $e) {
            log_message('error', '[Push] ' . $e->getMessage());
        }
    }

    /**
     * Mensaje del solicitante en el chat -> responsable del ticket (o, sin responsable,
     * los mismos destinatarios que un ticket nuevo).
     *
     * @param array<string, mixed> $ticket
     */
    public function chatFromRequester(array $ticket, string $message, string $senderName): void
    {
        try {
            if (! $this->push->enabled()) {
                return;
            }

            $assigned = (string) ($ticket['AssignedUserId'] ?? '');
            $title    = 'Mensaje en el ticket ' . $this->code($ticket);
            $text     = $this->short(($senderName !== '' ? $senderName . ': ' : '') . $message);
            $data     = ['type' => 'chat_message', 'ticketId' => (int) $ticket['IdTicket']];
            $link     = site_url('panel/tickets/' . (int) $ticket['IdTicket'] . '#chat');

            if ($assigned !== '') {
                $emails = array_column($this->users([], null, $assigned), 'Email');
                $this->push->sendToEmails($emails, $title, $text, $data, $link, true);

                return;
            }

            $this->toStaff($ticket, $title, $text, $data, $link);
        } catch (\Throwable $e) {
            log_message('error', '[Push] ' . $e->getMessage());
        }
    }

    /**
     * Mensaje del agente en el chat -> solicitante.
     *
     * @param array<string, mixed> $ticket
     */
    public function chatFromAgent(array $ticket, string $message, string $agentName): void
    {
        $this->toRequester(
            $ticket,
            'chat_message',
            'Nuevo mensaje en tu ticket ' . $this->code($ticket),
            $this->short(($agentName !== '' ? $agentName . ': ' : '') . $message),
        );
    }

    /**
     * Destinatarios del panel según fcm.useRoutes (todos los dispositivos del panel o reglas).
     *
     * @param array<string, mixed>  $ticket
     * @param array<string, scalar> $data
     */
    private function toStaff(array $ticket, string $title, string $text, array $data, string $link, ?string $actorId = null): void
    {
        // Paso 1 (por defecto): a todos los dispositivos registrados desde el panel.
        if (! config(\Config\Fcm::class)->useRoutes) {
            $this->push->sendToPanel($title, $text, $data, $link);

            return;
        }

        // Paso 2 (fcm.useRoutes = true): según las reglas de Panel > Notificaciones.
        $emails = $this->recipients($ticket, $actorId);

        if (config(\Config\Fcm::class)->debug) {
            log_message('error', '[FCM debug] Ticket ' . $ticket['IdTicket'] . ' (compañía ' . ($ticket['CodCompanies'] ?? '?') . ', sucursal ' . ($ticket['CodBranches'] ?? '?') . ') -> destinatarios: ' . json_encode($emails));
        }

        $this->push->sendToEmails($emails, $title, $text, $data, $link, true);
    }

    /**
     * Avisos al solicitante (llegan a los navegadores registrados desde el widget
     * con el correo del ticket; no a los del panel).
     */
    public function requesterAssigned(array $ticket): void
    {
        $name = '';

        if (! empty($ticket['AssignedUserId'])) {
            $user = db_connect()->table('Users')->select('FullName')->where('IdUser', $ticket['AssignedUserId'])->get()->getRowArray();
            $name = (string) ($user['FullName'] ?? '');
        }

        $this->toRequester(
            $ticket,
            'assigned',
            'Tu ticket ' . $this->code($ticket) . ' ya tiene responsable',
            $name !== '' ? $name . ' atenderá tu solicitud.' : 'Un técnico atenderá tu solicitud.',
        );
    }

    /** @param array<string, mixed> $ticket */
    public function requesterEscalated(array $ticket): void
    {
        $this->toRequester(
            $ticket,
            'escalated',
            'Tu ticket ' . $this->code($ticket) . ' fue escalado',
            'Un administrador está revisando tu solicitud.',
        );
    }

    /** @param array<string, mixed> $ticket Ticket ya actualizado */
    public function requesterStatus(array $ticket): void
    {
        $status = (string) ($ticket['Status'] ?? '');
        $label  = match ($status) {
            'cerrado'     => 'finalizado',
            'en_progreso' => 'en progreso',
            default       => 'abierto de nuevo',
        };

        $this->toRequester($ticket, 'status_' . $status, 'Tu ticket ' . $this->code($ticket) . ' está ' . $label, $this->subject($ticket));
    }

    /** @param array<string, mixed> $ticket */
    private function toRequester(array $ticket, string $type, string $title, string $body): void
    {
        try {
            $email = trim((string) ($ticket['RequesterEmail'] ?? ''));

            if ($email === '' || ! $this->push->enabled()) {
                return;
            }

            $results = $this->push->sendToEmails(
                [$email],
                $title,
                $body,
                ['type' => $type, 'ticketId' => (int) $ticket['IdTicket'], 'status' => (string) ($ticket['Status'] ?? '')],
                config(\Config\Fcm::class)->widgetUrl ?: null,
                false,
            );

            if (config(\Config\Fcm::class)->debug) {
                log_message('error', '[FCM debug] ' . $type . ' ticket ' . $ticket['IdTicket'] . ' -> solicitante ' . $email . ': ' . count($results) . ' dispositivo(s)');
            }
        } catch (\Throwable $e) {
            log_message('error', '[Push] ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $ticket */
    private function code(array $ticket): string
    {
        helper('panel');

        return ticket_code((int) $ticket['IdTicket']);
    }

    private function short(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        return mb_strlen($text) > 140 ? mb_substr($text, 0, 137) . '…' : $text;
    }

    /** @param array<string, mixed> $ticket */
    private function subject(array $ticket): string
    {
        $subject = trim((string) ($ticket['Subject'] ?? ''));

        return mb_strlen($subject) > 140 ? mb_substr($subject, 0, 137) . '…' : $subject;
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
