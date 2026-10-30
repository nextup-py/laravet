# Laravet

Sistema de gestión clínica con diagnóstico asistido por inteligencia artificial para la veterinaria "Mbopivet" de Areguá, Paraguay.

Laravet administra propietarios, mascotas, consultas, vacunaciones, cirugías y pruebas de laboratorio, e incorpora un asistente de diagnóstico con IA. Está hecho con **Laravel 12**, **Filament 3.3** (panel de administración, íntegramente en español), **Pest** y **Vite**.

## 📚 Proyecto de Tesis

Trabajo de **Tesis Final de Grado** de la carrera **Ingeniería en Informática** de la **Universidad del Cono Sur de las Américas (UCSA)**, año 2026.

**Título**: *"LARAVET: APLICACIÓN WEB DE GESTIÓN CLÍNICA CON DIAGNÓSTICO ASISTIDO POR INTELIGENCIA ARTIFICIAL PARA LA VETERINARIA MBOPIVET"*

## 🚀 Características

- **Diagnóstico asistido por IA**: el botón «Asistir con IA» del formulario de consulta sugiere diagnóstico y tratamiento con Claude (Anthropic) a partir de la anamnesis y los datos de la mascota.
- **Gestión clínica**: propietarios, mascotas, consultas, cirugías (Programada / Hoy / Realizada), pruebas con resultado opcional en archivos privados y vacunaciones con estado (Vencida / Por vencer / Al día / Renovada), «Revacunar» y recordatorios por correo y WhatsApp.
- **Roles y permisos**: roles dinámicos gestionados desde el panel con permisos fijos por recurso; los datos de contacto del propietario solo los ven quienes pueden ver propietarios.
- **Dashboard por rol**, **búsqueda global**, filtros en cada listado y **avisos diarios** al personal (campanita y correo).
- **Exportar y reportes**: listas a Excel/CSV, reportes fijos y personalizados (Excel, CSV y PDF) y documentos PDF por registro, con registro de cada exportación.
- **Ayuda dentro del panel**: guía de uso por rol, novedades, soporte, «Reportar un problema» y estado del sistema.
- **Ubicaciones de Paraguay**: departamentos, ciudades y barrios, con protección contra eliminar registros en uso.
- **Datos demo** contextualizados en Paraguay y un comando guiado para sembrarlos (`php artisan laravet:seed`).

## 📋 Requisitos

- **PHP 8.4** (el `composer.lock` lo exige) con las extensiones habituales de Laravel
- **Composer 2**
- **Node.js 18 o superior** y npm (Vite 5)
- **Git**
- **SQLite** (viene con PHP, es la opción por defecto) o **MySQL 8**; producción usa MySQL

```bash
php --version && composer --version && node --version && git --version
```

## ⚙️ Instalación

```bash
git clone https://github.com/nextup-py/laravet.git
cd laravet
composer install
npm install
cp .env.example .env          # en PowerShell: Copy-Item .env.example .env
php artisan key:generate
php artisan migrate           # crea las tablas (y database/database.sqlite si usas SQLite)
php artisan laravet:seed      # siembra la base y, en local, los datos demo
composer run dev              # servidor PHP + worker de la cola + Vite
```

La aplicación queda en **http://localhost:8000** (el panel está en la raíz; sin sesión redirige a `/login`).

> **Windows / Laragon**: sirve igual; asegúrate de que `php -v` muestre 8.4. Si `mysql` no se reconoce en PowerShell, usa el Terminal de Laragon o la ruta completa a `mysql.exe` (por ejemplo `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`).

### Variables de entorno

El `.env.example` está comentado y es suficiente para desarrollo con SQLite. Lo que conviene conocer:

