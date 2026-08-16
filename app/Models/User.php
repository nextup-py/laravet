<?php

namespace App\Models;

use App\Filament\Concerns\ClinicRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', // Nombre del usuario
        'email', // Correo electrónico del usuario
        'password', // Contraseña del usuario
        'department_id', // ID del departamento de residencia
        'city_id', // ID de la ciudad de residencia
        'neighborhood_id', // ID del barrio de residencia
        'address', // Dirección de residencia
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Un usuario pertenece a un departamento.
     * Esta relación define que cada usuario está asociado con un departamento específico.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Un usuario pertenece a una ciudad.
     * Esta relación define que cada usuario está asociado con una ciudad específica.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Un usuario pertenece a un barrio.
     * Esta relación define que cada usuario está asociado con un barrio específico.
     */
    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function surgeries(): HasMany
    {
        return $this->hasMany(Surgery::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(ClinicRoles::ALL_STAFF);
    }
}
