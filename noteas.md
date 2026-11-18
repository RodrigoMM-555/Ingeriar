# Ingeriar v3

## 1. Descripción general

Ingeriar es una aplicación web para gestionar la actividad de un grupo scout:

- usuarios y roles;
- educandos y sus relaciones con tutores;
- eventos del calendario;
- asistencias y entregas de documentos;
- facturas;
- configuración visual del grupo;
- indicadores y gráficas.

La aplicación está separada en cliente, servidor, base de datos y almacenamiento
privado. Esta separación evita publicar directamente credenciales o archivos
subidos y permite controlar los permisos en el servidor.

## 2. Estructura del proyecto

```text
ingeriar v3/
├── public/                         Cliente y puntos de entrada web
│   ├── index.html                  Aplicación principal
│   ├── login.html                  Pantalla de acceso
│   ├── login.js                    Envío del inicio de sesión
│   ├── instalador.html             Instalador interactivo (con instalador.js)
│   ├── codigo.js                   Inicialización y navegación
│   ├── estilo.css                  Estilos comunes de toda la aplicación
│   ├── api/                        Entradas públicas que delegan en server/api
│   ├── nucleo/
│   │   ├── utilidades.js           Peticiones HTTP y funciones comunes
│   │   ├── configuracion.js        Configuración del grupo
│   │   ├── formulario.js           Formularios automáticos
│   │   ├── tabla.js                Tablas, filtros y acciones
│   │   ├── calendario.js           Calendario y asistencias
│   │   ├── galeria.js              Galería de fotos por ronda y tipo de evento
│   │   ├── facturas.js             Facturas en tablero (pendientes, revisadas, pagadas)
│   │   ├── documentos.js           Documentación de inscripción y plantillas
│   │   ├── inventario.js           Inventario y listas de material
│   │   ├── dashboard.js            Indicadores y gráficas por rol
│   │   ├── graficas.js             Gráficas SVG sin librerías externas
│   │   ├── exportacion.js          Exportación de tablas y dashboard
│   │   └── documento-preview-worker.js
│   └── servicios/salida.php        Entrada pública de la copia de seguridad
├── server/
│   ├── dominio/                    Clases Modelo, Educando, Evento, Factura, Usuario (E-02)
│   │                               y AlmacenJson (inventario en JSON, E-04)
│   ├── api/
│   │   ├── login.php               Inicio de sesión
│   │   ├── logout.php              Cierre de sesión
│   │   ├── superapi.php            API principal de datos y permisos
│   │   ├── archivo.php             Subida y descarga de documentos
│   │   ├── galeria.php             Fotos de la galería (listar, ver, subir, borrar)
│   │   ├── facturas.php            Facturas: listar, imagen, crear, editar, borrar, estado
│   │   ├── documentos.php          Documentación de inscripción y plantillas
│   │   ├── inventario.php          Inventario y listas de material (JSON)
│   │   └── instalar.php            Creación de la base de datos
│   ├── auth/sesion.php              Sesión y autenticación
│   ├── data/
│   │   ├── database.php             Conexión PDO
│   │   ├── schema.sql               Esquema MySQL completo
│   │   ├── demo.sql                 Datos de prueba
│   │   ├── modelo.json              Modelo del instalador (generado desde schema.sql)
│   │   ├── semillas/                Datos iniciales: catálogos, cargos, documentos base
│   │   ├── config.php               Configuración del grupo
│   │   └── secciones.php            Cálculo de secciones por edad
│   ├── pruebas/pruebas.php         Pruebas automáticas por HTTP (solo terminal)
│   ├── pruebas/fotos-ejemplo.php   Fotos de ejemplo para la galería y las facturas
│   ├── pruebas/pruebas-copias.php  Pruebas de copia, cifrado y restauración (E-12)
│   ├── copias/                     copia.php (cron) y restaurar.php (E-12)
│   ├── instalacion/ModeloDatos.php Modelo y generación de SQL del instalador (E-11)
│   ├── herramientas/generar-modelo.php  Genera modelo.json desde una base
│   ├── pruebas/pruebas-instalador.php   Pruebas del instalador (E-11)
│   └── servicios/salida.php        Copia de seguridad JSON (solo administrador)
├── storage/
│   ├── documentos/                  Circulares y entregas
│   ├── facturas/                    Imágenes de facturas
│   └── galeria/                     Imágenes de galería
└── noteas.md                       Documentación y diario del proyecto
```

`public/` debe ser el `DocumentRoot` de Apache. `server/` y `storage/` no deben
ser accesibles como carpetas públicas. Los accesos a archivos se realizan a
través de PHP, después de comprobar autenticación y permisos.

La configuración general se guarda en `server/data/config.php` en formato JSON.
Desde la pantalla de administración se pueden cambiar:

- nombre y URL del logo del grupo;
- logo subido al almacenamiento privado;
- color principal, color secundario y modo oscuro;
- datos de contacto y del coordinador;
- nombres visibles de los roles;
- formato de nombres y educandos sin sección;
- asistencia en eventos pasados;
- tamaño máximo y formatos de archivos;
- categorías de facturas, tipos de evento y secciones.

Los tipos de evento se gestionan separando el identificador interno del nombre
visible. Por ejemplo, `sabado` puede mostrarse como `Sábado` y seguir siendo
el mismo valor para colores, filtros y gráficas. La configuración permite
Los nombres visibles se editan desde los catálogos `tipos_evento`,
`secciones` y `categorias_factura`. Los formatos de entrega son una preferencia
de archivos, mientras que las categorías de factura son un catálogo dinámico
independiente. El estado de una factura no es un catálogo: es un `ENUM` cerrado
con los valores `pendiente`, `pagada` y `rechazada`.
No se deben borrar identificadores internos usados por eventos antiguos: si se
quiere dejar de ofrecer uno nuevo, se puede ocultar en una futura mejora, pero
los registros existentes deben seguir siendo editables.

Los tipos de evento y las secciones son catálogos administrables. Cada uno
conserva un código interno estable y tiene nombre visible, orden y estado
activo. Las secciones también guardan un intervalo de edades para calcular
automáticamente la sección de cada educando. Para bases ya existentes hay que
ejecutar `server/data/migraciones/2025-02-catalogos-eventos-secciones.sql`.
Retirar un elemento lo archiva (`activo = false`) para conservar históricos.

