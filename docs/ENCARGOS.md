# Encargos de desarrollo

Estados: `pendiente`, `en curso`, `hecho` (rama sin fusionar), `fusionado`.

Orden de ejecución recomendado por el chat del documento (2026-11-17): E-10 → E-13 → E-05 → E-02 → E-04 → E-12 → E-11.
E-13 va antes que E-05 porque las facturas dependen de los permisos nuevos; E-11 al final porque el instalador debe
construirse sobre el esquema ya estable. Nota: E-10 está revertido provisionalmente por decisión del usuario
(ver su apartado), así que el orden empieza en la práctica por E-13.
Requisitos: RF-01 usuarios y roles · RF-02 educandos · RF-03 eventos y asistencia · RF-04 documentación y galería ·
RF-05 justificantes de gasto · RF-06 inventario · RF-07 dashboard, exportación y configuración ·
RNF-01 disponibilidad · RNF-02 compatibilidad · RNF-03 seguridad y datos · RNF-04 copias · RNF-05 mantenimiento ·
RNF-06 usabilidad y accesibilidad · RNF-07 rendimiento.

## E-01 · Contraseñas con password_hash
- Requisitos: RNF-03. Apartados de la memoria afectados: 2.6, 3.1, 4.4, 5.2.
- Hoy `server/api/login.php` compara con `sha256` sin sal. Pasar a `password_hash` y `password_verify`.
- Migración transparente: si el hash guardado es sha256 y coincide, permitir el acceso y reescribirlo con `password_hash`.
- Actualizar `demo.sql`, `schema.sql`, el instalador y cualquier alta de usuario en `superapi.php`.
- Aceptación: login correcto e incorrecto; usuario antiguo entra y queda migrado; ninguna contraseña en claro ni en sha256 tras entrar.
- Estado: fusionado

## E-02 · Capa de clases de dominio (acceso a datos con objetos)
- Requisitos: RF-02, RF-03, RF-05, RNF-05. Resultado de aprendizaje: Acceso a datos RA3 y RA6.
- Crear en `server/` clases PHP (`Educando`, `Evento`, `Factura`, `Usuario`) que mapeen filas de la base de datos a objetos y los serialicen a JSON.
- Mover a esas clases las consultas hoy repartidas en `superapi.php` (sin cambiar el comportamiento visible).
- Aceptación: las pantallas actuales funcionan igual; `superapi.php` reduce su tamaño; cada clase tiene métodos de carga, guardado y borrado.
- Estado: pendiente

## E-03 · Galería de fotografías
- Requisitos: RF-04, RNF-03. Apartados afectados: 3.1, 3.4.
- Subida de imágenes a `storage/galeria/` con validación (tipo real, tamaño, nombre aleatorio), miniaturas con el Web Worker existente, visor y permisos por rol y por sección.
- Las fotografías se sirven solo a través de PHP tras comprobar permisos.
- Aceptación: un tutor solo ve fotos de las secciones de sus educandos; un monitor sube y borra; acceso directo a la ruta de `storage` denegado.
- Decisión del usuario (2026-11-16): cada foto pertenece a un evento, pero la galería se organiza por carpetas
  ronda → tipo de evento (por ejemplo «Ronda 2026-2027 / Sábados», «Reuniones de familias», «Otros»), con una carpeta
  por cada tipo del catálogo `tipos_evento`, de modo que los tipos nuevos aparecen solos.
- Estado: hecho (rama `feat/E-03-galeria`, pendiente de fusionar)

## E-04 · Inventario en JSON y listas de material
- Requisitos: RF-06. Resultado de aprendizaje: Acceso a datos RA1 y RA5.
- Definido con el usuario el 2026-11-17 (amplía el texto original de «plantillas e informe de faltantes»):
  - **Inventario**: árbol libre de ubicaciones (por ejemplo Trastero → Zona A → Estantería 1; Local → Estantería 2) y
    materiales con su cantidad en cada ubicación. Un mismo material puede estar en varias ubicaciones.
  - **Listas de material** (por ejemplo «Acampada de verano»): a la izquierda lo que hay y dónde; a la derecha lo que se lleva.
    Se pasa la cantidad deseada arrastrando o con un botón. La lista **no descuenta del inventario**: sirve para generar un documento.
  - **Reparto y compras**: si se piden 60 bolígrafos y hay 40 (20 en un almacén y 20 en otro), el documento indica de qué
    ubicación coger cada cantidad y que hay que comprar 20. Decisión del usuario: el monitor elige a mano de qué
    ubicación coge cada cantidad (no hay reparto automático). También se pueden apuntar a mano cosas que no están en el inventario.
  - **Documento** para imprimir o guardar en PDF y enviarlo al resto de monitores.
  - **Plantillas**: cualquier lista se guarda como plantilla; de una plantilla se crea una lista nueva, se modifica y se
    puede guardar como otra plantilla (versiones).
