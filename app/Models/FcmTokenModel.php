<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Tokens FCM registrados (tabla t_fcm_tokens). Un token pertenece a un solo
 * correo: si se registra con otro correo, se reasigna.
 */
class FcmTokenModel extends Model
{
    protected $table            = 't_fcm_tokens';
    protected $primaryKey       = 'Id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    /** Prefijo de Navigator que identifica los tokens registrados desde el panel. */
    public const PANEL_TAG = 'PANEL';

    protected $allowedFields = [
        'Email', 'Token', 'Navigator', 'fecha_registro', 'Companies', 'Rol', 'branches',
    ];

    /**
     * Inserta o actualiza el token (clave: Token).
     *
     * @param array<string, mixed> $data Email, Token y opcionales Navigator, Companies, Rol, branches
     */
    public function register(array $data): void
    {
        $row = [
            'Email'          => mb_substr(trim((string) $data['Email']), 0, 120),
            'Token'          => trim((string) $data['Token']),
            'Navigator'      => $this->clip($data['Navigator'] ?? null, 100),
            'Companies'      => $this->clip($data['Companies'] ?? null, 30),
            'Rol'            => $this->clip($data['Rol'] ?? null, 50),
            'branches'       => $this->clip($data['branches'] ?? ($data['Branches'] ?? null), 30),
            'fecha_registro' => date('Y-m-d H:i:s'),
        ];

        $existing = $this->where('Token', $row['Token'])->first();

        if ($existing === null) {
            $this->skipValidation(true)->insert($row);

            return;
        }

        $this->skipValidation(true)->update($existing['Id'], $row);
    }

    /**
     * Tokens de uno o varios correos.
     *
     * @param list<string> $emails
     *
     * @return list<string>
     */
    public function tokensForEmails(array $emails, ?bool $panel = null): array
    {
        $emails = array_values(array_unique(array_filter(array_map('trim', $emails))));

        if ($emails === []) {
            return [];
        }

        $query = $this->select('Token')->whereIn('Email', $emails);

        // Los tokens del panel llevan el prefijo "PANEL " en Navigator.
        if ($panel === true) {
            $query->like('Navigator', self::PANEL_TAG, 'after');
        } elseif ($panel === false) {
            $query->groupStart()->notLike('Navigator', self::PANEL_TAG, 'after')->orWhere('Navigator', null)->groupEnd();
        }

        $rows = $query->findAll();

        return array_values(array_unique(array_column($rows, 'Token')));
    }

    public function forget(string $token): void
    {
        $this->where('Token', $token)->delete();
    }

    private function clip(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
