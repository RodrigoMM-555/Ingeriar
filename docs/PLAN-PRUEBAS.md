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

| BAJ-01 | El tutor no ve a su educando dado de baja | RF-02, RNF-03 | Superada | 2026-11-17 |
| BAJ-02 | El tutor confirma la asistencia de un educando activo | RF-03 | Superada | 2026-11-17 |
| BAJ-03 | El tutor no confirma la asistencia de un educando de baja → 400 | RF-03, RNF-03 | Superada | 2026-11-17 |
| BAJ-04 | El administrador sigue viendo al educando de baja | RF-02 | Superada | 2026-11-17 |
| BAJ-05 | La descarga de la copia completa queda registrada | RNF-03 | Superada | 2026-11-17 |
| BAJ-06 | Ver una entrega queda registrado | RF-04, RNF-03 | Superada | 2026-11-17 |
| BAJ-07 | Archivos sin sesión → 401 | RNF-03 | Superada | 2026-11-17 |
| BAJ-08 | Nadie puede borrar el registro de accesos, ni el administrador → 403 | RNF-03 | Superada | 2026-11-17 |
| BAJ-09 | Scouter no accede a los datos vencidos → 403 | RNF-03 | Superada | 2026-11-17 |
| BAJ-10 | Se lista el educando vencido y no el dado de baja ayer | RF-02, RNF-03 | Superada | 2026-11-17 |
| BAJ-11 | No se puede borrar un educando que no ha vencido → 400 | RNF-03 | Superada | 2026-11-17 |
| BAJ-12 | Eliminar vencidos borra educando, tutor elegido y archivos | RF-02, RF-04, RNF-03 | Superada | 2026-11-17 |

| ROL-01 | Un monitor solo ve sus facturas | RF-05, RNF-03 | Superada | 2026-11-17 |
| ROL-02 | Un monitor con tesorería ve todas las facturas | RF-01, RF-05 | Superada | 2026-11-17 |
| ROL-03 | Un monitor no puede editar la factura de otro → 403 | RF-05, RNF-03 | Superada | 2026-11-17 |
| ROL-04 | Un monitor edita su factura pendiente pero no cambia estados (403) | RF-05, RNF-03 | Superada | 2026-11-17 |
| ROL-05 | Tesorería cambia el estado de una factura ajena y queda registrado quién | RF-05 | Superada | 2026-11-17 |
| ROL-06 | La factura nueva de un monitor es suya y queda pendiente | RF-05 | Superada | 2026-11-17 |
| ROL-07 | Unión de permisos: tutor con tesorería ve todas las facturas pero no sube fotos | RF-01 | Superada | 2026-11-17 |
| ROL-08 | Un usuario no puede tener dos cargos básicos → 400 | RF-01 | Superada | 2026-11-17 |
| ROL-09 | Un usuario sin cargo básico → 400 | RF-01 | Superada | 2026-11-17 |
| ROL-10 | Alta con cargo básico y adicional guarda los dos | RF-01 | Superada | 2026-11-17 |
| ROL-11 | Un monitor no puede ver ni editar los cargos → 403 | RF-01, RNF-03 | Superada | 2026-11-17 |
| ROL-12 | Los permisos del administrador no se pueden cambiar → 400 | RNF-03 | Superada | 2026-11-17 |
| ROL-13 | Un cargo básico no se puede desactivar → 400 | RNF-03 | Superada | 2026-11-17 |
| ROL-14 | Un cargo nuevo da permisos y quitarlos tiene efecto sin volver a entrar | RF-01, RNF-03 | Superada | 2026-11-17 |
| ROL-15 | Un cargo desactivado no da permisos | RF-01 | Superada | 2026-11-17 |
| ROL-16 | Un usuario borrado pierde la sesión al momento → 401 | RNF-03 | Superada | 2026-11-17 |
| ROL-17 | Un monitor da de alta y edita educandos | RF-02 | Superada | 2026-11-17 |
| ROL-18 | Un monitor no borra educandos (403); el administrador sí | RF-02, RNF-03 | Superada | 2026-11-17 |
| ROL-19 | Un monitor no edita la tabla de asistencias → 403 | RF-03, RNF-03 | Superada | 2026-11-17 |
| ROL-20 | La cabecera recibe los nombres de los cargos | RF-01 | Superada | 2026-11-17 |

