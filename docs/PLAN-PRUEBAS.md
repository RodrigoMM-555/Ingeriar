# Plan de pruebas

Pruebas funcionales y de permisos de Ingeriar (encargo E-07). Hay dos tipos:

- **Automáticas**: las repite el script `server/pruebas/pruebas.php` por HTTP contra la aplicación en marcha.
- **Manuales**: comprueban la interfaz en el navegador. Se marcan a mano con el resultado y la fecha.

Cuando se añada una funcionalidad, se añaden sus pruebas aquí y, si se puede, al script.

## Cómo ejecutar las pruebas automáticas

1. Preparar una base de datos **de pruebas** con `server/data/schema.sql` (el script necesita los catálogos
   iniciales de `secciones` y `tipos_evento`). No hace falta cargar `demo.sql`.
2. Tener la aplicación accesible por HTTP (Apache o `php -S localhost:8000 -t public`).
3. Desde la raíz del proyecto, con las mismas variables de entorno de base de datos que usa Apache:

```bash
INGERIAR_DB_PASSWORD=... php server/pruebas/pruebas.php http://localhost:8000 --confirmar
```

- `--confirmar` es obligatorio: el script crea y borra datos (usuarios `@pruebas.ingeriar.test`, un educando y
  dos eventos «Prueba automática…») y los elimina al terminar, aunque una prueba falle.
- `--markdown` imprime además la tabla de resultados para copiarla en este documento.
- Devuelve el código de salida 0 si todas las pruebas se superan y 1 si alguna falla.
- Solo se ejecuta desde la línea de comandos: desde el navegador responde 403.

Usuarios que crea el script: un administrador, un scouter, un tutor con un educando de la sección tropa,
un usuario con la contraseña antigua en sha256 y otro con la contraseña guardada en claro.

## Pruebas automáticas

Entorno de la última ejecución: MySQL 8.4, PHP 8.5 con su servidor integrado, base creada con `schema.sql` y `demo.sql`.

| ID | Prueba | Requisito | Resultado | Fecha |
|---|---|---|---|---|
| AUT-01 | Login con contraseña correcta | RF-01 | Superada | 2026-11-16 |
| AUT-02 | Login con contraseña incorrecta → 401 | RF-01, RNF-03 | Superada | 2026-11-16 |
| AUT-03 | Login con email inexistente → 401 (mismo mensaje) | RNF-03 | Superada | 2026-11-16 |
| AUT-04 | Login sin datos → 400 | RF-01 | Superada | 2026-11-16 |
| AUT-05 | Login por GET → 405 | RNF-03 | Superada | 2026-11-16 |
| AUT-06 | Usuario con sha256 antiguo entra y queda migrado a `password_hash` | RNF-03 | Superada | 2026-11-16 |
| AUT-07 | Contraseña guardada en claro no da acceso → 401 | RNF-03 | Superada | 2026-11-16 |
| AUT-08 | API sin sesión → 401 | RNF-03 | Superada | 2026-11-16 |
| CON-01 | Alta con contraseña de menos de 8 caracteres → 400 | RF-01, RNF-03 | Superada | 2026-11-16 |
| CON-02 | Alta con contraseña solo de letras → 400 | RF-01, RNF-03 | Superada | 2026-11-16 |
| CON-03 | Alta sin contraseña → 400 | RF-01 | Superada | 2026-11-16 |
| CON-04 | Alta válida guarda un hash de `password_hash` | RNF-03 | Superada | 2026-11-16 |
| CON-05 | Editar sin contraseña conserva la actual | RF-01 | Superada | 2026-11-16 |
| CON-06 | La tabla usuarios no envía contraseñas (administrador) | RNF-03 | Superada | 2026-11-16 |
| CON-07 | La tabla usuarios no envía contraseñas (scouter) | RNF-03 | Superada | 2026-11-16 |
| PER-01 | Tutor no puede leer usuarios → 403 | RF-01, RNF-03 | Superada | 2026-11-16 |
| PER-02 | Tutor solo ve sus educandos | RF-02, RNF-03 | Superada | 2026-11-16 |
| PER-03 | Tutor no puede crear eventos → 403 | RF-03, RNF-03 | Superada | 2026-11-16 |
| PER-04 | Scouter no puede crear usuarios → 403 | RF-01, RNF-03 | Superada | 2026-11-16 |
| PER-05 | Scouter no puede borrar usuarios → 403 | RF-01, RNF-03 | Superada | 2026-11-16 |
| PER-06 | Tutor solo tiene la entidad educandos | RF-01 | Superada | 2026-11-16 |
| PER-07 | Scouter no puede guardar la configuración → 403 | RF-07, RNF-03 | Superada | 2026-11-16 |
| PER-08 | Tutor no puede consultar educandos de otro usuario → 403 | RF-02, RNF-03 | Superada | 2026-11-16 |
| SAL-01 | Copia de seguridad sin sesión → 401 | RNF-03, RNF-04 | Superada | 2026-11-16 |
| SAL-02 | Copia de seguridad como scouter → 403 | RNF-03, RNF-04 | Superada | 2026-11-16 |
| SAL-03 | El administrador descarga la copia sin contraseñas | RNF-04 | Superada | 2026-11-16 |
| INS-01 | Instalador con la aplicación en uso, sin sesión → 403 | RNF-03 | Superada | 2026-11-16 |
| INS-02 | Instalador con la aplicación en uso, como tutor → 403 | RNF-03 | Superada | 2026-11-16 |
| GAL-01 | Tutor no puede subir fotos → 403 | RF-04, RNF-03 | Superada | 2026-11-16 |
| GAL-02 | Archivo que no es imagen → 415 | RF-04, RNF-03 | Superada | 2026-11-16 |
| GAL-03 | Scouter sube fotos válidas | RF-04 | Superada | 2026-11-16 |
| GAL-04 | El tutor ve el evento de su sección y no el de otra | RF-04, RNF-03 | Superada | 2026-11-16 |
| GAL-05 | El tutor abre una foto de su sección | RF-04 | Superada | 2026-11-16 |
| GAL-06 | El tutor no abre una foto de otra sección → 403 | RF-04, RNF-03 | Superada | 2026-11-16 |
| GAL-07 | Ver una foto sin sesión → 401 | RNF-03 | Superada | 2026-11-16 |
| GAL-08 | Tutor no puede borrar fotos → 403 | RF-04, RNF-03 | Superada | 2026-11-16 |
| GAL-09 | Scouter borra una foto y su archivo | RF-04 | Superada | 2026-11-16 |

