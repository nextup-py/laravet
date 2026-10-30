<?php

use App\Enums\SurgeryStatus;
use App\Enums\TestStatus;
use App\Enums\VaccinationStatus;
use App\Filament\Concerns\ClinicRoles;
use App\Models\Consultation;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use App\Settings\ClinicSettings;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoVolumeSeeder;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\PetSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cantidad de filas por tabla, para comprobar que volver a sembrar no duplica nada.
 *
 * @return array<string, int>
 */
function demoRowCounts(): array
{
    return [
        'users' => User::count(),
        'owners' => Owner::count(),
        'pets' => Pet::count(),
        'consultations' => Consultation::count(),
        'vaccinations' => Vaccination::count(),
        'surgeries' => Surgery::count(),
        'tests' => Test::count(),
    ];
}

/**
 * Permisos de cada rol base, por nombre de rol.
 *
 * @return array<string, array<int, string>>
 */
function permissionsByRole(): array
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    return Role::with('permissions')->get()
        ->mapWithKeys(fn (Role $role): array => [$role->name => $role->permissions->pluck('name')->sort()->values()->all()])
        ->all();
}

describe('DemoSeeder', function () {
    beforeEach(function () {
        Storage::fake('test-results');
    });

    it('siembra los datos demo completos', function () {
        $this->seed(DemoSeeder::class);

        expect(User::role(ClinicRoles::VETERINARIAN)->count())->toBe(2)
            ->and(User::role(ClinicRoles::ASSISTANT)->count())->toBe(1)
            ->and(Owner::count())->toBe(count(OwnerSeeder::OWNERS))
            ->and(Pet::count())->toBe(count(PetSeeder::PETS))
            ->and(Consultation::count())->toBeGreaterThan(0)
            ->and(Vaccination::count())->toBeGreaterThan(0)
            ->and(Surgery::count())->toBeGreaterThan(0)
            ->and(Test::count())->toBeGreaterThan(0);

        // Ninguna ciudad quedó sin resolver: todos los propietarios tienen ubicación completa.
        expect(Owner::whereNull('city_id')->orWhereNull('neighborhood_id')->count())->toBe(0);
    });

    it('cada propietario tiene un barrio real de su ciudad', function () {
        $this->seed(DemoSeeder::class);

        foreach (OwnerSeeder::OWNERS as $data) {
            $owner = Owner::where('ci', $data['ci'])->with(['city', 'neighborhood'])->firstOrFail();

            expect($owner->city->name)->toBe($data['city'])
                ->and($owner->neighborhood->name)->toBe($data['neighborhood'])
                ->and($owner->neighborhood->city_id)->toBe($owner->city_id);
        }
    });

    it('los propietarios demo no tienen correos ni teléfonos que puedan ser de personas reales', function () {
        $this->seed(DemoSeeder::class);

        foreach (Owner::whereIn('ci', array_column(OwnerSeeder::OWNERS, 'ci'))->get() as $owner) {
            expect($owner->email)->toEndWith('@mbopivet-demo.test')
                ->and($owner->phone)->toMatch('/^09\d{2}0001\d{2}$/');
        }
    });

    it('los cachorros y gatitos tienen edad coherente con su historial', function () {
        $this->seed(DemoSeeder::class);

        foreach (['Coco', 'Duque', 'Pancho', 'Princesa'] as $name) {
            expect(Pet::where('name', $name)->firstOrFail()->birthdate->diffInMonths(now()))->toBeLessThan(6);
        }
    });

    it('completa los datos de la clínica sin pisar los que ya existen ni cargar el correo', function () {
        $settings = app(ClinicSettings::class);
        $settings->address = 'Mi dirección real';
        $settings->email = null;
        $settings->save();

        $this->seed(DemoSeeder::class);

        $settings = app(ClinicSettings::class);
        expect($settings->address)->toBe('Mi dirección real')
            ->and($settings->ruc)->not->toBeEmpty()
            ->and($settings->phone)->not->toBeEmpty()
            ->and($settings->email)->toBeNull();
    });

    it('es idempotente: volver a sembrar no duplica registros', function () {
        $this->seed(DemoSeeder::class);
        $before = demoRowCounts();

        $this->seed(DemoSeeder::class);

        expect(demoRowCounts())->toBe($before);
    });

    it('deja registros en todos los estados de vacunación, cirugía y prueba', function () {
        $this->seed(DemoSeeder::class);

        $vaccinationStatuses = Vaccination::withRenewedFlag()->get()->pluck('status')->unique();
        $surgeryStatuses = Surgery::all()->pluck('status')->unique();
        $testStatuses = Test::all()->pluck('status')->unique();

        expect($vaccinationStatuses->all())->toContain(
            VaccinationStatus::Overdue, VaccinationStatus::DueSoon, VaccinationStatus::UpToDate, VaccinationStatus::Renewed,
        )->and($surgeryStatuses->all())->toContain(
            SurgeryStatus::Scheduled, SurgeryStatus::Today, SurgeryStatus::Done,
        )->and($testStatuses->all())->toContain(TestStatus::Pending, TestStatus::WithResult);
    });

    it('guarda los PDF de ejemplo de los resultados en el disco privado', function () {
        $this->seed(DemoSeeder::class);

        $test = Test::withResult()->firstOrFail();

        Storage::disk('test-results')->assertExists($test->result[0]);
        expect(Storage::disk('test-results')->get($test->result[0]))->toStartWith('%PDF-')->toEndWith('%%EOF');
    });

    it('reparte el historial solo entre veterinarios', function () {
        $this->seed(DemoSeeder::class);

        $vetIds = User::role(ClinicRoles::VETERINARIAN)->pluck('id');

        foreach ([Pet::class, Consultation::class, Vaccination::class, Surgery::class, Test::class] as $model) {
            expect($model::whereNotIn('user_id', $vetIds)->count())->toBe(0);
        }
    });

    it('usa DEMO_PASSWORD si está definida', function () {
        Storage::fake('test-results');
        config(['app.demo_password' => 'clave-demo-123']);

        $this->seed(DemoSeeder::class);

        $vet = User::where('email', 'maria.gonzalez@mbopivet.com.py')->firstOrFail();
        expect(Hash::check('clave-demo-123', $vet->password))->toBeTrue();
    });

    it('usa password en local y una aleatoria que no se repite en staging', function () {
        Storage::fake('test-results');

        $this->seed(DemoSeeder::class);
        $vet = User::where('email', 'maria.gonzalez@mbopivet.com.py')->firstOrFail();
        expect(Hash::check('password', $vet->password))->toBeTrue();

        User::query()->delete();
        app()->detectEnvironment(fn () => 'staging');
        $this->seed(DemoSeeder::class);
        $staging = User::where('email', 'maria.gonzalez@mbopivet.com.py')->firstOrFail();
        expect(Hash::check('password', $staging->password))->toBeFalse();

        // Volver a sembrar no cambia una contraseña ya generada.
        $hash = $staging->password;
        $this->seed(DemoSeeder::class);
        expect($staging->fresh()->password)->toBe($hash);
    });

    it('no corre en producción', function () {
        app()->detectEnvironment(fn () => 'production');

        app(DemoSeeder::class)->run();

        expect(Owner::count())->toBe(0)->and(User::count())->toBe(0);
    });

    it('DatabaseSeeder siembra el demo en staging', function () {
        app()->detectEnvironment(fn () => 'staging');

        $this->seed(DatabaseSeeder::class);

        expect(Pet::count())->toBe(count(PetSeeder::PETS));
    });
});

