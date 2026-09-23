<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', static fn () => redirect()->to(site_url('panel')));

/*
 * Panel web (dashboard de tickets).
 * Requiere sesión iniciada con un usuario de la tabla Users.
 */
$routes->get('login', 'Panel\\Auth::login');
$routes->post('login', 'Panel\\Auth::attempt', ['filter' => 'csrf']);
$routes->get('logout', 'Panel\\Auth::logout');

$routes->group('panel', ['namespace' => 'App\\Controllers\\Panel', 'filter' => ['panelauth', 'csrf']], static function (RouteCollection $routes) {
    $routes->get('/', 'Dashboard::index');

    $routes->get('tickets', 'Tickets::index');
    $routes->get('tickets/nuevo', 'Tickets::create');
    $routes->post('tickets', 'Tickets::store');
    $routes->get('tickets/(:num)', 'Tickets::show/$1');
    $routes->post('tickets/(:num)', 'Tickets::update/$1');
    $routes->get('tickets/(:num)/messages', 'Tickets::messages/$1');
    $routes->post('tickets/(:num)/messages', 'Tickets::addMessage/$1');

    $routes->get('reportes', 'Reports::index');
    $routes->get('catalogo', 'Catalog::index');

    $routes->get('lookups/branches', 'Lookups::branches');
    $routes->get('lookups/subcategories', 'Lookups::subcategories');
});

/*
 * Rutas de la API.
 *
 * Todo el grupo 'api' pasa por el filtro 'apiauth' (app/Filters/ApiAuthFilter.php),
 * que exige en cada petición los headers:
 *   Authorization: Bearer {token}
 *   X-Api-Key: {key}
 * definidos en .env como api.token y api.key.
 *
 * Cada recurso define explícitamente sus 5 rutas CRUD contra el método
 * correspondiente del controlador (index/show/create/update/delete).
 */
// Antes de las rutas GET/POST de /api
$routes->options('(:any)', static function () {
    $response = service('response');

    $response->setHeader('Access-Control-Allow-Origin', '*');
    $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Api-Key, Accept');
    $response->setHeader('Access-Control-Max-Age', '3600');

    return $response->setStatusCode(204);
});

