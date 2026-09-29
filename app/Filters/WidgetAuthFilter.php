<?php

namespace App\Filters;

use App\Libraries\WidgetToken;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Exige un token firmado (header X-Widget-Token) en las rutas /widget/*.
 */
class WidgetAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! WidgetToken::isConfigured()) {
            log_message('critical', '[Widget] widget.secret no está configurado (mínimo 32 caracteres) en el .env.');

            return $this->fail(503, 'El widget no está configurado en el servidor.');
        }

        $token = trim($request->getHeaderLine('X-Widget-Token'));

        if ($token === '') {
            return $this->fail(401, 'Falta el token del widget.');
        }

        $result = WidgetToken::verify($token);

        if ($result['claims'] === null) {
            return $this->fail(401, $result['error'] ?? 'Token inválido.');
        }

        WidgetToken::setCurrent($result['claims']);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        WidgetToken::setCurrent(null);
    }

    private function fail(int $status, string $message): ResponseInterface
    {
        return service('response')->setStatusCode($status)->setJSON(['status' => $status, 'message' => $message]);
    }
}
