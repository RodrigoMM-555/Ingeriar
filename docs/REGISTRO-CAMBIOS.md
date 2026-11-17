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

## 2026-11-17 · E-05 · Facturas en tablero
- Rama / commit: `feat/E-05-facturas` (sale de `feat/E-13-roles-permisos`)
- Qué se ha hecho: nueva vista «Facturas» con tres columnas (Pendientes, Revisadas, Pagadas), tarjetas con miniatura,
  detalle con la imagen grande, formulario para subir y editar, y cambio de estado con botones o arrastrando. Nueva API
  `api/facturas.php` con todas las reglas en el servidor. Estados nuevos y registro de quién cambia el estado.
- Archivos creados o modificados:
  - Nuevos: `server/api/facturas.php`, `public/api/facturas.php`, `public/nucleo/facturas.js`,
    `server/data/migraciones/2026-11-facturas-tablero.sql`, `server/data/migraciones/2026-11-claves-foraneas.sql`,
    `server/pruebas/fotos-ejemplo.php`.
  - `server/api/superapi.php`: la tabla de facturas pasa a solo lectura; se quitan `guardarImagenFactura` y las reglas
    de facturas de las rutas genéricas; el borrado de datos vencidos borra asistencias y vínculos explícitamente.
  - `public/nucleo/utilidades.js`: la cola de miniaturas de la galería se mueve aquí para reutilizarla
    (`encolarMiniatura`); `galeria.js` la usa. `formulario.js` y `tabla.js`: se quita el código de facturas que ya no se usa.
  - `codigo.js`, `index.html`, `estilo.css` (sección «FACTURAS»); `schema.sql`, `demo.sql`, `modeloDeDatos.txt`;
    `server/pruebas/pruebas.php`; `noteas.md`, `docs/PLAN-PRUEBAS.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - Confirmado por el usuario: imagen obligatoria; una factura pagada no se edita ni se borra (409), ni siquiera el
    administrador; tesorería puede devolverla a revisada.
  - Monitor: edita o borra sus facturas solo en pendiente. `facturas.gestionar_todas` (administrador) edita las de
    cualquiera salvo pagadas y puede elegir el responsable.
  - API propia en lugar de las rutas genéricas: la imagen viaja en el mismo formulario y las reglas por estado no
    encajaban en la tabla genérica. Las rutas genéricas responden 400 para escribir facturas.
  - Migración de estados en tres pasos (ampliar ENUM, convertir, reducir) para que MySQL no rechace los valores.
    **En la base del usuario había 26 facturas `rechazada`** (datos de `demo.sql`), que pasan a `pendiente`.
  - `demo.sql`: las facturas se reparten entre monitores (antes había facturas de tutores) y usan categorías del catálogo.
  - **Fallo encontrado al probar con una copia de la base del usuario**: creada con el instalador antiguo, no tiene
    claves foráneas en las tablas originales, así que borrar no actúa en cascada. Se corrige de dos formas: el borrado de
    datos vencidos borra explícitamente lo dependiente, y `2026-11-claves-foraneas.sql` limpia huérfanos y crea las claves
    que falten (no cambia nada en una base creada con `schema.sql`).
- Requisitos que cubre (RF/RNF): RF-05, RF-01, RNF-03, RNF-06.
- Cómo probarlo (pasos):
  1. Ejecutar `2026-11-facturas-tablero.sql` y `2026-11-claves-foraneas.sql`; opcionalmente `php server/pruebas/fotos-ejemplo.php --confirmar`.
  2. Como monitor: Facturas → «+ Nueva factura» (sin imagen no deja guardar). Solo aparecen sus facturas.
  3. Como monitor con tesorería o administrador: mover facturas entre columnas; abrir una pagada: no se puede editar.
  4. `php server/pruebas/pruebas.php <url> --confirmar` → 79 de 79.
  Probado: 79 de 79 con `schema.sql` nuevo y en una copia de la base del usuario con todas las migraciones aplicadas.
- Impacto en la memoria (apartados que habría que revisar): 2.5, 2.6 (RF-05), 3.1, 3.4, 3.9 (indicados en el encargo);
  modelo de datos (estados de `facturas`, `estado_cambiado_por`, `estado_cambiado_en`); integridad referencial en bases antiguas.
- Pendiente o problemas:
  - El tablero no se ha visto en un navegador desde aquí (pruebas manuales MAN-29 a MAN-32).
  - Las fechas de la factura se guardan como fecha (la hora queda a 00:00).

## 2026-11-17 · E-13 · Roles y permisos (RBAC)
- Rama / commit: `feat/E-13-roles-permisos` (sale de `main` en `ae37f8f` más el commit de los encargos nuevos)
- Qué se ha hecho: los permisos pasan de estar escritos por rol en cada ruta a una tabla de permisos.
  Cada usuario tiene un cargo básico (administrador, monitor o tutor) y puede sumar cargos adicionales (tesorería
  y los que se creen). Todas las comprobaciones usan `tienePermiso()`. En Configuración hay una tabla para editar
  los permisos de cada cargo y crear cargos nuevos. El formulario de usuario permite elegir los cargos.
- Archivos creados o modificados:
  - Nuevos: `server/auth/permisos.php`, `server/data/semillas/roles-permisos.sql`,
    `server/data/migraciones/2026-11-roles.sql` y `2026-11-roles-retirar-columna.sql`.
  - Servidor: `superapi.php` (mapa `PERMISOS_TABLAS`, dashboard por permisos, ruta `roles`, cargos de usuarios,
    facturas propias, reglas contra el bloqueo), `login.php`, `archivo.php`, `galeria.php`, `salida.php`,
    `instalar.php`, `sesion.php` (se elimina `exigirRol`).
  - Datos: `schema.sql` (tablas nuevas, sin `usuarios.rol`), `demo.sql` (cargos en `usuarios_roles`; el usuario 2 es
    monitor con tesorería), `modeloDeDatos.txt`, `config.php` (sin `nombre_rol_*`).
  - Cliente: `utilidades.js` (`tienePermiso`, `nombreCargos`), `codigo.js`, `configuracion.js` (cargos y permisos),
    `formulario.js` (cargos del usuario y filtros de responsables), `tabla.js`, `calendario.js`, `dashboard.js`, `estilo.css`.
  - Pruebas y documentación: `server/pruebas/pruebas.php` (20 pruebas nuevas), `docs/PLAN-PRUEBAS.md`, `noteas.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - Decisiones del usuario: cargos básicos excluyentes y cargos adicionales combinables con cualquiera (también con tutor)
    sin repetir permisos; el monitor gestiona educandos (alta, edición y baja) pero no asistencias, y solo el administrador
    borra educandos; el monitor ve solo sus facturas y tesorería las ve todas y cambia estados.
  - Permisos recargados de la base de datos en cada petición (una consulta) y guardados en la sesión: los cambios y las
    bajas de usuarios tienen efecto inmediato. Antes, un usuario borrado conservaba su sesión.
  - **Precisión sobre el encargo**: el administrador tiene todos los permisos **salvo** `educandos.ver_propios` y
    `asistencia.responder_propios`, que describen la relación de un tutor con «sus» educandos; con ellos el administrador
    vería el panel y el calendario de tutor.
  - Permisos añadidos a la lista del encargo, necesarios para conservar el comportamiento: `usuarios.ver`,
    `educandos.ver_todos`, `educandos.borrar`, `asistencia.gestionar`, `facturas.gestionar_todas` y `registro.ver`.
  - Contra el bloqueo: los permisos del administrador no se pueden editar, los cargos básicos no se desactivan y cualquier
    cambio de usuarios, cargos o conservación que dejaría a nadie con `roles.gestionar` se deshace (transacción).
  - Migración en dos pasos: el código funciona con y sin la columna `usuarios.rol` (mientras existe, se mantiene igual al
    cargo básico), así que se puede comprobar todo antes de borrarla.
  - Parte de E-05 adelantada en el servidor (facturas propias y quién cambia estados), por decisión del usuario.
  - Dashboard: el administrador ve ahora también el desglose del sábado (tiene `asistencia.ver_todas`); el monitor con
    tesorería ve el gasto total.
