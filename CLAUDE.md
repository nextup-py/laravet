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
php artisan laravet:seed           # Siembra guiada (menú); atajos: composer run seed | seed:demo | seed:volume

# Filament
php artisan filament:make-resource NombreRecurso --generate
php artisan filament:make-widget NombreWidget
```

## Arquitectura

**Laravet** es un sistema de gestión clínica veterinaria para la clínica "Mbopivet" (Areguá, Paraguay). Proyecto de tesis final de Ingeniería Informática — UCSA.

Stack: Laravel 12 + Filament 3.3 (panel admin) + Pest + Vite + SQLite (default) / MySQL. Para desarrollo con IA se usa Laravel Boost (`laravel/boost`, solo dev): el servidor MCP está en `.mcp.json` (`php artisan boost:mcp`) y las guías generales (bloque delimitado por las etiquetas de Boost, al final de este archivo) se regeneran con `php artisan boost:update`; no editarlas a mano. Incluye la guía genérica de Filament; las convenciones de este proyecto (Resources, tablas, permisos) son las de arriba y tienen prioridad. Cuidado: no escribir en este archivo las etiquetas literales del bloque, porque `boost:install` reemplaza todo lo que haya entre ellas.

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

Toda la funcionalidad CRUD vive en `/app/Filament/Resources/`. No hay controladores personalizados — Filament maneja todo. El panel se configura en `app/Providers/Filament/AdminPanelProvider.php` (color amber, modo SPA, sidebar colapsable). **Menú lateral**: grupos en este orden (`navigationGroups()` del proveedor): Pacientes (mascotas, propietarios), Historial clínico (consultas, vacunaciones, cirugías, pruebas y Reportes), Configuración (plegado: datos de la clínica, roles, usuarios, registro de exportaciones), Catálogos (plegado: departamentos, ciudades, barrios) y Ayuda (Guía de uso, Soporte, Novedades, Reportar un problema y, para el admin, Reportes de problemas y Estado del sistema; Filament muestra primero lo que no tiene grupo, por eso tienen el suyo). Contadores: vacunaciones (vencidas o por vencer), cirugías (hoy y próximos 7 días) y pruebas (sin resultado), ocultos en cero y con tooltip; los de cirugías y pruebas usan `FocusesOnOwnRecords`, así que el veterinario cuenta solo lo suyo. Un Resource o página nuevo debe declarar su grupo y orden dentro de este esquema.

Los recursos principales son `PetResource` y `OwnerResource`. La ficha de `OwnerResource` lista las mascotas solo con su RelationManager (solo lectura: enlaza a la ficha de la mascota; el alta es el botón "Registrar mascota" con `?owner_id=`), sin sección duplicada en el infolist. En los RelationManagers de la mascota (consultas, vacunas, cirugías, pruebas) el clic en la fila abre la lectura en un modal (`recordAction('view')` con un `ViewAction` oculto por CSS, porque Filament no monta acciones `hidden()`) y el menú ofrece «Abrir ficha» y «Editar». `PetResource` agrupa los cuatro tipos de registros médicos como **RelationManagers** (en `/app/Filament/Resources/PetResource/RelationManagers/`), permitiendo gestión contextual desde la ficha de la mascota.

**URLs en español**: cada Resource declara `protected static ?string $slug` (mascotas, propietarios, consultas, vacunaciones, cirugias, pruebas, usuarios, departamentos, ciudades, barrios, roles —`config('filament-shield.shield_resource.slug')`—, exportaciones) y las páginas propias su `$slug` (`datos-de-la-clinica`, `reportes`, `guia-de-uso`, `perfil`); sin tildes. Los nombres de ruta siguen ese slug (`filament.admin.resources.mascotas.view`). Las URLs viejas en inglés dan 404 a propósito (sin redirecciones); los subtramos `create` y `edit` siguen en inglés. Un Resource nuevo debe declarar su `$slug` en español y `SpanishSlugsTest` lo lista.

`ConsultationResource`, `SurgeryResource` y `TestResource` usan página tipo `ManageRecords` (sin List/Create/Edit separados). `VaccinationResource` tiene páginas Create y Edit propias.

### Patrones de los Resources

Los diez Resources siguen la misma estructura; al tocar uno, mantenerla:

- **Campos y filtros compartidos**: el formulario de un Resource que también aparece como RelationManager en la ficha de mascota expone `formFields()` (o `formSchema()` en `PetResource`) y lo reutiliza el RelationManager; los filtros comunes viven en métodos estáticos (`VaccinationResource::statusFilter()`, `TestResource::statusFilter()`). No duplicar listas de opciones (`vaccineOptions()`, `manufacturerOptions()`, `typeOptions()`).
- **Título del registro**: sobrescribir `getRecordTitle()` con el mismo título que la ficha y la búsqueda global (ej. "Consulta de Toby · 11/10/2026"); si no, el rastro de navegación muestra el atributo crudo (`recordTitleAttribute`).
- **Búsqueda global**: `getGloballySearchableAttributes()`, `getGlobalSearchEloquentQuery()` con `with(...)` y `getGlobalSearchResultDetails()`.
- **Páginas Ver** con `getTitle()` y, donde aplique, subtítulo; los enlaces a otros registros solo se generan si el usuario puede verlos (`PetResource::canView($pet)`). El header de cada página Edit lleva «Ver» + «Eliminar»; las fichas ofrecen sus acciones propias: PDF (consulta, cirugía, prueba; mascota agrupa «Documentos PDF») y «Revacunar» en la vacunación. **Los PDF se abren en una pestaña nueva** (`->url(route('filament.admin.pdf.*'))->openUrlInNewTab()`, sin modal de confirmación): `PdfDocumentController` (rutas en `authenticatedRoutes`) los entrega `inline` y exige el permiso `view` del registro y `view_any` de los datos clínicos que incluye; `PdfGeneratorService::inline()` los genera. Al agregar un documento: ruta + método del controlador + test en `PdfDocumentsTest`.
- **Atajos**: botones que abren el formulario de creación con `?pet_id=` / `?owner_id=` / `?vaccine=` (el Select toma `->default(fn () => request()->query(...))`), y acciones de fila como "Revacunar" o "Cargar resultado". Las filas de Mascotas ofrecen "Nueva consulta/vacunación/cirugía/prueba" (`PetResource::registerActions()`, solo mascotas activas), las de Propietarios "Registrar mascota", y las vacunaciones vencidas o por vencer "Avisar por WhatsApp" (`VaccinationResource::whatsappReminderAction()`, solo con permiso sobre propietarios).
- **Tests**: cada Resource tiene un `tests/Feature/Regression/*UxTest.php` (Pest) con sus filtros, orden, fichas y permisos.

### Dashboard

Widgets en `app/Filament/Widgets/`, todos con `canView()` gateado por permiso de Shield (`auth()->user()?->can('view_any_x')`) — un usuario solo ve el widget si tiene permiso de ver ese recurso, sin necesidad de tocar código cuando cambian los permisos de un rol.

**Orden y foco por rol**: arriba los KPIs del día (`$sort = 0`, ancho completo); luego especies y vacunas a media anchura (`$columnSpan = 1`, grilla de 2 columnas), cirugías y pruebas a media anchura, y consultas recientes a ancho completo. El trait `App\Filament\Concerns\FocusesOnOwnRecords` hace que un usuario con rol `veterinarian` (y sin `admin`) vea solo sus propias consultas, cirugías y pruebas (títulos «Mis …»); `assistant` y `admin` ven los de toda la clínica. Las vacunas siempre son de toda la clínica. Un widget nuevo debe respetar este orden y, si lista registros con `user_id`, usar el trait.

- `PetSpeciesOverview` — barras horizontales de mascotas por especie; solo aparecen las especies con al menos una mascota (`view_any_pet`).
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
- **Perfil propio**: `App\Filament\Pages\Auth\EditProfile` (registrada con `->profile(EditProfile::class)` en `AdminPanelProvider`; menú de usuario → Perfil) la puede abrir cualquier usuario del panel sin permisos de Shield. Edita foto (`users.avatar_url`, disco `public`, carpeta `avatars/`; `User` implementa `HasAvatar`), nombre, teléfono (`users.phone`), domicilio (`UserResource::locationSection()`, compartido con el formulario de usuarios) y acceso: cambiar el correo o la contraseña exige la contraseña actual. El rol nunca se edita ahí. El modo claro/oscuro es el selector nativo de Filament (se guarda en el navegador); las preferencias de avisos viven en la sección «Notificaciones» de esta página.
- `User::canAccessPanel()` no depende de una lista de roles — cualquier usuario con al menos un rol asignado entra al panel (`$this->roles()->exists()`).
- `ClinicSettingsPage` es la única pantalla que sigue siendo un chequeo de rol directo (`hasRole(ClinicRoles::ADMIN)`, no un permiso de Shield) — decisión consciente para no meter Pages/Widgets en el sistema de permisos (`entities.pages`/`entities.widgets = false` en el config). Los 2 widgets del dashboard no tienen restricción propia — ya alcanza con el acceso al panel.

### Exportar listas

Las seis listas clínicas (mascotas, propietarios, vacunaciones, consultas, cirugías, pruebas) tienen un botón «Exportar» en el header: `App\Filament\Actions\DownloadExport::make(XExporter::class, 'nombre')`. Abre un modal (formato Excel/CSV y columnas), arma el archivo **en la misma petición** con lo que muestra la tabla (filtros y búsqueda) y lo descarga al confirmar; máximo 5.000 filas (`DownloadExport::$maxRows`), por encima avisa que se filtre antes. Las columnas, el formato de cada valor, la protección contra fórmulas (CSV injection, `ExportsLabels::text()`) y los datos del propietario salen de los exporters de `app/Filament/Exporters/`. Solo lo ve quien tiene el permiso custom `export_data` (por defecto `admin`, `veterinarian` y `assistant`; un único permiso para las seis listas); sin `view_any_owner` no se exportan los datos del propietario. Cada exportación deja una fila en `export_logs` (usuario —con su nombre guardado aunque se elimine—, lista, filas, formato, columnas y si trae datos de propietarios) que se consulta en «Configuración → Registro de exportaciones» (`ExportLogResource`, solo lectura, permiso custom `view_export_log`: solo `admin` por defecto). Los permisos se crean y se asignan a los roles por defecto con migraciones idempotentes (`create_export_data_permission`, `grant_export_permissions`): el deploy no corre seeders, así que todo cambio de permisos en producción debe ir también en una migración (y en `RolesAndPermissionsSeeder` para entornos nuevos). Al agregar una lista nueva: exporter con el mismo criterio de datos del propietario + `DownloadExport::make` + caso en `ExportDownloadTest`.

**No usar el `ExportAction` de Filament ni forzar `sync`** en sus jobs: el job `ExportCsv` hace `auth()->login()`, y dentro de la petición HTTP rota la sesión (el navegador recibe 419 «Página expirada»); con la cola `database` el archivo queda en la campanita y exige la tabla `notifications` (hoy existe y el panel tiene `->databaseNotifications()`, pero no se usa para exportar). Los tests de Livewire no detectan este tipo de fallos porque no pasan por sesión ni CSRF: probarlo con el servidor real.

### Tareas programadas

`routes/console.php` programa `send:vaccination-notifications` una vez al día (`dailyAt(reminder_time)` en la zona horaria de la clínica, `withoutOverlapping`; sin settings legibles cae a medianoche de `America/Asuncion`). Avisa por email al propietario solo de vacunaciones **vigentes** (`current()`) sin `reminder_sent_at`, con próxima aplicación desde `reminder_days_overdue` días atrás (30) hasta `reminder_days_before` días adelante (3); marca `reminder_sent_at` para no reenviar. Se puede apagar con `reminders_enabled`. El texto sale de la plantilla de la clínica (`App\Support\ReminderMessage`, marcadores `{propietario}`, `{mascota}`, `{vacuna}`, `{fecha}`, `{estado}`, `{clinica}`), también usada por «Avisar por WhatsApp».

### Reportes

`ReportsPage` («Reportes», permiso custom `view_reports`: `admin`, `veterinarian` y `assistant` por defecto; creado en la migración idempotente `grant_view_reports_permission` y en el seeder) ofrece **reportes fijos** (`App\Reports\FixedReport`, un caso del enum por reporte: etiqueta, descripción, `isAvailableTo()`, `generate()`) y un **constructor de reportes personalizados** (`App\Reports\ReportEntity`: consultas, vacunaciones, cirugías, pruebas o mascotas, con sus columnas, rango de fechas y filtros de especie y veterinario). Ambos devuelven un `ReportResult` (título, encabezados, filas, si trae datos de propietarios) que la página muestra (vista previa de 100 filas) y descarga en Excel/CSV (`App\Support\TabularDownload`, compartido con `DownloadExport`) o PDF (`pdf/report.blade.php` vía `PdfGeneratorService::output()`; es una descarga, no una pestaña nueva, porque sale de una acción de Livewire). Reglas: cada reporte exige `view_any_x` del registro que resume; las columnas del propietario solo con `view_any_owner`; el veterinario ve solo lo suyo en consultas, cirugías y pruebas (`seesOnlyOwnRecords()`); descargar exige además `export_data`, respeta el tope de `DownloadExport::$maxRows` y deja una fila en `export_logs` (`list` = «Reporte: …»). Los reportes personalizados guardados son privados de cada usuario (`saved_reports`, sin fechas). Un reporte fijo nuevo es un caso del enum; un tipo de registro nuevo para el constructor, un caso de `ReportEntity`.

### Guía de uso

`UserGuidePage` («Guía de uso», en el grupo Ayuda al final del menú; la ve cualquier usuario del panel) muestra temas escritos en Markdown en `resources/guia/NN-slug.md`, leídos por `App\Support\UserGuide`. Cada archivo empieza con un encabezado `---` con `title`, `order`, `icon` (heroicon) y, opcional, `permission` (un permiso de Shield: el tema solo lo ve quien lo tiene; sin `permission` lo ven todos). El prefijo numérico del archivo ordena el directorio y no forma parte de la URL (`?tema=slug`). Está escrita para el usuario final (español neutro, sin jerga técnica) y el Markdown se renderiza escapando HTML. `UserGuideTest` valida que cada tema tenga título y que su permiso exista. **Mantenerla al día es parte de cada cambio visible para el usuario** (ver «Documentación al día»).

### Notificaciones al personal

`send:staff-alerts` (programado a diario a las 07:00 en la zona horaria de la clínica) manda a cada usuario con rol un aviso por tipo de `App\Enums\StaffAlert` (vacunas vencidas o por vencer, cirugías de hoy y mañana, pruebas sin resultado) con la cantidad pendiente; no avisa si no hay nada y no repite el mismo aviso el mismo día (`Cache::add`). Cada usuario elige en su perfil («Notificaciones») por qué canal recibe cada tipo —campanita del panel (`database`, con polling cada 60 s) y/o correo—; por defecto solo la campanita (`users.notification_preferences`, `User::notificationChannels()`). Solo se ofrecen y envían los avisos de recursos que el rol puede ver (`StaffAlert::permission()`), y el veterinario recibe solo lo suyo en cirugías y pruebas (`User::seesOnlyOwnRecords()`, el mismo criterio del dashboard). El correo sale por la cola; la campanita usa el formato de Filament más una clave `alert`. Un tipo de aviso nuevo es un caso más del enum (etiqueta, descripción, permiso, consulta, título, URL). Los recordatorios a propietarios (`send:vaccination-notifications`) son aparte. **Identidad de los correos**: ambos tipos pasan por `App\Support\ClinicMail::identify()`: el remitente muestra el nombre de la clínica, las respuestas (Reply-To) van al email de «Datos de la clínica» y la dirección de envío es `mail_from_address` de «Datos de la clínica» (obligatoria; por ejemplo `no-reply@nextup.com.py`; la migración de settings la inicializa con `MAIL_FROM_ADDRESS`) y, si dejara de ser válida, se usa `MAIL_FROM_ADDRESS` de respaldo. Solo se admiten direcciones de dominios verificados en el proveedor: el de `MAIL_FROM_ADDRESS` más los de `MAIL_ALLOWED_FROM_DOMAINS` (`config('mail.allowed_from_domains')`); un dominio sin verificar haría fallar todos los envíos. El mailer de producción es `resend` con `RESEND_API_KEY`. Todo correo nuevo con `MailMessage` debe usar `ClinicMail::identify()`. **Diseño de los correos**: las plantillas de Laravel están publicadas en `resources/views/vendor/mail` y `vendor/notifications` y toman la marca de `ClinicMail::brand()` (logo o nombre en el encabezado, color principal en el botón con texto que contrasta, y pie con nombre, teléfono, WhatsApp, dirección, horario, redes, web y una línea que avisa que las respuestas llegan al email de la clínica); no escribir «Laravet» ni `config('app.name')` en una plantilla nueva. El recordatorio al propietario tiene asunto con mascota y vacuna (`ReminderMessage::subject()`) y, en lugar de un enlace al panel (el propietario no tiene acceso), el botón «Agendar turno por WhatsApp» con el número de la clínica (`WhatsappLink`, que también arma el enlace de `Owner::whatsapp_url`); sin WhatsApp cargado no hay botón. El texto base del correo es `ClinicSettings::DEFAULT_REMINDER_EMAIL`; la migración `update_default_reminder_email_message` lo cambió solo en clínicas que seguían con el original. **Sin el email de la clínica no se envían recordatorios a propietarios** (`send:vaccination-notifications` lo registra en el log y no marca nada como avisado): ese email es la casilla donde responden; el formulario lo exige mientras `reminders_enabled` esté activo.

### Ayuda: soporte, novedades, reportes y estado

`SupportPage` («Ayuda → Soporte») muestra el correo de soporte del sistema (`ClinicSettings::support_email`, obligatorio, editable en «Datos de la clínica → General»; la migración de settings lo inicializa con `DEFAULT_SUPPORT_EMAIL`) y un botón `mailto:` con el asunto y las preguntas listas. `ReleaseNotesPage` («Ayuda → Novedades») lista las entradas de `resources/novedades/AAAA-MM-DD-slug.md` (`App\Support\ReleaseNotes`; encabezado `---` con `title`, la fecha sale del nombre del archivo; Markdown con HTML escapado, igual que la guía), de la más nueva a la más vieja. Ambas las ve cualquier usuario del panel. **Todo cambio visible para el personal agrega una entrada en `resources/novedades/`** (lenguaje simple, tuteo, qué cambió y cómo le afecta) junto con la guía de uso. El encabezado `---` de la guía y de las novedades lo lee `App\Support\FrontMatter`. **Reportar un problema** (`ReportProblemPage`, cualquier usuario del panel): tipo (error, duda o sugerencia), descripción, pantalla de origen (`url()->previous()`), navegador y versión (`SystemInfo::version()`) y una captura opcional PNG/JPG de hasta 4 MB en el disco privado `problem-reports` (`storage/app/problem-reports`, sin URL pública). Guarda un `ProblemReport` (con `user_name` por si se elimina el usuario), avisa al correo de soporte con `ProblemReportNotification` (Reply-To al usuario, captura adjunta; usa `ClinicMail::identify(..., withReplyTo: false)`) y limita a 5 reportes por usuario y hora. `ProblemReportResource` («Reportes de problemas», permiso custom `manage_problem_reports`, solo admin por defecto, creado en la migración idempotente `create_manage_problem_reports_permission` y en el seeder) lista los reportes con estado nuevo/visto/resuelto y un contador de nuevos; la captura se sirve por `ProblemReportAttachmentController` (ruta `filament.admin.problem-reports.attachment`, permiso `manage_problem_reports`, solo PNG/JPG inline, `nosniff` y CSP `sandbox`). **Estado del sistema** (`SystemStatusPage`, solo admin por chequeo de rol, como «Datos de la clínica»): `App\Support\SystemInfo` junta versión (`git describe` en el servidor, o «no disponible» si no hay git), cola (`jobs`/`failed_jobs`), última corrida del programador, correo (remitente, Reply-To y avisos) y versiones; nunca muestra claves ni valores del `.env`. El programador deja una marca cada minuto (`scheduler-heartbeat` en `routes/console.php`, clave de caché `SystemInfo::SCHEDULER_HEARTBEAT`) y la página avisa si pasan más de 5 minutos sin marca.

### Configuración de la clínica

`ClinicSettingsPage` («Configuración → Datos de la clínica», solo `admin`) edita `App\Settings\ClinicSettings` (spatie/laravel-settings) en cuatro pestañas: General (nombre, nombre corto, zona horaria, color, logo/favicon, contacto, datos fiscales, redes), Recordatorios, Documentos PDF (pie, firma/sello, texto legal; los usa `pdf/layout.blade.php`) y Listas (vacunas, fabricantes, tipos de cirugía y de prueba). Las listas alimentan `vaccineOptions()`, `manufacturerOptions()` y `typeOptions()` vía `ClinicSettings::catalog()`, que cae a las listas iniciales (`DEFAULT_*`) si los settings no se pueden leer o la lista queda vacía; quitar una opción no toca los registros existentes. La zona horaria se aplica en `AppServiceProvider::boot()`. Imágenes solo PNG/JPG/WebP (un SVG serviría script desde el origen de la app). Toda propiedad nueva de `ClinicSettings` necesita una migración de settings idempotente en `database/settings/` (el deploy no corre seeders), y el código debe leer los settings con `ClinicSettings::tryGet()` cuando pueda ejecutarse antes de migrar.

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
- **`php artisan laravet:seed`** (`App\Console\Commands\SeedCommand`) es la forma guiada de
  sembrar: sin opciones y con terminal muestra un menú (base / demo / volumen / borrar demo) y
  explica cada paso; con opciones sirve para scripts: `--base`, `--demo` (crear o refrescar las
  fechas a hoy), `--volume[=N]`, `--reset-demo` (pide confirmación, o `--force` sin
  interacción) y `--force` (producción). Sin interacción ni opciones corre `DatabaseSeeder`.
  En producción solo permite `--base`; demo, volumen y borrado se niegan. Al terminar imprime
  una **tabla de usuarios y contraseñas** (`App\Support\SeedReport`, singleton que anotan
  `ProductionSeeder` y `DemoSeeder`; también la imprime `db:seed`) y avisa de que las
  contraseñas generadas no se vuelven a mostrar. `DemoSeeder` siembra solo la base si faltan
  roles o regiones, y `DemoVolumeSeeder` siembra el demo si faltan los veterinarios.
  `App\Support\DemoDataReset` borra solo lo demo (propietarios por CI o `@volumen.test`,
  con mascotas e historial en cascada, y los usuarios demo que no atendieron pacientes de la
  clínica; esos se conservan). Los atajos de Composer son `seed`, `seed:demo` y `seed:volume`.
- **`RolesAndPermissionsSeeder`** solo **agrega** permisos a los roles base
  (`givePermissionTo`, nunca `syncPermissions`): volver a correrlo no quita lo que un admin
  sumó desde el panel. Los cambios de permisos en producción siguen yendo en una migración
  idempotente (el deploy no corre seeders); `SeedersTest` verifica que seeder y migraciones
  dejen a los roles los mismos permisos.
- **`DemoSeeder`** corre en `local`, `testing` y `staging` (capacitación); **nunca en
  producción** (usa contraseña `password` y correos fijos, y `DemoSeeder`/`DemoVolumeSeeder`
  se niegan a correr con `APP_ENV=production` aunque se invoquen con `--class`). Crea 2
  veterinarios (Dra. María González, Dr. Carlos Giménez) y 1 asistente (Laura Benítez, para
  probar permisos reducidos) y datos contextualizados en Paraguay. **Es idempotente**: usa
  `updateOrCreate` por clave natural (CI del propietario; propietario+nombre de la mascota;
  mascota+diagnóstico/lote/tipo en el historial), así que se puede volver a correr para
  refrescar fechas sin duplicar. Los datos de las mascotas y propietarios están en las
  constantes `PetSeeder::PETS` y `OwnerSeeder::OWNERS`; el historial las referencia por
  posición (trait `Concerns\UsesDemoData`: `demoPets()`/`demoVets()`, el historial se reparte
  en orden entre los veterinarios, nunca al azar ni al admin). **Las fechas son relativas a
  hoy**, para que el demo siempre muestre vacunas vencidas / por vencer / al día / renovadas,
  cirugías realizadas / de hoy / programadas y pruebas pendientes / con resultado (estas
  últimas con un PDF de ejemplo en el disco privado `test-results`). Al agregar un registro
  demo, usar días relativos y mantener esa mezcla de estados.

| Seeder | Contenido |
|--------|-----------|
| `OwnerSeeder` | 10 propietarios con CI, teléfono y dirección paraguayos (ciudades por nombre exacto: `Areguá`, `Itauguá`…) |
| `PetSeeder` | 15 mascotas (10 caninos, 5 felinos) con razas y datos reales |
| `ConsultationSeeder` | 12 consultas con casos clínicos veterinarios reales |
| `VaccinationSeeder` | 24 registros (Séptuple, Antirrábica, Triple Felina, Leucemia Felina), con casos vencidos, por vencer, al día y renovada |
| `SurgerySeeder` | 8 cirugías (castraciones, OVH, suturas, extracción dental), una de hoy y una programada |
| `TestSeeder` | 9 exámenes (hemograma, bioquímica, ELISA, urinálisis, radiografía), 6 con resultado y 3 pendientes |

- **`DemoVolumeSeeder`** (opcional, `php artisan db:seed --class=DemoVolumeSeeder`, después del
  demo): completa hasta `DEMO_VOLUME` mascotas (200 por defecto, `config('app.demo_volume')`) con
  propietarios e historial vía factories, para probar paginación, filtros y el tope de
  exportación. Es idempotente: sus propietarios llevan un correo `@volumen.test` y solo agrega
  las que faltan.
- **Contraseña de los usuarios demo**: `DEMO_PASSWORD` (`config('app.demo_password')`) si está
  definida (se aplica siempre); si no, `password` en `local`/`testing` y, en `staging`, una
  aleatoria que se muestra una sola vez al crear el usuario (volver a sembrar no la cambia).
  Staging debe usar `APP_ENV=staging` para que `db:seed` incluya el demo.
- Verificado con MariaDB 10.11 además de SQLite (`migrate:fresh --seed`, segunda corrida sin
  duplicados y `SeedersTest`).

Credenciales demo por defecto en `.env`/`.env.example`:
`ADMIN_EMAIL=admin@mbopivet.com.py` (la password se genera y se muestra al
correr el seeder por primera vez, ya no es fija).

### Seguridad y datos

- **Datos del propietario**: el apellido, la CI, el teléfono (y su enlace a WhatsApp) y los enlaces a su ficha solo se muestran si el usuario puede ver propietarios (`OwnerResource::canViewAny()` / `canView()`); si no, las tablas de mascotas, consultas, vacunaciones, cirugías y pruebas, los widgets de tabla del dashboard y los PDF muestran como mucho el nombre de pila. Los PDF reciben `$showOwnerData` de `PdfGeneratorService::output()` (permiso `viewAny` de `Owner`): una vista de PDF nueva debe usarlo para apellido, CI, teléfono y dirección. El select de propietario del formulario de mascotas busca por CI solo con ese permiso. Hay una prueba por superficie en `OwnerDataVisibilityTest`. La sección de mascotas de la ficha de propietario se oculta sin permiso sobre mascotas. Al agregar una columna o ficha que muestre datos del propietario, aplicar el mismo criterio.
- **Eliminar es peligroso por `cascadeOnDelete`**: borrar un usuario borra sus consultas, vacunaciones, cirugías, pruebas y mascotas; borrar una ciudad o un barrio con propietarios borra a esos propietarios y su historial. Por eso `User::deletionBlockedReason()` (uno mismo, único admin, con registros clínicos) y `deletionBlockedReason()` de `Department`/`City`/`Neighborhood` (en uso), `Owner` (con mascotas) y `Pet` (con historial clínico; se desactiva en su lugar) deshabilitan la acción, y `App\Filament\Concerns\GuardsDeletion` envuelve la `DeleteAction` (página Edit y modal de los RelationManagers) para bloquearla también en el servidor (acepta un `$resolver` para modelos de terceros: `RoleResource::deletionBlockedReason()` bloquea los roles base y los que tienen usuarios). La protección está en la interfaz y no en el modelo a propósito: los scripts de limpieza de datos demo borran usuarios por Tinker.
- **Un admin no puede cambiar su propio rol** ni dejar el sistema sin administradores (`UserResource`).
- **Resultados de pruebas laboratoriales**: archivos en el disco privado `test-results` (`storage/app/test-results`, no accesible por `/storage`), solo PDF/PNG/JPG, servidos por `TestResultController` (ruta del panel `filament.admin.tests.result`) que exige sesión y el permiso `view` sobre la prueba. Solo se muestran inline PDF y PNG/JPG; cualquier otro tipo se fuerza como descarga con `nosniff` y CSP `sandbox` (evita XSS almacenado). Una migración movió los archivos antiguos desde el disco público.

### Versión de Filament

Se queda **a propósito en Filament 3.3** (hoy 3.3.57; se actualizan solo parches con `composer update "filament/*" bezhansalleh/filament-shield --with-dependencies`). Decisión (octubre 2026): el sistema está en producción con la clínica y no hay urgencia de seguridad; migrar solo si hay una razón fuerte. Destino futuro: **Filament 5 + Shield 4** (la 5 es la 4 más Livewire 4; ir directo a la 5 evita dos saltos). Una simulación (`composer require "filament/filament:^5.0" "bezhansalleh/filament-shield:*" --dry-run -W`) resuelve sin conflictos: Filament 5.10, Livewire 4.4, Shield 4.3. El costo está en el código, no en la instalación.

Riesgos a revisar antes de migrar:
- Más de 90 archivos usan la API de Filament 3 (formularios, tablas, acciones, infolists, widgets): la 4 cambió namespaces y componentes centrales.
- `app/Filament/Resources/RoleResource.php` es una copia publicada y modificada de Shield (etiquetas en español, `deletionBlockedReason()`, «Duplicar»): hay que volver a aplicarla sobre el recurso de Shield 4.
- Posible cambio del formato de nombres de permiso en Shield 4 (hoy `view_any_pet`): verificarlo, porque afecta filas en la base de datos de producción y todos los `can('...')` y `hasRole`/seeders del código.
- `ClinicSettingsPage` (spatie/laravel-settings) y las páginas/widgets propios.
- Livewire 4: URLs de endpoints, `wire:model` diferido por defecto y rutas propias; revisar cualquier componente Livewire propio.
- Boost incluye hoy la guía genérica de Filament; al migrar, regenerar con `php artisan boost:update`.

Cómo probar la migración sin riesgo: rama de prueba (sin merge) con `filament/upgrade` y su script, `composer why-not filament/filament "^5.0"`, y correr la suite en SQLite y MySQL antes de decidir.

### Despliegue

- El deploy es el workflow manual `.github/workflows/deploy.yml` (`workflow_dispatch` sobre `main`): corre los tests, despliega por SSH ejecutando las migraciones, hace un health check y crea el tag `prod-AAAA-MM-DD_HHMM`. Si falla manda un correo; el detalle del paso que falló queda en `storage/logs/deploy.log` del servidor, y los errores de la app en `storage/logs/laravel.log`.
- El workflow `tests.yml` corre `pest` en cada PR y en cada push a `main`.
- Las migraciones corren solas en cada deploy: una migración de datos (mover archivos, renombrar un registro) debe ser idempotente.
- Un fallo de red al conectar por SSH (`i/o timeout`) no ejecuta nada: se puede relanzar. El tag anterior sirve de punto de retorno.

### Trampas técnicas

- **Los tests corren en SQLite y producción usa MySQL**: lo que SQLite tolera MySQL lo rechaza. Caso real: una subconsulta escalar (`addSelect` con `(select 1 …)`) que devuelve varias filas falla en MySQL con `1242 Subquery returns more than 1 row`; hay que usar `limit(1)`. Ante consultas con subconsultas, `groupBy` o funciones de fecha, probar también contra MySQL.
- **Pint**: el repo está limpio y el CI (`Estilo (Pint)` en `tests.yml`) corre `vendor/bin/pint --test` en cada PR. Antes de commitear, `./vendor/bin/pint --dirty` formatea solo lo modificado; comprobar todo el repo con `./vendor/bin/pint --test`.
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
- Las acciones de fila en las tablas de Filament se agrupan en un menú desplegable con `Tables\Actions\ActionGroup::make([...])` (Editar + atajos propios del recurso), en vez de íconos sueltos. **No hay «Ver» en el menú**: el clic en la fila ya abre la ficha. `Eliminar` **no** es una acción de fila suelta en ningún Resource/RelationManager — es deliberadamente menos accesible para reducir el riesgo de borrar por error un registro clínico desde una tabla densa: en Resources con página propia vive en el header de la página Edit (`Actions\DeleteAction::make()`); en RelationManagers (sin página propia) vive dentro del modal de "Editar" vía `EditAction::make()->extraModalFooterActions([DeleteAction::make()->cancelParentActions()])`. Tampoco hay `DeleteBulkAction` en ninguna tabla del panel.
- **Idioma**: todo texto visible al usuario (labels, notificaciones, mensajes de validación, emails, prompt de IA) va en español neutro, con tuteo y sin voseo ("Intenta", "Revisa", no "Intentá"/"Revisá"); los identificadores de código (clases, tablas, columnas, rutas) siguen en inglés. `fallback_locale` es `es`. Un label vacío en un campo de formulario sale en inglés ("Department id"): ponerlo siempre.
- **Tablas**: ID, "Registrado el" y "Actualizado el" ocultos por defecto (`toggleable`), fechas `d/m/Y`, orden por defecto explícito, conteos con `->counts('relación')`.
- **Git**: una rama por tema desde `origin/main`, un PR por tema; mensajes de commit y PR en español.
- **Documentación al día**: todo cambio que altere comportamiento, permisos, rutas, comandos, configuración o la interfaz que ve el usuario actualiza en el mismo PR: este `CLAUDE.md`, `README.md`/`CONTRIBUTING.md` si los afecta, una entrada en `resources/novedades/` (ver «Ayuda») y la guía de uso del panel (`resources/guia/*.md`, ver «Guía de uso»): si el cambio altera lo que ve o hace un usuario (pantallas, botones, flujos, avisos, permisos), se edita el tema correspondiente o se agrega uno nuevo. Antes de cerrar una tarea, revisar si algo de lo anterior quedó desactualizado.
- **Listas con tabs**: si una lista tiene tabs (ej. Mascotas: Todos/Activos/Inactivos), no agregar un filtro con valor por defecto que se solape con ellos; el tab «Todos» debe abrir sin filtros activos.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project uses the streamlined Laravel 11+ structure: register middleware, exceptions, and routing in `bootstrap/app.php` and service providers in `bootstrap/providers.php`. There is no `app/Http/Kernel.php` or `app/Console/Kernel.php`, and commands in `app/Console/Commands/` auto-register.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== filament/filament/core rules ===

## Filament

- Filament is used by this application, check how and where to follow existing application conventions.
- Filament is a Server-Driven UI (SDUI) framework for Laravel. It allows developers to define user interfaces in PHP using structured configuration objects. It is built on top of Livewire, Alpine.js, and Tailwind CSS.
- You can use the `search-docs` tool to get information from the official Filament documentation when needed. This is very useful for Artisan command arguments, specific code examples, testing functionality, relationship management, and ensuring you're following idiomatic practices.
- Utilize static `make()` methods for consistent component initialization.

### Artisan

- You must use the Filament specific Artisan commands to create new files or components for Filament. You can find these with the `list-artisan-commands` tool, or with `php artisan` and the `--help` option.
- Inspect the required options, always pass `--no-interaction`, and valid arguments for other options when applicable.

### Filament's Core Features

- Actions: Handle doing something within the application, often with a button or link. Actions encapsulate the UI, the interactive modal window, and the logic that should be executed when the modal window is submitted. They can be used anywhere in the UI and are commonly used to perform one-time actions like deleting a record, sending an email, or updating data in the database based on modal form input.
- Forms: Dynamic forms rendered within other features, such as resources, action modals, table filters, and more.
- Infolists: Read-only lists of data.
- Notifications: Flash notifications displayed to users within the application.
- Panels: The top-level container in Filament that can include all other features like pages, resources, forms, tables, notifications, actions, infolists, and widgets.
- Resources: Static classes that are used to build CRUD interfaces for Eloquent models. Typically live in `app/Filament/Resources`.
- Schemas: Represent components that define the structure and behavior of the UI, such as forms, tables, or lists.
- Tables: Interactive tables with filtering, sorting, pagination, and more.
- Widgets: Small component included within dashboards, often used for displaying data in charts, tables, or as a stat.

### Relationships

- Determine if you can use the `relationship()` method on form components when you need `options` for a select, checkbox, repeater, or when building a `Fieldset`:

<code-snippet name="Relationship example for Form Select" lang="php">
Forms\Components\Select::make('user_id')
    ->label('Author')
    ->relationship('author')
    ->required(),
</code-snippet>

## Testing

- It's important to test Filament functionality for user satisfaction.
- Ensure that you are authenticated to access the application within the test.
- Filament uses Livewire, so start assertions with `livewire()` or `Livewire::test()`.

### Example Tests

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1))
        ->searchTable($users->last()->email)
        ->assertCanSeeTableRecords($users->take(-1))
        ->assertCanNotSeeTableRecords($users->take($users->count() - 1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Howdy',
            'email' => 'howdy@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Howdy',
        'email' => 'howdy@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Multiple Panels (setup())" lang="php">
    use Filament\Facades\Filament;

    Filament::setCurrentPanel('app');
</code-snippet>

<code-snippet name="Calling an Action in a Test" lang="php">
    livewire(EditInvoice::class, [
        'invoice' => $invoice,
    ])->callAction('send');

    expect($invoice->refresh())->isSent()->toBeTrue();
</code-snippet>

## Version 3 Changes To Focus On

- Resources are located in `app/Filament/Resources/` directory.
- Resource pages (List, Create, Edit) are auto-generated within the resource's directory - e.g., `app/Filament/Resources/PostResource/Pages/`.
- Forms use the `Forms\Components` namespace for form fields.
- Tables use the `Tables\Columns` namespace for table columns.
- A new `Filament\Forms\Components\RichEditor` component is available.
- Form and table schemas now use fluent method chaining.
- Added `php artisan filament:optimize` command for production optimization.
- Requires implementing `FilamentUser` contract for production access control.

</laravel-boost-guidelines>