El servidor conserva los valores existentes y valida los booleanos, el tamaño
máximo y las opciones de archivo antes de escribir el archivo. Las listas se
guardan como texto separado por comas para que sean fáciles de editar; su
aplicación sobre los `ENUM` de la base de datos requerirá una migración
posterior si se desea que cambien las opciones reales de esos formularios.

## 3. Tecnologías y motivos

### HTML, CSS y JavaScript sin frameworks

La interfaz usa HTML, CSS y JavaScript estándar. Se eligió esta opción porque:

- el proyecto es educativo y permite ver claramente el funcionamiento;
- no requiere instalar ni actualizar dependencias;
- facilita entender el DOM, los eventos y las peticiones `fetch`;
- la aplicación es suficientemente pequeña para no necesitar un framework.

No se usa React, Vue ni otra librería de interfaz porque añadirían complejidad,
compilación y conceptos que no son necesarios para las pantallas actuales.

### PHP y MySQL

El servidor usa PHP y MySQL:

- PHP encaja con el entorno Apache del proyecto;
- MySQL permite relaciones, claves foráneas y consultas agregadas;
- PDO permite consultas preparadas y evita construir valores directamente en SQL;
- `information_schema` permite descubrir columnas, tipos y relaciones para crear
  formularios reutilizables.

### SVG para gráficas

Las gráficas se dibujan con SVG desde `graficas.js`. Así se evitan dependencias
externas, se mantienen ligeras y se pueden añadir tooltips y etiquetas con el
DOM estándar.

### Web Worker para previsualizaciones

La generación de vistas previas de imágenes se ejecuta en
`documento-preview-worker.js` para no bloquear la interfaz principal. Los PDF
se convierten en el servidor mediante `pdftoppm` para mostrar su primera página.

## 4. Flujo de inicio de la aplicación

1. El usuario abre `public/login.html`.
2. `login.js` envía el correo y la contraseña a `api/login.php`.
3. El servidor consulta el usuario con PDO y verifica la contraseña con
   `password_verify` (ver «Contraseñas» más abajo).
4. Se crea una sesión PHP con el identificador, nombre, apellidos y rol.
5. El navegador redirige a `index.html`.
6. `codigo.js` consulta `usuario_actual`.
7. Se cargan la configuración, los módulos y las entidades permitidas.
8. Se crean los objetos de tablas, formularios, calendario, dashboard y gráficas.
9. Se muestra inicialmente el dashboard.

Si una petición devuelve un error HTTP o un objeto con `error`, las utilidades
lo convierten en un error visible para el código que hizo la petición.

### Contraseñas

Las contraseñas se guardan con `password_hash` (bcrypt con sal) y se comprueban
con `password_verify`. Las funciones comunes están en `server/auth/contrasenas.php`
y las usan el login, el alta y edición de usuarios de `superapi.php` y el instalador.

- Reglas para una contraseña nueva: mínimo 8 caracteres y al menos un número o
  un carácter especial. Se comprueban en el servidor; el formulario solo orienta.
- Migración transparente: las versiones anteriores guardaban sha256 sin sal. Si
  el valor guardado tiene ese formato y coincide, el usuario entra y su
  contraseña se reescribe con `password_hash` en ese momento.
- El hash nunca sale del servidor: la ruta `tabla` de `usuarios` no lo devuelve
  y el formulario muestra un campo de contraseña vacío. Al editar, dejarlo vacío
  conserva la contraseña actual.
- Si el email no existe, el login calcula igualmente un hash para que el tiempo
  de respuesta no revele qué emails están registrados.

La migración solo mejora cómo se guarda la contraseña, no la contraseña en sí:
una contraseña antigua que no cumpla las reglas sigue siendo válida hasta que se
cambie. El código que acepta sha256 es temporal. Mientras queden hashes sha256,
una copia robada de la base de datos permitiría descifrarlos con facilidad porque
no llevan sal. Para comprobar cuántos quedan:

```sql
SELECT COUNT(*) FROM usuarios WHERE `contraseña` NOT LIKE '$2%';
```

Cuando el resultado sea 0, o tras un plazo razonable, se puede eliminar la rama
sha256 de `verificarContrasena`; a quien no haya entrado, un administrador le
asigna una contraseña nueva. Los usuarios creados con versiones anteriores cuya
contraseña quedó guardada en claro no pueden entrar y también necesitan una nueva.

## 5. Flujo de datos

El cliente no accede directamente a MySQL:

```text
Navegador
   ↓ fetch
public/api/*.php
   ↓ require
server/api/*.php
   ↓ PDO
MySQL
```

Para datos normales, `superapi.php` recibe una ruta como `tabla`, `columnas`,
`crear`, `editar` o `borrar`. Comprueba la sesión y el permiso antes de consultar
o modificar la base de datos.

La copia de seguridad (`servicios/salida.php`) descarga todas las tablas en
JSON. Solo la puede usar un administrador (401 sin sesión, 403 con otro rol) y
no incluye la columna `contraseña`. Se descarga desde la pantalla de
configuración, en el bloque «Copia de seguridad».

Para archivos, `archivo.php` comprueba el usuario, el evento, el educando y la
ruta física antes de devolver el archivo. Los archivos no se sirven directamente
desde `storage`.

## 5 bis. Capa de dominio: mapeo objeto-relacional (E-02)

`server/dominio/` contiene clases PHP que representan las entidades principales y
convierten filas de MySQL en objetos y objetos en filas:

```text
Modelo (abstracta)            tabla, columnas ↔ propiedades, ocultas
├── Educando                  sección, activo, carpeta, tutor, vencidos, borrado completo
├── Evento                    convocatorias por sección, terminado, fotos
├── Factura                   quién la ve, quién la edita, cambio de estado
└── Usuario                   cargos, educandos vinculados (sin contraseña en el JSON)
```

`Modelo` hace a mano lo que un ORM como Doctrine (PHP) o Hibernate (Java) hace con
anotaciones: cada subclase declara su tabla y el mapa columna → propiedad, y con eso
`desdeFila`, `buscar`, `donde`, `rellenar`, `guardar` (INSERT o UPDATE), `borrar` y
`jsonSerialize` funcionan para todas. Las consultas son preparadas y los nombres de
tabla y columna salen de constantes de la clase, nunca de datos del usuario.

No se usa una librería ORM porque el proyecto no usa dependencias externas y así se
ve el funcionamiento completo. Las rutas genéricas de `superapi.php` (tablas y
formularios automáticos) siguen trabajando con filas, porque se adaptan a cualquier
tabla; las reglas de negocio de estas cuatro entidades están en sus clases.

