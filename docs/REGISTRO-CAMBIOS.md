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

## 2026-11-17 · Dashboard: asistencias del sábado en un único anillo doble
- Rama / commit: `feat/dashboard`
- Qué se ha hecho: a petición del usuario (con cinco gráficas no se veía bien), las asistencias del sábado se muestran en un
  solo círculo: anillo interior con una porción por sección (proporcional a sus educandos convocados) y anillo exterior con
  los tramos de cada sección (asiste y entregó, asiste sin circular, sin responder, no asiste) en los tonos de su color.
  Al lado, una tabla con las cifras por sección y tramo y el total. Al pasar el ratón se ve la cifra y el porcentaje.
- Archivos creados o modificados: `public/nucleo/graficas.js` (nuevo `crearAnilloAgrupado`), `public/nucleo/dashboard.js`
  (`renderAsistenciasSabado`), `public/estilo.css` (`.anillo-agrupado`, `.tabla-grafica-agrupada`, `.muestra-color`;
  fuera `.graficas-secciones`), versión de los scripts, `noteas.md`.
- Decisiones técnicas y motivo: anillo doble (tipo «sunburst») para ver a la vez el peso de cada sección y su desglose;
  la tabla evita tener que adivinar cifras pequeñas. La tabla no usa el estilo general (cabecera de color) para no
  recargar el bloque. Comprobado con capturas de Firefox sin ventana.
- Requisitos que cubre (RF/RNF): RF-03, RF-07, RNF-06.
- Cómo probarlo (pasos): entrar como monitor y pasar el ratón por el anillo.
- Impacto en la memoria (apartados que habría que revisar): RF-07 (dashboard), gráficas SVG.
- Pendiente o problemas: ninguno conocido.

## 2026-11-17 · Dashboard: solo las gráficas del sábado, más grandes
- Rama / commit: `feat/dashboard`
- Qué se ha hecho: a petición del usuario, se quitan las demás gráficas del dashboard («Próximos eventos por tipo»,
  «Usuarios por cargo» y «Estado de próximas asistencias» del tutor) y las gráficas del sábado se colocan en una
  cuadrícula de dos columnas (una en pantallas de menos de 900 px) para que se vean más grandes.
- Archivos creados o modificados: `server/api/superapi.php` (ya no calcula ni envía `graficas`), `public/nucleo/dashboard.js`
  (sin `renderGraficas`), `public/estilo.css` (`.graficas-secciones` en dos columnas; fuera `.dashboard-graficas`),
  versión de los scripts, `noteas.md`.
- Requisitos que cubre (RF/RNF): RF-07, RNF-06.
- Cómo probarlo (pasos): entrar como monitor: solo aparecen las gráficas del sábado, dos por fila.
- Impacto en la memoria (apartados que habría que revisar): RF-07 (dashboard).
- Pendiente o problemas: con cinco secciones la última queda sola en su fila.

## 2026-11-17 · Dashboard: asistencias del sábado en gráficas por sección
- Rama / commit: `feat/dashboard`
- Qué se ha hecho: a petición del usuario, la tabla de asistencias del próximo sábado se sustituye por una gráfica de anillo
  por sección. Cada gráfica usa los tonos del color de su sección, de claro a oscuro: asiste y ha entregado la circular,
  asiste sin entregarla, no ha respondido y no asiste. Solo para el próximo evento de tipo sábado.
- Archivos creados o modificados: `server/api/superapi.php` (cuatro grupos por sección y si el sábado lleva circular),
  `public/nucleo/graficas.js` (parámetro opcional `colores`), `public/nucleo/dashboard.js` (`renderAsistenciasSabado`,
  `tonosSeccion`, `mezclarColor`, `ordenarSecciones`), `public/estilo.css`, versión de los scripts, `noteas.md`.
