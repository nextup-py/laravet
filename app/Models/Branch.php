<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sucursal (establecimiento) de la clínica. El código de 3 dígitos coincide con el establecimiento de la
 * factura paraguaya; la principal es la `001`.
 */
class Branch extends Model
{
    use HasFactory;

    /** Código de la sucursal principal, creada por la migración. */
    public const MAIN_CODE = '001';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code', // Código de establecimiento, 3 dígitos (001, 002…)
        'name', // Nombre de la sucursal
        'address', // Dirección
        'department_id', // Departamento
        'city_id', // Ciudad
        'neighborhood_id', // Barrio
        'phone', // Teléfono de la sucursal
        'email', // Correo de la sucursal
        'is_active', // Si está habilitada
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Solo las sucursales habilitadas. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** La sucursal principal, o null si se borró de la base. */
    public static function main(): ?self
    {
        return static::query()->where('code', self::MAIN_CODE)->first();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    /**
     * Motivo por el que no puede eliminarse (está en uso), o null si puede.
     */
    public function deletionBlockedReason(): ?string
    {
        if ($this->code === self::MAIN_CODE) {
            return 'Es la sucursal principal y no se puede eliminar.';
        }

        if ($this->users()->exists()) {
            return 'Tiene personal asignado. Reasígnalo primero.';
        }

        // Los registros clínicos solo anotan la sucursal, pero la base los protege con restrictOnDelete.
        foreach ([Owner::class, Pet::class, Consultation::class, Vaccination::class, Surgery::class, Test::class, PetWeight::class] as $model) {
            if ($model::query()->where('branch_id', $this->id)->exists()) {
                return 'Tiene registros asociados (propietarios, mascotas o historial clínico).';
            }
        }

        return null;
    }
}