- Requisitos que cubre (RF/RNF): RF-01, RF-02, RF-05, RNF-03, RNF-05.
- Cómo probarlo (pasos):
  1. En la base existente: `2026-11-roles.sql`. Entrar con cada tipo de usuario: todo debe funcionar como antes, salvo
     que el monitor ya solo ve sus facturas y no tiene botón Borrar en educandos.
  2. `php server/pruebas/pruebas.php <url> --confirmar` → 69 de 69. Después, `2026-11-roles-retirar-columna.sql` y repetir.
  3. Configuración → «Cargos y permisos»: crear un cargo, darle permisos y asignárselo a un usuario.
  4. Pruebas manuales MAN-24 a MAN-28 de `docs/PLAN-PRUEBAS.md`.
  Probado: 69 de 69 en las tres bases (esquema anterior más migración 1, tras la migración 2, y `schema.sql` nuevo); el
  dashboard de cada tipo de usuario de `demo.sql`; instalación desde cero con el instalador y primer administrador.
- Impacto en la memoria (apartados que habría que revisar): 2.5, 2.6 (RF-01), 3.1, 3.4, 3.9 (indicados en el encargo);
  modelo de datos (cuatro tablas nuevas, sin `usuarios.rol`); descripción de roles; RF-05 (facturas propias).