- Decisiones técnicas y motivo:
  - Los colores de sección en el CSS son pastel (fondos de tabla); para las gráficas se usa el mismo matiz más intenso y
    se sacan cuatro tonos mezclando con blanco y negro. Una sección nueva del catálogo usa el color del grupo.
  - Sin circular, «asiste y entregó» y «asiste sin circular» se juntan en «Asiste» (tres tramos).
  - Se comprobó el aspecto con una página de prueba y una captura de Firefox sin ventana.
- Requisitos que cubre (RF/RNF): RF-03, RF-07.
- Cómo probarlo (pasos): entrar como monitor o administrador: aparecen las gráficas de las secciones convocadas al próximo sábado.
- Impacto en la memoria (apartados que habría que revisar): RF-07 (dashboard), gráficas SVG con paleta por sección.
- Pendiente o problemas: los colores base de las secciones están en `dashboard.js`; si se cambian en el CSS hay que cambiarlos también aquí.

## 2026-11-17 · Dashboard: bloque «Próximas actividades»
- Rama / commit: `feat/dashboard`
- Qué se ha hecho: a petición del usuario, el bloque `dashboard-resumen` muestra el próximo sábado, la próxima acampada
  (con cuánto falta), el próximo evento que no es sábado ni acampada y los cumpleaños de la semana contada de domingo a
  sábado (nombre, edad que cumple y día). Antes: próximo evento, número de sábados del mes, próxima acampada y cumpleaños
  de los próximos siete días.
- Archivos creados o modificados: `server/api/superapi.php` (datos del resumen), `public/nucleo/dashboard.js`
  (`renderResumen`, `formatearDiaMes`), versión de los scripts en las páginas, `noteas.md`.
- Decisiones técnicas y motivo: el tutor solo ve los cumpleaños de sus educandos (antes veía los de todo el grupo);
  coherente con la indicación del usuario de que al tutor solo le aparezca lo de sus educandos.
- Requisitos que cubre (RF/RNF): RF-07, RNF-03.
- Cómo probarlo (pasos): entrar como monitor y como tutor y comparar los cumpleaños y los próximos eventos.
- Impacto en la memoria (apartados que habría que revisar): RF-07 (contenido del dashboard).
- Pendiente o problemas: los cumpleaños son solo de educandos (los usuarios no tienen fecha de nacimiento).

## 2026-11-17 · Dashboard sin indicadores
- Rama / commit: `feat/dashboard`
- Qué se ha hecho: a petición del usuario, se quita la fila de indicadores del dashboard (`article.indicador`: usuarios,
  educandos, eventos próximos, documentos, gasto, mis facturas, asistencias…). El resto del dashboard (próximas actividades,
  tabla del sábado, panel del tutor y gráficas) queda como estaba.
- Archivos creados o modificados: `server/api/superapi.php` (ya no calcula ni envía `indicadores`; se quitan las consultas
  que solo servían para ellos), `public/nucleo/dashboard.js` (sin `renderIndicadores`), `public/estilo.css` (sin
  `.indicador` ni `.dashboard-indicadores`), versión de los scripts en las páginas, `noteas.md`.
- Requisitos que cubre (RF/RNF): RF-07.
- Cómo probarlo (pasos): entrar con cualquier cargo: el dashboard empieza por «Próximas actividades».
- Impacto en la memoria (apartados que habría que revisar): RF-07 (contenido del dashboard).
- Pendiente o problemas: el usuario seguirá revisando el dashboard.

## 2026-11-17 · Revisión del dashboard (deshecha) y versión en los scripts
- Rama / commit: `feat/dashboard`
- Qué se ha hecho: se implementó una revisión del dashboard propuesta por el usuario (próximas actividades por categorías,
  sábado por sección, cumpleaños, facturas para tesorería y bloque del tutor con calendario trimestral). Al verla, el
  usuario pidió deshacerla para replantearla: se ha revertido con `git revert` y **el dashboard vuelve a ser el anterior**.
  No hay que documentar esa revisión en la memoria.
- Se mantiene: las páginas cargan los scripts y `estilo.css` con `?v=<versión>` (hay que cambiarla al modificar JS o CSS),
  porque el navegador del usuario mezcló un `codigo.js` nuevo con un `dashboard.js` antiguo de la caché.
