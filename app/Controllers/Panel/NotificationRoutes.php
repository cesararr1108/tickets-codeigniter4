<?php

namespace App\Controllers\Panel;

use App\Libraries\PushApiClient;
use App\Models\CompanyPushKeyModel;
use App\Models\NotificationRouteModel;

/**
 * Administración del enrutamiento de notificaciones (solo administradores):
 * "los tickets de la compañía X se notifican al rol Y", y la API key de la
 * API de notificaciones de cada compañía.
 */
class NotificationRoutes extends BasePanelController
{
    /** GET /panel/admin/notificaciones */
    public function index()
    {
        $db        = db_connect();
        $available = NotificationRouteModel::available();

        $rows = ! $available ? [] : $db->table('TicketNotificationRoutes r')
            ->select('r.Id, r.CodCompanies, c.Companies, r.RoleId, ro.Descripcion AS Role')
            ->select('(SELECT COUNT(*) FROM Users u WHERE u.RoleId = r.RoleId AND u.IsActive = 1) AS Users', false)
            ->join('Companies c', 'c.CodCompanies = r.CodCompanies', 'left')
            ->join('Roles ro', 'ro.Id = r.RoleId', 'left')
            ->orderBy('c.Companies')
            ->orderBy('ro.Descripcion')
            ->get()
            ->getResultArray();

        $keys = ! CompanyPushKeyModel::available() ? [] : $db->table('CompanyPushKeys')
            ->select('CodCompanies, ApiKey, UpdatedAt')
            ->get()
            ->getResultArray();

        return $this->render('admin/notifications', [
            'active'        => 'notifications',
            'title'         => 'Notificaciones',
            'available'     => $available,
            'rows'          => $rows,
            'keysAvailable' => CompanyPushKeyModel::available(),
            // Solo la parte pública de la llave: el secreto nunca vuelve al navegador.
            'keys'          => array_column(array_map(static fn ($k) => [
                'CodCompanies' => $k['CodCompanies'],
                'Masked'       => CompanyPushKeyModel::mask((string) $k['ApiKey']),
                'UpdatedAt'    => $k['UpdatedAt'],
            ], $keys), null, 'CodCompanies'),
            'defaultKey'    => trim(config(\Config\PushApi::class)->apiKey) !== '',
            'companies'     => $db->table('Companies')->orderBy('Companies')->get()->getResultArray(),
            'roles'     => $db->table('Roles')->orderBy('Descripcion')->get()->getResultArray(),
        ]);
    }

    /** POST /panel/admin/notificaciones */
    public function create()
    {
        $company = trim((string) $this->request->getPost('CodCompanies'));
        $roleId  = (int) $this->request->getPost('RoleId');
        $db      = db_connect();

        if (! NotificationRouteModel::available()) {
            return redirect()->back()->with('error', 'Falta crear la tabla TicketNotificationRoutes (ver app/Database/sql/NotificationRoutes.sql).');
        }

        if ($db->table('Companies')->where('CodCompanies', $company)->countAllResults() === 0
            || $db->table('Roles')->where('Id', $roleId)->countAllResults() === 0) {
            return redirect()->back()->with('error', 'Selecciona una compañía y un rol válidos.');
        }

        $exists = $db->table('TicketNotificationRoutes')->where('CodCompanies', $company)->where('RoleId', $roleId)->countAllResults() > 0;

        if (! $exists) {
            model(NotificationRouteModel::class)->insert(['CodCompanies' => $company, 'RoleId' => $roleId]);
        }

        return redirect()->to(site_url('panel/admin/notificaciones'))->with('success', 'Regla guardada.');
    }

    /** POST /panel/admin/notificaciones/delete/{id} */
    public function delete(int $id)
    {
        if (NotificationRouteModel::available()) {
            model(NotificationRouteModel::class)->delete($id);
        }

        return redirect()->to(site_url('panel/admin/notificaciones'))->with('success', 'Regla eliminada.');
    }

    /** POST /panel/admin/notificaciones/llaves  (CodCompanies, ApiKey) */
    public function saveKey()
    {
        $company = trim((string) $this->request->getPost('CodCompanies'));
        $key     = trim((string) $this->request->getPost('ApiKey'));

        if (! CompanyPushKeyModel::available()) {
            return redirect()->back()->with('error', 'Falta crear la tabla CompanyPushKeys (ver app/Database/sql/PushApiKeys.sql).');
        }

        if (db_connect()->table('Companies')->where('CodCompanies', $company)->countAllResults() === 0) {
            return redirect()->back()->with('error', 'Selecciona una compañía válida.');
        }

        if (! preg_match(CompanyPushKeyModel::KEY_PATTERN, $key)) {
            return redirect()->back()->with('error', 'La API key no tiene el formato pk_<12 caracteres>.<64 caracteres> que entrega php spark apikey:create.');
        }

        model(CompanyPushKeyModel::class)->saveKey($company, $key);

        // Se comprueba contra la API, pero se guarda igual (la API puede estar caída en este momento).
        $failed = array_values(array_filter(PushApiClient::forCompany($company)->diagnose(), static fn ($step) => ! $step['ok']));

        if ($failed !== []) {
            return redirect()->to(site_url('panel/admin/notificaciones'))
                ->with('error', 'Llave guardada para la compañía ' . $company . ', pero la API no la aceptó: ' . $failed[0]['step'] . ' — ' . $failed[0]['detail']);
        }

        return redirect()->to(site_url('panel/admin/notificaciones'))->with('success', 'Llave de la compañía ' . $company . ' guardada y verificada con la API.');
    }

    /** POST /panel/admin/notificaciones/llaves/delete/{CodCompanies} */
    public function deleteKey(string $company)
    {
        if (CompanyPushKeyModel::available()) {
            model(CompanyPushKeyModel::class)->removeKey(urldecode($company));
        }

        return redirect()->to(site_url('panel/admin/notificaciones'))->with('success', 'Llave eliminada.');
    }
}
