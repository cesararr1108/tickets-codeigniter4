<?php

namespace App\Controllers\Panel;

use App\Libraries\Purchases;
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

        // Páginas dinámicas: sin esto el navegador (o la app instalada) puede mostrar
        // una copia vieja al volver a entrar y el estado/cronómetro parece no haber cambiado.
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0');
    }

    /**
     * Rango de fechas del query string (?desde=AAAA-MM-DD&hasta=AAAA-MM-DD).
     * Descarta fechas inválidas e invierte el rango si viene al revés.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function dateRange(): array
    {
        $parse = static function ($value): ?string {
            $value = trim((string) $value);
            $date  = \DateTime::createFromFormat('!Y-m-d', $value);

            return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
        };

        $from = $parse($this->request->getGet('desde'));
        $to   = $parse($this->request->getGet('hasta'));

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
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
            'pending'    => Purchases::exclude($db->table('Tickets'))->whereIn('Status', ['abierto', 'en_progreso'])->countAllResults(),
            'mine'       => $this->user === null ? 0 : Purchases::exclude($db->table('Tickets'))
                ->whereIn('Status', ['abierto', 'en_progreso'])
                ->where('AssignedUserId', $this->user['id'])
                ->countAllResults(),
            'unassigned' => Purchases::exclude($db->table('Tickets'))
                ->whereIn('Status', ['abierto', 'en_progreso'])
                ->where('AssignedUserId', null)
                ->countAllResults(),
            // Compras pendientes (pestaña "Compras").
            'purchases'  => Purchases::available()
                ? $db->table('Tickets')->where('IsPurchase', 1)->whereIn('Status', ['abierto', 'en_progreso'])->countAllResults()
                : 0,
            'escalated'  => model(\App\Models\TicketEscalationModel::class)->countActive(),
            // Desarrollos del mes sin aprobar (los propios, para el programador).
            'developments' => \App\Libraries\DevelopmentBoard::available() && $this->user !== null
                ? (static function () use ($db) {
                    $builder = $db->table('Developments')
                        ->where('PlanMonth', \App\Libraries\DevelopmentBoard::currentMonth())
                        ->where('ApprovedAt', null);

                    if (! panel_is_admin()) {
                        $builder->where('AssignedUserId', panel_user_id());
                    }

                    return $builder->countAllResults();
                })()
                : 0,
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