`server/api/comun.php` reúne `responder()` y `tamanoMaximoArchivos()`, que antes
estaban copiadas en cada API.

## 6. Roles y permisos

Desde E-13 los permisos no dependen del nombre del rol, sino de una tabla de
permisos (control de acceso basado en roles):

```text
usuarios ── usuarios_roles ── roles ── roles_permisos ── permisos
```

- **Cargo básico**: administrador, monitor (`scouter`) o tutor. Todo usuario tiene
  exactamente uno; el servidor rechaza cero o dos.
- **Cargos adicionales**: tesorería y los que se creen. Se suman al básico (también
  a un tutor: el comité lo forman familias) y solo añaden permisos que el básico no tiene.
- Los permisos de un usuario son la **unión** de los de todos sus cargos activos.
- `server/auth/permisos.php`: `exigirUsuario()` recarga en cada petición los cargos y
  permisos (una consulta) y los guarda en la sesión; `tienePermiso()` y
  `exigirPermiso()` son las únicas comprobaciones. Quitar un permiso o borrar un usuario
  tiene efecto en su siguiente acción.
- Lo que depende de «ser tutor de» un educando se sigue comprobando con
  `usuarios_educandos`.
- `superapi.php` tiene un mapa `PERMISOS_TABLAS` (tabla → permisos de lectura,
  escritura, borrado y menú). Una tabla que no esté en el mapa exige
  `configuracion.gestionar`.

| Cargo | Tipo | Permisos |
|---|---|---|
| Administrador | básico | todos menos los de tutor (`educandos.ver_propios`, `asistencia.responder_propios`) |
| Monitor | básico | usuarios.ver, educandos.ver_todos, educandos.gestionar, eventos.ver, eventos.gestionar, asistencia.ver_todas, documentos.ver_todos, galeria.ver, galeria.subir, facturas.subir_propias |
| Tutor | básico | educandos.ver_propios, eventos.ver, asistencia.responder_propios, galeria.ver |
| Tesorería | adicional | facturas.ver_todas, facturas.cambiar_estado |

Reglas destacadas:

- El monitor crea y edita educandos y los da de baja; solo el administrador los
  borra (`educandos.borrar`). El monitor ya no edita la tabla de asistencias.
- Facturas: cada uno ve, edita y borra solo las suyas; el responsable es siempre quien
  la sube. Solo `facturas.cambiar_estado` (tesorería, administrador) cambia estados,
  también en las propias. `facturas.ver_todas` muestra las de todos.
- Evitar el bloqueo: los permisos del administrador no se pueden cambiar, los cargos
  básicos no se pueden desactivar y se rechaza cualquier cambio de usuarios o cargos que
  deje a nadie con `roles.gestionar`.
- Configuración → «Cargos y permisos» (`roles.gestionar`): tabla de cargos por permisos,
  alta de cargos adicionales y desactivación.
- Dashboard: cada bloque aparece si el usuario tiene su permiso; con varios cargos se
  suman los bloques y los indicadores comunes salen una vez.

Los permisos se comprueban siempre en el servidor. Ocultar un botón en el cliente
(`utilidades.tienePermiso`) solo mejora la interfaz.

## 7. Base de datos

La base de datos estable se llama `ingeriar`. Las tablas principales son:

- `usuarios`: cuentas, credenciales y roles;
- `educandos`: datos de los menores;
- `usuarios_educandos`: relación entre tutores y educandos;
- `eventos`: actividades del calendario;
- `educandos_asistencias`: asistencia y archivo de entrega;
- `facturas`: gastos, responsable e imagen opcional;
- `roles`, `permisos`, `usuarios_roles` y `roles_permisos`: cargos y permisos (E-13).

Las claves foráneas mantienen la integridad de las relaciones. La relación entre
usuario y educando se guarda en una tabla intermedia porque un tutor puede tener
varios educandos y un educando puede estar relacionado con más de un usuario.

El esquema principal está en `server/data/schema.sql` y los datos de prueba en
`server/data/demo.sql`. El demo genera 30 usuarios, 100 educandos, 100 relaciones,
40 eventos, 80 facturas y 160 asistencias.

## 8. Formularios automáticos

`formulario.js` solicita las columnas de la tabla y crea controles según el tipo:

- `DATE` crea un selector de fecha;
- `DATETIME` crea fecha y hora;
- números crean campos numéricos;
- `ENUM` crea un `select`;
- booleanos crean `Sí`/`No`;
- relaciones crean selectores con nombres legibles.

Las relaciones se detectan con `information_schema`. También existen relaciones
convencionales de respaldo para bases antiguas. Los identificadores se mantienen
para guardar datos, pero el usuario ve nombres y apellidos.

Las facturas tienen un campo especial de imagen. El archivo se envía junto con
el resto del formulario mediante `FormData`; los campos normales siguen viajando
como JSON dentro de ese formulario.

## 9. Facturas en tablero (E-05)

La vista «Facturas» (`facturas.js` y `api/facturas.php`) muestra tres columnas:
Pendientes, Revisadas y Pagadas. Cada factura es una tarjeta con la miniatura de su
imagen (Web Worker), el concepto, el importe, la fecha de compra y la categoría. Al
pulsarla se abre el detalle con la imagen grande. Quien ve todas las facturas
(`facturas.ver_todas`: tesorería y administrador) las tiene agrupadas en cada columna por
responsable, por orden alfabético, con un separador que indica su nombre, el número de
facturas y el importe.

- Estados: `pendiente` → `revisada` → `pagada` (se eliminó `rechazada`). Solo
  `facturas.cambiar_estado` (tesorería y administrador) los cambia, con botones en la
  tarjeta o arrastrando entre columnas, también en sus propias facturas. Se guarda quién
  hizo el último cambio y cuándo (`estado_cambiado_por`, `estado_cambiado_en`).
- La imagen es obligatoria al crear: tipo real con `finfo` (JPEG, PNG o WebP), tamaño de
  la configuración y nombre aleatorio en `storage/facturas/`. Se sirve solo por PHP.
- El monitor ve solo sus facturas y las edita o borra mientras están pendientes. Una
  factura pagada no la edita ni la borra nadie (409); tesorería puede devolverla a revisada.
- Las rutas genéricas de `superapi.php` ya no escriben facturas: la tabla queda de solo
  lectura para el dashboard y la exportación.
- Bases existentes: `2026-11-facturas-tablero.sql` (las rechazadas pasan a pendiente).

