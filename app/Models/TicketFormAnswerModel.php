<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Respuestas de los formularios adicionales (Proyecto, Requerimiento,
 * Incidente...). Ver app/Database/sql/TicketFormAnswers.sql.
 */
class TicketFormAnswerModel extends Model
{
    protected $table            = 'TicketFormAnswers';
    protected $primaryKey       = 'IdAnswer';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'IdTicket',
        'FormKey',
        'FieldKey',
        'Label',
        'Value',
        'SortOrder',
    ];

    protected $useTimestamps = false;

    /**
     * Respuestas de un ticket en el orden del formulario.
     *
     * @return list<array<string, mixed>>
     */
    public function forTicket(int $idTicket): array
    {
        return $this->where('IdTicket', $idTicket)
            ->orderBy('SortOrder')
            ->orderBy('IdAnswer')
            ->findAll();
    }

    /**
     * Guarda las respuestas enviadas por el widget.
     *
     * @param list<array{key?: string, label?: string, value?: mixed}> $answers
     */
    public function saveForTicket(int $idTicket, string $formKey, array $answers): int
    {
        $rows = [];

        foreach (array_values($answers) as $i => $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $label = trim((string) ($answer['label'] ?? ''));
            $key   = trim((string) ($answer['key'] ?? ''));
            $value = $answer['value'] ?? '';

            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }

            if ($label === '' && $key === '') {
                continue;
            }

            $rows[] = [
                'IdTicket'  => $idTicket,
                'FormKey'   => mb_substr($formKey, 0, 40),
                'FieldKey'  => mb_substr($key !== '' ? $key : $label, 0, 60),
                'Label'     => mb_substr($label !== '' ? $label : $key, 0, 150),
                'Value'     => trim((string) $value),
                'SortOrder' => $i,
            ];
        }

        if ($rows !== []) {
            $this->insertBatch($rows);
        }

        return count($rows);
    }

    /**
     * Guarda (inserta o actualiza) los campos que llena el área desde el
     * panel. Van al final del formulario (SortOrder 1000+).
     *
     * @param array<string, array<string, mixed>> $definitions Config\Tickets::$areaFields[$formKey]
     * @param array<string, mixed>                 $values      valores enviados (clave => valor)
     */
    public function saveAreaFields(int $idTicket, string $formKey, array $definitions, array $values): void
    {
        $order = 1000;

        foreach ($definitions as $key => $definition) {
            $order++;

            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = trim((string) $values[$key]);

            if (($definition['type'] ?? '') === 'select' && $value !== '' && ! in_array($value, $definition['options'] ?? [], true)) {
                continue;
            }

            $existing = $this->where('IdTicket', $idTicket)->where('FieldKey', $key)->first();

            if ($existing !== null) {
                $this->update($existing['IdAnswer'], ['Value' => $value]);

                continue;
            }

            $this->insert([
                'IdTicket'  => $idTicket,
                'FormKey'   => $formKey,
                'FieldKey'  => $key,
                'Label'     => (string) $definition['label'],
                'Value'     => $value,
                'SortOrder' => $order,
            ]);
        }
    }
}