describe('DemoVolumeSeeder', function () {
    it('crea la cantidad configurada de mascotas sobre el demo', function () {
        Storage::fake('test-results');
        $this->seed(DemoSeeder::class);
        $base = Pet::count();
        config(['app.demo_volume' => 6]);

        $this->seed(DemoVolumeSeeder::class);

        expect(Pet::count())->toBe($base + 6);

        // Volver a correrlo no suma más: ya llegó a la cantidad configurada.
        $this->seed(DemoVolumeSeeder::class);

        expect(Pet::count())->toBe($base + 6);

        config(['app.demo_volume' => 8]);
        $this->seed(DemoVolumeSeeder::class);

        expect(Pet::count())->toBe($base + 8);
    });

    it('no corre en producción', function () {
        app()->detectEnvironment(fn () => 'production');

        app(DemoVolumeSeeder::class)->run();

        expect(Pet::count())->toBe(0);
    });
});

describe('RolesAndPermissionsSeeder', function () {
    it('no pisa los permisos que un admin sumó a un rol base', function () {
        $assistant = Role::findByName(ClinicRoles::ASSISTANT);
        $assistant->givePermissionTo('delete_pet');

        $this->seed(RolesAndPermissionsSeeder::class);

        expect($assistant->fresh()->hasPermissionTo('delete_pet'))->toBeTrue();
    });

    it('es idempotente', function () {
        $before = permissionsByRole();
        $permissions = Permission::count();

        $this->seed(RolesAndPermissionsSeeder::class);

        expect(permissionsByRole())->toBe($before)->and(Permission::count())->toBe($permissions);
    });

    it('deja a los roles lo mismo que las migraciones de permisos (que son lo que corre en producción)', function () {
        $fromSeeder = permissionsByRole();
        $permissionNames = Permission::pluck('name')->all();

        // Producción no corre seeders: parte de roles vacíos y solo aplica las migraciones de permisos.
        DB::table('role_has_permissions')->delete();
        Permission::query()->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $migrations = array_filter(
            glob(database_path('migrations/*_{create,grant}_*permission*.php'), GLOB_BRACE),
            fn (string $file): bool => ! str_contains($file, 'permission_tables'),
        );
        expect($migrations)->not->toBeEmpty();

        foreach ($migrations as $file) {
            (require $file)->up();
        }

        $fromMigrations = permissionsByRole();

        expect(Permission::pluck('name')->all())->each->toBeIn($permissionNames);

        foreach ($fromMigrations as $role => $permissions) {
            expect($permissions)->each->toBeIn($fromSeeder[$role]);
        }
    });
});
