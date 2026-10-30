# Guía de Contribución - Laravet

¡Gracias por interesarte en contribuir a Laravet! Este documento explica el flujo de trabajo del proyecto. Para la arquitectura y las convenciones en detalle, lee [CLAUDE.md](CLAUDE.md); para instalar el proyecto, el [README.md](README.md).

## 🔧 Configuración del entorno

Necesitas PHP 8.4, Composer 2, Node 18+ y Git (ver los requisitos del README).

```bash
git clone https://github.com/nextup-py/laravet.git   # o tu fork
cd laravet
composer install
npm install
cp .env.example .env        # en PowerShell: Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan laravet:seed    # base del sistema y datos demo (muestra los usuarios al final)
composer run dev            # servidor PHP + worker de la cola + Vite → http://localhost:8000
```

`composer run seed:demo` vuelve a calcular las fechas del demo a hoy sin duplicar datos. Con `QUEUE_CONNECTION=database` (el valor del `.env.example`) hace falta el worker de la cola para que salgan correos y avisos; `composer run dev` ya lo levanta.

## 🌿 Ramas y Pull Requests

- **Una rama por tema, creada desde `origin/main`, y un Pull Request por tema.**
- Nombres con prefijo del tipo de cambio: `feat/…`, `fix/…`, `docs/…`, `refactor/…`, `chore/…`, `test/…` (por ejemplo `feat/roles-en-espanol`).
- El título y la descripción del PR van en español. Incluye un resumen, qué cambia, cómo se probó y qué debe revisar quien lo lea.
- Las ramas se borran solas al mergear el PR.

```bash
git fetch origin
git checkout -b fix/mi-correccion origin/main
```

## ✅ Antes de enviar un Pull Request

1. **Formato**: `./vendor/bin/pint --dirty` formatea solo lo que modificaste. El CI corre `vendor/bin/pint --test` sobre todo el repo, que debe estar limpio. No formatees carpetas enteras: ensucia el PR con archivos ajenos.
2. **Tests**: `php artisan test` (o `--filter=NombreDelTest` mientras trabajas). Deben pasar todos.
3. **Documentación al día** (en el mismo PR):
   - `CLAUDE.md` si cambia el comportamiento, los permisos, las rutas, los comandos o la configuración.
   - `README.md` y este archivo si los afecta.
   - Una entrada en `resources/novedades/` si el cambio lo ve el personal (lenguaje simple, tuteo).
   - El tema que corresponda en `resources/guia/` si cambian pantallas, botones, flujos, avisos o permisos.
4. **Pruébalo en el navegador** si el cambio es visible.

## 🧪 Tests

El proyecto usa [Pest](https://pestphp.com). Los tests del panel viven en `tests/Feature/Regression/`. El archivo `tests/Pest.php` ya siembra roles y regiones antes de cada test y define `actingAsRole()`:

```php
// tests/Feature/Regression/EjemploUxTest.php
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Models\Pet;
use Livewire\Livewire;

it('filtra mascotas por propietario', function () {
    actingAsRole('admin');
    $suya = Pet::factory()->create();
    $ajena = Pet::factory()->create();

    Livewire::test(ListPets::class)
        ->filterTable('owner_id', $suya->owner_id)
        ->assertCanSeeTableRecords([$suya])
        ->assertCanNotSeeTableRecords([$ajena]);
});
```

Qué conviene cubrir en cada recurso: filtros, orden por defecto, la ficha (título y enlaces), la búsqueda global y los permisos (un rol con solo `view_*` no debe ver datos que requieren otro permiso). Usa las factories en lugar de crear modelos a mano.

### Los tests corren en SQLite, producción usa MySQL

Una consulta puede pasar todos los tests y fallar en MySQL: por ejemplo, una subconsulta escalar que devuelve más de una fila funciona en SQLite pero MySQL responde `1242 Subquery returns more than 1 row`. Si agregas subconsultas, `groupBy`, funciones de fecha o `addSelect` con subconsultas, pruébalas también contra MySQL, o verifica el SQL generado con `->toSql()` en un test.

Para correr los tests contra MySQL usa **una base aparte** (los tests la vacían) y define las variables antes de ejecutar:

```powershell
$env:DB_CONNECTION="mysql"; $env:DB_HOST="127.0.0.1"; $env:DB_DATABASE="laravet_tests"; $env:DB_USERNAME="root"
php artisan test
```

## 📝 Commits

- Mensajes **en español**, en presente, con el prefijo del tipo de cambio: `feat:`, `fix:`, `docs:`, `refactor:` (por ejemplo `fix: evitar el error 1242 de MySQL en la marca de vacunación renovada`).
- La primera línea puede superar los 50 caracteres si el prefijo y el resumen lo requieren; el detalle va en el cuerpo.
- Commits pequeños: un cambio lógico por commit, sin mezclar temas.

## ✍️ Estilo de código

- **PHP**: PSR-12 y las convenciones de Laravel, formateadas con Pint. Tipos explícitos en parámetros y retornos, nombres descriptivos y PHPDoc en lugar de comentarios en línea.
- **Idioma**: todo texto visible al usuario (etiquetas, notificaciones, validaciones, correos) va en español neutro, con tuteo y sin voseo («Intenta», «Revisa»). Los identificadores de código (clases, tablas, columnas, rutas) van en inglés.
- **Filament**: sigue la estructura de los Resources existentes (campos y filtros compartidos, `getRecordTitle()`, búsqueda global, acciones en menú desplegable). El detalle está en CLAUDE.md.
- **Blade y JavaScript**: indentación de 2 espacios, componentes Blade cuando se pueda y consistencia con lo existente.

## 🔐 Seguridad y datos

- No subas el `.env`, claves ni credenciales.
- **Datos del propietario**: apellido, CI, teléfono y enlaces a su ficha solo se muestran a quien puede ver propietarios; cualquier columna o ficha nueva debe respetarlo.
- **Eliminar es peligroso por las claves foráneas en cascada** (borrar un usuario, ciudad o barrio puede borrar historial clínico): respeta los bloqueos existentes (`deletionBlockedReason()`).
- **Permisos en producción**: el despliegue no corre seeders, así que todo cambio de permisos o de datos de referencia debe ir en una **migración idempotente** (y también en `RolesAndPermissionsSeeder` para instalaciones nuevas).
- Reporta vulnerabilidades en privado, no en un issue público.

## 🐛 Reporte de bugs y propuestas

- **Bug**: verifica que no esté reportado y abre un issue con título descriptivo, pasos para reproducir, comportamiento esperado y actual, capturas y datos del entorno. Desde el panel también existe **Ayuda → Reportar un problema**.
- **Nueva característica**: abre un issue describiendo la funcionalidad y sus casos de uso, y espera comentarios antes de empezar a trabajar.

## 📋 Checklist del Pull Request

- [ ] `./vendor/bin/pint --dirty` sin cambios pendientes
- [ ] `php artisan test` pasa completo
- [ ] Agregué o actualicé tests para el cambio
- [ ] Actualicé `CLAUDE.md`, `README.md`, novedades y guía si corresponde
- [ ] La rama parte de `origin/main` y no tiene conflictos
- [ ] No hay archivos secretos (`.env`, credenciales) ni código comentado sobrante
- [ ] Si agregué consultas con subconsultas o funciones de base de datos, las probé en MySQL
- [ ] Si el cambio muestra datos de propietarios o elimina registros, respeté los permisos y las protecciones (ver CLAUDE.md)
- [ ] Si cambié permisos o datos de referencia, hay una migración idempotente

¡Gracias por contribuir! 🎉
