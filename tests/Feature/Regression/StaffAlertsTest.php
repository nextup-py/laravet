<?php

use App\Enums\StaffAlert;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use App\Notifications\StaffAlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Cache::flush());

it('por defecto el usuario recibe los avisos de sus permisos solo en la campanita', function () {
    $vet = actingAsRole('veterinarian');

    expect($vet->notificationChannels(StaffAlert::Vaccinations))->toBe(['database'])
        ->and($vet->notificationChannels(StaffAlert::Surgeries))->toBe(['database']);
});

it('un rol sin permiso sobre el recurso no recibe ese aviso aunque lo haya elegido', function () {
    $user = actingAsRole('assistant');
    $user->roles->first()->revokePermissionTo('view_any_surgery');
    $user->forgetCachedPermissions();
    $user->update(['notification_preferences' => ['surgeries' => ['database', 'mail']]]);

    expect($user->fresh()->notificationChannels(StaffAlert::Surgeries))->toBe([]);
});

it('el comando avisa con el conteo y respeta los canales elegidos', function () {
    Notification::fake();
    $vet = actingAsRole('veterinarian');
    $vet->update(['notification_preferences' => ['vaccinations' => ['database', 'mail'], 'surgeries' => [], 'tests' => []]]);
    Vaccination::factory()->proximaAVencer(2)->create();

    test()->artisan('send:staff-alerts')->assertSuccessful();

    Notification::assertSentTo($vet, StaffAlertNotification::class, function (StaffAlertNotification $notification) use ($vet): bool {
        return $notification->alert === StaffAlert::Vaccinations
            && $notification->count === 1
            && $notification->via($vet) === ['database', 'mail'];
    });
    Notification::assertCount(1);
});

it('no avisa cuando no hay nada pendiente y no repite el aviso el mismo día', function () {
    Notification::fake();
    actingAsRole('admin');

    test()->artisan('send:staff-alerts')->assertSuccessful();
    Notification::assertNothingSent();

    Test::factory()->create(['result' => null]);
    test()->artisan('send:staff-alerts')->assertSuccessful();
    test()->artisan('send:staff-alerts')->assertSuccessful();

    Notification::assertCount(1);
});

it('el veterinario solo recibe aviso de sus propias cirugías y pruebas', function () {
    Notification::fake();
    $vet = actingAsRole('veterinarian');
    Surgery::factory()->create(['date' => today()]);
    Test::factory()->create(['result' => null]);

    test()->artisan('send:staff-alerts')->assertSuccessful();
    Notification::assertNothingSent();

    Surgery::factory()->create(['date' => today(), 'user_id' => $vet->id]);
    Cache::flush();
    test()->artisan('send:staff-alerts')->assertSuccessful();

    Notification::assertSentTo($vet, StaffAlertNotification::class, fn (StaffAlertNotification $n): bool => $n->alert === StaffAlert::Surgeries && $n->count === 1);
});

it('la campanita guarda el aviso con su enlace y sin duplicar el tipo', function () {
    $vet = actingAsRole('veterinarian');
    Vaccination::factory()->proximaAVencer(2)->create();

    test()->artisan('send:staff-alerts')->assertSuccessful();

    $notification = $vet->notifications()->first();
    expect($notification->data['alert'])->toBe('vaccinations')
        ->and($notification->data['title'])->toContain('vacuna')
        ->and($notification->data['actions'][0]['url'])->toBe(StaffAlert::Vaccinations->url());
});

it('el usuario elige sus canales de aviso desde el perfil y se guardan', function () {
    $user = actingAsRole('veterinarian');
    $department = Department::create(['name' => 'Dpto '.uniqid(), 'capital' => 'Capital']);
    $city = City::create(['department_id' => $department->id, 'name' => 'Ciudad', 'population' => 100]);
    $neighborhood = Neighborhood::create(['city_id' => $city->id, 'name' => 'Barrio']);

    Livewire::test(EditProfile::class)
        ->assertFormSet(['notification_preferences.vaccinations' => ['database']])
        ->fillForm([
            'department_id' => $department->id,
            'city_id' => $city->id,
            'neighborhood_id' => $neighborhood->id,
            'address' => 'Calle 1',
            'notification_preferences.vaccinations' => ['database', 'mail'],
            'notification_preferences.surgeries' => [],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->notificationChannels(StaffAlert::Vaccinations))->toBe(['database', 'mail'])
        ->and($user->fresh()->notificationChannels(StaffAlert::Surgeries))->toBe([]);
});

it('el perfil de un rol sin permisos clínicos no ofrece avisos', function () {
    $role = Role::create(['name' => 'sin_permisos', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    Livewire::test(EditProfile::class)->assertFormFieldDoesNotExist('notification_preferences.vaccinations');
});
