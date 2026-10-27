<?php

namespace App\Models;

use App\Enums\StaffAlert;
use App\Filament\Concerns\ClinicRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
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
        'phone', // Teléfono de contacto
        'avatar_url', // Ruta de la foto de perfil en el disco público
        'notification_preferences', // Canales elegidos por tipo de aviso (StaffAlert)
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
            'notification_preferences' => 'array',
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

    /**
     * Indica si el usuario registró algún dato clínico (mascotas, consultas, vacunaciones, cirugías o pruebas).
     * Al eliminar un usuario esas filas se borran en cascada, por eso se usa para bloquear la eliminación.
     */
    public function hasClinicalRecords(): bool
    {
        return $this->pets()->exists()
            || $this->consultations()->exists()
            || $this->vaccinations()->exists()
            || $this->surgeries()->exists()
            || $this->tests()->exists();
    }

    /**
     * Motivo por el que este usuario no puede eliminarse, o null si puede.
     */
    public function deletionBlockedReason(?self $actor = null): ?string
    {
        if ($actor && $actor->is($this)) {
            return 'No puede eliminar su propio usuario.';
        }

        if ($this->hasRole(ClinicRoles::ADMIN) && static::role(ClinicRoles::ADMIN)->count() <= 1) {
            return 'No se puede eliminar al único administrador.';
        }

        if ($this->hasClinicalRecords()) {
            return 'Tiene registros clínicos asociados que se perderían. Reasigne o conserve el usuario.';
        }

        return null;
    }

    /**
     * El veterinario (sin ser admin) ve en el dashboard y en los avisos solo sus
     * propias consultas, cirugías y pruebas; el resto del personal ve las de toda la clínica.
     */
    public function seesOnlyOwnRecords(): bool
    {
        return $this->hasRole(ClinicRoles::VETERINARIAN) && ! $this->hasRole(ClinicRoles::ADMIN);
    }

    /**
     * Canales por los que el usuario recibe un aviso: los que eligió en su perfil
     * (por defecto solo la campanita), y nada si su rol no puede ver ese recurso.
     *
     * @return array<int, string>
     */
    public function notificationChannels(StaffAlert $alert): array
    {
        if (! $this->can($alert->permission())) {
            return [];
        }

        $chosen = $this->notification_preferences[$alert->value] ?? ['database'];

        return array_values(array_intersect(['database', 'mail'], (array) $chosen));
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::disk('public')->url($this->avatar_url) : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists();
    }
}