- Pendiente o problemas:
  - La interfaz nueva (tabla de cargos, control de cargos del usuario) no se ha visto en un navegador desde aquí.
  - Un cargo adicional desactivado no aparece en el formulario de usuario: si se guarda el usuario, ese cargo se le quita.
  - La prueba del bloqueo por quedarse sin administradores es manual (MAN-26): con `demo.sql` hay diez administradores.

## 2026-11-17 · E-10 · Reversión: contraseña de desarrollo en el código
- Rama / commit: `feat/E-06-conservacion` (commit propio después del de E-06)
- Qué se ha hecho: `database.php` vuelve a usar la contraseña de desarrollo escrita en el código cuando Apache no define
  `INGERIAR_DB_PASSWORD`. Si la variable existe, tiene prioridad.
- Archivos creados o modificados: `server/data/database.php`, `noteas.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo: decisión del usuario para seguir entrando en su instalación local sin configurar
  Apache por ahora. Contradice la regla de `CLAUDE.md` de no subir credenciales; se avisó antes de hacerlo. La contraseña
  ya estaba en el historial público, así que no se expone nada nuevo.
- Requisitos que cubre (RF/RNF): ninguno; deja RNF-03 como estaba antes de E-10.
- Cómo probarlo (pasos): sin `SetEnv INGERIAR_DB_PASSWORD`, el login vuelve a funcionar.
- Impacto en la memoria (apartados que habría que revisar): si la memoria ya describía E-10, indicar que está pendiente.
- Pendiente o problemas: retomar E-10 (variable de entorno y contraseña nueva). Además, en la instalación local Apache
  sirve todo `/var/www/html` con `AllowOverride None`, así que `server/` y `storage/` de la copia son accesibles desde
  el navegador (comprobado). Hay que publicar solo `public/` antes de usar datos reales.

## 2026-11-17 · E-06 · Bajas, conservación y registro de accesos
- Rama / commit: `feat/E-06-conservacion` (sale de `feat/E-07-pruebas`)
- Qué se ha hecho: los educandos tienen `fecha_baja`; al darlos de baja salen de las vistas activas pero se conservan.
  Pasado el plazo de conservación (configurable, 5 años), el administrador los elimina definitivamente desde
  Configuración → «Datos vencidos», junto con sus archivos y, si lo marca, los tutores que se quedan sin educandos.
  Los accesos a documentación sensible quedan en `registro_accesos`, que no se puede modificar.
- Archivos creados o modificados:
  - Nuevos: `server/auth/registro.php`, `server/data/migraciones/2026-11-bajas-accesos.sql`.
  - `server/api/superapi.php`: filtro de bajas (dashboard, cumpleaños, asistencias, tabla del tutor, `tutorTieneEducando`),
    ruta `conservacion`, registro no modificable, plazo en la configuración.
  - `server/api/archivo.php`: tutor solo con educandos activos; registro de ver, previsualizar y subir entregas;
    función `responder()` (sin ella, una petición sin sesión acababa en error fatal en lugar de 401).
  - `server/servicios/salida.php`: registra la descarga de la copia. `server/api/galeria.php`: bajas según la fecha del evento.
  - `public/nucleo/configuracion.js` (plazo y bloque «Datos vencidos»), `public/nucleo/tabla.js` y `public/estilo.css` (filas de baja).
  - `server/data/schema.sql`, `modeloDeDatos.txt`, `config.php`; `server/pruebas/pruebas.php` (12 pruebas nuevas);
    `noteas.md`, `docs/PLAN-PRUEBAS.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - **Cambio de requisito acordado con el usuario**: el encargo decía «se eliminan los archivos»; ahora se elimina
    también el registro del educando al vencer el plazo (principio de limitación del plazo de conservación del RGPD),
    y se conserva mientras tanto (historial, posibles reclamaciones). El texto de E-06 en `ENCARGOS.md` está actualizado.
  - Borrado en dos pasos: lista previa y confirmación. El servidor recalcula lo vencido y descarta cualquier id que no lo esté.
    La base de datos se borra en una transacción y los archivos después del `commit`.
  - Tutores sin educandos: no se borran solos; el administrador los marca (decisión propuesta y aceptada).
  - El registro guarda identificadores, no nombres, para no conservar datos del educando tras borrarlo.
    No registra la galería (cada miniatura sería un acceso). Si falta la tabla, el acceso no se bloquea (se anota en el log de PHP).
