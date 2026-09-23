<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Tickets;
use Psr\Log\LoggerInterface;

/**
 * Base de los controladores del panel web.
 */
abstract class BasePanelController extends BaseController
{
    protected $helpers = ['panel', 'url', 'form'];

    protected Tickets $config;

    /**
     * Usuario con sesión iniciada (ver Auth::attempt()).
     *
     * @var array<string, mixed>|null
     */
    protected ?array $user = null;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->config = config(Tickets::class);
        $this->user   = session('panelUser');
    }

    /**
     * Renderiza una vista dentro del layout del panel.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        $db = db_connect();

        $data['nav'] = [
            'active'     => $data['active'] ?? '',
            'pending'    => $db->table('Tickets')->whereIn('Status', ['abierto', 'en_progreso'])->countAllResults(),
            'mine'       => $this->user === null ? 0 : $db->table('Tickets')
                ->whereIn('Status', ['abierto', 'en_progreso'])
                ->where('AssignedUserId', $this->user['id'])
                ->countAllResults(),
            'unassigned' => $db->table('Tickets')
                ->whereIn('Status', ['abierto', 'en_progreso'])
                ->where('AssignedUserId', null)
                ->countAllResults(),
        ];

        $data['user']   = $this->user;
        $data['config'] = $this->config;

        return view('panel/' . $view, $data);
    }

    /**
     * Opciones para los selects de filtros y formularios.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function lookups(): array
    {
        $db = db_connect();

        return [
            'companies'  => $db->table('Companies')->orderBy('Companies')->get()->getResultArray(),
            'branches'   => $db->table('Branches')->orderBy('Branches')->get()->getResultArray(),
            'categories' => $db->table('Category')->orderBy('Category')->get()->getResultArray(),
            'agents'     => $db->table('Users')
                ->select('IdUser, FullName')
                ->where('IsActive', 1)
                ->orderBy('FullName')
                ->get()
                ->getResultArray(),
        ];
    }
}
