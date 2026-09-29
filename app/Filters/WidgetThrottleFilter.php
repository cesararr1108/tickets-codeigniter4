<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Widget;

/**
 * Límite de peticiones por IP a la API del widget.
 *
 * Además, los POST (crear ticket) tienen un límite estricto por hora.
 */
class WidgetThrottleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config    = config(Widget::class);
        $throttler = service('throttler');
        $ip        = $request->getIPAddress();

        if (! $throttler->check('wg_' . md5($ip), $config->requestsPerMinute, MINUTE)) {
            return $this->tooMany($throttler->getTokenTime());
        }

        if ($request->is('post')) {
            if (! $throttler->check('wgt_' . md5($ip), $config->ticketsPerHour, HOUR)) {
                return $this->tooMany($throttler->getTokenTime());
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function tooMany(int $wait): ResponseInterface
    {
        return service('response')
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) max(1, $wait))
            ->setJSON(['status' => 429, 'message' => 'Demasiadas solicitudes. Intenta de nuevo en unos minutos.']);
    }
}