- Archivos creados o modificados (netos): `public/index.html`, `public/login.html`, `public/instalador.html`, `noteas.md`.
  En la base local del usuario se quitó la columna `usuarios.fecha_nacimiento` que había añadido la revisión.
- Requisitos que cubre (RF/RNF): RNF-02, RNF-05.
- Impacto en la memoria (apartados que habría que revisar): ninguno por el dashboard; despliegue (versión de los scripts).
- Pendiente o problemas: replantear el dashboard con el usuario.

## 2026-11-17 · E-11 · Instalador interactivo
- Rama / commit: `feat/E-11-instalador` (sale de `feat/E-12-copias`)
- Qué se ha hecho: `instalador.html` pasa a ser un asistente en cuatro pasos (base de datos, tablas, datos iniciales y
  administrador, revisión del SQL e instalación). Las tablas se editan sobre un modelo JSON (tipos, longitudes, ENUM, nulos,
  valores por defecto, claves, AUTO_INCREMENT, únicos, índices y claves foráneas con ON DELETE / ON UPDATE), que se puede
  descargar y volver a cargar, o se sube un `schema.sql` validado. Sustituye al formato de texto `modeloDeDatos.txt`.
- Archivos creados o modificados:
  - Nuevos: `server/instalacion/ModeloDatos.php`, `server/data/modelo.json`, `server/herramientas/generar-modelo.php`,
    `server/data/semillas/catalogos.sql`, `public/instalador.js`, `server/pruebas/pruebas-instalador.php`.
  - `server/api/instalar.php` (reescrito como API JSON), `public/instalador.html`, `public/estilo.css` (sección «INSTALADOR»),
    `server/pruebas/pruebas.php` (INS-01 e INS-02 con la API nueva), `noteas.md`, `docs/PLAN-PRUEBAS.md`, `docs/ENCARGOS.md`.
  - Eliminado: `server/data/modeloDeDatos.txt`.
- Decisiones técnicas y motivo:
  - El SQL lo genera el servidor a partir del modelo validado (nombres con comillas invertidas, valores con `PDO::quote`):
    el cliente nunca envía SQL en el modo asistente. Las claves foráneas se crean al final con ALTER TABLE.
  - `modelo.json` se genera automáticamente desde una base creada con `schema.sql` (`generar-modelo.php`), en lugar de
    escribirlo a mano, para que el asistente y `schema.sql` no se desincronicen. La prueba INI-05 lo comprueba.
  - Se corrige lo detectado en E-09: el instalador ya crea las claves foráneas reales, carga los catálogos iniciales y
    `facturas.categoria` coincide con `schema.sql` (el modelo sale de él).
  - Subida de `schema.sql`: máximo 1 MB, UTF-8; se divide respetando cadenas y comentarios y se valida entero antes de
    ejecutar. Se admiten CREATE TABLE, INSERT (también INSERT … SELECT) y ALTER TABLE … ADD; se rechazan DROP, GRANT,
    REVOKE, LOAD, INTO OUTFILE/DUMPFILE, DELETE, UPDATE, TRUNCATE, procedimientos, etc. CREATE DATABASE y USE se ignoran
    para instalar siempre en la base configurada.
  - Transacciones: MySQL confirma cada CREATE TABLE y ALTER TABLE al momento (no se pueden deshacer); por eso se valida todo
    antes y, si una sentencia falla, se indica cuál. Los datos iniciales y el administrador sí van en una transacción.
  - Se mantiene el bloqueo de E-09 y se añade el token opcional `INGERIAR_TOKEN_INSTALACION`.
  - La contraseña de MySQL sigue viniendo de la configuración del servidor (E-10 sigue revertido provisionalmente).
