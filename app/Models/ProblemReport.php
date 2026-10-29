<?php

namespace App\Models;

use App\Enums\ProblemReportStatus;
use App\Enums\ProblemReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte de un problema, duda o sugerencia enviado por el personal desde «Ayuda → Reportar un problema».
 */
class ProblemReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', // Quién reportó (null si se eliminó el usuario)
        'user_name', // Nombre al momento de reportar
        'type',
        'description',
        'screen', // Pantalla desde la que se abrió el formulario
        'user_agent', // Navegador del usuario
        'app_version', // Versión del sistema al reportar
        'attachment', // Captura opcional (disco privado problem-reports)
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProblemReportType::class,
            'status' => ProblemReportStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Reportes que el administrador todavía no vio. */
    public function scopeNew($query)
    {
        return $query->where('status', ProblemReportStatus::New->value);
    }
}
