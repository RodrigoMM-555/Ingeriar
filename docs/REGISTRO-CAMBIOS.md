# Registro de cambios

Este archivo lo rellena Claude Code al terminar cada encargo. El chat del documento lo lee para actualizar la memoria.
Añadir las entradas nuevas arriba. Formato:

```
## AAAA-MM-DD · E-XX · título corto
- Rama / commit:
- Qué se ha hecho (2-4 líneas):
- Archivos creados o modificados:
- Decisiones técnicas y motivo:
- Requisitos que cubre (RF/RNF):
- Cómo probarlo (pasos):
- Impacto en la memoria (apartados que habría que revisar):
- Pendiente o problemas:
```

## 2026-11-16 · E-08 · Proteger la exportación completa
- Rama / commit: `feat/E-08-salida` (sale de `feat/E-10-credenciales`)
- Qué se ha hecho: `servicios/salida.php` pasa a ser una copia de seguridad en JSON solo para administradores.
  Antes cualquiera sin sesión podía descargar toda la base de datos. Se excluyen los hashes de contraseña y se
  añade un enlace de descarga en la pantalla de configuración. Se eliminó `servicios/entrada.php` (vacío y sin uso).
- Archivos creados o modificados: `server/servicios/salida.php`, `public/nucleo/configuracion.js`;
  eliminados `server/servicios/entrada.php` y `public/servicios/entrada.php`; `noteas.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - Se mantiene el servicio en lugar de borrarlo porque sirve como copia de seguridad (RNF-04).
  - La sesión y el rol se comprueban antes de abrir la conexión con MySQL.
  - `Content-Disposition: attachment` con fecha en el nombre y `Cache-Control: no-store` para que el navegador
    descargue el archivo y no lo guarde en caché.
  - `entrada.php` se elimina: no tenía código y nada lo usaba. Una futura importación sería un encargo propio.
- Requisitos que cubre (RF/RNF): RNF-03, RNF-04, RF-07.
- Cómo probarlo (pasos):
  1. Sin sesión, abrir `servicios/salida.php`: `{"error":"No autenticado"}` (401).
  2. Con un scouter o tutor: `No tienes permisos para esta operación` (403).
  3. Como administrador: Configuración → «Descargar copia (JSON)». Se descarga `ingeriar-copia-<fecha>.json` y en `usuarios` no aparece `contraseña`.
  4. `servicios/entrada.php` devuelve 404.
- Impacto en la memoria (apartados que habría que revisar): seguridad (RNF-03), copias de seguridad (RNF-04), configuración (RF-07).
- Pendiente o problemas: la copia no incluye los archivos de `storage/` (documentos, facturas, galería); habría que copiarlos aparte.
  Probado desde la terminal con sesiones simuladas: sin sesión 401, tutor y scouter 403, administrador pasa la comprobación.

## 2026-11-16 · E-10 · Credenciales fuera del repositorio
- Rama / commit: `feat/E-10-credenciales`
- Qué se ha hecho: `database.php` ya no tiene una contraseña de MySQL por defecto; solo la lee de
  `INGERIAR_DB_PASSWORD`. Si falta, se lanza el error de configuración que ya existía. Se ha revisado el
  repositorio en busca de otras claves o datos reales.
- Archivos creados o modificados: `server/data/database.php`, `noteas.md` (instalación y diario), `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - Se mantienen los valores por defecto que no son secretos (host, puerto, nombre de base y usuario `ingeriar_app`).
  - La contraseña antigua sigue en el historial de git: no se reescribe el historial (afectaría a todos los clones);
    se documenta que cualquier servidor que la use debe cambiarla.
  - Revisión: no hay más credenciales. Los PDF de `storage/documentos/biblioteca/` son el impreso de inscripción
    en blanco (campos del formulario vacíos); `circular1.pdf` y `todas si.pdf` son idénticos. Los datos de `demo.sql` son ficticios.
- Requisitos que cubre (RF/RNF): RNF-03, RNF-05.
- Cómo probarlo (pasos):
  1. Con `SetEnv INGERIAR_DB_PASSWORD …` en el VirtualHost, la aplicación funciona igual que antes.
  2. Comentar esa línea y reiniciar Apache: el login muestra «No se pudo conectar con la base de datos…» y no se conecta con ninguna clave del código.
- Impacto en la memoria (apartados que habría que revisar): instalación y configuración del servidor, seguridad (RNF-03).
- Pendiente o problemas: si la instalación local dependía del valor por defecto, hay que añadir `SetEnv INGERIAR_DB_PASSWORD`
  al VirtualHost antes de fusionar, o la aplicación dejará de conectar.

## 2026-11-16 · E-01 · Contraseñas con password_hash
- Rama / commit: `feat/E-01-contrasenas`
- Qué se ha hecho: las contraseñas se guardan con `password_hash` y se comprueban con `password_verify`.
  Los usuarios con sha256 antiguo entran y su contraseña se reescribe en ese momento. Se exigen reglas
  mínimas (8 caracteres y al menos un número o carácter especial). El hash ya no se envía nunca al cliente.
  El instalador puede crear el primer administrador si no hay usuarios.
