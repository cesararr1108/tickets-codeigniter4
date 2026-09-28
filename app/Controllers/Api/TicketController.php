<?php

namespace App\Controllers\Api;

use App\Models\TicketAttachmentModel;
use App\Models\TicketFormAnswerModel;
use App\Models\TicketMessageModel;
use App\Models\TicketModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class TicketController extends BaseApiController
{
    protected string $modelName = TicketModel::class;

    /** Tamaño máximo por archivo adjunto (bytes). */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /** Extensiones permitidas para adjuntos. */
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'zip', 'rar', 'msg', 'eml',
    ];

    /**
     * POST /tickets
     *
     * Acepta JSON o multipart/form-data con:
     *   - campos del ticket (CodCompanies, CodBranches, Subject, ...)
     *   - Description (opcional): se guarda como primer mensaje
     *   - FormKey + Answers (opcional): respuestas del formulario adicional;
     *     Answers es una lista [{key, label, value}] (o su JSON en multipart)
     *   - file / files[] (opcional): adjuntos
     *
     * Todo se guarda en una transacción: si algo falla no queda nada a medias.
     */
    public function create()
    {
        $payload = $this->request->getPost() ?: $this->getPayload();

        $answers = $payload['Answers'] ?? [];
        if (is_string($answers)) {
            $answers = json_decode($answers, true) ?: [];
        }

        $formKey     = trim((string) ($payload['FormKey'] ?? ''));
        $description = trim((string) ($payload['Description'] ?? ''));
        $senderName  = trim((string) ($payload['SenderName'] ?? ($payload['RequesterEmail'] ?? '')));

        $files = $this->uploadedFiles();

        foreach ($files as $file) {
            if ($error = $this->fileError($file)) {
                return $this->failValidationErrors(['file' => $error]);
            }
        }

        $ticketFields = array_intersect_key($payload, array_flip([
            'CodCompanies', 'CodBranches', 'AssignedUserId', 'RequesterEmail',
            'Subject', 'IdCategory', 'IdSubCategory', 'Priority', 'Status',
        ]));

        $db    = db_connect();
        $model = model(TicketModel::class);

        $db->transBegin();

        try {
            if (! $model->insert($ticketFields)) {
                $db->transRollback();

                return $this->failValidationErrors($model->errors());
            }

            $id = (int) $model->getInsertID();

            if ($description !== '') {
                model(TicketMessageModel::class)->insert([
                    'IdTicket'   => $id,
                    'SenderType' => 'cliente',
                    'SenderName' => mb_substr($senderName !== '' ? $senderName : 'Solicitante', 0, 150),
                    'Message'    => $description,
                ]);
            }

            if ($formKey !== '' && is_array($answers) && $answers !== []) {
                model(TicketFormAnswerModel::class)->saveForTicket($id, $formKey, $answers);
            }

            foreach ($files as $file) {
                $this->storeAttachment($id, $file);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Error de base de datos al guardar el ticket.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', '[API tickets] ' . $e->getMessage());

            return $this->failServerError('No fue posible guardar el ticket.');
        }

        return $this->respondCreated($model->find($id));
    }

    /**
     * Archivos enviados como "file" o "files[]".
     *
     * @return list<UploadedFile>
     */
    private function uploadedFiles(): array
    {
        $files = [];

        foreach (['file', 'files'] as $name) {
            $value = $this->request->getFileMultiple($name) ?? [];

            if ($value === [] && ($single = $this->request->getFile($name)) !== null) {
                $value = [$single];
            }

            foreach ($value as $file) {
                if ($file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function fileError(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return 'No se pudo recibir "' . $file->getClientName() . '": ' . $file->getErrorString();
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return '"' . $file->getClientName() . '" supera el máximo de 10 MB.';
        }

        $extension = strtolower($file->getClientExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return 'Tipo de archivo no permitido: "' . $file->getClientName() . '".';
        }

        return null;
    }

    /**
     * Guarda el archivo en writable/uploads/tickets/{id}/ y lo registra.
     * FilePath queda relativo a writable/uploads.
     */
    private function storeAttachment(int $idTicket, UploadedFile $file): void
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

    /**
     * GET /tickets/{id}/messages
     */
    public function messages($id = null)
    {
        if ($this->model()->find($id) === null) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        $messages = model(TicketMessageModel::class)->forTicket((int) $id);

        return $this->respond($messages);
    }

    /**
     * POST /tickets/{id}/messages
     */
    public function addMessage($id = null)
    {
        if ($this->model()->find($id) === null) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        $model = model(TicketMessageModel::class);
        $data  = $this->getPayload();
        $data['IdTicket'] = (int) $id;

        if (! $model->insert($data)) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated($model->find($model->getInsertID()));
    }

    /**
     * GET /tickets/{id}/attachments
     */
    public function attachments($id = null)
    {
        if ($this->model()->find($id) === null) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        $rows = model(TicketAttachmentModel::class)
            ->where('TicketId', $id)
            ->findAll();

        return $this->respond($rows);
    }

    /**
     * POST /tickets/{id}/attachments
     */
    public function addAttachment($id = null)
    {
        if ($this->model()->find($id) === null) {
            return $this->failNotFound('Ticket no encontrado.');
        }

        $model = model(TicketAttachmentModel::class);
        $data  = $this->getPayload();
        $data['TicketId'] = (int) $id;

        if (! $model->insert($data)) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated($model->find($model->getInsertID()));
    }
}
