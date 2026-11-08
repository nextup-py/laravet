<?php

use App\Settings\ClinicSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Código de establecimiento de la sucursal principal (3 dígitos, como en la factura paraguaya). */
    private const MAIN_CODE = '001';

    /**
     * Crea la tabla `branches`, la sucursal principal (`001`, con los datos de la clínica si ya existen) y
     * `users.branch_id`, que se rellena con la sucursal principal para el personal que no es administrador
     * (el admin no tiene sucursal propia y elige una activa en el panel). Idempotente: el deploy corre las
     * migraciones sin seeders.
     */
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                // Código de establecimiento de 3 dígitos; coincide con el de la factura electrónica (SIFEN).
                $table->char('code', 3)->unique();
                $table->string('name');
                $table->string('address')->nullable();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('neighborhood_id')->nullable()->constrained()->nullOnDelete();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $mainId = $this->ensureMainBranch();

        if (! Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                // restrictOnDelete: una sucursal con personal no se elimina (ver Branch::deletionBlockedReason()).
                $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            });
        }

        $this->assignStaffToMainBranch($mainId);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        Schema::dropIfExists('branches');
    }

    /** Crea la sucursal principal si no existe y devuelve su id. */
    private function ensureMainBranch(): int
    {
        $existing = DB::table('branches')->where('code', self::MAIN_CODE)->value('id');

        if ($existing) {
            return (int) $existing;
        }

        // Si los ajustes todavía no se pueden leer (tabla sin migrar) la sucursal queda con datos mínimos.
        $settings = ClinicSettings::tryGet();

        return (int) DB::table('branches')->insertGetId([
            'code' => self::MAIN_CODE,
            'name' => 'Casa central',
            'address' => $settings?->address,
            'phone' => $settings?->phone,
            'email' => $settings?->email,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Asigna la sucursal principal a los usuarios sin sucursal que no son administradores. */
    private function assignStaffToMainBranch(int $mainId): void
    {
        $adminIds = [];

        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $adminIds = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'admin')
                ->where('model_has_roles.model_type', 'App\\Models\\User')
                ->pluck('model_has_roles.model_id')
                ->all();
        }

        DB::table('users')
            ->whereNull('branch_id')
            ->when($adminIds !== [], fn ($query) => $query->whereNotIn('id', $adminIds))
            ->update(['branch_id' => $mainId]);
    }
};
