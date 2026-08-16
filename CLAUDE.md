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

El widget `PetSpeciesOverview` muestra estadísticas de especies en el dashboard.

### Diagnóstico asistido por IA

`app/Services/AIDiagnosticService.php` encapsula la integración con la API de Claude (Anthropic). El método `suggest(Pet $pet, string $anamnesis): array` construye un prompt con los datos clínicos de la mascota (especie, raza, edad, peso, género, reproducción) y la anamnesis del veterinario, llama al modelo configurado en `config('services.anthropic.model')` (env `ANTHROPIC_MODEL`, default `claude-opus-4-8`) y retorna `['diagnosis' => '...', 'treatment' => '...']` en JSON.

El botón **"Asistir con IA"** aparece en el formulario de consulta tanto en `ConsultationsRelationManager` como en `ConsultationResource`, en creación y edición. Al presionarlo, pre-rellena los campos Diagnóstico y Tratamiento — el veterinario puede editar antes de guardar. Si esos campos ya tienen contenido, se pide confirmación antes de sobrescribirlos.

Requiere `ANTHROPIC_API_KEY` en `.env`. La key se obtiene en [console.anthropic.com](https://console.anthropic.com).

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
