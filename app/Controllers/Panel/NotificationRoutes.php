<?php

namespace App\Controllers\Panel;

use App\Models\NotificationRouteModel;

/**
 * Administración del enrutamiento de notificaciones (solo administradores):
 * "los tickets de la compañía X se notifican al rol Y".
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

        return $this->render('admin/notifications', [
            'active'    => 'notifications',
            'title'     => 'Notificaciones',
            'available' => $available,
            'rows'      => $rows,
            'companies' => $db->table('Companies')->orderBy('Companies')->get()->getResultArray(),
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
}