- Requisitos que cubre (RF/RNF): RF-02, RF-04, RNF-03.
- Cómo probarlo (pasos):
  1. Ejecutar `server/data/migraciones/2026-11-bajas-accesos.sql` (o recargar `schema.sql`).
  2. Poner `fecha_baja` de hoy a un educando de un tutor: el tutor deja de verlo; el administrador lo ve en cursiva al final.
  3. Ponerle `fecha_baja` de hace 6 años: aparece en Configuración → «Datos vencidos»; al eliminar desaparecen él y sus entregas.
  4. Ver una entrega y abrir la tabla `registro_accesos`: aparece la fila; intentar borrarla → error.
  5. `php server/pruebas/pruebas.php <url> --confirmar`: 49 de 49 superadas (MySQL 8.4 y servidor integrado de PHP).
- Impacto en la memoria (apartados que habría que revisar): requisitos (texto de RF-02/RF-04 sobre conservación),
  modelo de datos (`fecha_baja`, `registro_accesos`), seguridad y protección de datos (RNF-03), plan de pruebas (3.9, 5.x).
- Pendiente o problemas:
  - El registro de accesos crece sin límite; podría necesitar su propio plazo de conservación.
  - No hay ningún aviso automático de que hay datos vencidos: hay que entrar en Configuración.
  - La interfaz nueva (bloque «Datos vencidos», filas de baja) no se ha visto en un navegador desde aquí.

## 2026-11-16 · E-07 · Plan de pruebas
- Rama / commit: `feat/E-07-pruebas` (sale de `feat/E-03-galeria`)
- Qué se ha hecho: `docs/PLAN-PRUEBAS.md` con 37 pruebas automáticas (autenticación, contraseñas, permisos por rol,
  copia de seguridad, instalador y galería), cada una con su requisito, resultado y fecha, y 20 pruebas manuales de
  la interfaz para marcar a mano. El script `server/pruebas/pruebas.php` repite las automáticas por HTTP.