- Datos en ficheros JSON en `storage/inventario/`, leídos y escritos solo desde PHP con bloqueo (`flock`), fuera del repositorio.
- Permisos (decisión del usuario): administrador y scouter gestionan el inventario y las listas; el tutor no tiene acceso.
- Aceptación: alta, edición y borrado de ubicaciones y material; una lista calcula de dónde coger cada cosa y qué comprar;
  las plantillas se guardan y se reutilizan; el JSON no queda accesible desde la web.
- Estado: pendiente

## E-05 · Facturas en tablero (sustituye al E-05 anterior, 2026-11-17)
- Requisitos: RF-05, RF-01, RNF-06. Depende de E-13. Apartados afectados: 2.5, 2.6 (RF-05), 3.1, 3.4, 3.9.
- Cambio de estados (decisión del usuario): tres estados, `pendiente`, `revisada` y `pagada`. Se elimina `rechazada`.
  Migración: las facturas `rechazada` pasan a `pendiente` (avisar de cuántas hay antes de migrar). Actualizar `schema.sql` y el modelo.
- Nueva vista «Facturas» en forma de tablero, similar a la de circulares y a la galería:
  - Tres columnas: Pendientes, Revisadas y Pagadas.
  - Cada factura es una tarjeta con la miniatura de la imagen (reutilizar el Web Worker) y la información relevante:
    concepto, importe, fecha de compra, categoría y, para tesorería, el responsable.
  - Al pulsar una tarjeta se abre el detalle con la imagen grande y todos los campos.
- Monitor (`facturas.subir_propias`): ve solo sus facturas, sube nuevas con un formulario (número, CIF del proveedor, fechas,
  concepto, importe, categoría e imagen) y puede editarlas o borrarlas únicamente mientras están `pendiente`.
- Tesorería y administrador (`facturas.ver_todas` y `facturas.cambiar_estado`): ven las facturas de todos, suben las suyas y
  cambian el estado de cualquiera (botones en la tarjeta; se puede añadir arrastrar entre columnas, pero siempre con botones
  para que se pueda usar con teclado). Nadie más puede cambiar un estado, ni siquiera sobre sus propias facturas.
- Registrar quién cambió el estado y cuándo (`estado_cambiado_por`, `estado_cambiado_en`).
- La comprobación es en el servidor (permisos del E-13), no solo en la interfaz.
- Confirmado por el usuario (2026-11-17): imagen obligatoria al subir; una factura pagada no se puede editar ni borrar
  (tesorería puede devolverla a revisada).
- Adelantado en E-13 (decisión del usuario): el servidor ya aplica que el monitor ve, edita y borra solo sus facturas,
  que el responsable es quien la sube y que solo `facturas.cambiar_estado` cambia estados. Falta el tablero, los estados
  nuevos y `estado_cambiado_por` / `estado_cambiado_en`.
- Aceptación: monitor ve solo las suyas y no puede cambiar estados; tesorería y administrador las ven todas y pueden cambiarlos;
  un monitor que intenta cambiar un estado por la API recibe 403; las miniaturas cargan; pruebas automáticas nuevas.
- Estado: hecho (rama `feat/E-05-facturas`, pendiente de fusionar)

## E-06 · Bajas, conservación y registro de accesos
- Requisitos: RF-02, RF-04, RNF-03.
- Regla (decidida con el usuario el 2026-11-17, sustituye a «se eliminan los archivos»): cuando un educando deja el grupo
  se le pone `fecha_baja`; desaparece de las vistas activas pero sus datos se conservan. Cuando pasa el plazo configurable
  (5 años por defecto) se borran definitivamente sus archivos **y su registro** (con asistencias y vínculos). Los tutores
  que se quedan sin educandos se muestran y el administrador decide si borrar también su cuenta.
- Registrar los accesos a documentación sensible (quién, qué, cuándo) en una tabla que nadie puede modificar.
- Aceptación: un proceso (botón de administración) lista y elimina lo vencido; los accesos quedan en una tabla de registro.
- Estado: hecho (rama `feat/E-06-conservacion`, pendiente de fusionar)

