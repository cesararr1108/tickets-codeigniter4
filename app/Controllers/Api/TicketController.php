<?php

namespace App\Controllers\Api;

use App\Models\TicketAttachmentModel;
use App\Models\TicketMessageModel;
use App\Models\TicketModel;

class TicketController extends BaseApiController
{
    protected string $modelName = TicketModel::class;

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
