# Laravet

Sistema de gestión clínica con diagnóstico asistido por inteligencia artificial para la veterinaria "Mbopivet" de Areguá, Paraguay.

Laravet es una aplicación web moderna que facilita la administración de clientes, mascotas, consultas, cirugías y registros médicos en una clínica veterinaria, incorporando capacidades de diagnóstico inteligente.

## 📚 Proyecto de Tesis

Este proyecto es un trabajo de **Tesis Final de Grado** de la carrera **Ingeniería en Informática** de la **Universidad del Cono Sur de las Américas (UCSA)**.

**Título de la Tesis**: *"LARAVET: APLICACIÓN WEB DE GESTIÓN CLÍNICA CON DIAGNÓSTICO ASISTIDO POR INTELIGENCIA ARTIFICIAL PARA LA VETERINARIA MBOPIVET"*

**Institución**: Universidad del Cono Sur de las Américas (UCSA)  
**Carrera**: Ingeniería en Informática  
**Año**: 2026

## 🚀 Características

- **Diagnóstico Asistido por IA**: Botón "Asistir con IA" en el formulario de consulta que sugiere diagnóstico y tratamiento usando Claude (Anthropic), basándose en la anamnesis y los datos clínicos de la mascota
- **Gestión de Clientes (Propietarios)**: Registro y administración de información de clientes
- **Gestión de Mascotas**: Información detallada de mascotas incluyendo raza, edad y datos médicos
- **Consultas Veterinarias**: Registro de consultas, síntomas y tratamientos, con el resumen clínico de la mascota y sus últimas consultas al registrar
- **Cirugías**: Registro y programación de procedimientos quirúrgicos (estados Programada / Hoy / Realizada) con el veterinario que las realizó
- **Vacunaciones**: Registro de vacunas con estado de vencimiento (Vencida / Por vencer / Al día / Renovada: solo la última aplicación de cada vacuna cuenta), próxima aplicación sugerida a un año, acción "Revacunar" y recordatorios automáticos por email a los propietarios
- **Pruebas/Exámenes Laboratoriales**: Registro de pruebas con resultado opcional (Pendiente / Con resultado); los archivos de resultado (PDF, PNG o JPG) se guardan en un almacenamiento privado y solo los ven los usuarios con permiso
- **Ubicaciones**: Integración con divisiones administrativas de Paraguay (departamentos, ciudades, barrios), con protección contra eliminar registros en uso
- **Búsqueda y filtros**: Búsqueda global de propietarios, mascotas, consultas, vacunaciones, cirugías, pruebas y usuarios, y filtros por estado, tipo, fechas, veterinario y ubicación en cada listado
- **Roles y permisos**: Roles dinámicos gestionados desde el panel, con permisos fijos por recurso; los datos de contacto del propietario solo los ven quienes pueden ver propietarios
- **Panel de Administración**: Interfaz intuitiva con Filament, íntegramente en español
- **Reportes en PDF**: Generación de reportes y documentos

## 📋 Requisitos Previos

### Sistema Operativo
- Windows, macOS o Linux