## E-07 · Plan de pruebas
- Requisitos: todos. Apartados afectados: 3.9, 5.1, 5.2, 5.7.
- Lista de pruebas funcionales y de permisos por rol, con resultado (superada o no) y fecha. Un script o una página de comprobación para repetirlas.
- Estado: hecho (rama `feat/E-07-pruebas`, pendiente de fusionar). Se ampliará al terminar E-02, E-04, E-05 y E-06.

## E-08 · Proteger la exportación completa (`servicios/salida.php`)
- Requisitos: RNF-03, RNF-04, RF-07. Apartados de la memoria afectados: a determinar por el chat del documento (seguridad y copias).
- Hoy `public/servicios/salida.php` devuelve todas las tablas en JSON sin comprobar la sesión: cualquiera que conozca la URL descarga
  educandos, usuarios y hashes de contraseñas. `public/servicios/entrada.php` delega en `server/servicios/entrada.php`, que está vacío.
- Exigir sesión de administrador en el servidor (`exigirRol(['administrador'])`) o, si no se usa, eliminar ambos servicios.
  Si se mantiene, excluir la columna `contraseña` y valorar ofrecerla como copia de seguridad desde la configuración (RNF-04).
- Decidir con el usuario si `entrada.php` (importación) se implementa o se elimina.
- Aceptación: sin sesión o con rol scouter/tutor la URL devuelve 401/403; como administrador funciona y no incluye contraseñas.
- Estado: hecho (rama `feat/E-08-salida`, pendiente de fusionar)

## E-09 · Proteger el instalador
- Requisitos: RNF-03, RNF-05. Apartados de la memoria afectados: a determinar (instalación y seguridad).
- Hoy `public/instalador.html` y `public/api/instalar.php` se pueden ejecutar sin sesión en cualquier momento y crean tablas con el modelo recibido.
  Desde E-01 no permiten crear un administrador si ya hay usuarios, pero siguen abiertos.
- Bloquear el instalador una vez instalada la aplicación. Opciones: marca en `storage/` tras la primera instalación,
  rechazarlo si ya existe la tabla `usuarios` con datos, o exigir sesión de administrador para reinstalar. Elegir con el usuario.
- Aceptación: en una instalación en uso, el instalador responde 403 sin modificar la base de datos; en una instalación nueva funciona.
- Estado: hecho (rama `feat/E-09-instalador`, pendiente de fusionar)

## E-10 · Credenciales fuera del repositorio
- Requisitos: RNF-03, RNF-05.
- `server/data/database.php` usa como valor por defecto una contraseña de MySQL escrita en el código, visible en el repositorio público.
- Quitar el valor por defecto: si falta `INGERIAR_DB_PASSWORD`, mostrar el error de configuración que ya existe.
  Documentar en `noteas.md` que la contraseña de cualquier servidor que la haya usado debe cambiarse (sigue en el historial de git).
- Revisar el resto del repositorio en busca de claves o datos reales.
- Aceptación: ninguna credencial en el código; sin la variable de entorno, la aplicación muestra el mensaje de configuración.
- Revertido (2026-11-17, decisión del usuario): se vuelve a usar la contraseña de desarrollo escrita en `database.php`
  cuando Apache no define `INGERIAR_DB_PASSWORD`, para seguir accediendo a la instalación local. Se retomará más adelante.
  Contradice la regla de `CLAUDE.md` de no subir credenciales; la contraseña ya estaba en el historial público.
- Estado: pendiente (revertido)

## E-11 · Instalador interactivo
- Requisitos: RNF-05, RNF-06, RNF-03. Apartados afectados: 3.1, 3.5, 4.4. Depende de que el esquema esté estable
  (después de E-13, E-05 y E-04).
- Sustituir el formato de texto `modeloDeDatos.txt` por un asistente interactivo en `instalador.html`:
  - Paso de base de datos y conexión (nombre de la base; la contraseña sigue viniendo de variable de entorno).
  - Creador de tablas: añadir tablas y columnas, elegir tipo (VARCHAR con longitud, INT, DECIMAL, DATE, DATETIME, BOOLEAN,
    TEXT, ENUM con sus valores), NULL o NOT NULL, valor por defecto, clave primaria, AUTO_INCREMENT y UNIQUE.
  - Claves foráneas: elegir columna, tabla y columna destino, y ON DELETE / ON UPDATE.
  - Datos iniciales (catálogos, roles y permisos) y primer administrador.
  - Vista previa del SQL generado y confirmación antes de ejecutar.