Fotos de ejemplo para probar: `php server/pruebas/fotos-ejemplo.php --confirmar`
dibuja paisajes para la galería y tiques para las facturas sin imagen (PNG generado en
PHP, sin la extensión gd; imágenes ficticias).

## 10. Eventos, circulares y asistencias

El calendario carga eventos desde `superapi.php`, los agrupa por día y permite
crear, editar o borrar según el rol. Cada tipo de evento recibe una clase CSS y
un color diferente.

Los tipos de evento definidos son `reunión de familias`, `sabado`, `festival`,
`excursion` y `otro`. La normalización de clases también admite datos antiguos
como `campamento`, `taller`, `convivencia` y `actividad`.

Cada evento se relaciona con una o varias secciones mediante `eventos_secciones`.
Las secciones disponibles son `colonia`, `manada`, `tropa`, `posta` y `rutas`.
Si no hay filas relacionadas, el evento se considera dirigido a todas. El
formulario ofrece casillas independientes y una opción “Todas las secciones”.
La tabla intermedia permite seleccionar varias secciones sin guardar JSON.

Aunque el campo `seccion` es un `ENUM`, cada fila solo representa una sección.
Un mismo evento puede tener varias filas relacionadas, por ejemplo:

```text
id_evento | seccion
----------|--------
12        | colonia
12        | manada
12        | tropa
```

En este caso, el evento `12` está convocando a colonia, manada y tropa. La
clave primaria compuesta `(id_evento, seccion)` evita repetir una misma sección
en el mismo evento, mientras que permite añadir todas las secciones necesarias.
Se utiliza `ENUM` para limitar los valores a las secciones válidas y evitar
errores de escritura. Esta estructura es preferible a guardar una lista
separada por comas o un JSON porque mantiene los datos normalizados y facilita
las consultas y los filtros de asistencia.

La circular que se adjunta a los eventos es la plantilla «Autorización de
participación en actividades» del catálogo `documentos_base` (E-14). El
administrador la sube en Configuración → «Documentos base y plantillas» y se guarda
en `storage/documentos/biblioteca/`. Mientras no se suba, la descarga responde 404
con un aviso.

Las entregas se guardan en:

```text
storage/documentos/<ronda>/<seccion>/<educando>/
```

Las secciones se calculan a partir de `naz` al inicio de cada ronda:

- 5-6 años: `colonia`;
- 7-9 años: `manada`;
- 10-12 años: `tropa`;
- 13-15 años: `posta`;
- 16-19 años: `rutas`.

No se guarda la sección en la base de datos porque depende de la edad y cambiaría
al comenzar una nueva ronda. Las filas de educandos muestran un color según la
sección.

La asistencia y las entregas no se pueden registrar después de la fecha de fin
del evento. El visor de documentación muestra tarjetas con previsualización y
permite descargar el archivo original.

## 10 bis. Galería de fotografías

Cada foto pertenece a un evento (tabla `galeria_fotos`). La galería no guarda
carpetas: las calcula a partir del evento, así que si cambia la fecha o el tipo
de un evento, sus fotos se recolocan solas.

```text
Ronda 2026-2027            (ronda scout de septiembre a agosto, según la fecha)
├── Sábados                (una carpeta por tipo del catálogo tipos_evento)
│   └── Actividad 12/09    (cada evento con fotos)
├── Reuniones de familias
└── <tipo nuevo>           (los tipos añadidos en configuración aparecen solos)
```

- Archivos: `storage/galeria/` con nombre aleatorio (`bin2hex(random_bytes(16))`).
  Se comprueba el tipo real con `finfo` (JPEG, PNG o WebP), el tamaño con
  `tamano_maximo_archivos_mb` de la configuración y un máximo de 20 fotos por
  subida. Se validan todas antes de guardar ninguna.
- Las fotos solo se sirven a través de `api/galeria.php?accion=ver`, después de
  comprobar los permisos, con `X-Content-Type-Options: nosniff`.
- Permisos (en el servidor): administrador y scouter ven, suben y borran todo.
  Un tutor solo ve las fotos de eventos que convocan alguna sección de sus
  educandos, calculada con la edad que tenían en la fecha del evento. Un evento
  sin secciones convoca a todas.
- Las miniaturas las genera el Web Worker `documento-preview-worker.js`, con un
  máximo de cuatro trabajadores a la vez. El visor permite pasar fotos con las
  flechas del teclado y cerrar con Escape.
- Al borrar un evento se borran en cascada sus filas de `galeria_fotos` y
  `superapi.php` borra también los archivos.

## 10 ter. Bajas, conservación y registro de accesos

- **Baja**: un educando con `fecha_baja` deja de aparecer en el dashboard, los
  cumpleaños, las asistencias y la vista del tutor, y el tutor ya no puede operar
  con él (`tutorTieneEducando` solo admite educandos activos). El administrador lo
  sigue viendo en la tabla, en cursiva y al final; quitar la fecha deshace la baja.
  En la galería cuenta solo para los eventos anteriores a su baja.
- **Conservación**: `plazo_conservacion_anos` (5 por defecto) en la configuración.
  Configuración → «Datos vencidos» (ruta `conservacion`, solo administrador) lista los
  educandos con baja anterior al plazo y los tutores que se quedarían sin educandos.
  Al confirmar se borran el educando (asistencias y vínculos en cascada), los tutores
  marcados y los archivos de entregas con sus carpetas vacías. El servidor vuelve a
  comprobar qué está vencido: no borra nada que no lo esté aunque se le pida.
- **Registro de accesos** (`registro_accesos`): quién, acción, recurso, educando,
  fecha e IP. Se registran ver, previsualizar y subir entregas, la descarga de la copia
  completa y el borrado de datos vencidos. No se registran las fotos de la galería (cada
  miniatura sería un acceso). El detalle no guarda nombres, solo identificadores. El
  administrador lo consulta como una tabla, pero nadie puede editarlo ni borrarlo.
- Bases existentes: ejecutar `server/data/migraciones/2026-11-bajas-accesos.sql`.

## 10 cuater. Documentación de inscripción (E-14)

Las fichas básicas del grupo (ficha sanitaria, hoja de inscripción, exclusión de
responsabilidad y autorización para ausentarse) están en el catálogo `documentos_base`
junto con la circular de participación que se adjunta a los eventos.

