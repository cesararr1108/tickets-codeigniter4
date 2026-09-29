<?php

namespace App\Controllers\Panel;

/**
 * Descarga de adjuntos. Los archivos viven en writable/uploads (fuera de
 * public/), así que solo se pueden bajar con sesión iniciada.
 */
class Attachments extends BasePanelController
{
    public function download(int $id)
    {
        $row = db_connect()->table('TicketAttachments')->where('AttachmentId', $id)->get()->getRowArray();

        if ($row === null) {
            return redirect()->back()->with('error', 'El adjunto no existe.');
        }

        $base = realpath(WRITEPATH . 'uploads');
        $path = $base === false ? false : realpath($base . DIRECTORY_SEPARATOR . $row['FilePath']);

        // Solo archivos dentro de writable/uploads (evita ../ en FilePath).
        if ($path === false || ! str_starts_with($path, $base . DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return redirect()->back()->with('error', 'El archivo ya no está disponible en el servidor.');
        }

        $download = $this->response->download($path, null);

        return $download
            ->setFileName($row['FileName'])
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }
}