| FAC-01 | Crear una factura sin imagen → 400 | RF-05 | Superada | 2026-11-17 |
| FAC-02 | Crear una factura con un archivo que no es imagen → 415 | RF-05, RNF-03 | Superada | 2026-11-17 |
| FAC-03 | Datos incompletos → 400 | RF-05 | Superada | 2026-11-17 |
| FAC-04 | El monitor solo lista las suyas y no puede cambiar estados | RF-05, RNF-03 | Superada | 2026-11-17 |
| FAC-05 | El monitor ve la imagen de su factura; sin sesión 401; tesorería la ve | RF-05, RNF-03 | Superada | 2026-11-17 |
| FAC-06 | Otro monitor no ve la imagen de una factura ajena → 403 | RNF-03 | Superada | 2026-11-17 |
| FAC-07 | El monitor no edita su factura cuando ya está revisada → 403 | RF-05 | Superada | 2026-11-17 |
| FAC-08 | Una factura pagada no la edita ni la borra nadie, ni el administrador (409) | RF-05 | Superada | 2026-11-17 |
| FAC-09 | Tesorería puede devolver una factura pagada a revisada | RF-05 | Superada | 2026-11-17 |
| FAC-10 | Las rutas genéricas ya no escriben facturas → 400 | RF-05, RNF-03 | Superada | 2026-11-17 |
| FAC-11 | El monitor borra su factura pendiente y su imagen | RF-05 | Superada | 2026-11-17 |
| FAC-12 | Un tutor no accede a las facturas → 403 | RNF-03 | Superada | 2026-11-17 |

| DOC-01 | Cualquier usuario descarga la circular de participación en blanco | RF-04 | Superada | 2026-11-17 |
| DOC-02 | Un documento sin plantilla → 404 | RF-04 | Superada | 2026-11-17 |
| DOC-03 | El administrador sube la plantilla y el tutor la descarga | RF-04, RF-07 | Superada | 2026-11-17 |
| DOC-04 | Un monitor no cambia las plantillas → 403 | RNF-03 | Superada | 2026-11-17 |
| DOC-05 | El tutor ve la documentación de su educando | RF-04 | Superada | 2026-11-17 |
| DOC-06 | El tutor no ve la documentación de otro educando → 403 | RNF-03 | Superada | 2026-11-17 |
| DOC-07 | El tutor entrega un documento y queda pendiente en la carpeta del educando | RF-04 | Superada | 2026-11-17 |
| DOC-08 | El tutor no entrega documentos de otro educando → 403 | RNF-03 | Superada | 2026-11-17 |
| DOC-09 | Un archivo con formato no admitido → 415 | RNF-03 | Superada | 2026-11-17 |
| DOC-10 | El tutor no puede revisar → 403 | RNF-03 | Superada | 2026-11-17 |
| DOC-11 | Un monitor pide corrección (con observaciones obligatorias) | RF-04 | Superada | 2026-11-17 |
| DOC-12 | Volver a entregar deja el documento pendiente y borra el archivo anterior | RF-04 | Superada | 2026-11-17 |
| DOC-13 | Un monitor ve el documento y el acceso queda registrado | RNF-03 | Superada | 2026-11-17 |
| DOC-14 | La circular de participación de los eventos sale del catálogo | RF-03, RF-04 | Superada | 2026-11-17 |

| MOD-01 | Educando: guardar crea la fila, buscar la recupera y borrar la elimina | RF-02, RNF-05 | Superada | 2026-11-17 |
| MOD-02 | Usuario: el JSON no incluye la contraseña | RNF-03 | Superada | 2026-11-17 |
| MOD-03 | Educando: sección según la edad en la fecha y activo según la baja | RF-02 | Superada | 2026-11-17 |
| MOD-04 | Evento: un evento sin secciones convoca a todas | RF-03 | Superada | 2026-11-17 |

| INV-01 | Un tutor no accede al inventario → 403 | RF-06, RNF-03 | Superada | 2026-11-17 |
| INV-02 | Se crea un árbol de ubicaciones | RF-06 | Superada | 2026-11-17 |
| INV-03 | Un material puede estar en varias ubicaciones | RF-06 | Superada | 2026-11-17 |
| INV-04 | No se borra una ubicación con otras dentro ni con material (409) | RF-06 | Superada | 2026-11-17 |
| INV-05 | Una ubicación no se puede mover dentro de sí misma → 400 | RF-06 | Superada | 2026-11-17 |
| INV-06 | Cantidad negativa → 400 | RF-06 | Superada | 2026-11-17 |
| INV-07 | Una lista guarda de dónde se coge cada cantidad y no descuenta del inventario | RF-06 | Superada | 2026-11-17 |
| INV-08 | No se puede coger de un sitio más de lo que hay → 400 | RF-06 | Superada | 2026-11-17 |
| INV-09 | Una lista se guarda como plantilla y de la plantilla sale una lista nueva | RF-06 | Superada | 2026-11-17 |
| INV-10 | Diez escrituras simultáneas no pierden ningún dato (bloqueo de fichero) | RF-06, RNF-03 | Superada | 2026-11-17 |
| INV-11 | Mover material entre ubicaciones resta del origen y suma en el destino | RF-06 | Superada | 2026-11-17 |
| INV-12 | No se mueve más de lo que hay en el origen → 409 | RF-06 | Superada | 2026-11-17 |
| INV-13 | Mover a la misma ubicación o 0 unidades → 400 | RF-06 | Superada | 2026-11-17 |
| INV-14 | Un tutor no puede mover material → 403 | RF-06, RNF-03 | Superada | 2026-11-17 |

