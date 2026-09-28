<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Api as ApiConfig;

/**
 * Middleware de autenticación para la API.
 *
 * Exige que cada petición traiga, en los headers:
 *   - Authorization: Bearer {token}   (token de la API)
 *   - X-Api-Key: {key}                (clave de la API)
 *
 * Ambos valores deben coincidir con los configurados en .env
 * (api.token y api.key, ver app/Config/Api.php). Si falta alguno
 * o no coincide, la petición se rechaza con 401 antes de llegar
 * al controlador.
 */
class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = config(ApiConfig::class);

        $token = $this->extractToken($request, $config->tokenHeader);
        $key   = $request->getHeaderLine($config->keyHeader);

        if ($config->token === '' || $config->key === '') {
            return $this->unauthorized('La API no tiene configuradas las credenciales (api.token / api.key).');
        }

        if ($token === null || $token === '') {
            return $this->unauthorized('Falta el token en el header ' . $config->tokenHeader . '.');
        }

        if ($key === '') {
            return $this->unauthorized('Falta la clave en el header ' . $config->keyHeader . '.');
        }

        if (! hash_equals($config->token, $token) || ! hash_equals($config->key, $key)) {
            return $this->unauthorized('Token o clave inválidos.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No se requiere acción posterior.
    }

    /**
     * Obtiene el token del header, admitiendo tanto "Bearer {token}" como
     * el valor crudo del header.
     */
    private function extractToken(RequestInterface $request, string $headerName): ?string
    {
        $header = $request->getHeaderLine($headerName);

        if ($header === '') {
            return null;
        }

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return trim($header);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = service('response');

        return $response->setStatusCode(401)->setJSON([
            'status'  => 401,
            'error'   => 'Unauthorized',
            'message' => $message,
        ]);
    }
}
