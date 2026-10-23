<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     * Estos son los campos que se pueden rellenar directamente al crear o actualizar un registro.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'department_id', // ID del departamento al que pertenece la ciudad
        'name', // Nombre de la ciudad
        'population', // Población de la ciudad
    ];

    /**
     * Una ciudad pertenece a un departamento.
     * Esta relación define que una ciudad está asociada con un solo departamento.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Una ciudad tiene muchos barrios.
     * Esta relación define que una ciudad puede tener múltiples barrios asociados.
     */
    public function neighborhoods(): HasMany
    {
        return $this->hasMany(Neighborhood::class);
    }

    /**
     * Una ciudad puede tener muchos usuarios.
     * Esta relación podría utilizarse para asociar usuarios a una ciudad específica.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Una ciudad puede tener muchos dueños de mascotas.
     * Esta relación podría utilizarse para asociar los dueños de mascotas registrados en la aplicación con la ciudad en la que residen.
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
        if ($this->neighborhoods()->exists()) {
            return 'Tiene barrios asociados. Elimínelos o reubíquelos primero.';
        }

        if ($this->owners()->exists() || $this->users()->exists()) {
            return 'Hay propietarios o usuarios con domicilio en esta ciudad; eliminarla los borraría junto con su historial.';
        }

        return null;
    }
}
