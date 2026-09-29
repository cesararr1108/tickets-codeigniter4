<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Limita /login y /panel a redes autorizadas (Config\Tickets::$panelNetworks).
 * Vacío = sin restricción.
 */
class PanelNetworkFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $networks = config(\Config\Tickets::class)->allowedNetworks();

        if ($networks === [] || self::matches($request->getIPAddress(), $networks)) {
            return null;
        }

        log_message('warning', '[Panel] Acceso bloqueado desde ' . $request->getIPAddress());

        return service('response')->setStatusCode(403)->setBody('Acceso no permitido desde esta red.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    /**
     * @param list<string> $networks IPs o rangos CIDR (IPv4 o IPv6)
     */
    public static function matches(string $ip, array $networks): bool
    {
        $addr = @inet_pton($ip);

        if ($addr === false) {
            return false;
        }

        foreach ($networks as $network) {
            [$base, $bits] = array_pad(explode('/', $network, 2), 2, null);

            $baseAddr = @inet_pton($base);

            if ($baseAddr === false || strlen($baseAddr) !== strlen($addr)) {
                continue;
            }

            $bits  = $bits === null ? strlen($addr) * 8 : max(0, min((int) $bits, strlen($addr) * 8));
            $bytes = intdiv($bits, 8);
            $rest  = $bits % 8;

            if (substr($addr, 0, $bytes) !== substr($baseAddr, 0, $bytes)) {
                continue;
            }

            if ($rest === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $rest)) & 0xFF;

            if ((ord($addr[$bytes]) & $mask) === (ord($baseAddr[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