- El administrador sube y cambia las plantillas en blanco en Configuración →
  «Documentos base y plantillas» (cada grupo usa las suyas) y puede añadir documentos.
  Se guardan en `storage/documentos/biblioteca/` con nombre aleatorio.
- En la ficha de cada educando («Ver documentación») el tutor descarga cada plantilla y
  sube el documento relleno. Se entrega una vez; volver a subirlo sustituye al anterior y
  lo deja pendiente de revisión. Un monitor también puede entregarlo (copia en papel).
- Un monitor lo marca como revisado o pide corrección con observaciones
  (`documentos.revisar`). Estados: pendiente, revisado, corregir.
- Los archivos van con las circulares, en la carpeta del educando en la ronda
  (`storage/documentos/<ronda>/<sección>/<educando>/inscripcion-<documento>-<aleatorio>`),
  así que el borrado de datos vencidos los elimina. Ver y subir queda en `registro_accesos`.
- Dashboard: «Documentos pendientes» (tutor) y «Documentos por revisar» (monitor).
- Bases existentes: `2026-11-documentacion.sql`.

## 10 quinquies. Inventario y listas de material (E-04)

El inventario usa una base documental en ficheros JSON (no MySQL), según `CLAUDE.md`:

```text
storage/inventario/inventario.json   ubicaciones (árbol con «padre») y materiales con existencias
storage/inventario/listas.json       listas y plantillas: líneas {material, necesarios, tomas} y compras
```

- `server/dominio/AlmacenJson.php` lee con bloqueo compartido y modifica con bloqueo
  exclusivo (`flock` sobre `.bloqueo`). La escritura es atómica (temporal + `rename`).
  La validación se hace dentro del bloqueo. Una prueba lanza diez escrituras simultáneas
  y comprueba que no se pierde ninguna.
- Ubicaciones en árbol libre (Trastero › Zona A › Estantería 1). No se puede borrar una
  ubicación con otras dentro o con material, ni mover una dentro de sí misma.
- Plano (E-15): la pestaña «Material y ubicaciones» muestra cada ubicación como una caja
  con sus sububicaciones dentro y una ficha por material con su cantidad (selector
  «Plano / Tabla»; la tabla es la vista anterior). Arrastrar una ficha a otra caja abre
  un cuadro con la cantidad (número y deslizador, por defecto todo) y llama a
  `mover_material`, que comprueba dentro del bloqueo que haya bastante en el origen (409
  si no) y suma en el destino. Arrastrar el título de una caja a otra la mete dentro
  (`guardar_ubicacion`); al fondo del plano, pasa a principal. El botón «⇄» de cada ficha
  hace lo mismo sin ratón. Arrastrar y soltar es el nativo de HTML: lo arrastrado se
  guarda en `this.arrastre` y la caja más interior que lo acepta recoge el evento.
- Listas (reparto automático desde 2026-11-18; antes el monitor elegía a mano de qué
  ubicación cogía cada cantidad): a la izquierda el mismo plano, con lo que queda tras lo
  que coge la lista. A la derecha, «Lo que necesitamos»: se añade arrastrando una ficha,
  con «→» o escribiendo el nombre (con sugerencias del inventario) y solo se indica
  cuántos. `repartir` coge primero de las ubicaciones elegidas arrastrando (`preferidas`,
  la última delante) y después de donde más hay. «Material que tenemos y dónde está» y
  «Hay que comprar» se calculan solos y se repintan al cambiar una cantidad. A comprar =
  necesarios − lo que se coge, más lo que no está en el inventario (`compras`). El
  reparto se recalcula al abrir la lista con las existencias actuales. La lista no
  descuenta del inventario. El documento para imprimir agrupa por ubicación.
- Plantillas: una lista se guarda como plantilla; de una plantilla sale una lista nueva o
  una versión nueva (se guarda de cuál viene).
- Datos de ejemplo (ficticios) para probar el plano y las listas:
  `php server/pruebas/inventario-ejemplo.php <url> <correo> <contraseña> --confirmar`.
  Usa la API con un usuario que gestione el inventario (no necesita permiso de escritura
  en `storage/`) y no duplica lo que ya existe: 18 ubicaciones en cuatro niveles, 33
  materiales (dos sin existencias), cuatro listas y una plantilla, con faltas para
  comprar y ubicaciones preferidas.
- Permiso `inventario.gestionar` (administrador y monitor). Bases existentes:
  `2026-11-inventario.sql`. Los JSON se incluyen en la copia de seguridad de `storage/`.

## 11. Dashboard, gráficas y exportación

El dashboard usa la ruta `superapi.php?ruta=dashboard`, que devuelve indicadores
y datos ya agrupados para las gráficas. Esto evita cargar todas las tablas cuando
solo se necesitan resúmenes.

También muestra un resumen común («Próximas actividades») con el próximo sábado,
la próxima acampada (con cuánto falta), el próximo evento que no es ni sábado ni
acampada y los cumpleaños de educandos de la semana, contada de domingo a sábado.
Los tutores no ven los cumpleaños.

Para el tutor hay además dos bloques (2026-11-17):

- «Pendientes de este mes»: una ficha por educando y evento de este mes que aún no ha
  terminado y convoca a la sección de ese educando, si falta confirmar la asistencia
  (con el aviso «Necesita circular» si el evento la lleva) o si va y falta entregar la
  circular («Falta documentación»). La ficha tiene el color de la sección del educando.
- (Tesorería) «Facturas»: ver más abajo.
- «Calendario del trimestre»: próximos eventos de las secciones de sus educandos que
  empiezan antes de que acabe el trimestre natural en curso (ene–mar, abr–jun, jul–sep,
  oct–dic).
Quien puede cambiar el estado de las facturas (`facturas.cambiar_estado`: tesorería y
administración) ve el bloque «Facturas» con dos columnas, «Pendientes de revisar»
(estado `pendiente`) y «Pendientes de pagar» (estado `revisada`), cada una con el número
de facturas y el importe total. Se muestran las 8 más antiguas de cada columna con
concepto, importe, responsable y fecha de compra; el resto se consulta en Facturas.

En tutores, el próximo evento y el mini calendario solo incluyen actividades
compatibles con las secciones de sus educandos. Los monitores disponen de un
desglose por sección de las respuestas del próximo sábado. Los tutores ven
además circulares pendientes y asistencias sin confirmar. Los cumpleaños se
calculan exclusivamente desde `educandos`; los usuarios no tienen fecha de
nacimiento para este propósito.

