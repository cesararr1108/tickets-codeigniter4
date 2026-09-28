<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restringe la administración a los roles de Config\Tickets::$adminRoles.
 */
class PanelAdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('panel');

        if (panel_is_admin()) {
            return null;
        }

        return redirect()->to(site_url('panel'))->with('error', 'No tienes permiso para administrar catálogos.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
