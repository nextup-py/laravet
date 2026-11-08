<?php

namespace App\Models;

use App\Enums\PetGender;
use App\Enums\PetReproductionStatus;
use App\Enums\PetSize;
use App\Enums\PetSpecies;
use App\Models\Concerns\BelongsToBranch;
use App\Observers\PetObserver;
use App\Support\Measure;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(PetObserver::class)]
class Pet extends Model
{
    use BelongsToBranch, HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     * Estos campos se pueden rellenar directamente al crear o actualizar una mascota.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branch_id', // Sucursal donde se registró (se completa sola al crear)
        'name', // Nombre de la mascota
        'species', // Especie de la mascota
        'breed', // Raza de la mascota
        'gender', // Género de la mascota
        'birthdate', // Fecha de nacimiento de la mascota
        'size', // Tamaño de la mascota
        'weight', // Peso de la mascota
        'fur', // Tipo de pelaje de la mascota
        'reproduction', // Estado reproductivo de la mascota
        'active', // Indica si la mascota está activa o no
        'image', // Ruta de la imagen de la mascota
        'owner_id', // ID del dueño de la mascota
        'user_id', // ID del veterinario que registró la mascota
    ];

    protected function casts(): array
    {
        return [
            'species' => PetSpecies::class,
            'gender' => PetGender::class,
            'size' => PetSize::class,
            'reproduction' => PetReproductionStatus::class,
            'birthdate' => 'date',
        ];
    }

    /**
     * Edad de la mascota en años (o meses si es menor a un año).
     */
    protected function age(): Attribute
    {
        return Attribute::make(get: function () {
            $years = (int) $this->birthdate->diffInYears(now());

            if ($years > 0) {
                return "{$years} ".($years === 1 ? 'año' : 'años');
            }

            $months = (int) $this->birthdate->diffInMonths(now());

            return "{$months} ".($months === 1 ? 'mes' : 'meses');
        });
    }

    /**
     * Resumen clínico en una línea: especie, raza, edad, peso y género.
     */
    public function summaryLine(): string
    {
        return "{$this->species->getLabel()} · {$this->breed} · {$this->age} · ".Measure::kg($this->weight)." · {$this->gender->getLabel()}";
    }

    /**
     * Avatar SVG (data URI) con las iniciales de la mascota, para cuando no tiene foto.
     */
    protected function initialsAvatarUrl(): Attribute
    {
        return Attribute::make(get: function () {
            $initial = e(mb_strtoupper(mb_substr($this->name, 0, 1)));
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="#f59e0b"/>'
                .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="sans-serif" font-size="30" fill="#fff">'.$initial.'</text></svg>';

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        });
    }

    /**
     * Historial de pesajes (el más reciente es el peso actual).
     */
    public function weights(): HasMany
    {
        return $this->hasMany(PetWeight::class);
    }

    /**
     * Deja `weight` igual al pesaje más reciente (fecha y, a igual fecha, el último cargado). Sin
     * eventos: no es una edición de la ficha. Sin pesajes, conserva el valor que tiene.
     */
    public function refreshCurrentWeight(): void
    {
        $latest = $this->weights()->orderByDesc('weighed_at')->orderByDesc('id')->first();

        if ($latest && (float) $latest->weight !== (float) $this->weight) {
            $this->forceFill(['weight' => $latest->weight])->saveQuietly();
        }
    }

    /**
     * Una mascota pertenece a un dueño.
     * Esta relación define que cada mascota está asociada con un dueño específico.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /**
     * Una mascota pertenece a un usuario (veterinario).
     * Esta relación define que cada mascota está asociada con un veterinario específico.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Una mascota tiene muchas vacunaciones.
     * Esta relación define que una mascota puede tener múltiples registros de vacunación asociados.
     */
    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    /**
     * Una mascota tiene muchas consultas.
     * Esta relación define que una mascota puede tener múltiples consultas médicas asociadas.
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    /**
     * Una mascota tiene muchas cirugías.
     * Esta relación define que una mascota puede tener múltiples cirugías asociadas.
     */
    public function surgeries(): HasMany
    {
        return $this->hasMany(Surgery::class);
    }

    /**
     * Una mascota tiene muchos exámenes.
     * Esta relación define que una mascota puede tener múltiples exámenes de laboratorio asociados.
     */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    /**
     * Motivo por el que no puede eliminarse (tiene historial clínico), o null si puede.
     */
    public function deletionBlockedReason(): ?string
    {
        $hasHistory = $this->consultations()->exists()
            || $this->vaccinations()->exists()
            || $this->surgeries()->exists()
            || $this->tests()->exists();

        if ($hasHistory) {
            return 'Tiene historial clínico; eliminarla lo borraría. Márcala como inactiva en su lugar.';
        }

        return null;
    }
}