- Modo alternativo: subir un `schema.sql` y construir la base desde él. Validar el archivo: solo sentencias CREATE DATABASE,
  CREATE TABLE, INSERT y ALTER TABLE ... ADD; rechazar DROP, GRANT, LOAD, INTO OUTFILE y similares; límite de tamaño;
  ejecución en transacción cuando sea posible.
- Poder descargar el modelo creado en el asistente (como JSON y como `schema.sql`) y volver a cargarlo.
- Corregir lo detectado en E-09: crear de verdad las claves foráneas, insertar los catálogos iniciales y que
  `facturas.categoria` coincida con `schema.sql`.
- Mantener el bloqueo de E-09 (403 si la aplicación ya está en uso). Valorar un token de instalación en variable de entorno
  para que no baste con llegar a la URL en una instalación nueva.
- Aceptación: instalar solo con el asistente deja un esquema equivalente a `schema.sql` (incluidas las claves foráneas y los
  roles); instalar subiendo `schema.sql` funciona; un archivo con DROP se rechaza; en una instalación en uso responde 403; pruebas nuevas.
- Estado: pendiente

## E-12 · Copia de seguridad de la base de datos y de storage
- Requisitos: RNF-04, RNF-03. Apartados afectados: 2.6 (RNF-04), 3.5, 3.6, 4.4, 4.5.
- Script de línea de comandos `server/copias/copia.php`, pensado para programarlo con cron en el servidor del grupo:
  - Genera un único archivo .zip con fecha en el nombre que contiene el volcado de la base de datos (mysqldump, lanzado como
    proceso externo) y la carpeta `storage/` (documentos, facturas, galería, configuración, inventario).
  - La contraseña de MySQL no se pasa por la línea de comandos (usar variable de entorno o archivo de opciones).
  - Destino configurable por variable de entorno, fuera de `public/`; archivo con permisos 0600.
  - Cifrado opcional del zip (AES) con clave en variable de entorno; avisar en el registro si no está activado, porque la
    copia contiene datos de menores.
  - Retención configurable (por ejemplo, conservar las últimas N copias), bloqueo para que no se ejecuten dos a la vez y
    registro de cada ejecución (correcta o con error, tamaño y fecha).
- Botón en Configuración (`copia.descargar`): lista las copias existentes y descarga la última; la descarga se anota en `registro_accesos`.
- Script de restauración y procedimiento documentado en `noteas.md`. Probar una restauración completa en una base vacía.
- La copia externa (por ejemplo, a Google Drive) queda como paso manual documentado, no como código de la aplicación.
- `servicios/salida.php` se mantiene como exportación en JSON; la copia de seguridad real es la nueva.
- Aceptación: el script genera el zip con base de datos y archivos; restaurarlo en una base vacía reproduce los datos y los
  archivos; la retención elimina las copias antiguas; el botón solo funciona con permiso; el zip no queda accesible desde la web.
- Estado: pendiente

## E-13 · Roles y permisos (RBAC)
- Requisitos: RF-01, RNF-03, RNF-05. Apartados de la memoria afectados: 2.5, 2.6 (RF-01), 3.1, 3.4, 3.9.
- Hoy `usuarios.rol` es un `ENUM('administrador','scouter','tutor')` y los permisos están escritos en el código de cada ruta.
- Modelo nuevo (decisión del usuario): usuarios ← usuarios_roles → roles ← roles_permisos → permisos. Un usuario puede tener
  varios roles (por ejemplo monitor y tesorería) y se pueden añadir roles y permisos sin tocar las rutas.
  - `roles` (codigo PK, nombre, activo, orden).
  - `permisos` (codigo PK, descripcion).
  - `usuarios_roles` (id_usuario, codigo_rol), clave primaria compuesta, claves foráneas con ON DELETE CASCADE.
  - `roles_permisos` (codigo_rol, codigo_permiso), clave primaria compuesta.
- Roles iniciales: administrador (todos los permisos), scouter (nombre visible «Monitor»), tutor y tesoreria.
- Permisos iniciales (revisar contra el código y ajustar): usuarios.gestionar, educandos.gestionar, educandos.ver_propios,
  eventos.gestionar, eventos.ver, asistencia.responder_propios, asistencia.ver_todas, documentos.ver_todos, galeria.ver,
  galeria.subir, facturas.subir_propias, facturas.ver_todas, facturas.cambiar_estado, configuracion.gestionar,
  roles.gestionar, copia.descargar, conservacion.gestionar.