El dashboard ya no muestra la fila de indicadores numéricos (`article.indicador`),
quitada a petición del usuario el 2026-11-17, ni otras gráficas (próximos eventos por
tipo, usuarios por cargo, estado de asistencias del tutor). Según los permisos muestra
las asistencias del próximo sábado y el panel de pendientes del tutor.

Asistencias del próximo sábado (monitores): un único anillo doble. Por dentro, una
porción por sección (proporcional a sus educandos convocados, con su color); por
fuera, encima de cada sección, sus tramos con los tonos de su color, de claro a
oscuro: asiste y ha entregado la circular, asiste sin entregarla, no ha respondido y no
asiste. Al lado, una tabla con todas las cifras. Si el sábado no lleva circular, los
dos primeros tramos se juntan en «Asiste». Solo cuenta el próximo evento de tipo
sábado y las secciones que convoca. Lo dibuja `DarkorangeGraficas.crearAnilloAgrupado`.

`graficas.js` dibuja gráficas de barras, tarta y anillo con SVG. Los tooltips
indican valor y porcentaje.

`exportacion.js` permite exportar los datos visibles a impresión, HTML, JSON o
CSV. Se reutiliza desde tablas y dashboard para no duplicar controles.

## 12. Estilos y vistas

**Caché del navegador**: `index.html`, `login.html` e `instalador.html` cargan los
scripts y `estilo.css` con un número de versión (`codigo.js?v=20261117b`). Al cambiar
cualquier JS o CSS hay que cambiar ese número en las tres páginas; si no, el navegador
puede mezclar archivos nuevos con antiguos guardados en caché.

`estilo.css` está organizado por secciones:

- cabecera;
- acceso y asistencias;
- estructura principal;
- menús y navegación;
- contenido y vistas;
- tablas;
- botones;
- formularios y modales;
- calendario;
- dashboard;
- responsive e impresión.

Las vistas usan clases comunes:

- `.vista`: identifica una pantalla;
- `.vista-tabla`, `.vista-dashboard`, `.vista-calendario` y
  `.vista-configuracion`: identifican pantallas concretas;
- `.vista-superficie`: unifica fondo, borde, radio y sombra;
- `.vista-formulario`: identifica formularios reutilizables.

Esta organización permite crear futuras pantallas heredando la superficie y los
controles comunes sin copiar reglas CSS.

## 13. Instalación y configuración

1. Crear la base de datos `ingeriar` y el usuario de aplicación.
2. Configurar las variables de entorno de Apache:

```apache
SetEnv INGERIAR_DB_HOST localhost
SetEnv INGERIAR_DB_PORT 3306
SetEnv INGERIAR_DB_NAME ingeriar
SetEnv INGERIAR_DB_USER ingeriar_app
SetEnv INGERIAR_DB_PASSWORD tu_clave_de_mysql
```

3. Configurar `public/` como `DocumentRoot`.
4. Crear las tablas con el instalador (`instalador.html`, ver «Instalador» más
   abajo) o ejecutando `schema.sql`. El instalador puede crear además el primer
   administrador si la tabla `usuarios` está vacía. Una vez que hay usuarios
   responde 403 salvo a un administrador con la sesión iniciada.
5. Cargar `demo.sql` si se necesitan datos de prueba. La contraseña de cada
   usuario de prueba es `ingeriar-<numero>` (por ejemplo, `usuario1@ingeriar.test`
   / `ingeriar-1`).
6. Subir las plantillas en blanco (Configuración → «Documentos base y
   plantillas»), incluida la circular de participación de los eventos.
7. Dar permisos de escritura al servidor web sobre `storage/documentos/`,
   `storage/facturas/`, `storage/galeria/` y `storage/configuracion/`.
8. Para subir varias fotos a la vez, revisar en `php.ini` `upload_max_filesize`,
   `post_max_size` y `max_file_uploads` (por defecto PHP limita a 2 MB por archivo).
9. En bases ya creadas, ejecutar en este orden `server/data/migraciones/2026-11-galeria.sql`,
   `2026-11-bajas-accesos.sql`, `2026-11-roles.sql`, `2026-11-facturas-tablero.sql` y
   `2026-11-claves-foraneas.sql` (crea las claves foráneas que faltan en bases hechas
   con el instalador antiguo), `2026-11-documentacion.sql` y `2026-11-inventario.sql`.
   Cuando las pruebas pasen,
   `2026-11-roles-retirar-columna.sql` quita la columna antigua `usuarios.rol`. El
   código funciona antes y después de este último paso.

El archivo `.gitignore` excluye del repositorio todo lo que se sube desde la
aplicación (fotos, facturas, entregas y logo), porque puede contener datos reales.

Provisionalmente, si Apache no define `INGERIAR_DB_PASSWORD`, `database.php`
usa la contraseña de desarrollo escrita en el código (decisión del 2026-11-17;
E-10 queda pendiente). En un servidor real hay que definir la variable y usar
otra contraseña: la del código es pública.

No se debe usar `root` desde PHP. En Ubuntu puede fallar por autenticación
`unix_socket`. Si aparece `using password: NO`, Apache no está transmitiendo
`INGERIAR_DB_PASSWORD`; hay que revisar `mod_env`, el VirtualHost y reiniciar
Apache.

### Pruebas

`docs/PLAN-PRUEBAS.md` recoge las pruebas funcionales y de permisos por rol con
su resultado y fecha. Las automáticas se repiten con:

```bash
INGERIAR_DB_PASSWORD=... php server/pruebas/pruebas.php http://localhost:8000 --confirmar
```

Solo contra una base de datos de pruebas: el script crea y borra sus propios datos.

### Instalador (E-11)

`instalador.html` es un asistente en cuatro pasos:

1. Base de datos: se instala en la base configurada en el servidor
   (`INGERIAR_DB_NAME`); la contraseña de MySQL nunca se escribe en el formulario.
   Si existe `INGERIAR_TOKEN_INSTALACION`, hay que escribir ese token.
2. Tablas, de dos formas:
   - Asistente: carga el modelo de Ingeriar (`server/data/modelo.json`) y permite
     cambiar tablas y columnas (tipo, longitud, valores de ENUM, nulo, valor por
     defecto, clave primaria, AUTO_INCREMENT, único, sin signo), índices únicos de
     varias columnas y claves foráneas con ON DELETE / ON UPDATE. El modelo se puede
     descargar y volver a cargar en JSON.
   - Subir un `schema.sql`: solo se admiten CREATE TABLE, INSERT y ALTER TABLE … ADD
     (se rechazan DROP, GRANT, LOAD, INTO OUTFILE…); CREATE DATABASE y USE se ignoran.
