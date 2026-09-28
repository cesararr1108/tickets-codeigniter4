<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Protege las rutas del panel: exige un usuario con sesión iniciada.
 */
class PanelAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session('panelUser') !== null) {
            return null;
        }

        if ($request->isAJAX()) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['message' => 'La sesión expiró. Vuelve a iniciar sesión.']);
        }

        session()->set('redirectAfterLogin', current_url(true)->__toString());

        return redirect()->to(site_url('login'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