- Requisitos que cubre (RF/RNF): RNF-05, RNF-06, RNF-03.
- Cómo probarlo (pasos):
  1. Crear una base vacía, apuntar a ella con `INGERIAR_DB_NAME` y abrir `instalador.html`: recorrer los cuatro pasos.
  2. Probar el modo «Subir schema.sql» con `server/data/schema.sql` y con un archivo que tenga `DROP TABLE` (se rechaza).
  3. `INGERIAR_DB_NAME=<base vacía> php server/pruebas/pruebas-instalador.php <url> --confirmar` → 7 de 7.
  Probado: 7 de 7 (incluida la equivalencia asistente = schema.sql) y 111 de 111 en la batería general.
- Impacto en la memoria (apartados que habría que revisar): 3.1, 3.5, 4.4 (indicados en el encargo); instalación y despliegue.
- Pendiente o problemas: la interfaz del asistente no se ha visto en un navegador desde aquí (MAN-40 y MAN-41). En la instalación
  local del usuario la aplicación ya está en uso, así que el instalador solo funciona con la sesión de administrador.

## 2026-11-17 · E-12 · Copia de seguridad de la base de datos y de storage
- Rama / commit: `feat/E-12-copias` (sale de `feat/E-04-inventario`)
- Qué se ha hecho: `server/copias/copia.php` (para cron) genera un zip con el volcado de MySQL y toda la carpeta `storage/`,
  con cifrado AES-256 opcional, permisos 0600, retención de las últimas N copias, bloqueo y registro de cada ejecución.
  `server/copias/restaurar.php` restaura una copia en una base vacía. En Configuración, «Copias de seguridad» lista y
  descarga las copias (con registro de accesos); la exportación JSON de `salida.php` se mantiene aparte.
