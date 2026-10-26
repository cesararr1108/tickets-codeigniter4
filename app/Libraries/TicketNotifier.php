<?php

namespace App\Libraries;

use App\Models\CompanyPushKeyModel;
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
 * El envío y el registro de dispositivos los hace la API de notificaciones
 * (Config\PushApi): se envía a todos los navegadores donde esos correos
 * activaron las notificaciones. La API tiene una llave por compañía: a cada
 * usuario del panel se le avisa con la llave de SU compañía (con la que registró
 * su navegador) y al solicitante con la de la compañía del ticket.
 * Un fallo nunca interrumpe la creación del ticket.
 */
class TicketNotifier
{
    /** @var array<string, PushApiClient> Clientes por compañía. */
    private array $clients = [];

    /**
     * @param array<string, mixed> $ticket
     * @param string|null          $actorId Usuario que creó el ticket desde el panel (no se le avisa)
     */
    public function ticketCreated(array $ticket, ?string $actorId = null): void
    {
        try {
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
            $assigned = (string) ($ticket['AssignedUserId'] ?? '');
            $title    = 'Mensaje en el ticket ' . $this->code($ticket);
            $text     = $this->short(($senderName !== '' ? $senderName . ': ' : '') . $message);
            $data     = ['type' => 'chat_message', 'ticketId' => (int) $ticket['IdTicket']];
            $link     = site_url('panel/tickets/' . (int) $ticket['IdTicket'] . '#chat');

            if ($assigned !== '') {
                $this->sendToUsers($this->users([], null, $assigned), $title, $text, $data, $link);

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
        $fcm = config(\Config\Fcm::class);

        // Sin reglas ni filtro por sede: a todos los navegadores registrados desde el panel (rol pushapi.rolPanel).
        if (! $fcm->useRoutes && ! $fcm->filterByBranch) {
            $this->sendToAllPanels($title, $text, $data, $link);

            return;
        }

        $users = $this->recipients($ticket, $actorId);

        if ($fcm->debug) {
            log_message('error', '[FCM debug] Ticket ' . $ticket['IdTicket'] . ' (compañía ' . ($ticket['CodCompanies'] ?? '?') . ', sucursal ' . ($ticket['CodBranches'] ?? '?') . ') -> destinatarios: ' . json_encode(array_column($users, 'Email')));
        }

        $this->sendToUsers($users, $title, $text, $data, $link);
    }

    /**
     * Agrupa a los usuarios por compañía y envía a cada grupo con la llave de su compañía.
     *
     * @param list<array<string, mixed>> $users Filas de Users (Email, CodCompanies)
     * @param array<string, scalar>      $data
     */
    private function sendToUsers(array $users, string $title, string $text, array $data, string $link): void
    {
        $groups = [];

        foreach ($users as $user) {
            $groups[trim((string) ($user['CodCompanies'] ?? ''))][] = (string) $user['Email'];
        }

        foreach ($groups as $company => $emails) {
            $this->client((string) $company)->sendToEmails($emails, $title, $text, $data, $link);
        }
    }

    /**
     * Todos los navegadores del panel: un envío por cada llave distinta
     * (las de las compañías y la del .env).
     *
     * @param array<string, scalar> $data
     */
    private function sendToAllPanels(string $title, string $text, array $data, string $link): void
    {
        $seen = [];

        foreach (array_merge([''], CompanyPushKeyModel::companies()) as $company) {
            $key = (string) ($company === '' ? config(\Config\PushApi::class)->apiKey : CompanyPushKeyModel::keyFor($company));

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $client     = $company === '' ? new PushApiClient() : $this->client($company);
            $client->sendToPanel($title, $text, $data, $link);
        }
    }

    private function client(string $company): PushApiClient
    {
        return $this->clients[$company] ??= PushApiClient::forCompany($company);
    }

    /**
     * Avisos al solicitante (llegan a los navegadores registrados con el correo del ticket).
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

            $push  = $this->client(trim((string) ($ticket['CodCompanies'] ?? '')));

            if ($email === '' || ! $push->enabled()) {
                return;
            }

            $result = $push->sendToEmails(
                [$email],
                $title,
                $body,
                ['type' => $type, 'ticketId' => (int) $ticket['IdTicket'], 'status' => (string) ($ticket['Status'] ?? '')],
                config(\Config\Fcm::class)->widgetUrl ?: null,
            );

            if (config(\Config\Fcm::class)->debug) {
                log_message('error', '[FCM debug] ' . $type . ' ticket ' . $ticket['IdTicket'] . ' -> solicitante ' . $email . ': ' . json_encode($result));
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
     * Usuarios (IdUser, Email, CodCompanies) que deben recibir el aviso:
     *  - ticket con responsable: solo el responsable;
     *  - si no: usuarios activos de los roles de la regla de la compañía (fcm.useRoutes) y/o
     *    que tengan asignada la sede del ticket (fcm.filterByBranch, sede principal o adicional).
     *
     * @param array<string, mixed> $ticket
     *
     * @return list<array<string, mixed>>
     */
    private function recipients(array $ticket, ?string $actorId): array
    {
        $fcm      = config(\Config\Fcm::class);
        $assigned = (string) ($ticket['AssignedUserId'] ?? '');
        $branch   = trim((string) ($ticket['CodBranches'] ?? ''));

        if ($assigned !== '') {
            $users = $this->users([], null, $assigned);
        } else {
            $roles = $fcm->useRoutes ? NotificationRouteModel::rolesFor((string) ($ticket['CodCompanies'] ?? '')) : [];
            $ids   = $fcm->filterByBranch && $branch !== '' ? UserBranches::usersWithBranch($branch) : null;

            // Nadie tiene esa sede: no se avisa a otros (el ticket sigue visible en el panel).
            $users = $ids === [] ? [] : $this->users($roles, $ids);
        }

        $requester = strtolower(trim((string) ($ticket['RequesterEmail'] ?? '')));
        // En modo debug (fcm.debug = true) también se avisa a quien creó el ticket, para probar con un solo usuario.
        $excludeCreator = ! $fcm->debug;
        $result = [];

        foreach ($users as $user) {
            if ($excludeCreator && ((string) $user['IdUser'] === (string) $actorId || strtolower(trim((string) $user['Email'])) === $requester)) {
                continue;
            }

            $result[] = $user;
        }

        return $result;
    }

    /**
     * Usuarios activos, opcionalmente filtrados por roles, por una lista de IdUser o por un IdUser.
     *
     * @param list<int>         $roles
     * @param list<string>|null $ids
     *
     * @return list<array<string, mixed>>
     */
    private function users(array $roles = [], ?array $ids = null, ?string $userId = null): array
    {
        $query = db_connect()->table('Users')->select('IdUser, Email, CodCompanies')->where('IsActive', 1);

        if ($roles !== []) {
            $query->whereIn('RoleId', $roles);
        }

        if ($ids !== null) {
            $query->whereIn('IdUser', $ids);
        }

        if ($userId !== null) {
            $query->where('IdUser', $userId);
        }

        return $query->get()->getResultArray();
    }
}
