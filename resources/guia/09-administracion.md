---
title: Administración
order: 90
icon: heroicon-o-cog-6-tooth
permission: view_any_user
---

# Administración

## Usuarios

En **Configuración → Usuarios** se crean las cuentas del personal y se les asigna un rol. Al crear un usuario se define su contraseña; el usuario puede cambiarla desde su perfil.

La lista muestra nombre, correo, teléfono, rol y ciudad. Los contadores de consultas, cirugías, vacunaciones y pruebas de cada usuario vienen ocultos: actívalos desde el selector de columnas de la tabla.

Protecciones:

- No puedes cambiar **tu propio rol** ni eliminar tu propio usuario.
- No se puede quitar ni eliminar al **único administrador**.
- Un usuario con registros clínicos no se puede eliminar, porque se perderían sus consultas, vacunas, cirugías y pruebas.

## Catálogos geográficos

En **Catálogos** están los departamentos, las ciudades y los barrios que se usan en los domicilios. Los barrios salen ordenados por ciudad y luego por nombre, y también puedes ordenarlos por departamento. Las columnas *Propietarios* y *Usuarios* (cuántos usan cada elemento) vienen ocultas; el filtro **Uso** sigue disponible para encontrar lo que está en uso o sin uso.

## Roles y permisos

En **Configuración → Roles y permisos** el administrador crea roles y marca qué puede hacer cada uno (ver, crear, editar, eliminar cada tipo de registro, usar la IA, exportar). Los roles base (administrador, veterinario y asistente) no se pueden eliminar ni cambiar de nombre (sí puedes editar sus permisos), y tampoco se eliminan los roles que tienen usuarios asignados.

## Datos de la clínica

En **Configuración → Datos de la clínica** (solo administradores) hay cuatro pestañas:

- **General**: nombre, nombre corto, zona horaria, color, logo, contacto y datos fiscales. El **nombre** aparece como remitente de los correos del sistema, y el **email** es la casilla a la que llegan las respuestas de los propietarios (es obligatorio mientras los recordatorios estén activos). La **dirección de envío** es obligatoria y se puede cambiar por otra del mismo dominio verificado, por ejemplo una de «no responder». El **logo**, el **color**, el teléfono, el WhatsApp, la dirección, el horario, las redes y la web también aparecen en los correos: el logo y el color en el encabezado y el botón, y el resto en el pie. El **correo de soporte del sistema** es obligatorio y es el que todo el personal ve en *Ayuda → Soporte*.
- **Recordatorios**: si se envían, cuántos días antes y después del vencimiento, a qué hora y con qué texto.
- **Documentos PDF**: pie de página, firma o sello y texto legal.
- **Listas**: vacunas, fabricantes, tipos de cirugía y tipos de prueba que aparecen en los formularios, y los **límites de peso por especie** con los que el sistema propone el tamaño de una mascota (hasta cuántos kg es Diminuto, Pequeño, Mediano y Grande; por encima es Gigante). Una especie que no figura no propone tamaño; por defecto solo vienen los caninos.

## Registro de exportaciones

**Configuración → Registro de exportaciones** muestra quién exportó qué lista y cuándo (solo para quien tenga ese permiso).