| Variable | Para qué sirve |
|----------|----------------|
| `APP_ENV` | `local`, `staging` o `production`. Los datos demo solo se siembran en `local` y `staging`. |
| `DB_CONNECTION` y `DB_*` | SQLite por defecto; para MySQL descomenta el bloque del archivo. |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | Igual que producción: cola y caché en `database`, sesiones en archivos (`file`). Con la cola en `database` hace falta un worker: `composer run dev` ya lo levanta. |
| `MAIL_MAILER` | `log` en desarrollo (los correos quedan en `storage/logs/laravel.log`); `resend` en producción con `RESEND_API_KEY`. |
| `MAIL_FROM_ADDRESS`, `MAIL_ALLOWED_FROM_DOMAINS` | Remitente y dominios verificados en el proveedor de correo; un dominio sin verificar hace fallar los envíos. |
| `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` | IA del diagnóstico asistido (opcional). Se obtiene en [console.anthropic.com](https://console.anthropic.com). |
| `ADMIN_NAME`, `ADMIN_EMAIL` | Administrador que crea la primera siembra. |
| `DEMO_PASSWORD`, `DEMO_VOLUME` | Contraseña de los usuarios demo y cantidad de mascotas del volumen de prueba (solo local y staging). |

La zona horaria, el correo de la clínica y los recordatorios se configuran en el panel (**Configuración → Datos de la clínica**), no en el `.env`.

### Sembrar la base de datos

```bash
php artisan laravet:seed          # menú guiado: base, demo, volumen o borrar el demo
composer run seed:demo            # crea o refresca el demo (las fechas se recalculan a hoy)
composer run seed:volume          # agrega mascotas de prueba hasta DEMO_VOLUME (200 por defecto)
```

Opciones para scripts: `--base`, `--demo`, `--volume[=N]`, `--reset-demo` (borra solo los datos demo; pide confirmación o `--force`). Es repetible: volver a ejecutarlo actualiza, no duplica. Al terminar muestra una tabla con los usuarios y sus contraseñas; **la contraseña del administrador se genera una sola vez**: guárdala en ese momento.

Los datos demo son 2 veterinarios y 1 asistente (contraseña `password` en local, o la de `DEMO_PASSWORD`; en staging se genera una aleatoria), 10 propietarios, 15 mascotas y su historial clínico, con fechas relativas a hoy. Nunca se siembran en producción.

## 🔐 Acceso

| Usuario | Correo | Contraseña |
|---------|--------|------------|
| Administrador | `ADMIN_EMAIL` (por defecto `admin@mbopivet.com.py`) | La genera la siembra; se muestra una sola vez |
| Veterinarios | `maria.gonzalez@mbopivet.com.py`, `carlos.gimenez@mbopivet.com.py` | `password` (local) |
| Asistente | `laura.benitez@mbopivet.com.py` | `password` (local) |

Si pierdes la contraseña del administrador en desarrollo, vuelve a empezar con `php artisan migrate:fresh` y `php artisan laravet:seed`.

## 🛠️ Comandos útiles

| Comando | Descripción |
|---------|-------------|
| `composer run dev` | Servidor PHP, worker de la cola y Vite en una sola terminal |
| `php artisan serve` / `npm run dev` | Servidor y assets por separado |
| `php artisan queue:listen --tries=1` | Procesar la cola (correos y avisos) en desarrollo |
| `php artisan schedule:work` | Probar las tareas programadas (recordatorios y avisos diarios) |
| `php artisan test` | Tests (SQLite en memoria); `--filter=NombreDelTest` para uno |
| `./vendor/bin/pint --dirty` | Formatear los archivos modificados (el CI exige `pint --test`) |
| `php artisan migrate:fresh --seed` | Reinicia la base y la vuelve a sembrar |
| `npm run build` | Compilar assets para producción |

### Tests contra MySQL

Los tests corren en SQLite, pero producción usa MySQL y algunas consultas que SQLite tolera MySQL las rechaza. Para probar contra MySQL usa **una base aparte** (los tests la vacían) y define las variables antes de ejecutar:

```powershell
$env:DB_CONNECTION="mysql"; $env:DB_HOST="127.0.0.1"; $env:DB_DATABASE="laravet_tests"; $env:DB_USERNAME="root"
php artisan test
```

## 🚢 Producción

Además de las variables de la tabla anterior (`APP_ENV=production`, `APP_DEBUG=false`, `DB_*` de MySQL, `MAIL_MAILER=resend` con `RESEND_API_KEY`, `MAIL_FROM_ADDRESS` de un dominio verificado), el servidor necesita dos procesos en marcha:

- **El programador**: una tarea cron cada minuto con `php artisan schedule:run` (recordatorios a propietarios y avisos al personal).
- **Un worker de la cola**: `php artisan queue:work`, normalmente bajo un supervisor, para que salgan los correos y avisos.

El despliegue es el workflow manual `.github/workflows/deploy.yml` (corre los tests, ejecuta las migraciones por SSH, comprueba la salud y crea el tag `prod-AAAA-MM-DD_HHMM`). Las migraciones corren solas en cada despliegue; **el despliegue no ejecuta seeders**, por eso todo cambio de permisos o de datos de referencia va en una migración idempotente. Los detalles están en [CLAUDE.md](CLAUDE.md).

### Cambiar el `.env` en el servidor

En producción la configuración queda en caché (el despliegue la cachea), así que **editar el `.env` no cambia nada hasta regenerarla**. El procedimiento seguro:

```bash
cp -p .env ".env.bak-$(date +%F-%H%M)"      # copia de seguridad (contiene secretos: bórrala cuando termines)
nano .env                                    # editar solo lo necesario
php artisan config:cache                     # aplicar los cambios
php artisan about --only=environment,cache,drivers   # comprobar entorno y controladores
```

Luego revisa **Ayuda → Estado del sistema** en el panel (cola, programador y correo). Los procesos de larga duración, como el worker de la cola, conservan la configuración anterior hasta que se reinician (`php artisan queue:restart`, si algo los vuelve a lanzar). Para revertir: restaurar la copia y ejecutar `php artisan config:cache`.

## 📁 Estructura del proyecto

```
laravet/
├── app/
│   ├── Console/Commands/     # laravet:seed, recordatorios de vacunación, avisos al personal
│   ├── Enums/                # Estados y opciones (especie, estado de vacunación, avisos…)
│   ├── Filament/             # Panel: Resources, Pages, Widgets, Exporters, Actions, Concerns
│   ├── Http/                 # Controllers (PDF, resultados de pruebas, capturas de reportes)
│   ├── Models/  Policies/    # Modelos y políticas de permisos
│   ├── Notifications/        # Correos y avisos
│   ├── Reports/              # Reportes fijos y constructor de reportes
│   ├── Services/             # IA del diagnóstico y generación de PDF
│   ├── Settings/             # Datos de la clínica (spatie/laravel-settings)
│   └── Support/              # Utilidades: correo, siembra, guía, novedades, estado del sistema
├── database/                 # Migraciones, settings, factories, seeders y datos de regiones
├── lang/                     # Traducciones al español
├── resources/
│   ├── guia/                 # Guía de uso del panel (Markdown)
│   ├── novedades/            # Novedades para el personal (Markdown)
│   └── views/                # Vistas Blade (PDF, correos, páginas del panel)
├── tests/                    # Tests Pest (Feature/Regression)
├── .env.example              # Plantilla de variables de entorno
└── CLAUDE.md                 # Arquitectura y convenciones en detalle
```

## 🐛 Solución de problemas

- **`composer install` falla por la versión de PHP**: el proyecto necesita PHP 8.4; revisa `php -v`.
- **Los assets no cargan**: ejecuta `npm install` y `npm run dev` (o `npm run build`); con `composer run dev` ya se levanta Vite.
- **No salen correos ni avisos en desarrollo**: con `QUEUE_CONNECTION=database` hace falta el worker (`composer run dev` o `php artisan queue:listen`). Con `MAIL_MAILER=log` los correos se leen en `storage/logs/laravel.log`.
- **«Class not found» en Artisan**: `composer dump-autoload`.
- **Base SQLite bloqueada**: cierra otros procesos que la usen (otro `serve`, un visor de BD) y reintenta.
- **Error 500 solo en producción**: revisa `storage/logs/laravel.log`; suele ser una consulta que SQLite tolera y MySQL rechaza (ver «Tests contra MySQL»).
- **No se abre el archivo de resultado de una prueba**: se sirven por una ruta autenticada desde el disco privado `test-results` y hace falta el permiso para ver pruebas.
- **No puedo registrar un propietario de cierta ciudad**: el barrio es obligatorio y algunas ciudades todavía no tienen barrios cargados.

## 🤝 Contribución

Lee [CONTRIBUTING.md](CONTRIBUTING.md) para el flujo de trabajo y [CLAUDE.md](CLAUDE.md) para la arquitectura y las convenciones.

## 📝 Licencia

Licencia privada. Todos los derechos reservados para "Mbopivet".

## 👨‍💻 Autores

- **Equipo de Desarrollo**: [edfsosa](https://github.com/edfsosa)

## 📞 Soporte

Para reportar problemas o sugerencias, abre un issue en el repositorio o usa **Ayuda → Reportar un problema** dentro del panel.
