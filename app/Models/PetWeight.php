<?php

namespace App\Models;

use App\Observers\PetWeightObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(PetWeightObserver::class)]
class PetWeight extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'pet_id', // Mascota pesada
        'weight', // Peso en kilogramos
        'weighed_at', // Fecha del pesaje
        'user_id', // Quién lo registró
        'consultation_id', // Consulta en la que se anotó (null: pesaje suelto)
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'weighed_at' => 'date',
        ];
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }
}