3. Datos iniciales (catálogos, cargos y permisos, documentos base; archivos de
   `server/data/semillas/`) y primer administrador.
4. Vista previa del SQL (se puede descargar como `schema.sql`) e instalación.

El SQL lo genera siempre el servidor (`server/instalacion/ModeloDatos.php`) a partir
del modelo validado; los nombres van entre comillas invertidas y los valores
escapados. Primero se crean las tablas y después las claves foráneas. MySQL confirma
cada CREATE TABLE al momento, así que si una sentencia falla se indica cuál; los
datos iniciales y el administrador sí van en una transacción.

`modelo.json` se genera desde una base creada con `schema.sql`:
`INGERIAR_DB_NAME=<base> php server/herramientas/generar-modelo.php`. Hay que
regenerarlo cuando cambie `schema.sql`. La prueba INI-05 comprueba que instalar con el
asistente deja exactamente el mismo esquema y datos iniciales que `schema.sql`.

### Copias de seguridad (E-12)

`server/copias/copia.php` genera un único `ingeriar-copia-AAAA-MM-DD-HHMMSS.zip` con
el volcado de la base de datos (`mysqldump`) y toda la carpeta `storage/`
(documentos, facturas, galería, configuración e inventario).

- La contraseña de MySQL va en un fichero de opciones temporal (0600), nunca en la
  orden, donde la vería cualquiera con `ps`.
- Destino: `INGERIAR_COPIAS_DESTINO` (por defecto `copias/` en la raíz del proyecto,
  fuera de `public/`, con `Require all denied` y fuera del repositorio). Permisos 0600.
- Cifrado: si existe `INGERIAR_COPIAS_CLAVE`, el zip se cifra con AES-256 (CBC por
  bloques de 1 MB + HMAC-SHA256, claves con PBKDF2) y queda como `.zip.cifrado`. Sin
  clave, el registro avisa de que la copia contiene datos de menores sin cifrar.
- Retención: `INGERIAR_COPIAS_RETENER` (por defecto 10 copias). Bloqueo para que no se
  ejecuten dos a la vez. Cada ejecución queda en `registro-copias.log`.
- Configuración → «Copias de seguridad» (`copia.descargar`) lista las copias y las
  descarga; cada descarga queda en `registro_accesos`. `servicios/salida.php` sigue
  siendo una exportación en JSON, no una copia de seguridad.

Programarla con cron **con el mismo usuario que Apache** (para que la web pueda leer
las copias), por ejemplo cada noche a las 3:00:

```bash
sudo crontab -u www-data -e
0 3 * * * INGERIAR_DB_PASSWORD=... INGERIAR_COPIAS_CLAVE=... php /var/www/ingeriar/server/copias/copia.php
```

Restaurar en una base vacía (crear antes la base y dar permisos al usuario de la aplicación):

```bash
INGERIAR_DB_PASSWORD=... INGERIAR_COPIAS_CLAVE=... \
  php server/copias/restaurar.php copias/ingeriar-copia-....zip.cifrado --base=ingeriar --confirmar
```

El script se niega a restaurar sobre una base con tablas o sobre un `storage/` con
archivos, salvo con `--sobrescribir`. `--storage=<carpeta>` restaura los archivos en
otra carpeta. Se comprobó una restauración completa con `server/pruebas/pruebas-copias.php`.

Copia externa (paso manual): la carpeta de copias debe copiarse fuera del servidor
(por ejemplo, a la unidad de Google Drive del grupo) con la periodicidad que decida el
grupo. Subir solo copias cifradas y guardar la clave en un lugar distinto de las copias.

## 14. Migraciones para bases antiguas

Añadir las columnas que no existan:

```sql
ALTER TABLE eventos
    ADD COLUMN circular BOOLEAN NOT NULL DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS eventos_secciones (
    id_evento INT UNSIGNED NOT NULL,
    seccion ENUM('colonia', 'manada', 'tropa', 'posta', 'rutas') NOT NULL,
    PRIMARY KEY (id_evento, seccion),
    FOREIGN KEY (id_evento) REFERENCES eventos(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

ALTER TABLE educandos_asistencias
    ADD COLUMN archivo_entrega VARCHAR(255) NULL;

ALTER TABLE facturas
    ADD COLUMN imagen_factura VARCHAR(255) NULL;
```

Si la migración anterior creó `eventos.seccion`, elimínala después de crear la
tabla intermedia:

```sql
ALTER TABLE eventos DROP COLUMN seccion;
```

Si existe la columna antigua, eliminarla:

```sql
ALTER TABLE eventos
    DROP COLUMN circular_archivo;
```

Las sentencias deben ejecutarse solo si las columnas todavía no existen.

## 15. Diario de cambios

### 2026-11-18 (inventario de ejemplo)

- `server/pruebas/inventario-ejemplo.php`: ubicaciones, materiales y listas ficticias por la API.

### 2026-11-18 (listas con reparto automático)

- Listas: se indica cuánto se necesita y la aplicación reparte entre ubicaciones (primero
  las elegidas arrastrando, después donde más hay) y calcula «Hay que comprar». INV-15.

### 2026-11-17 (E-15 inventario visual)

- Inventario como plano de cajas con fichas: mover material y ubicaciones arrastrando,
  `mover_material` en el servidor, listas con el mismo plano. Pruebas INV-11 a INV-14.

### 2026-11-17 (facturas por responsable)

- Tablero de facturas: para quien ve todas, cada columna se separa por responsable.

### 2026-11-17 (facturas de tesorería)

- Dashboard: bloque «Facturas» con las pendientes de revisar y de pagar para quien
  tiene `facturas.cambiar_estado`.

### 2026-11-17 (calendario del trimestre)

- El mini calendario del dashboard solo muestra los eventos del trimestre natural en curso.

### 2026-11-17 (pendientes del tutor)

- Tutor: sin cumpleaños; «Pendientes de este mes» (fichas con el color de la sección,
  confirmar asistencia o falta documentación) y «Calendario» en bloques separados.

### 2026-11-17 (anillo doble del sábado)

- Las asistencias del sábado pasan de cinco gráficas a un único anillo doble
  (secciones por dentro, tramos por fuera) con una tabla de cifras al lado.

### 2026-11-17 (gráficas del dashboard)

- Se quitan las demás gráficas del dashboard; las del sábado van en dos columnas para
  que se vean más grandes.

