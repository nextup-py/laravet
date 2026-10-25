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

**Estados como enums** (cada uno con `HasLabel` + `HasColor`, accessor `status` en el modelo y filtro en la tabla): `VaccinationStatus` (Vencida / Por vencer ≤ 7 días / Al día / Renovada), `SurgeryStatus` (Programada / Hoy / Realizada, por fecha) y `TestStatus` (Pendiente / Con resultado, según haya archivos). `OwnerGender` es el enum del género del propietario (valores guardados `Male`/`Female`, etiquetas en español).

**Vacunación "vigente"**: solo la última aplicación de cada vacuna por mascota cuenta (misma mascota + mismo nombre de vacuna; ante igual fecha gana el id mayor). Las anteriores quedan `Renovada` y no generan alertas. Scopes en `Vaccination`: `current()`, `renewed()`, `actionable()` (vigentes vencidas o por vencer), `withStatus()` y `withRenewedFlag()` (agrega `is_renewed` a la consulta para no hacer una consulta por fila).

### Panel admin (Filament)

Toda la funcionalidad CRUD vive en `/app/Filament/Resources/`. No hay controladores personalizados — Filament maneja todo. El panel se configura en `app/Providers/Filament/AdminPanelProvider.php` (color amber, modo SPA, sidebar colapsable).

Los recursos principales son `PetResource` y `OwnerResource`. La ficha de `OwnerResource` lista las mascotas solo con su RelationManager (solo lectura: enlaza a la ficha de la mascota; el alta es el botón "Registrar mascota" con `?owner_id=`), sin sección duplicada en el infolist. `PetResource` agrupa los cuatro tipos de registros médicos como **RelationManagers** (en `/app/Filament/Resources/PetResource/RelationManagers/`), permitiendo gestión contextual desde la ficha de la mascota.

`ConsultationResource`, `SurgeryResource` y `TestResource` usan página tipo `ManageRecords` (sin List/Create/Edit separados). `VaccinationResource` tiene páginas Create y Edit propias.

### Patrones de los Resources

Los diez Resources siguen la misma estructura; al tocar uno, mantenerla:

- **Campos y filtros compartidos**: el formulario de un Resource que también aparece como RelationManager en la ficha de mascota expone `formFields()` (o `formSchema()` en `PetResource`) y lo reutiliza el RelationManager; los filtros comunes viven en métodos estáticos (`VaccinationResource::statusFilter()`, `TestResource::statusFilter()`). No duplicar listas de opciones (`vaccineOptions()`, `manufacturerOptions()`, `typeOptions()`).
- **Título del registro**: sobrescribir `getRecordTitle()` con el mismo título que la ficha y la búsqueda global (ej. "Consulta de Toby · 11/10/2026"); si no, el rastro de navegación muestra el atributo crudo (`recordTitleAttribute`).
- **Búsqueda global**: `getGloballySearchableAttributes()`, `getGlobalSearchEloquentQuery()` con `with(...)` y `getGlobalSearchResultDetails()`.
- **Páginas Ver** con `getTitle()` y, donde aplique, subtítulo; los enlaces a otros registros solo se generan si el usuario puede verlos (`PetResource::canView($pet)`).
- **Atajos**: botones que abren el formulario de creación con `?pet_id=` / `?owner_id=` / `?vaccine=` (el Select toma `->default(fn () => request()->query(...))`), y acciones de fila como "Revacunar" o "Cargar resultado".
- **Tests**: cada Resource tiene un `tests/Feature/Regression/*UxTest.php` (Pest) con sus filtros, orden, fichas y permisos.

### Dashboard

Widgets en `app/Filament/Widgets/`, todos con `canView()` gateado por permiso de Shield (`auth()->user()?->can('view_any_x')`) — un usuario solo ve el widget si tiene permiso de ver ese recurso, sin necesidad de tocar código cuando cambian los permisos de un rol:

- `PetSpeciesOverview` — stats de cantidad de mascotas por especie (`view_any_pet`).
- `TodayOverviewWidget` — KPIs del día: consultas de hoy, cirugías próximas (`Surgery::scopeUpcoming()`), pruebas sin resultado (`Test::scopeWithoutResult()`). A diferencia de los demás, cada stat se agrega al array solo si el usuario tiene el permiso correspondiente (`view_any_consultation`/`view_any_surgery`/`view_any_test`) — un usuario puede ver algunos KPIs de la fila y no otros, no es todo-o-nada como en los widgets de tabla.
- `UpcomingVaccinationsWidget` / `UpcomingSurgeriesWidget` / `TestsWithoutResultWidget` / `RecentConsultationsWidget` — tablas de seguimiento operativo, cada una gateada por el permiso `view_any_x` de su modelo. Las de vacunas usan `actionable()` (solo vigentes); el contador del menú de Vacunaciones también.

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
- Permiso custom fuera del patrón CRUD de un Resource: `use_ai_diagnostics` (controla el botón "Asistir con IA" en `ConsultationResource`/`ConsultationsRelationManager`, vía `auth()->user()?->can('use_ai_diagnostics')`). Sirve de ejemplo para agregar otros permisos custom — se crean con `Permission::firstOrCreate()` en el seeder y Shield los muestra solos bajo la pestaña "Permisos personalizados" en el form de rol (`entities.custom_permissions = true` en el config). Sus etiquetas en español se agregan en `lang/es/permissions.php` (`custom.<permiso>`): Shield no ofrece un hook para traducirlas, por eso el `RoleResource` está **publicado en `app/Filament/Resources/RoleResource.php`** (`shield:publish admin`) y sobrescribe `getCustomPermissionOptions()`. Al actualizar `bezhansalleh/filament-shield` hay que revisar a mano esa copia.
- `User::canAccessPanel()` no depende de una lista de roles — cualquier usuario con al menos un rol asignado entra al panel (`$this->roles()->exists()`).
- `ClinicSettingsPage` es la única pantalla que sigue siendo un chequeo de rol directo (`hasRole(ClinicRoles::ADMIN)`, no un permiso de Shield) — decisión consciente para no meter Pages/Widgets en el sistema de permisos (`entities.pages`/`entities.widgets = false` en el config). Los 2 widgets del dashboard no tienen restricción propia — ya alcanza con el acceso al panel.