Resultado: 37 de 37 superadas.

## Pruebas manuales (interfaz)

Para hacerlas en el navegador con `demo.sql` cargado: administrador `usuario1@ingeriar.test` / `ingeriar-1`,
scouter `usuario2@ingeriar.test` / `ingeriar-2`, tutor `usuario3@ingeriar.test` / `ingeriar-3`.
Rellenar la columna Resultado con «Superada» o «No superada (motivo)» y la fecha.

| ID | Rol | Prueba | Resultado esperado | Requisito | Resultado | Fecha |
|---|---|---|---|---|---|---|
| MAN-01 | Todos | Iniciar sesión desde `login.html` | Entra y muestra el dashboard de su rol | RF-01, RF-07 | Pendiente | |
| MAN-02 | Todos | Contraseña incorrecta | Mensaje «Email o contraseña incorrectos» sin recargar | RF-01 | Pendiente | |
| MAN-03 | Todos | Botón «Salir» | Vuelve a `login.html`; al pulsar Atrás no se ven datos | RF-01 | Pendiente | |
| MAN-04 | Administrador | Editar un usuario | El campo contraseña aparece vacío; guardarlo vacío no la cambia | RF-01, RNF-03 | Pendiente | |
| MAN-05 | Administrador | Crear usuario con `abcdefgh` | Aviso con el motivo; no se cierra el formulario | RF-01 | Pendiente | |
| MAN-06 | Administrador | Crear, editar y borrar un educando | Los cambios aparecen en la tabla con su sección | RF-02 | Pendiente | |
| MAN-07 | Tutor | Menú y tabla de educandos | Solo la entidad educandos y solo sus educandos | RF-01, RF-02 | Pendiente | |
| MAN-08 | Scouter | Crear un evento con varias secciones | Aparece en el calendario con el color de su tipo | RF-03 | Pendiente | |
| MAN-09 | Tutor | Confirmar asistencia y subir una entrega | Se guarda; no se permite tras la fecha de fin | RF-03, RF-04 | Pendiente | |
| MAN-10 | Scouter | Crear una factura con imagen | La factura se guarda con la imagen | RF-05 | Pendiente | |
| MAN-11 | Administrador | Cambiar color y nombre del grupo en configuración | Se aplican al guardar | RF-07 | Pendiente | |
| MAN-12 | Administrador | Configuración → «Descargar copia (JSON)» | Descarga `ingeriar-copia-<fecha>.json` | RNF-04 | Pendiente | |
| MAN-13 | Administrador | Exportar una tabla a CSV, JSON, HTML e impresión | Los cuatro formatos funcionan | RF-07 | Pendiente | |
| MAN-14 | Scouter | Galería → «Subir fotos» a un evento | Aparecen en «Ronda / tipo / evento» con miniaturas | RF-04 | Pendiente | |
| MAN-15 | Todos | Visor de la galería | Flechas del teclado pasan de foto; Escape cierra; «Descargar» funciona | RF-04, RNF-06 | Pendiente | |
| MAN-16 | Tutor | Galería | Sin botón de subir ni de borrar; solo eventos de sus secciones | RF-04, RNF-03 | Pendiente | |
| MAN-17 | Todos | Galería y formularios en modo oscuro | Textos y carpetas legibles | RNF-06 | Pendiente | |
| MAN-18 | Todos | Pantalla estrecha (móvil) | La galería y las tablas se pueden usar sin desbordarse | RNF-02, RNF-06 | Pendiente | |
| MAN-19 | Cualquiera | Abrir `/storage/galeria/<archivo>` y `/server/` en Apache | Acceso denegado | RNF-03 | Pendiente | |
| MAN-20 | Sin sesión | Abrir `instalador.html` y enviarlo con la aplicación en uso | «La aplicación ya está instalada…» | RNF-03 | Pendiente | |