- Una única función `tienePermiso(codigo)` en `server/auth/` que lee los permisos guardados en la sesión (unión de los de
  todos sus roles). Sustituir las comprobaciones de rol por permisos en `superapi.php`, `archivo.php`, `galeria.php`,
  `salida.php`, la conservación y el instalador. Los datos que dependen de «ser tutor de» un educando siguen comprobándose con la relación.
- Migración: crear tablas, insertar roles y permisos, copiar cada `usuarios.rol` a `usuarios_roles` y retirar la columna
  `rol` al final, cuando las pruebas pasen. Los nombres visibles de rol de `config.php` pasan a la tabla `roles`.
- Interfaz: el formulario de usuario permite elegir varios roles. En Configuración, una pantalla para ver y editar los
  permisos de cada rol y crear roles nuevos (solo con `roles.gestionar`). Evitar el bloqueo: no se puede quitar
  `roles.gestionar` al rol administrador ni dejar el sistema sin ningún usuario que lo tenga.
- Dashboard con varios roles: proponer una regla (por ejemplo, combinar los indicadores de todos sus roles) y confirmarla con el usuario.
- Aceptación: un usuario con dos roles obtiene la unión de sus permisos; las 49 pruebas automáticas siguen superadas
  (actualizando las que dependen de rol) y hay pruebas nuevas de varios roles y de no bloqueo; ya no existe `usuarios.rol`.
- Riesgo: toca todas las comprobaciones de permisos. Hacerlo en una rama propia, con las pruebas automáticas como red de seguridad.
- Decisiones del usuario (2026-11-17): administrador, monitor y tutor son cargos básicos excluyentes (exactamente uno por
  usuario); los cargos adicionales (tesorería y futuros) se combinan con cualquiera, también con tutor, y no repiten permisos
  del básico. El monitor gestiona educandos (alta, edición y baja) pero no asistencias; solo el administrador borra educandos.
  El monitor ve solo sus facturas; tesorería ve todas y cambia estados. Permisos recargados en cada petición.
  Dashboard: suma de bloques según permisos.
- Estado: hecho (rama `feat/E-13-roles-permisos`, pendiente de fusionar). La columna `usuarios.rol` se retira con la
  segunda migración, que el usuario ejecuta en su base después de comprobar las pruebas.

## E-14 · Documentación de inscripción
- Requisitos: RF-04, RF-02, RNF-03. Propuesto por Claude Code y definido con el usuario el 2026-11-17.
  Apartados de la memoria afectados: a determinar por el chat del documento (documentación y galería, RF-04).
- Hoy solo existe la circular de participación (fija en el código) que se adjunta a los eventos con circular. Las fichas
  básicas del grupo (ficha sanitaria, hoja de inscripción, exclusión de responsabilidad y autorización para ausentarse)
  no se pueden descargar ni entregar desde la aplicación.
- Catálogo de documentos base con su plantilla en blanco (en `storage/documentos/biblioteca/`). El administrador sube y
  cambia las plantillas desde Configuración, porque cada grupo usa las suyas (este grupo, las de la FEV). La circular de
  participación pasa a ser una entrada más del catálogo.
- El tutor descarga cada plantilla y sube el documento relleno desde la ficha de su educando. Las fichas se entregan una
  vez; se pueden volver a subir para actualizar los datos (vuelven a quedar pendientes de revisión).
- Un monitor revisa cada documento (revisado o a corregir, con observaciones). Decisión provisional del usuario: puede
  cambiar cuando lo consulte con el grupo.
- Se guardan junto a las circulares, en la carpeta del educando en la ronda: `storage/documentos/<ronda>/<sección>/<educando>/`.
  El borrado de datos vencidos (E-06) los elimina con el resto. Cada acceso queda en `registro_accesos`.
- Dashboard: documentos pendientes para el tutor y documentos por revisar para el monitor.
- Aceptación: el tutor descarga las plantillas y sube los documentos de sus educandos (no de otros); el monitor los ve y
  los revisa; el administrador cambia las plantillas; volver a subir deja el documento pendiente; pruebas automáticas nuevas.
- Estado: hecho (rama `feat/E-14-documentacion`, pendiente de fusionar)
