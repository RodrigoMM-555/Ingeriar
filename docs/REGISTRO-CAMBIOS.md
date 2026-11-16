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
- Pendiente o problemas:
  - Los usuarios dados de alta con la versión anterior tienen la contraseña guardada en claro y no podrán entrar
    (no es ni sha256 ni `password_hash`). Un administrador debe asignarles una contraseña nueva desde la edición de usuarios.
  - Problemas de seguridad detectados fuera de este encargo (propuestos como encargos nuevos, sin tocar):
    `servicios/salida.php` devuelve toda la base de datos sin pedir sesión; `api/instalar.php` no pide sesión;
    `database.php` contiene una contraseña de MySQL por defecto en el repositorio público.
  - No se ha probado contra MySQL desde este equipo: se ha comprobado la sintaxis de PHP y JS y la lógica de contraseñas con un script de 16 casos.
