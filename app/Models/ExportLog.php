<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría: quién exportó qué lista, cuántas filas y en qué formato.
 */
class ExportLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', // Usuario que exportó (null si se eliminó después)
        'user_name', // Nombre del usuario al momento de exportar
        'list', // Lista exportada (Mascotas, Propietarios…)
        'rows', // Cantidad de filas del archivo
        'format', // xlsx o csv
        'columns', // Nombres de las columnas exportadas
        'includes_owner_data', // Si el archivo trae datos personales de propietarios
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'includes_owner_data' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