### Tareas programadas

`routes/console.php` programa `send:vaccination-notifications` una vez al día (`->daily()`, zona `America/Asuncion`, `withoutOverlapping`). Avisa por email al propietario solo de vacunaciones **vigentes** (`current()`) sin `reminder_sent_at`, con próxima aplicación desde 30 días atrás hasta 3 días adelante; marca `reminder_sent_at` para no reenviar, y el texto dice "venció el…" o "está por vencer el…" según la fecha.

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

### Seguridad y datos

- **Datos del propietario**: el apellido, la CI, el teléfono (y su enlace a WhatsApp) y los enlaces a su ficha solo se muestran si el usuario puede ver propietarios (`OwnerResource::canViewAny()` / `canView()`); si no, las tablas de mascotas, consultas, vacunaciones, cirugías y pruebas muestran como mucho el nombre de pila. La sección de mascotas de la ficha de propietario se oculta sin permiso sobre mascotas. Al agregar una columna o ficha que muestre datos del propietario, aplicar el mismo criterio.
- **Eliminar es peligroso por `cascadeOnDelete`**: borrar un usuario borra sus consultas, vacunaciones, cirugías, pruebas y mascotas; borrar una ciudad o un barrio con propietarios borra a esos propietarios y su historial. Por eso `User::deletionBlockedReason()` (uno mismo, único admin, con registros clínicos) y `deletionBlockedReason()` de `Department`/`City`/`Neighborhood` (en uso) deshabilitan la acción, y `App\Filament\Concerns\GuardsDeletion` envuelve la `DeleteAction` (página Edit y modal de los RelationManagers) para bloquearla también en el servidor. La protección está en la interfaz y no en el modelo a propósito: los scripts de limpieza de datos demo borran usuarios por Tinker.
- **Un admin no puede cambiar su propio rol** ni dejar el sistema sin administradores (`UserResource`).
- **Resultados de pruebas laboratoriales**: archivos en el disco privado `test-results` (`storage/app/test-results`, no accesible por `/storage`), solo PDF/PNG/JPG, servidos por `TestResultController` (ruta del panel `filament.admin.tests.result`) que exige sesión y el permiso `view` sobre la prueba. Solo se muestran inline PDF y PNG/JPG; cualquier otro tipo se fuerza como descarga con `nosniff` y CSP `sandbox` (evita XSS almacenado). Una migración movió los archivos antiguos desde el disco público.

### Despliegue

- El deploy es el workflow manual `.github/workflows/deploy.yml` (`workflow_dispatch` sobre `main`): corre los tests, despliega por SSH ejecutando las migraciones, hace un health check y crea el tag `prod-AAAA-MM-DD_HHMM`. Si falla manda un correo; el detalle del paso que falló queda en `storage/logs/deploy.log` del servidor, y los errores de la app en `storage/logs/laravel.log`.
- El workflow `tests.yml` corre `pest` en cada PR y en cada push a `main`.
- Las migraciones corren solas en cada deploy: una migración de datos (mover archivos, renombrar un registro) debe ser idempotente.
- Un fallo de red al conectar por SSH (`i/o timeout`) no ejecuta nada: se puede relanzar. El tag anterior sirve de punto de retorno.

### Trampas técnicas

- **Los tests corren en SQLite y producción usa MySQL**: lo que SQLite tolera MySQL lo rechaza. Caso real: una subconsulta escalar (`addSelect` con `(select 1 …)`) que devuelve varias filas falla en MySQL con `1242 Subquery returns more than 1 row`; hay que usar `limit(1)`. Ante consultas con subconsultas, `groupBy` o funciones de fecha, probar también contra MySQL.
- **Pint**: formatear solo los archivos tocados (`./vendor/bin/pint ruta/archivo.php`); sobre carpetas enteras reformatea archivos ajenos.
- **Tinker**: para ejecutar código usar `php artisan tinker --execute="..."`; pasar un archivo abre una consola interactiva que se queda esperando.
- **Pruebas de tablas Filament**: en filtros `multiple()` el helper `filterTable('x', ['a'])` recibe la lista directa, no `['values' => [...]]`; con muchas filas (ej. 450 barrios) los registros pueden quedar fuera de la primera página, acotar con `searchTable()`.
- **Orden por defecto de listas**: no ordenar por una fecha de vencimiento ascendente si los registros históricos quedan vencidos para siempre (la primera página queda inútil).

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
- **Idioma**: todo texto visible al usuario (labels, notificaciones, mensajes de validación, emails, prompt de IA) va en español neutro, con tuteo y sin voseo ("Intenta", "Revisa", no "Intentá"/"Revisá"); los identificadores de código (clases, tablas, columnas, rutas) siguen en inglés. `fallback_locale` es `es`. Un label vacío en un campo de formulario sale en inglés ("Department id"): ponerlo siempre.
- **Tablas**: ID, "Registrado el" y "Actualizado el" ocultos por defecto (`toggleable`), fechas `d/m/Y`, orden por defecto explícito, conteos con `->counts('relación')`.
- **Git**: una rama por tema desde `origin/main`, un PR por tema; mensajes de commit y PR en español.