### 2026-11-17 (asistencias del sábado)

- Asistencias del próximo sábado en una gráfica de anillo por sección, con los tonos
  del color de cada sección (asiste con circular → no asiste).

### 2026-11-17 (resumen del dashboard)

- «Próximas actividades»: próximo sábado, próxima acampada, próximo evento de otro
  tipo y cumpleaños de domingo a sábado (el tutor, solo los de sus educandos).

### 2026-11-17 (indicadores)

- Se quitan del dashboard los indicadores (`article.indicador`) y las consultas que
  solo servían para ellos. El resto del dashboard no cambia.

### 2026-11-17 (dashboard revertido)

- Se probó una revisión del dashboard y se deshizo a petición del usuario: el
  dashboard vuelve a ser el anterior. Se mantiene el número de versión en los scripts
  y estilos de las páginas para que el navegador no mezcle archivos de la caché.

### 2026-11-17 (E-11)

- E-11: instalador interactivo en cuatro pasos con modelo JSON (sustituye a
  `modeloDeDatos.txt`), claves foráneas reales, datos iniciales, vista previa del SQL,
  subida validada de `schema.sql` y token de instalación opcional. Comprobado que el
  asistente deja el mismo esquema que `schema.sql`.

### 2026-11-17 (E-12)

- E-12: copia de seguridad completa (base de datos y storage) en zip con cifrado
  AES-256 opcional, retención, bloqueo y registro; restauración probada en una base
  vacía; descarga desde Configuración. 111 + 8 pruebas superadas.

### 2026-11-17 (E-04)

- E-04: inventario en JSON con árbol de ubicaciones, listas de material (qué coger,
  de dónde y qué comprar), plantillas con versiones y documento para imprimir.
  Bloqueo de fichero probado con escrituras simultáneas. 107 pruebas superadas.

### 2026-11-17 (E-02)

- E-02: capa de dominio con mapeo objeto-relacional hecho a mano (`Modelo`,
  `Educando`, `Evento`, `Factura`, `Usuario`); las API la usan y se eliminan las
  funciones repetidas. 97 pruebas automáticas superadas.

### 2026-11-17 (E-14)

- E-14: documentación de inscripción. Catálogo de documentos base con plantillas que
  sube el administrador, entrega por el tutor desde la ficha del educando y revisión
  por un monitor. Se quitaron de `biblioteca/` dos PDF de pruebas antiguos.

### 2026-11-17 (E-05)

- E-05: facturas en tablero (pendiente, revisada, pagada) con miniaturas, imagen
  obligatoria, facturas pagadas bloqueadas y registro de quién cambia el estado.
  Migración de claves foráneas para bases antiguas y script de fotos de ejemplo.

### 2026-11-17 (E-13)

- E-13: roles y permisos con tablas (cargo básico único y cargos adicionales), función
  `tienePermiso`, pantalla de cargos en configuración, facturas propias para el monitor
  y migración en dos pasos. 69 pruebas automáticas superadas.

### 2026-11-17 (E-10 revertido)

- Se restauró la contraseña de desarrollo por defecto en `database.php` para seguir
  accediendo a la instalación local sin configurar Apache. E-10 vuelve a pendiente.

### 2026-11-17 (E-06)

- E-06: bajas de educandos con plazo de conservación configurable, borrado de datos
  vencidos desde configuración y registro de accesos a documentación sensible.

### 2026-11-16 (E-07)

- E-07: plan de pruebas en `docs/PLAN-PRUEBAS.md` (37 pruebas automáticas superadas
  y 20 manuales) y script `server/pruebas/pruebas.php` para repetirlas.

### 2026-11-16 (E-03)

- E-03: galería de fotos por ronda y tipo de evento, con permisos por rol y por
  sección, subida validada, miniaturas con el Web Worker y visor. Nuevo `.gitignore`
  para no subir archivos de usuarios al repositorio.

### 2026-11-16 (E-09)

- E-09: el instalador queda bloqueado cuando la aplicación está en uso (solo
  administrador). Se corrigió el instalador para que respete las claves primarias
  del modelo y una línea partida de `modeloDeDatos.txt`; ya instala el modelo completo.

### 2026-11-16 (E-08)

- E-08: `salida.php` exige sesión de administrador, excluye las contraseñas y se
  descarga desde configuración; se eliminó `entrada.php`, que estaba vacío.

### 2026-11-16 (E-10)

- E-10: se quitó la contraseña de MySQL por defecto de `database.php`; ahora solo
  se lee de la variable de entorno. Revisado el repositorio sin otras credenciales.

### 2026-11-16

- E-01: contraseñas con `password_hash`/`password_verify`, migración automática
  de sha256 al entrar, reglas de contraseña, hash oculto al cliente, primer
  administrador opcional en el instalador y `demo.sql` con hashes bcrypt.

### 2026-11-09

- Se amplió la configuración con identidad del grupo, logo subido, colores,
  modo oscuro, información de contacto, nombres de roles, archivos y listas.
- Se eliminó la configuración específica del calendario y se mejoró el contraste
  del modo oscuro.
- Se añadió la selección opcional de sección para los eventos y se filtraron las
  asistencias según la sección calculada de cada educando.
- Se sustituyó la selección única por casillas para convocar una o varias
  secciones, con la opción de convocar a todas.
- Se añadieron colores diferentes para los eventos del calendario según su tipo.
- Se cambió la tabla de facturas para mostrar el nombre del responsable en vez
  de su identificador.
- Se creó un dashboard con indicadores y gráficas específicas para administrador,
  scouter y tutor.
- Se limitó el ancho de una gráfica cuando es la única visible.
- Se agrupó el usuario y el botón de configuración en la cabecera de administrador.
- Se corrigió el selector de tipo de evento al editar un evento.
- Se limpió código no utilizado de las gráficas y se reutilizaron consultas del
  dashboard.
- Se añadió el botón de documentación de educandos para los usuarios autorizados.
- Se creó `storage/facturas/` y se añadió la subida validada de imágenes de facturas.
- Se unificaron las superficies y controles de las vistas mediante las clases
  `.vista` y `.vista-superficie`.
- Se corrigieron los márgenes de modales y del visor de circulares.
- Se reorganizó `estilo.css` con títulos por sección y estilos comunes para
  formularios y selectores.
- Se actualizó esta documentación con la arquitectura, los flujos, los permisos,
  las decisiones técnicas y las migraciones.