- Archivos creados o modificados: nuevos `docs/PLAN-PRUEBAS.md` y `server/pruebas/pruebas.php`; `noteas.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - Script PHP de línea de comandos en lugar de una página web: no queda expuesto en el servidor
    (está en `server/`, fuera del `DocumentRoot`, y además rechaza ejecutarse fuera de la terminal).
  - Prueba la aplicación de verdad por HTTP con cURL (una sesión por rol), no las funciones sueltas, para comprobar
    que los permisos se aplican en el servidor tal como los recibe un navegador.
  - Crea sus propios usuarios, educando y eventos y los borra al terminar (también si falla), así no depende de
    `demo.sql` ni deja datos. Exige `--confirmar` porque escribe en la base de datos.
  - El PNG de prueba se genera con `gzcompress` y `crc32`, sin la extensión gd.
- Requisitos que cubre (RF/RNF): RF-01 a RF-05 y RF-07 en las pruebas; RNF-03, RNF-04 y RNF-05.
- Cómo probarlo (pasos): ver «Cómo ejecutar las pruebas automáticas» en `docs/PLAN-PRUEBAS.md`.
  Resultado de hoy (MySQL 8.4 y servidor integrado de PHP): 37 de 37 superadas, sin datos residuales.
- Impacto en la memoria (apartados que habría que revisar): 3.9, 5.1, 5.2, 5.7 (indicados en el encargo).
- Pendiente o problemas:
  - Las pruebas manuales están en «Pendiente»: las tiene que hacer el usuario en el navegador.
  - No cubre aún E-02, E-04, E-05 ni E-06; habrá que añadir sus pruebas al terminarlos.
  - Las pruebas se han ejecutado con el servidor integrado de PHP, no con Apache: MAN-19 comprueba en Apache que `storage/` y `server/` no son accesibles.

## 2026-11-16 · E-03 · Galería de fotografías
- Rama / commit: `feat/E-03-galeria` (sale de `feat/E-09-instalador`)
- Qué se ha hecho: nueva vista «Galería» para todos los roles. Las fotos se asocian a un evento y se navegan
  por carpetas ronda → tipo de evento → evento → fotos, con miniaturas generadas por el Web Worker existente
  y un visor. Administrador y scouter suben (varias a la vez) y borran; el tutor solo ve las fotos de los
  eventos que convocan a las secciones de sus educandos. Las fotos solo se sirven a través de PHP.
- Archivos creados o modificados:
  - Nuevos: `server/api/galeria.php`, `public/api/galeria.php`, `public/nucleo/galeria.js`,
    `server/data/migraciones/2026-11-galeria.sql`, `.gitignore`.
  - `server/data/schema.sql` y `server/data/modeloDeDatos.txt`: tabla `galeria_fotos`.
  - `server/api/superapi.php`: `galeria_fotos` no aparece como entidad; al borrar un evento se borran sus archivos.
  - `public/codigo.js`, `public/index.html`: enlace «Galería». `public/estilo.css`: sección «GALERÍA».
  - `noteas.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - Decisión del usuario: agrupar por ronda y tipo de evento. Las carpetas no se guardan; se calculan con la fecha
    (`rondaActual`, de septiembre a agosto) y el tipo del evento. Así los tipos nuevos crean su carpeta solos y
    las fotos se recolocan si se corrige un evento. Los tipos antiguos fuera del catálogo tienen su propia carpeta.
  - Archivos en `storage/galeria/` con nombre aleatorio y sin subcarpetas: el nombre original solo se guarda en la base de datos.
  - Visibilidad del tutor: sección de cada educando calculada con su edad en la fecha del evento
    (`seccionDeEducandoConCatalogo`), para que las fotos de rondas pasadas sigan correspondiendo a la sección de entonces.
    Un tutor sin educandos con sección no ve fotos. La misma regla se aplica al listar y al servir cada foto.
  - Tamaño máximo tomado de `tamano_maximo_archivos_mb` de la configuración; formatos JPEG, PNG y WebP comprobados
    con `finfo`; máximo 20 fotos por subida; si una no es válida, no se guarda ninguna.
  - Miniaturas con `documento-preview-worker.js` sin modificarlo, con una cola de 4 trabajadores simultáneos.
  - `.gitignore` nuevo: excluye fotos, facturas, entregas y logo subidos, para que nunca lleguen al repositorio público (CLAUDE.md).
- Requisitos que cubre (RF/RNF): RF-04, RNF-03 (y RNF-06 en el visor accesible con teclado).
- Cómo probarlo (pasos):
  1. Ejecutar `server/data/migraciones/2026-11-galeria.sql` (o recargar `schema.sql`).
  2. Como scouter: Galería → «Subir fotos» → elegir un evento y varias imágenes. Aparecen en «Ronda … / <tipo> / <evento>».
  3. Intentar subir un archivo de texto renombrado a `.jpg` → «no es una imagen JPEG, PNG o WebP».
  4. Como tutor: solo aparecen eventos que convocan a las secciones de sus educandos; no hay botón de subir.
     Con la sesión del tutor, abrir `api/galeria.php?accion=ver&id=<foto de otro evento>` → 403.
  5. Abrir una foto, pasar con las flechas, descargarla y (como scouter) borrarla.
  6. Pedir `/storage/galeria/<archivo>` desde el navegador → Apache lo deniega.
  Probado contra MySQL 8.4 con el servidor de PHP: subida (tutor 403, imagen falsa 415, evento inexistente 400,
  sin fotos 400, válidas 200), listado por rol, `ver` (sin sesión 401, tutor visible 200, tutor no visible 403,
  descarga como adjunto), borrado (tutor 403, scouter 200 y archivo eliminado) y borrado de evento con sus archivos.