### Dependencias de Software
- **PHP 8.2 o superior**
- **Composer** (gestor de dependencias PHP) - [Descargar](https://getcomposer.org)
- **Node.js 16.0.0 o superior** y **npm** - [Descargar](https://nodejs.org)
- **Git** - [Descargar](https://git-scm.com)
- **SQLite** (incluido en PHP) o **MySQL 8.0+** / **PostgreSQL 12+** (opcional)

### Verificar Instalación
```bash
php --version          # Debe mostrar PHP 8.2 o superior
composer --version     # Debe mostrar version 2.x
node --version         # Debe mostrar v16.0.0 o superior
npm --version          # Debe mostrar 8.0.0 o superior
git --version          # Debe mostrar una versión de git
```

## ⚙️ Instalación

### Paso 1: Clonar el Repositorio
```bash
git clone https://github.com/edfsosa/laravet.git
cd laravet
```

### Paso 2: Instalar Dependencias PHP
```bash
composer install
```

Este comando descargará e instalará:
- Laravel 11.9
- Filament 3.2 (panel de administración)
- Laravel DomPDF (generación de PDF)
- Soporte para regiones de Paraguay
- Y otras librerías necesarias

### Paso 3: Instalar Dependencias de Frontend
```bash
npm install
```

Este comando descargará e instalará:
- Vite 5.0 (build tool)
- Alpine.js (interactividad frontend)
- Laravel Vite Plugin
- Axios y otros paquetes frontend

### Paso 4: Configurar Variables de Entorno
```bash
cp .env.example .env
```

Luego genera la APP_KEY:
```bash
php artisan key:generate
```

**Nota**: El archivo `.env.example` contiene la configuración por defecto para SQLite. Si deseas usar MySQL o PostgreSQL, edita el archivo `.env` con la configuración de tu base de datos.

#### Variable para la IA (opcional)

Para habilitar el diagnóstico asistido por IA, agrega tu API key de Anthropic en el `.env`:

```
ANTHROPIC_API_KEY=sk-ant-...
```

Puedes obtener la key en [console.anthropic.com](https://console.anthropic.com). Sin esta variable, la aplicación funciona normalmente pero el botón "Asistir con IA" retornará un error.

### Paso 5: Crear la Base de Datos y Ejecutar Migraciones
```bash
php artisan migrate
```

Este comando:
- Crea la base de datos SQLite en `database/database.sqlite` (si usas SQLite)
- Crea todas las tablas necesarias
- Ejecuta todos los seeders de datos iniciales

### Paso 6: Llenar la Base de Datos con los datos de demo
```bash
php artisan db:seed
```

Este comando llena la base de datos con datos de ejemplo contextualizados en Paraguay:
- 3 veterinarios con ubicaciones del departamento Central
- 10 propietarios con nombres, CI y teléfonos paraguayos
- 15 mascotas (caninos y felinos) con razas y datos clínicos reales
- 12 consultas veterinarias, 23 vacunaciones, 6 cirugías y 9 exámenes de laboratorio

### Paso 7: Iniciar el Servidor de Desarrollo

La forma más simple es ejecutar un único comando que levanta el servidor PHP y Vite en paralelo:

```bash
composer run dev
```

La aplicación estará disponible en: **http://localhost:8000**

#### Alternativa (dos terminales separadas):

```bash
# Terminal 1 - servidor PHP
php artisan serve

# Terminal 2 - assets con hot-reload
npm run dev
```

#### Para producción (compilar assets):
```bash
npm run build
```

## 🔐 Acceso a la Aplicación

### URL Principal
- **http://localhost:8000**

### Panel de Administración
- **http://localhost:8000** (el panel está en la raíz; si no hay sesión redirige a `/login`)

Credenciales de acceso (datos demo):

| Campo | Valor |
|-------|-------|
| Email | `admin@mbopivet.com.py` (configurable con `ADMIN_EMAIL` en `.env`) |
| Contraseña | La genera el seeder la primera vez y se muestra **una sola vez** en la consola: guárdala en ese momento (si la pierdes, vuelve a ejecutar `php artisan migrate:fresh --seed`) |

## 📁 Estructura del Proyecto

```
laravet/
├── app/                          # Código de aplicación
│   ├── Enums/                   # Estados y opciones (especie, estado de vacunación, etc.)
│   ├── Filament/                # Recursos del panel de administración
│   │   ├── Concerns/            # Roles por defecto y protección de eliminación
│   │   ├── Widgets/             # Tablero de inicio
│   │   └── Resources/           # Interfaz para gestionar entidades
│   │       ├── CityResource
│   │       ├── ConsultationResource
│   │       ├── DepartmentResource
│   │       ├── NeighborhoodResource
│   │       ├── OwnerResource
│   │       ├── PetResource
│   │       ├── SurgeryResource
│   │       ├── TestResource
│   │       ├── UserResource
│   │       └── VaccinationResource
│   ├── Models/                  # Modelos de datos
│   ├── Services/                # Integración con IA y generación de PDF
│   ├── Http/                    # Controllers (entrega autenticada de resultados de pruebas)
│   └── Console/                 # Comandos personalizados (recordatorios de vacunación)
├── config/                      # Archivos de configuración
├── lang/                        # Traducciones al español (incluye etiquetas de permisos)
├── database/
│   ├── migrations/              # Migraciones de BD
│   ├── factories/               # Factories para tests
│   ├── seeders/                 # Seeders de datos iniciales
│   └── database.sqlite          # Archivo de BD SQLite (se crea al ejecutar migrate)
├── resources/
│   ├── css/                     # Estilos CSS
│   ├── js/                      # Código JavaScript
│   └── views/                   # Vistas Blade
├── routes/                      # Definición de rutas
├── tests/                       # Tests unitarios y funcionales
├── storage/                     # Logs y archivos subidos
├── public/                      # Archivos públicos
├── .env.example                 # Plantilla de variables de entorno
├── composer.json                # Dependencias PHP
├── package.json                 # Dependencias Node.js
├── vite.config.js              # Configuración de Vite
├── phpunit.xml                 # Configuración de tests
└── README.md                    # Este archivo
```

## 🛠️ Comandos Útiles

### Gestión de Base de Datos
| Comando | Descripción |
|---------|-------------|
| `php artisan migrate` | Ejecutar todas las migraciones de BD |
| `php artisan migrate:rollback` | Revertir la última migración |
| `php artisan db:seed` | Ejecutar todos los seeders |
| `php artisan db:seed --class=ClassName` | Ejecutar un seeder específico |
| `php artisan tinker` | Consola interactiva de Laravel (REPL) |

### Modelos y Generación de Código
| Comando | Descripción |
|---------|-------------|
| `php artisan make:model ModelName -m` | Crear un modelo con migración |
| `php artisan make:model ModelName -c` | Crear un modelo con controller |
| `php artisan make:model ModelName -a` | Crear modelo con todas las relacionesy migración |
| `php artisan make:migration create_table_name` | Crear una nueva migración |
| `php artisan make:seeder SeederName` | Crear un nuevo seeder |

### Frontend
| Comando | Descripción |
|---------|-------------|
| `npm run dev` | Compilar assets en modo desarrollo con hot-reload |
| `npm run build` | Compilar assets para producción |
| `npm run preview` | Previsualizar build de producción localmente |

### Tests y Calidad de Código
| Comando | Descripción |
|---------|-------------|
| `php artisan test` | Ejecutar todos los tests |
| `php artisan test --filter=TestName` | Ejecutar tests específicos |
| `./vendor/bin/pint` | Formatear código según estándares |
| `./vendor/bin/pint --check` | Verificar formato sin cambios |

### Otros Comandos Útiles
| Comando | Descripción |
|---------|-------------|
| `composer run dev` | Iniciar servidor PHP + Vite en una sola terminal |
| `php artisan serve` | Iniciar solo el servidor PHP (puerto 8000) |
| `php artisan serve --port=8080` | Iniciar servidor en puerto personalizado |
| `php artisan cache:clear` | Limpiar caché |
| `php artisan config:cache` | Caché configuración |
| `php artisan view:clear` | Limpiar caché de vistas |
| `php artisan storage:link` | Crear link simbólico de storage |

## 🐛 Solución de Problemas

### Problema: "composer not found"
**Solución**: Instalar Composer desde [getcomposer.org](https://getcomposer.org) o usar `php composer.phar` si descargaste el archivo PHAR.

### Problema: "php not found" o versión incorrecta
**Solución**: 
- Instalar PHP 8.2+ desde [php.net](https://www.php.net/downloads)
- Verificar que PHP está en el PATH de tu sistema
- En Windows, posiblemente necesites agregar la ruta manualmente a variables de entorno

### Problema: "npm not found"
**Solución**: Instalar Node.js y npm desde [nodejs.org](https://nodejs.org)

### Problema: Error en migraciones de SQLite
**Solución**: Verificar permisos de escritura en la carpeta `database/`
```bash
chmod 755 database/
ls -la database/database.sqlite  # Debe mostrar permisos de lectura/escritura
```

### Problema: Assets no se cargan en navegador
**Soluciones**:
1. Ejecutar `npm run build` nuevamente
2. Asegurar que Vite se está ejecutando con `npm run dev` en modo desarrollo
3. Limpiar caché del navegador (Ctrl+Shift+Del o Cmd+Shift+Del)
4. Verificar consola del navegador (F12) para errores

### Problema: Error "Class not found" en Artisan
**Solución**: Ejecutar `composer dump-autoload` para regenerar el autoloader

### Problema: Base de datos bloqueada (SQLite)
**Solución**: Es raro en desarrollo, pero si ocurre:
1. Detener el servidor con Ctrl+C
2. Eliminar `database/database.sqlite`
3. Ejecutar `php artisan migrate` nuevamente

### Problema: Error 500 solo en producción (con MySQL)
Los tests corren en SQLite y producción usa MySQL; algunas consultas que SQLite tolera MySQL las rechaza (por ejemplo, subconsultas que devuelven más de una fila). Revisa el mensaje real en `storage/logs/laravel.log` del servidor:
```bash
grep "production.ERROR" storage/logs/laravel.log | tail -n 3 | cut -c1-900
```

### Problema: No se abre el archivo de resultado de una prueba
Los resultados se guardan en el disco privado `test-results` (`storage/app/test-results`) y se sirven por una ruta autenticada; hace falta tener el permiso para ver pruebas. Si el archivo es anterior a este cambio, ejecuta las migraciones pendientes (`php artisan migrate`), que lo mueven desde el almacenamiento público.

## 📚 Documentación Adicional

- [Laravel Documentation](https://laravel.com/docs)
- [Filament Documentation](https://filamentphp.com/docs)
- [Alpine.js Documentation](https://alpinejs.dev)
- [Vite Documentation](https://vitejs.dev)
- [Laravel Blade Templates](https://laravel.com/docs/11.x/blade)

## 🤝 Contribución

Para contribuir al proyecto, por favor consulta [CONTRIBUTING.md](CONTRIBUTING.md) para más detalles sobre el proceso de desarrollo y estándares de código.

## 📝 Licencia

Este proyecto está bajo licencia privada. Todos los derechos reservados para "Mbopivet".

## 👨‍💻 Autores

- **Equipo de Desarrollo**: [edfsosa](https://github.com/edfsosa)

## 📞 Soporte

Para reportar problemas, preguntas o sugerencias, por favor abre un issue en el repositorio.

---

**Última actualización**: Octubre 2026 — v1.2 (panel en español, mejoras de UX en todos los recursos, vacunas vigentes y resultados privados)
