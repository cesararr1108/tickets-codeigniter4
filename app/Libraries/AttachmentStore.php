<?php

namespace App\Libraries;

use App\Models\TicketAttachmentModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Valida y guarda archivos de un ticket en writable/uploads/tickets/{id}/.
 * FilePath queda relativo a writable/uploads (igual que la API).
 */
class AttachmentStore
{
    public const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'zip', 'rar', 'msg', 'eml',
    ];

    /**
     * Archivos subidos en el campo (múltiple) indicado.
     *
     * @return list<UploadedFile>
     */
    public static function uploaded(string $field): array
    {
        $files = [];

        foreach (service('request')->getFileMultiple($field) ?? [] as $file) {
            if ($file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                $files[] = $file;
            }
        }

        return $files;
    }

    public static function error(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return 'No se pudo recibir "' . $file->getClientName() . '": ' . $file->getErrorString();
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return '"' . $file->getClientName() . '" supera el máximo de 10 MB.';
        }

        if (! in_array(strtolower($file->getClientExtension()), self::ALLOWED_EXTENSIONS, true)) {
            return 'Tipo de archivo no permitido: "' . $file->getClientName() . '".';
        }

        return null;
    }

    public static function store(int $idTicket, UploadedFile $file): void
    {
        $folder = 'tickets/' . $idTicket;
        $name   = bin2hex(random_bytes(10)) . '.' . strtolower($file->getClientExtension());

        $file->move(WRITEPATH . 'uploads/' . $folder, $name);

        $saved = model(TicketAttachmentModel::class)->insert([
            'TicketId' => $idTicket,
            'FileName' => mb_substr($file->getClientName(), 0, 255),
            'FilePath' => $folder . '/' . $name,
        ]);

        if ($saved === false) {
            throw new \RuntimeException('No se pudo registrar el adjunto ' . $file->getClientName());
        }
    }
}
