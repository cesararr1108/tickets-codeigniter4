<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Widget;

/**
 * CORS con lista de dominios autorizados (Config\Widget::$origins).
 *
 * Si el Origin de la petición no está en la lista, no se envían cabeceras
 * CORS y el navegador bloquea la respuesta. Las peticiones sin Origin
 * (servidor a servidor, mismo dominio) no se ven afectadas.
 */
class Cors implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtolower($request->getMethod()) !== 'options') {
            return null;
        }

        return $this->decorate(service('response')->setStatusCode(204), $request, true);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $this->decorate($response, $request, false);
    }

    private function decorate(ResponseInterface $response, RequestInterface $request, bool $preflight): ResponseInterface
    {
        $response->setHeader('Vary', 'Origin');

        $origin = rtrim(trim($request->getHeaderLine('Origin')), '/');

        if ($origin === '' || ! in_array($origin, config(Widget::class)->allowedOrigins(), true)) {
            return $response;
        }

        $response->setHeader('Access-Control-Allow-Origin', $origin);

        if ($preflight) {
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Widget-Token');
            $response->setHeader('Access-Control-Max-Age', '3600');
        }

        return $response;
    }
}
