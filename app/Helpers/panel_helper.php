<?php

use Config\Tickets;

if (! function_exists('tickets_config')) {
    function tickets_config(): Tickets
    {
        return config(Tickets::class);
    }
}

if (! function_exists('status_label')) {
    function status_label(?string $status): string
    {
        return tickets_config()->statuses[$status] ?? ucfirst((string) $status);
    }
}

if (! function_exists('priority_label')) {
    function priority_label(?string $priority): string
    {
        return tickets_config()->priorities[$priority] ?? ucfirst((string) $priority);
    }
}

if (! function_exists('status_badge')) {
    function status_badge(?string $status): string
    {
        return '<span class="badge badge-status badge-' . esc((string) $status, 'attr') . '">'
            . esc(status_label($status)) . '</span>';
    }
}

if (! function_exists('priority_badge')) {
    function priority_badge(?string $priority): string
    {
        return '<span class="badge badge-prio badge-' . esc((string) $priority, 'attr') . '">'
            . '<span class="dot"></span>' . esc(priority_label($priority)) . '</span>';
    }
}

if (! function_exists('ticket_code')) {
    function ticket_code(int|string $id): string
    {
        return 'TK-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('utc_to_local')) {
    /**
     * Convierte una fecha UTC de la BD a la zona horaria de visualización.
     */
    function utc_to_local(?string $utc): ?DateTimeImmutable
    {
        if ($utc === null || $utc === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        } catch (Exception) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone(tickets_config()->displayTimezone));
    }
}

if (! function_exists('format_date')) {
    function format_date(?string $utc, string $format = 'd/m/Y H:i'): string
    {
        return utc_to_local($utc)?->format($format) ?? '—';
    }
}

if (! function_exists('time_ago')) {
    function time_ago(?string $utc): string
    {
        $date = utc_to_local($utc);

        if ($date === null) {
            return '—';
        }

        $seconds = time() - $date->getTimestamp();

        return match (true) {
            $seconds < 60     => 'hace un momento',
            $seconds < 3600   => 'hace ' . intdiv($seconds, 60) . ' min',
            $seconds < 86400  => 'hace ' . intdiv($seconds, 3600) . ' h',
            $seconds < 172800 => 'ayer',
            $seconds < 604800 => 'hace ' . intdiv($seconds, 86400) . ' días',
            default           => $date->format('d/m/Y'),
        };
    }
}

if (! function_exists('age_hours')) {
    function age_hours(?string $utc): float
    {
        $date = utc_to_local($utc);

        return $date ? max(0, (time() - $date->getTimestamp()) / 3600) : 0;
    }
}

if (! function_exists('target_state')) {
    /**
     * Estado de la meta de atención: ok | warn | late | done.
     */
    function target_state(array $ticket): string
    {
        if (($ticket['Status'] ?? '') === 'cerrado') {
            return 'done';
        }

        $target = tickets_config()->targetHours[$ticket['Priority'] ?? ''] ?? 72;
        $age    = age_hours($ticket['CreatedAt'] ?? null);

        return match (true) {
            $age >= $target        => 'late',
            $age >= $target * 0.75 => 'warn',
            default                => 'ok',
        };
    }
}

if (! function_exists('format_age')) {
    function format_age(?string $utc): string
    {
        $hours = age_hours($utc);

        return $hours < 24
            ? max(1, (int) round($hours)) . ' h'
            : (int) floor($hours / 24) . ' d';
    }
}

if (! function_exists('initials')) {
    function initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name)) ?: [];
        $out   = '';

        foreach (array_slice(array_filter($words), 0, 2) as $word) {
            $out .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $out !== '' ? $out : '?';
    }
}

if (! function_exists('chart_color')) {
    function chart_color(int $index): string
    {
        $palette = ['#2f6fdf', '#ea6a2f', '#1faa7a', '#e8a200', '#e5779f', '#5b4fcf', '#0e9fb3', '#d64545'];

        return $palette[$index % count($palette)];
    }
}

if (! function_exists('panel_user')) {
    function panel_user(): ?array
    {
        return session('panelUser');
    }
}

if (! function_exists('query_with')) {
    /**
     * URL actual con parámetros de query modificados.
     */
    function query_with(array $changes): string
    {
        $query = array_merge(service('request')->getGet() ?? [], $changes);
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');

        return current_url() . ($query ? '?' . http_build_query($query) : '');
    }
}

if (! function_exists('icon')) {
    /**
     * Icono SVG en línea (trazo, 24x24).
     */
    function icon(string $name, string $class = 'icon'): string
    {
        static $paths = [
            'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
            'plus'      => '<path d="M12 5v14M5 12h14"/>',
            'list'      => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
            'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'inbox'     => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13L22 12v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z"/>',
            'chart'     => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
            'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'search'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
            'moon'      => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
            'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
            'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'check'     => '<path d="M20 6L9 17l-5-5"/>',
            'alert'     => '<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 17.5v.5"/>',
            'flag'      => '<path d="M4 22V4M4 4h12l-2 4 2 4H4"/>',
            'send'      => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
            'paperclip' => '<path d="M21 11.5l-8.5 8.5a5 5 0 0 1-7-7L14 4.5a3.3 3.3 0 0 1 4.7 4.7L10.2 17.7a1.7 1.7 0 0 1-2.4-2.4L15.5 7.6"/>',
            'arrow-left'=> '<path d="M19 12H5M11 18l-6-6 6-6"/>',
            'chat'      => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>',
            'building'  => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M10 21v-3h4v3"/>',
            'tag'       => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
            'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
            'filter'    => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
            'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'x'         => '<path d="M18 6L6 18M6 6l12 12"/>',
            'trending'  => '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
            'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        ];

        return '<svg class="' . esc($class, 'attr') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . ($paths[$name] ?? '') . '</svg>';
    }
}
