<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Neighborhood extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     * Estos campos se pueden rellenar directamente al crear o actualizar un barrio.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'city_id', // ID de la ciudad a la que pertenece el barrio
        'name', // Nombre del barrio
    ];

    /**
     * Un barrio pertenece a una ciudad.
     * Esta relación define que cada barrio está asociado con una ciudad específica.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Un barrio puede tener muchos usuarios.
     * Esta relación podría utilizarse para asociar usuarios a un barrio específico.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Un barrio puede tener muchos dueños de mascotas.
     * Esta relación podría utilizarse para asociar los dueños de mascotas registrados en la aplicación con el barrio en el que residen.
     */
    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class);
    }

    /**
     * Motivo por el que no puede eliminarse (está en uso), o null si puede.
     */
    public function deletionBlockedReason(): ?string
    {
        if ($this->owners()->exists() || $this->users()->exists()) {
            return 'Hay propietarios o usuarios con domicilio en este barrio; eliminarlo los borraría junto con su historial.';
        }

        return null;
    }
}