- Archivos creados o modificados:
  - Nuevos: `server/copias/comun-copias.php`, `copia.php`, `restaurar.php`; `server/api/copias.php`, `public/api/copias.php`;
    `server/pruebas/pruebas-copias.php`; `copias/.htaccess`.
  - `public/nucleo/configuracion.js` y `public/estilo.css` (bloque de copias), `.gitignore` (`copias/*`),
    `server/pruebas/pruebas.php` (4 pruebas de la API), `noteas.md`, `docs/PLAN-PRUEBAS.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - **Diferencia con el encargo (aviso)**: el encargo pide cifrar el zip con AES. El cifrado AES nativo del formato zip
    requiere la extensión `php-zip` (ZipArchive), que no está instalada en el equipo del usuario. Se hace así: el zip se crea
    con `PharData` (incluido en PHP; se abre con cualquier programa) y, si hay clave, el zip completo se cifra con
    **AES-256-CBC por bloques de 1 MB + HMAC-SHA256** (cifrar y después autenticar) con claves derivadas por PBKDF2
    (200 000 vueltas). Resultado: `.zip.cifrado`, que se abre con `restaurar.php`. Si se instala `php-zip`, se podría
    cambiar a zip con AES nativo.
  - `mysqldump` y `mysql` se lanzan con `proc_open` sin pasar por la shell, y la contraseña va en un fichero de opciones
    temporal con permisos 0600 (no en la línea de órdenes).
  - Destino por defecto `copias/` en la raíz (fuera de `public/`, con `Require all denied` y en `.gitignore`), configurable con
    `INGERIAR_COPIAS_DESTINO`. Cron con el mismo usuario que Apache para que la web pueda leer copias con permisos 0600.
  - `restaurar.php` no restaura sobre una base con tablas ni sobre un `storage/` con archivos salvo `--sobrescribir`;
    con una clave incorrecta no toca nada (el HMAC se comprueba antes de descifrar).
  - La copia externa (Google Drive) queda como paso manual documentado en `noteas.md`.
- Requisitos que cubre (RF/RNF): RNF-04, RNF-03.
- Cómo probarlo (pasos):
  1. `php server/copias/copia.php` → aparece la copia en `copias/` y una línea en `copias/registro-copias.log`.
  2. Con `INGERIAR_COPIAS_CLAVE=...` → `.zip.cifrado`.
  3. Crear una base vacía y `php server/copias/restaurar.php <copia> --base=<base> --storage=/tmp/prueba --confirmar`.
  4. Configuración → «Copias de seguridad»: lista y descarga.
  5. `php server/pruebas/pruebas-copias.php --base-vacia=<base> --confirmar` → 8 de 8; `pruebas.php` → 111 de 111.
- Impacto en la memoria (apartados que habría que revisar): 2.6 (RNF-04), 3.5, 3.6, 4.4, 4.5 (indicados en el encargo);
  explicar el cifrado elegido y por qué no es el AES nativo del zip.
- Pendiente o problemas: programar la tarea con cron en el servidor del grupo y decidir la copia externa. En la instalación
  local del usuario el cron no está configurado (lo tiene que hacer con sudo).

## 2026-11-17 · E-04 · Inventario en JSON y listas de material
- Rama / commit: `feat/E-04-inventario` (sale de `feat/E-02-clases-dominio`)
- Qué se ha hecho: nueva vista «Inventario» (administrador y monitores) con dos pestañas. «Material y ubicaciones»: árbol
  libre de ubicaciones y materiales con su cantidad en cada una, buscador y filtro por ubicación. «Listas de material»: a la
  izquierda lo que hay y dónde, a la derecha lo que se lleva; se arrastra o se usa «→»; el monitor elige de qué ubicación coge
  cada cantidad; se calcula lo que hay que comprar; documento para imprimir; plantillas reutilizables con versiones.
- Archivos creados o modificados:
  - Nuevos: `server/dominio/AlmacenJson.php`, `server/api/inventario.php`, `public/api/inventario.php`, `public/nucleo/inventario.js`,
    `server/data/migraciones/2026-11-inventario.sql`, `storage/inventario/.gitkeep`.
  - `codigo.js`, `index.html`, `estilo.css` (sección «INVENTARIO»), `schema.sql` y `semillas/roles-permisos.sql`
    (permiso `inventario.gestionar`), `.gitignore`, `server/pruebas/pruebas.php` (10 pruebas), `noteas.md`, `docs/PLAN-PRUEBAS.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - Base documental en JSON, como fija `CLAUDE.md` para el inventario (RA1 y RA5 de Acceso a datos): un documento para el
    inventario y otro para las listas. Las ubicaciones se guardan como lista plana con «padre» (árbol) para poder moverlas
    sin reescribir subárboles.
  - Concurrencia: `flock` compartido para leer y exclusivo para modificar, con la validación dentro del bloqueo; escritura
    atómica con fichero temporal y `rename`. Comprobado con diez escrituras simultáneas (INV-10).
  - Decisiones del usuario: la lista no descuenta del inventario; el monitor elige de dónde coge cada cantidad; lo que falta
    se apunta como compra; acceso para administradores y monitores (no tutores).
  - Cada línea guarda también el nombre del material para que una lista antigua se lea aunque el material se borre.
  - El documento se genera en una ventana aparte con su propio estilo de impresión (se puede guardar como PDF).
- Requisitos que cubre (RF/RNF): RF-06, RNF-03, RNF-06.
- Cómo probarlo (pasos):
  1. Ejecutar `2026-11-inventario.sql` (añade el permiso).
  2. Inventario → crear ubicaciones y material. Listas → nueva lista, pasar material, ajustar necesarios, añadir compras,
     «Documento para imprimir», «Guardar como plantilla» y «Usar para una salida».
  3. `php server/pruebas/pruebas.php <url> --confirmar` → 107 de 107.
- Impacto en la memoria (apartados que habría que revisar): RF-06, modelo de datos (documentos JSON), acceso a datos
  (base documental y bloqueo de ficheros), plan de pruebas.
- Pendiente o problemas:
  - La interfaz no se ha visto en un navegador desde aquí (MAN-36 a MAN-39).
  - En la instalación local del usuario `storage/` es accesible desde la web porque Apache sirve toda la carpeta del
    proyecto con `AllowOverride None` (anotado en E-10): habrá que corregirlo antes de usar datos reales.