- Archivos creados o modificados:
  - Nuevo: `server/auth/contrasenas.php` (validar, cifrar, verificar y detectar hashes antiguos).
  - `server/api/login.php`: `password_verify`, migración sha256 → `password_hash`, tiempo constante si el email no existe.
  - `server/api/superapi.php`: alta y edición de `usuarios` cifran y validan la contraseña (vacía al editar = no cambiar);
    `tabla` no devuelve la columna `contraseña`; `columnas` la marca como `tipo_formulario: PASSWORD`.
  - `public/nucleo/formulario.js`: campo `type="password"` vacío, obligatorio solo al crear; los errores
    del servidor al guardar se muestran con `alert`.
  - `public/instalador.html` y `server/api/instalar.php`: datos opcionales del primer administrador.
  - `server/data/demo.sql`: 30 hashes bcrypt precalculados; contraseñas de prueba `ingeriar-<n>`.
  - `server/data/schema.sql`, `server/data/modeloDeDatos.txt`: comentario sobre el contenido de la columna.
  - `noteas.md`: apartado «Contraseñas» en el flujo de inicio, pasos de instalación y diario.
- Decisiones técnicas y motivo:
  - `PASSWORD_DEFAULT` (bcrypt hoy) con `password_needs_rehash`, para que un cambio futuro de algoritmo
    se aplique solo al iniciar sesión. La columna sigue en `VARCHAR(255)`, como recomienda PHP.
  - Un sha256 antiguo se reconoce por su forma (64 caracteres hexadecimales) y se compara con `hash_equals`.
  - Antes, el alta de usuarios guardaba la contraseña en claro y la ruta `tabla` enviaba el hash al
    administrador y al scouter (selector de responsable de facturas). Se corrige porque el criterio de
    aceptación exige que no quede ninguna contraseña en claro ni en sha256.
  - El administrador inicial solo se crea con la tabla `usuarios` vacía, porque el instalador no pide sesión.
  - Las contraseñas de prueba pasan de `demo-<n>` a `ingeriar-<n>` porque `demo-<n>` no cumple la longitud mínima.
  - Las longitudes se cuentan con `preg_match_all` para no depender de la extensión `mbstring`.
- Requisitos que cubre (RF/RNF): RNF-03 (y RF-01 en el alta de usuarios).
- Cómo probarlo (pasos):
  1. Recargar `schema.sql` y `demo.sql`. Entrar con `usuario1@ingeriar.test` / `ingeriar-1`: correcto. Con otra contraseña: «Email o contraseña incorrectos».
  2. Simular un usuario antiguo: ``UPDATE usuarios SET `contraseña` = SHA2('demo-2', 256) WHERE id = 2;``
     Entrar con `usuario2@ingeriar.test` / `demo-2`: entra. ``SELECT `contraseña` FROM usuarios WHERE id = 2;`` empieza ahora por `$2y$`.
  3. Como administrador, crear un usuario con `abcdefgh` (rechazado), con `abc1` (rechazado) y con `scouts2026` (aceptado). Comprobar en MySQL que se guarda `$2y$…`.
  4. Editar ese usuario dejando la contraseña vacía: la contraseña no cambia.
  5. Con la sesión iniciada, abrir `api/superapi.php?ruta=tabla&tabla=usuarios`: no aparece `contraseña`.
  6. Instalador: con usuarios existentes, rellenar el administrador → «Ya existen usuarios». En una base vacía → se crea y puede entrar.
  7. Comprobación general: ``SELECT COUNT(*) FROM usuarios WHERE `contraseña` NOT LIKE '$2y$%';`` debe ser 0 cuando todos hayan entrado al menos una vez.
- Impacto en la memoria (apartados que habría que revisar): 2.6, 3.1, 4.4, 5.2. Además, en los datos de prueba, las contraseñas son ahora `ingeriar-<n>`.
  El código de migración sha256 es temporal; `noteas.md` (apartado «Contraseñas») explica cuándo se puede retirar.
- Pendiente o problemas:
  - Los usuarios dados de alta con la versión anterior tienen la contraseña guardada en claro y no podrán entrar
    (no es ni sha256 ni `password_hash`). Un administrador debe asignarles una contraseña nueva desde la edición de usuarios.
  - Problemas de seguridad detectados fuera de este encargo (añadidos a `ENCARGOS.md` como E-08, E-09 y E-10, sin tocar el código):
    `servicios/salida.php` devuelve toda la base de datos sin pedir sesión; `api/instalar.php` no pide sesión;
    `database.php` contiene una contraseña de MySQL por defecto en el repositorio público.
  - No se ha probado contra MySQL desde este equipo: se ha comprobado la sintaxis de PHP y JS y la lógica de contraseñas con un script de 16 casos.