$routes->group('api', ['namespace' => 'App\Controllers\Api', 'filter' => 'apiauth'], static function (RouteCollection $routes) {
    // Antes de las rutas GET/POST de /api
    $routes->options('(:any)', static function () {
        $response = service('response');

        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Api-Key, Accept');
        $response->setHeader('Access-Control-Max-Age', '3600');

        return $response->setStatusCode(204);
    });
    // Companies (PK CodCompanies: alfanumérico)
    $routes->get('companies', 'CompanyController::index');
    $routes->get('companies/(:segment)', 'CompanyController::show/$1');
    $routes->post('companies', 'CompanyController::create');
    $routes->put('companies/(:segment)', 'CompanyController::update/$1');
    $routes->patch('companies/(:segment)', 'CompanyController::update/$1');
    $routes->delete('companies/(:segment)', 'CompanyController::delete/$1');

    // Branches (PK CodBranches: alfanumérico)
    $routes->get('branches', 'BranchController::index');
    $routes->get('branches/(:segment)', 'BranchController::show/$1');
    $routes->post('branches', 'BranchController::create');
    $routes->put('branches/(:segment)', 'BranchController::update/$1');
    $routes->patch('branches/(:segment)', 'BranchController::update/$1');
    $routes->delete('branches/(:segment)', 'BranchController::delete/$1');

    // Category (PK IdCategory: numérico)
    $routes->get('categories', 'CategoryController::index');
    $routes->get('categories/(:num)', 'CategoryController::show/$1');
    $routes->post('categories', 'CategoryController::create');
    $routes->put('categories/(:num)', 'CategoryController::update/$1');
    $routes->patch('categories/(:num)', 'CategoryController::update/$1');
    $routes->delete('categories/(:num)', 'CategoryController::delete/$1');

    // SubCategory (PK IdSubCategory: numérico)
    $routes->get('subcategories/category/(:num)', 'SubCategoryController::byCategory/$1');
    $routes->get('subcategories', 'SubCategoryController::index');
    $routes->get('subcategories/(:num)', 'SubCategoryController::show/$1');
    $routes->post('subcategories', 'SubCategoryController::create');
    $routes->put('subcategories/(:num)', 'SubCategoryController::update/$1');
    $routes->patch('subcategories/(:num)', 'SubCategoryController::update/$1');
    $routes->delete('subcategories/(:num)', 'SubCategoryController::delete/$1');

    // Roles (PK Id: numérico)
    $routes->get('roles', 'RoleController::index');
    $routes->get('roles/(:num)', 'RoleController::show/$1');
    $routes->post('roles', 'RoleController::create');
    $routes->put('roles/(:num)', 'RoleController::update/$1');
    $routes->patch('roles/(:num)', 'RoleController::update/$1');
    $routes->delete('roles/(:num)', 'RoleController::delete/$1');

    // TicketForms (PK IdForm: numérico)
    $routes->get('ticket-forms', 'TicketFormController::index');
    $routes->get('ticket-forms/(:num)', 'TicketFormController::show/$1');
    $routes->post('ticket-forms', 'TicketFormController::create');
    $routes->put('ticket-forms/(:num)', 'TicketFormController::update/$1');
    $routes->patch('ticket-forms/(:num)', 'TicketFormController::update/$1');
    $routes->delete('ticket-forms/(:num)', 'TicketFormController::delete/$1');

    // Users (PK IdUser: alfanumérico)
    $routes->get('users', 'UserController::index');
    $routes->get('users/(:segment)', 'UserController::show/$1');
    $routes->post('users', 'UserController::create');
    $routes->put('users/(:segment)', 'UserController::update/$1');
    $routes->patch('users/(:segment)', 'UserController::update/$1');
    $routes->delete('users/(:segment)', 'UserController::delete/$1');

    // Tickets (PK IdTicket: numérico) + sub-recursos
    $routes->get('tickets/(:num)/messages', 'TicketController::messages/$1');
    $routes->post('tickets/(:num)/messages', 'TicketController::addMessage/$1');
    $routes->get('tickets/(:num)/attachments', 'TicketController::attachments/$1');
    $routes->post('tickets/(:num)/attachments', 'TicketController::addAttachment/$1');
    $routes->get('tickets', 'TicketController::index');
    $routes->get('tickets/(:num)', 'TicketController::show/$1');
    $routes->post('tickets', 'TicketController::create');
    $routes->put('tickets/(:num)', 'TicketController::update/$1');
    $routes->patch('tickets/(:num)', 'TicketController::update/$1');
    $routes->delete('tickets/(:num)', 'TicketController::delete/$1');

    // TicketAttachments (PK AttachmentId: numérico)
    $routes->get('ticket-attachments', 'TicketAttachmentController::index');
    $routes->get('ticket-attachments/(:num)', 'TicketAttachmentController::show/$1');
    $routes->post('ticket-attachments', 'TicketAttachmentController::create');
    $routes->put('ticket-attachments/(:num)', 'TicketAttachmentController::update/$1');
    $routes->patch('ticket-attachments/(:num)', 'TicketAttachmentController::update/$1');
    $routes->delete('ticket-attachments/(:num)', 'TicketAttachmentController::delete/$1');

    // TicketMessages (PK MessageId: numérico)
    $routes->get('ticket-messages', 'TicketMessageController::index');
    $routes->get('ticket-messages/(:num)', 'TicketMessageController::show/$1');
    $routes->post('ticket-messages', 'TicketMessageController::create');
    $routes->put('ticket-messages/(:num)', 'TicketMessageController::update/$1');
    $routes->patch('ticket-messages/(:num)', 'TicketMessageController::update/$1');
    $routes->delete('ticket-messages/(:num)', 'TicketMessageController::delete/$1');
});