## 2026-11-17 · E-02 · Capa de clases de dominio (mapeo objeto-relacional)
- Rama / commit: `feat/E-02-clases-dominio` (sale de `feat/E-14-documentacion`)
- Qué se ha hecho: nuevas clases en `server/dominio/`. `Modelo` es una clase abstracta que mapea filas de la base de datos
  a objetos y al revés (buscar, cargar con condición, guardar con INSERT o UPDATE, borrar y JSON). `Educando`, `Evento`,
  `Factura` y `Usuario` la heredan y reúnen las reglas que estaban repartidas por las API. Las API las usan y se eliminan
  las funciones repetidas (cinco copias de `responder()`, tres de «¿es su tutor?», tres del tamaño máximo).
- Archivos creados o modificados:
  - Nuevos: `server/dominio/Modelo.php`, `Educando.php`, `Evento.php`, `Factura.php`, `Usuario.php`; `server/api/comun.php`.
  - `server/api/superapi.php` (de 1512 a 1415 líneas), `facturas.php`, `archivo.php`, `documentos.php`, `galeria.php`,
    `server/servicios/salida.php`; `server/pruebas/pruebas.php` (4 pruebas de las clases); `noteas.md`, `docs/PLAN-PRUEBAS.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - **Mapeo hecho a mano, sin ORM** (Doctrine o Hibernate): el proyecto no usa dependencias externas y así se ve cómo
    funciona un ORM por dentro. Cada subclase declara su tabla y un mapa columna → propiedad (`COLUMNAS`); `Modelo` hace
    el resto. Es el patrón *Active Record* (el objeto sabe guardarse y borrarse).
  - Seguridad: consultas preparadas; los nombres de tabla y columna salen de constantes de la clase; `OCULTAS` impide
    que la contraseña llegue al JSON.
  - Las rutas genéricas de `superapi.php` (tablas y formularios automáticos) siguen trabajando con filas porque se adaptan
    a cualquier tabla; las clases se usan para las reglas de estas cuatro entidades. `Factura::listadoPara` devuelve filas
    porque incluye datos de otras tablas (nombres de usuarios).
  - Sin cambios de comportamiento: las 93 pruebas anteriores siguen superadas.
- Requisitos que cubre (RF/RNF): RF-02, RF-03, RF-05, RNF-05. Resultados de aprendizaje: Acceso a datos RA3 y RA6.
- Cómo probarlo (pasos): usar la aplicación como siempre (no debe cambiar nada). `php server/pruebas/pruebas.php <url> --confirmar`
  → 97 de 97.
- Impacto en la memoria (apartados que habría que revisar): arquitectura del servidor (nueva capa de dominio), acceso a datos
  (mapeo objeto-relacional y comparación con un ORM), mantenimiento (RNF-05).
- Pendiente o problemas: con la copia de la base del usuario anterior a cargar las plantillas, DOC-01 y DOC-14 fallan porque
  el catálogo apunta a `circular1.pdf`, que ya no está en el repositorio; no es un fallo del código (en su Apache funciona).

## 2026-11-17 · E-14 (cierre) · Fichas de la FEV y Configuración en cajas
- Rama / commit: `feat/E-14-documentacion` (commit propio después del de E-14)
- Qué se ha hecho:
  - Catálogo de documentos base con las fichas de la FEV que el usuario ha reunido: ficha de inscripción, fichas sanitarias
    (menor y mayor de edad), exclusión de responsabilidad, autorizaciones de ausencia, uso de imagen (menor y mayor),
    grupos de WhatsApp, viaje en vehículo privado y volver solo a casa. La circular de los eventos es la «Autorización de
    participación en actividades».
  - Configuración: cada apartado en su propia caja con el mismo estilo (datos del grupo, apariencia, usuarios, archivos,
    conservación, listas, cargos, documentos base, copia de seguridad y datos vencidos). El botón «Guardar configuración»
    queda fijo al pie y guarda las cajas del formulario general.
- Archivos creados o modificados: `server/data/semillas/documentos-base.sql`, `schema.sql`, `migraciones/2026-11-documentacion.sql`,
  `server/api/archivo.php`, `server/api/documentos.php`, `public/nucleo/configuracion.js`, `documentos.js`, `public/estilo.css`,
  `server/pruebas/pruebas.php`, `.gitignore`, `noteas.md`. Se elimina `storage/documentos/biblioteca/circular1.pdf` (era la hoja
  de inscripción de la FEV; la quitó el usuario) y se añade `biblioteca/.gitkeep`.
- Decisiones técnicas y motivo:
  - Decisión del usuario: todas las fichas activas; ya decidirá cuáles son obligatorias. Por ahora son obligatorias todas
    salvo las versiones «mayor de edad» (un menor no puede entregar las dos versiones).
  - Las plantillas no se versionan (`.gitignore`): son documentos de la FEV y cada grupo sube las suyas. Por eso el catálogo
    empieza sin archivos; mientras no se suba la circular de participación, su descarga responde 404 con un aviso.
  - Las pruebas DOC-01 y DOC-14 ya no modifican la circular real del grupo: comprueban 200 si tiene plantilla y 404 si no.
- Requisitos que cubre (RF/RNF): RF-04, RF-07, RNF-06.
- Cómo probarlo (pasos): Configuración → cada apartado en su caja y «Guardar configuración» al pie; «Documentos base y
  plantillas» con las 11 fichas. Como tutor, «Ver documentación» muestra todas. Pruebas: 93 de 93 con y sin plantilla de participación.
- Impacto en la memoria (apartados que habría que revisar): RF-04 (lista de fichas), interfaz de Configuración.
- Pendiente o problemas: decidir qué fichas son obligatorias; quizá algunas solo deban pedirse según la edad (menor o mayor).

## 2026-11-17 · E-14 · Documentación de inscripción
- Rama / commit: `feat/E-14-documentacion` (sale de `feat/E-05-facturas`)
- Qué se ha hecho: encargo nuevo propuesto por Claude Code y definido con el usuario. Las fichas básicas (ficha sanitaria,
  hoja de inscripción, exclusión de responsabilidad, autorización para ausentarse) se pueden descargar en blanco y entregar
  desde la ficha de cada educando; un monitor las revisa. El administrador sube y cambia las plantillas desde Configuración.
  La circular de participación de los eventos pasa a ser una entrada del mismo catálogo.
- Archivos creados o modificados:
  - Nuevos: `server/api/documentos.php`, `public/api/documentos.php`, `public/nucleo/documentos.js`,
    `server/data/semillas/documentos-base.sql`, `server/data/migraciones/2026-11-documentacion.sql`.
  - `server/api/archivo.php` (circular desde el catálogo), `superapi.php` (borrado de vencidos incluye estos documentos;
    indicadores del dashboard), `instalar.php` (carga la semilla de documentos), `tabla.js` (bloque en «Ver documentación»),
    `configuracion.js` (bloque de plantillas), `codigo.js`, `index.html`, `estilo.css`.
  - Datos: `schema.sql`, `modeloDeDatos.txt`, `semillas/roles-permisos.sql`, `migraciones/2026-11-roles.sql`
    (permisos `documentos.subir_propios` y `documentos.revisar`).
  - Se eliminan `storage/documentos/biblioteca/todas.pdf` y `todas si.pdf` (pruebas de una aplicación antigua, según el usuario);
    `.gitignore` deja fuera las plantillas que se suban.
  - `server/pruebas/pruebas.php` (14 pruebas nuevas), `noteas.md`, `docs/PLAN-PRUEBAS.md`, `docs/ENCARGOS.md`.
- Decisiones técnicas y motivo:
  - Decisiones del usuario: las fichas se entregan una sola vez (se pueden volver a subir para actualizar datos); un monitor
    las revisa (provisional, pendiente de consultarlo con el grupo); las plantillas las sube el administrador porque cada grupo
    usa las suyas.
  - Se guardan en la misma carpeta que las circulares (ronda / sección / educando) para que el borrado de datos vencidos
    (E-06) las elimine sin cambios. Una fila por educando y documento (`UNIQUE`): volver a subir sustituye el archivo.
  - `documentos.subir_propios` es un permiso «propio» del tutor y, como los otros dos, no lo tiene el administrador.
    El monitor puede entregar en nombre de una familia (por si la trae en papel).
  - Las plantillas en blanco no son datos personales: cualquier usuario con sesión las descarga. Los documentos entregados sí,
    y cada acceso se registra.
- Requisitos que cubre (RF/RNF): RF-04, RF-02, RNF-03.
- Cómo probarlo (pasos):
  1. Ejecutar `2026-11-documentacion.sql`.
  2. Como administrador: Configuración → «Documentos base y plantillas» → subir las plantillas de la FEV.
  3. Como tutor: Educandos → «Ver documentación» → descargar en blanco y subir. El dashboard muestra los pendientes.
  4. Como monitor: revisar o pedir corrección. `php server/pruebas/pruebas.php <url> --confirmar` → 93 de 93.
  Probado: 93 de 93 con base nueva y en una copia de la base del usuario con todas las migraciones; instalación desde cero
  con el catálogo de documentos cargado.
- Impacto en la memoria (apartados que habría que revisar): RF-04 (documentación), modelo de datos (`documentos_base`,
  `documentos_educando`), permisos (dos nuevos), interfaz (ficha del educando y Configuración). Es un encargo nuevo: el chat
  del documento debe decidir en qué apartados encaja.
- Pendiente o problemas:
  - La revisión por un monitor es provisional hasta que el usuario lo consulte con el grupo.
  - `circular1.pdf` es en realidad la hoja de inscripción de la FEV: hasta que se suba la circular de participación real
    desde Configuración, es la que se descarga en los eventos.
  - La interfaz nueva no se ha visto en un navegador desde aquí (MAN-33 a MAN-35).

## 2026-11-17 · Correcciones de interfaz (E-03 y E-05)
- Rama / commit: `feat/E-05-facturas` (commit propio después del de E-05)
- Qué se ha hecho: arreglos pedidos por el usuario al probar en el navegador.
  - Las miniaturas de la galería y de las facturas se quedaban en «Cargando…». Dos fallos: se encolaban antes de que
    la tarjeta estuviera en la página (la cola las descartaba) y el Web Worker recibía una ruta relativa que resolvía
    desde `nucleo/` (404). Ahora se procesan cuando la vista ya está montada y con la dirección completa.
  - Migas de la galería en fila y con el aspecto de la barra de filtros de las tablas (fondo del color del grupo,
    texto blanco). Usaban `<nav>` y heredaban los estilos del menú lateral (en columna y poco legibles).
  - Botón «Descargar» (galería, facturas, copia de seguridad) con aspecto de botón, en azul (`.boton-descargar`).
  - Campos de sí o no del formulario genérico (por ejemplo «circular» en eventos) con dos botones de radio en lugar
    de un desplegable.
- Archivos creados o modificados: `public/nucleo/utilidades.js`, `galeria.js`, `facturas.js`, `configuracion.js`,
  `formulario.js`, `public/estilo.css`.
- Decisiones técnicas y motivo: la corrección de la cola está en `utilidades.js`, que comparten galería y facturas.
- Requisitos que cubre (RF/RNF): RF-03, RF-04, RF-05, RNF-06.
- Cómo probarlo (pasos): Galería → abrir un evento: las miniaturas aparecen. Facturas: las tarjetas muestran la imagen.
  Crear o editar un evento: «Circular» con Sí / No.
- Impacto en la memoria (apartados que habría que revisar): interfaz (galería, facturas, formularios).
- Pendiente o problemas: ninguno conocido; comprobado por el usuario en el navegador antes de estas correcciones.

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
