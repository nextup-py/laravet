<?php

namespace App\Models;

use App\Reports\ReportEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte personalizado que un usuario guardó para volver a generarlo (son privados de cada usuario).
 * No guarda fechas: el rango se elige cada vez.
 */
class SavedReport extends Model
{
    protected $fillable = [
        'user_id', // Usuario dueño del reporte
        'name', // Nombre que le puso el usuario
        'entity', // Tipo de registro (ReportEntity)
        'config', // columns (claves), species y vet_id
    ];

    protected function casts(): array
    {
        return [
            'entity' => ReportEntity::class,
            'config' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