| COP-01 | Un monitor no ve las copias de seguridad → 403 | RNF-03, RNF-04 | Superada | 2026-11-17 |
| COP-02 | El administrador ve la lista de copias | RNF-04 | Superada | 2026-11-17 |
| COP-03 | Un nombre que no es de copia (../) se rechaza → 400 | RNF-03 | Superada | 2026-11-17 |
| COP-04 | Descargar una copia queda en el registro de accesos | RNF-03, RNF-04 | Superada | 2026-11-17 |

Resultado: 115 de 115 superadas el 2026-11-17 (con las pruebas de E-15).

### Copias de seguridad (`server/pruebas/pruebas-copias.php --base-vacia=<base> --confirmar`)

| ID | Prueba | Requisito | Resultado | Fecha |
|---|---|---|---|---|
| CPS-01 | La copia genera un zip con la base de datos y storage/, con permisos 0600 | RNF-04 | Superada | 2026-11-17 |
| CPS-02 | La retención conserva solo las N copias más recientes | RNF-04 | Superada | 2026-11-17 |
| CPS-03 | No se ejecutan dos copias a la vez (bloqueo) | RNF-04 | Superada | 2026-11-17 |
| CPS-04 | Con clave, la copia se cifra con AES-256 y no se puede abrir sin ella | RNF-03, RNF-04 | Superada | 2026-11-17 |
| CPS-05 | El registro anota cada ejecución y avisa de las copias sin cifrar | RNF-04 | Superada | 2026-11-17 |
| CPS-06 | Con una clave incorrecta no se restaura nada | RNF-03 | Superada | 2026-11-17 |
| CPS-07 | Restaurar la copia cifrada en una base vacía reproduce datos y archivos | RNF-04 | Superada | 2026-11-17 |
| CPS-08 | No se restaura sobre una base con datos sin --sobrescribir | RNF-04 | Superada | 2026-11-17 |

Resultado: 8 de 8 superadas el 2026-11-17 (CPS-07 compara el número de filas de todas las tablas y el contenido
de cada archivo de storage/ entre el origen y la restauración).

### Instalador (`INGERIAR_DB_NAME=<base vacía> server/pruebas/pruebas-instalador.php <url> --confirmar`)

| ID | Prueba | Requisito | Resultado | Fecha |
|---|---|---|---|---|
| INI-01 | En una base vacía el instalador está disponible sin sesión | RNF-05 | Superada | 2026-11-17 |
| INI-02 | Un schema.sql con DROP, GRANT o INTO OUTFILE se rechaza sin ejecutar nada | RNF-03 | Superada | 2026-11-17 |
| INI-03 | Un modelo con errores se rechaza con un mensaje claro | RNF-06 | Superada | 2026-11-17 |
| INI-04 | Instalar subiendo schema.sql crea el esquema completo | RNF-05 | Superada | 2026-11-17 |
| INI-05 | Instalar con el asistente deja el mismo esquema y datos que schema.sql (aceptación) | RNF-05 | Superada | 2026-11-17 |
| INI-06 | El primer administrador entra con su cargo y sus permisos | RF-01 | Superada | 2026-11-17 |
| INI-07 | Con la aplicación en uso, el instalador responde 403 sin sesión | RNF-03 | Superada | 2026-11-17 |

Resultado: 7 de 7 superadas el 2026-11-17. El token de instalación (`INGERIAR_TOKEN_INSTALACION`) se comprobó a mano:
sin token o con uno incorrecto, 403; con el correcto, 200.

Resultado anterior: 107 de 107 superadas el 2026-11-17 (servidor de PHP con 4 procesos para que las escrituras sean simultáneas).
El script copia los JSON del inventario al empezar y los restaura al terminar.

Resultado anterior: 97 de 97 superadas el 2026-11-17 con `schema.sql` y `demo.sql` nuevos tras la refactorización de E-02
(las 93 anteriores sirven para comprobar que el comportamiento no ha cambiado).

Resultado anterior: 93 de 93 superadas el 2026-11-17 con `schema.sql` y `demo.sql` nuevos, y en una copia de la base del usuario
con todas las migraciones aplicadas.

Resultado anterior: 79 de 79 superadas el 2026-11-17 con `schema.sql` y `demo.sql` nuevos, y en una copia de la base del usuario
tras aplicar todas las migraciones.