- Impacto en la memoria (apartados que habría que revisar): 3.1, 3.4 (indicados en el encargo); diseño de la base de datos
  (tabla `galeria_fotos`); interfaz (nueva vista); instalación (migración, permisos de `storage/galeria/`, límites de subida de `php.ini`).
- Pendiente o problemas:
  - La interfaz (`galeria.js` y los estilos) no se ha probado en un navegador desde aquí: solo se ha comprobado la sintaxis.
    Hay que revisarla a mano, en especial el visor y el modo oscuro.
  - Con el servidor de PHP de pruebas, una ruta inexistente devuelve `index.html`; la protección de `storage/` depende
    de Apache (fuera del `DocumentRoot` y `Require all denied`).
  - Sin miniaturas en el servidor: cada miniatura descarga la foto completa. Con muchas fotos grandes podría ser lento (RNF-07).

## 2026-11-16 · E-09 · Proteger el instalador
- Rama / commit: `feat/E-09-instalador` (sale de `feat/E-08-salida`)
- Qué se ha hecho: el instalador solo funciona sin sesión en una instalación nueva (no existe la base de datos,
  no existe la tabla `usuarios` o está vacía). Si la aplicación ya está en uso, responde 403 sin tocar la base de
  datos, salvo a un administrador con la sesión iniciada. Al probarlo contra MySQL aparecieron dos fallos previos
  que impedían instalar `modeloDeDatos.txt`, y se han corregido.
- Archivos creados o modificados: `server/api/instalar.php`, `public/instalador.html`, `server/data/modeloDeDatos.txt`,
  `noteas.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - Se usa «hay usuarios» como señal de instalación en uso: no necesita archivos de marca y coincide con la regla
    del administrador inicial de E-01. La comprobación se hace antes de leer el modelo.
  - La página `instalador.html` sigue visible (es HTML estático), pero no sirve de nada sin permiso: el control está en el servidor.
  - Fallo previo 1: el instalador añadía siempre `id … PRIMARY KEY`, lo que chocaba con los catálogos (`codigo` es la
    clave) y con `eventos_secciones` (clave compuesta). Ahora solo añade `id` si el modelo no declara clave primaria.
  - Fallo previo 2: en `modeloDeDatos.txt`, `DEFAULT 'pendiente'` estaba en una línea aparte y se leía como nombre de tabla.
- Requisitos que cubre (RF/RNF): RNF-03, RNF-05.
- Cómo probarlo (pasos):
  1. Base de datos vacía: instalar con `modeloDeDatos.txt` → «Base de datos MySQL creada correctamente» (10 tablas).
  2. Repetir rellenando el administrador con `abcdefgh` → error de contraseña; con `scouts2026` → «Se ha creado el administrador».
  3. Sin sesión, volver a enviar el instalador → 403 «La aplicación ya está instalada…».
  4. Iniciar sesión como administrador y enviarlo → funciona y dice «Ya existen usuarios: no se ha creado el administrador».
  Probado así contra un MySQL 8.4 de pruebas con el servidor de PHP: los cuatro pasos dan el resultado esperado.
- Impacto en la memoria (apartados que habría que revisar): instalación y despliegue, seguridad (RNF-03).
- Pendiente o problemas (detectados, sin tocar):
  - El instalador no crea las claves foráneas: MySQL ignora `REFERENCES` escrito dentro de una columna. Con `schema.sql` sí se crean.
  - El instalador no inserta los valores iniciales de `tipos_evento`, `secciones` y `categorias_factura`; tras instalar
    con él, los catálogos quedan vacíos hasta rellenarlos desde la configuración. `modeloDeDatos.txt` también
    define `facturas.categoria` como `ENUM`, mientras que `schema.sql` usa `VARCHAR` con catálogo.

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
