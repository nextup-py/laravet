# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development (PHP server + Vite en una terminal)
composer run dev

# Individual servers
php artisan serve
npm run dev

# Tests
php artisan test
php artisan test --filter=NombreDelTest

# Code formatting
./vendor/bin/pint

# Migrations & seeders
php artisan migrate
php artisan db:seed
php artisan migrate:fresh --seed   # Resetea y rellena con datos demo

# Filament
php artisan filament:make-resource NombreRecurso --generate
php artisan filament:make-widget NombreWidget
```

## Arquitectura

**Laravet** es un sistema de gestión clínica veterinaria para la clínica "Mbopivet" (Areguá, Paraguay). Proyecto de tesis final de Ingeniería Informática — UCSA.

Stack: Laravel 11 + Filament 3 (panel admin) + Vite + SQLite (default) / MySQL.

### Modelo de dominio

El flujo central es: `User (veterinario)` atiende `Pet (mascota)` que pertenece a `Owner (propietario)`.

Cada `Pet` tiene cuatro tipos de registros médicos, todos con `belongs_to` a `Pet` y `User`:
- `Consultation` — anamnesis, diagnóstico, tratamiento
- `Vaccination` — fecha aplicación, próxima aplicación, lote, fabricante
- `Surgery` — fecha, tipo, observación
- `Test` — fecha, tipo, resultado

La localización sigue la jerarquía paraguaya: `Department → City → Neighborhood`. Tanto `User` como `Owner` tienen los tres campos de ubicación. Los selects de ciudad y barrio son dinámicos (dependen del departamento/ciudad seleccionado).

### Panel admin (Filament)

Toda la funcionalidad CRUD vive en `/app/Filament/Resources/`. No hay controladores personalizados — Filament maneja todo. El panel se configura en `app/Providers/Filament/AdminPanelProvider.php` (color amber, modo SPA, sidebar colapsable).

Los recursos principales son `PetResource` y `OwnerResource`. `PetResource` agrupa los cuatro tipos de registros médicos como **RelationManagers** (en `/app/Filament/Resources/PetResource/RelationManagers/`), permitiendo gestión contextual desde la ficha de la mascota.

`ConsultationResource`, `SurgeryResource` y `TestResource` usan página tipo `ManageRecords` (sin List/Create/Edit separados). `VaccinationResource` tiene páginas Create y Edit propias.

### Dashboard

Widgets en `app/Filament/Widgets/`, todos con `canView()` gateado por permiso de Shield (`auth()->user()?->can('view_any_x')`) — un usuario solo ve el widget si tiene permiso de ver ese recurso, sin necesidad de tocar código cuando cambian los permisos de un rol:

- `PetSpeciesOverview` — stats de cantidad de mascotas por especie (`view_any_pet`).
- `TodayOverviewWidget` — KPIs del día: consultas de hoy, cirugías próximas (`Surgery::scopeUpcoming()`), pruebas sin resultado (`Test::scopeWithoutResult()`). A diferencia de los demás, cada stat se agrega al array solo si el usuario tiene el permiso correspondiente (`view_any_consultation`/`view_any_surgery`/`view_any_test`) — un usuario puede ver algunos KPIs de la fila y no otros, no es todo-o-nada como en los widgets de tabla.
- `UpcomingVaccinationsWidget` / `UpcomingSurgeriesWidget` / `TestsWithoutResultWidget` / `RecentConsultationsWidget` — tablas de seguimiento operativo, cada una gateada por el permiso `view_any_x` de su modelo.

Todos se descubren automáticamente vía `discoverWidgets` en `AdminPanelProvider` — no hace falta registrarlos a mano, solo `AccountWidget` (core de Filament) está en el array `->widgets([...])`.

### Diagnóstico asistido por IA

`app/Services/AIDiagnosticService.php` encapsula la integración con la API de Claude (Anthropic). El método `suggest(Pet $pet, string $anamnesis): array` construye un prompt con los datos clínicos de la mascota (especie, raza, edad, peso, género, reproducción) y la anamnesis del veterinario, llama al modelo configurado en `config('services.anthropic.model')` (env `ANTHROPIC_MODEL`, default `claude-opus-4-8`) y retorna `['diagnosis' => '...', 'treatment' => '...']` en JSON.

El botón **"Asistir con IA"** aparece en el formulario de consulta tanto en `ConsultationsRelationManager` como en `ConsultationResource`, en creación y edición. Al presionarlo, pre-rellena los campos Diagnóstico y Tratamiento — el veterinario puede editar antes de guardar. Si esos campos ya tienen contenido, se pide confirmación antes de sobrescribirlos.

Requiere `ANTHROPIC_API_KEY` en `.env`. La key se obtiene en [console.anthropic.com](https://console.anthropic.com).

### Roles y autorización

**Roles dinámicos, permisos fijos**, vía [`bezhansalleh/filament-shield`](https://github.com/bezhanSalleh/filament-shield) sobre Spatie Laravel-Permission. Un admin gestiona roles desde el panel — **"Configuración → Roles y permisos"** — creando/editando/eliminando roles y marcando qué permisos tiene cada uno, sin tocar código. El catálogo de *permisos* (qué acciones existen) sí está fijo en código:

- `php artisan shield:generate --resource=NombreResource --panel=admin` genera automáticamente una Policy (`app/Policies/`) y los permisos CRUD (`view_x`, `view_any_x`, `create_x`, `update_x`, `delete_x`, `delete_any_x`, etc.) para un Resource nuevo — correrlo cada vez que se agregue un Resource, para que aparezca en la pantalla de Roles. Los Resources **no** definen su propia lógica de autorización (no hay traits de rol) — Filament consulta la Policy generada automáticamente en cuanto existe.
- Los permisos reales en base de datos (las filas, no las Policies) se siembran en `database/seeders/RolesAndPermissionsSeeder.php`, que también define qué permisos tienen por defecto los 3 roles sembrados (`admin`, `veterinarian`, `assistant` — constantes en `app/Filament/Concerns/ClinicRoles.php`). Si se agrega un Resource nuevo, además de `shield:generate` hay que decidir en ese seeder qué permisos le corresponden a `veterinarian`/`assistant` por defecto (`admin` no necesita nada, ver abajo).
- El rol `admin` es el **Super Admin** de Shield (`config/filament-shield.php` → `super_admin.name = 'admin'`, `define_via_gate = true`): bypassea todos los permisos vía `Gate::before`, así que nunca hay que sincronizarle permisos a mano, ni siquiera cuando se agregan Resources nuevos.
- Permiso custom fuera del patrón CRUD de un Resource: `use_ai_diagnostics` (controla el botón "Asistir con IA" en `ConsultationResource`/`ConsultationsRelationManager`, vía `auth()->user()?->can('use_ai_diagnostics')`). Sirve de ejemplo para agregar otros permisos custom — se crean con `Permission::firstOrCreate()` en el seeder y Shield los muestra solos bajo la pestaña "Permisos personalizados" en el form de rol (`entities.custom_permissions = true` en el config).
- `User::canAccessPanel()` no depende de una lista de roles — cualquier usuario con al menos un rol asignado entra al panel (`$this->roles()->exists()`).
- `ClinicSettingsPage` es la única pantalla que sigue siendo un chequeo de rol directo (`hasRole(ClinicRoles::ADMIN)`, no un permiso de Shield) — decisión consciente para no meter Pages/Widgets en el sistema de permisos (`entities.pages`/`entities.widgets = false` en el config). Los 2 widgets del dashboard no tienen restricción propia — ya alcanza con el acceso al panel.

### Tareas programadas

`routes/console.php` define el comando `send:vaccination-notifications` que corre cada minuto para recordatorios de vacunación.

### Seeders

`DatabaseSeeder` separa datos de producción de datos de demo, siguiendo el
patrón `ProductionSeeder`/`DemoSeeder`:

- **`ProductionSeeder`** corre siempre (en cualquier entorno). Llama a
  `RolesAndPermissionsSeeder` y `RegionsSeeder`, y crea (o reutiliza si ya
  existe) el usuario admin usando `ADMIN_NAME`/`ADMIN_EMAIL` del `.env`
  (expuestos como `config('app.admin_name')`/`config('app.admin_email')`).
  Si el admin se crea por primera vez, se genera una password aleatoria con
  `Str::password(16)` que se muestra **una sola vez** por consola — hay que
  guardarla en ese momento.
- **`DemoSeeder`** corre solo en entornos `local`/`testing` (`php artisan
  migrate:fresh --seed`). Crea 2 veterinarios ficticios (Dra. María González,
  Dr. Carlos Giménez) y luego datos contextualizados en Paraguay:

| Seeder | Contenido |
|--------|-----------|
| `OwnerSeeder` | 10 propietarios con CI, teléfono y dirección paraguayos |
| `PetSeeder` | 15 mascotas (10 caninos, 5 felinos) con razas y datos reales |
| `ConsultationSeeder` | 12 consultas con casos clínicos veterinarios reales |
| `VaccinationSeeder` | 23 registros (Séptuple, Antirrábica, Triple Felina, Leucemia Felina) |
| `SurgerySeeder` | 6 cirugías (castraciones, OVH, suturas, extracción dental) |
| `TestSeeder` | 9 exámenes (hemograma, bioquímica, ELISA, urinálisis, radiografía) |

Credenciales demo por defecto en `.env`/`.env.example`:
`ADMIN_EMAIL=admin@mbopivet.com.py` (la password se genera y se muestra al
correr el seeder por primera vez, ya no es fija).

### Convenciones del proyecto

- Los modelos usan `cascadeOnDelete()` en las foreign keys — no hay soft deletes.
- Los campos de `Pet` como `species`, `gender`, `size`, `reproduction` son enums PHP definidos directamente en el modelo.
- Las imágenes de mascotas se suben con el editor de imágenes de Filament.
- La base de datos por defecto es SQLite (`database/database.sqlite`). Para MySQL, configurar `DB_*` en `.env`.
- Los seeders usan queries SQL con collation de MySQL para búsquedas de ciudades con acentos (ej: "Itauguá").
- Estilo de imports (dos patrones distintos, según el paquete):
  - **Sub-namespaces de Filament** (`Forms`, `Tables`, `Infolists`): se importa el namespace padre (`use Filament\Forms;`, `use Filament\Tables;`, `use Filament\Infolists;`) y se referencia el componente completo, ej. `Forms\Components\Select::make()`, `Tables\Columns\TextColumn::make()`, `Tables\Actions\EditAction::make()`, `Infolists\Components\TextEntry::make()`. No se importa cada componente individual.
  - **Facades de Illuminate** (`Illuminate\Support\Facades\*`) y el resto del código (modelos, enums, servicios propios): se importa la clase individual arriba, ej. `use Illuminate\Support\Facades\Auth;`, y se usa `Auth::id()` directo.
- Las acciones de fila en las tablas de Filament se agrupan en un menú desplegable con `Tables\Actions\ActionGroup::make([...])` (Ver/Editar), en vez de íconos sueltos. `Eliminar` **no** es una acción de fila suelta en ningún Resource/RelationManager — es deliberadamente menos accesible para reducir el riesgo de borrar por error un registro clínico desde una tabla densa: en Resources con página propia vive en el header de la página Edit (`Actions\DeleteAction::make()`); en RelationManagers (sin página propia) vive dentro del modal de "Editar" vía `EditAction::make()->extraModalFooterActions([DeleteAction::make()->cancelParentActions()])`. Tampoco hay `DeleteBulkAction` en ninguna tabla del panel.