Resultado anterior: 69 de 69 superadas el 2026-11-17 en tres bases: con el esquema anterior más `2026-11-roles.sql`
(columna `rol` aún presente), después de `2026-11-roles-retirar-columna.sql` y con `schema.sql` y `demo.sql` nuevos.

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
| MAN-21 | Administrador | Poner `fecha_baja` a un educando desde su formulario | Sale en cursiva al final de la tabla; desaparece del tutor y del dashboard | RF-02 | Pendiente | |
| MAN-22 | Administrador | Configuración → «Datos vencidos» con un educando de baja de hace más de 5 años | Aparece con su número de archivos; al eliminar desaparece | RF-02, RNF-03 | Pendiente | |
| MAN-23 | Administrador | Abrir la tabla `registro_accesos` tras ver una entrega | Aparece la fila; editar o borrar da error | RNF-03 | Pendiente | |
| MAN-24 | Administrador | Crear un usuario: elegir un cargo básico y marcar Tesorería | Se guardan los dos; la tabla muestra «Monitor · Tesorería» | RF-01 | Pendiente | |
| MAN-25 | Administrador | Configuración → «Cargos y permisos»: crear «Secretaría» y darle `educandos.gestionar` | Aparece la columna; un tutor con ese cargo puede editar educandos | RF-01 | Pendiente | |
| MAN-26 | Administrador | En una base con un único administrador, quitarle el cargo o borrarlo | «No se puede: nadie podría gestionar los cargos» | RNF-03 | Pendiente | |
| MAN-27 | Monitor | Tabla de educandos | Ve Nuevo y Editar, no Borrar; puede rellenar `fecha_baja` | RF-02 | Pendiente | |
| MAN-28 | Monitor con tesorería | Cabecera, dashboard y facturas | Cabecera «Monitor · Tesorería»; ve el gasto total y todas las facturas | RF-01, RF-07 | Pendiente | |
| MAN-29 | Monitor | Facturas → «+ Nueva factura» con imagen | Aparece en Pendientes con su miniatura | RF-05 | Pendiente | |
| MAN-30 | Tesorería | Mover una factura con los botones y arrastrándola | Cambia de columna; el detalle muestra quién y cuándo | RF-05, RNF-06 | Pendiente | |
| MAN-31 | Cualquiera | Abrir una factura pagada | Sin botones de editar ni borrar; aviso de bloqueo | RF-05 | Pendiente | |
| MAN-32 | Monitor | Tablero en pantalla estrecha | Las columnas se apilan | RNF-02, RNF-06 | Pendiente | |
| MAN-33 | Administrador | Configuración → «Documentos base y plantillas»: subir la ficha sanitaria | Aparece «Plantilla actual» | RF-04, RF-07 | Pendiente | |
| MAN-34 | Tutor | Educandos → «Ver documentación»: descargar y subir la ficha sanitaria | Queda «pendiente de revisión»; el dashboard baja el contador | RF-04 | Pendiente | |
| MAN-35 | Monitor | Revisar ese documento y pedir una corrección | El tutor ve «A corregir» con la observación | RF-04 | Pendiente | |
| MAN-36 | Monitor | Inventario: crear Trastero › Zona A y Local, y material en ambos | Árbol y tabla con «Dónde está» | RF-06 | Pendiente | |
| MAN-37 | Monitor | Listas: arrastrar material a la derecha y usar «→» con el teclado | Pide la cantidad; calcula lo que hay que comprar | RF-06, RNF-06 | Pendiente | |
| MAN-38 | Monitor | «Documento para imprimir» | Material agrupado por ubicación y lista de compras | RF-06 | Pendiente | |
| MAN-39 | Monitor | Guardar como plantilla y «Usar para una salida» | Se crea la lista con el contenido de la plantilla | RF-06 | Pendiente | |
| MAN-40 | Sin sesión | Instalador en una base vacía: recorrer los 4 pasos con el modelo de Ingeriar | Se instala y se puede entrar con el administrador | RNF-05, RNF-06 | Pendiente | |
| MAN-41 | Sin sesión | Instalador: añadir una tabla con una clave foránea, descargar y volver a cargar el modelo JSON | La vista previa muestra la tabla y la clave | RNF-05 | Pendiente | |
| MAN-42 | Monitor | Plano del inventario: arrastrar una ficha a otra caja y elegir la cantidad | Se resta del origen y se suma en el destino | RF-06 | Pendiente | |
| MAN-43 | Monitor | Plano: arrastrar el título de una caja dentro de otra, y luego al fondo | Pasa a ser sububicación y luego ubicación principal | RF-06 | Pendiente | |
| MAN-44 | Monitor | Plano con teclado: botón «⇄» de una ficha | Pide destino y cantidad y mueve el material | RF-06, RNF-06 | Pendiente | |
| MAN-45 | Monitor | Lista: arrastrar fichas del plano a la derecha y una toma (⠿) de vuelta al plano | Las fichas muestran lo que queda; la toma se quita | RF-06 | Pendiente | |
