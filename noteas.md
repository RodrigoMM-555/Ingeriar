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
│   ├── instalador.html             Formulario del instalador
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
│   │   ├── dashboard.js            Indicadores y gráficas por rol
│   │   ├── graficas.js             Gráficas SVG sin librerías externas
│   │   ├── exportacion.js          Exportación de tablas y dashboard
│   │   └── documento-preview-worker.js
│   └── servicios/salida.php        Entrada pública de la copia de seguridad
├── server/
│   ├── api/
│   │   ├── login.php               Inicio de sesión
│   │   ├── logout.php              Cierre de sesión
│   │   ├── superapi.php            API principal de datos y permisos
│   │   ├── archivo.php             Subida y descarga de documentos
│   │   ├── galeria.php             Fotos de la galería (listar, ver, subir, borrar)
│   │   └── instalar.php            Creación de la base de datos
│   ├── auth/sesion.php              Sesión y autenticación
│   ├── data/
│   │   ├── database.php             Conexión PDO
│   │   ├── schema.sql               Esquema MySQL completo
│   │   ├── demo.sql                 Datos de prueba
│   │   ├── modeloDeDatos.txt        Modelo usado por el instalador
│   │   ├── config.php               Configuración del grupo
│   │   └── secciones.php            Cálculo de secciones por edad
│   ├── pruebas/pruebas.php         Pruebas automáticas por HTTP (solo terminal)
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

## 6. Roles y permisos

### Administrador

- acceso completo a usuarios, educandos, eventos y facturas;
- gestión de la configuración del grupo;
- acceso al dashboard general;
- exportación de datos;
- consulta de documentación de cualquier educando.

### Scouter

- gestión de eventos, educandos y facturas;
- consulta de asistencias;
- subida de imágenes de facturas;
- dashboard de actividades y gastos;
- consulta de documentación de cualquier educando.

### Tutor

- consulta únicamente de sus educandos;
- consulta del calendario;
- registro de asistencia de sus educandos;
- subida de entregas cuando corresponda;
- consulta de la documentación de sus educandos;
- sin edición ni borrado general de datos.

Los permisos se comprueban siempre en el servidor. Ocultar un botón en el
cliente solo mejora la interfaz, pero no sustituye la comprobación de PHP.

## 7. Base de datos

La base de datos estable se llama `ingeriar`. Las tablas principales son:

- `usuarios`: cuentas, credenciales y roles;
- `educandos`: datos de los menores;
- `usuarios_educandos`: relación entre tutores y educandos;
- `eventos`: actividades del calendario;
- `educandos_asistencias`: asistencia y archivo de entrega;
- `facturas`: gastos, responsable e imagen opcional.

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

## 9. Facturas e imágenes

Las facturas contienen `imagen_factura`, una ruta relativa opcional. Al guardar:

1. PHP comprueba que la petición corresponde a una factura.
2. Comprueba tamaño máximo de 10 MB.
3. Comprueba el MIME real con `finfo`.
4. Acepta JPEG, PNG y WebP.
5. Genera un nombre aleatorio para evitar colisiones y nombres peligrosos.
6. Guarda la imagen en `storage/facturas/`.
7. Guarda el nombre generado en la factura.

Para una base instalada antes de esta funcionalidad:

```sql
ALTER TABLE facturas
    ADD COLUMN imagen_factura VARCHAR(255) NULL;
```

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

La circular fija se encuentra en:

```text
storage/documentos/biblioteca/circular1.pdf
```

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

## 11. Dashboard, gráficas y exportación

El dashboard usa la ruta `superapi.php?ruta=dashboard`, que devuelve indicadores
y datos ya agrupados para las gráficas. Esto evita cargar todas las tablas cuando
solo se necesitan resúmenes.

También muestra un resumen común con el próximo evento, los sábados del mes,
la próxima acampada y los cumpleaños de educandos de los próximos siete días.
En tutores, el próximo evento y el mini calendario solo incluyen actividades
compatibles con las secciones de sus educandos. Los monitores disponen de un
desglose por sección de las respuestas del próximo sábado. Los tutores ven
además circulares pendientes y asistencias sin confirmar. Los cumpleaños se
calculan exclusivamente desde `educandos`; los usuarios no tienen fecha de
nacimiento para este propósito.

La información depende del rol:

- administrador: usuarios, educandos, eventos, gasto total y usuarios por rol;
- scouter: educandos, eventos próximos, facturas propias y gasto propio;
- tutor: educandos vinculados y estados de asistencia.

`graficas.js` dibuja gráficas de barras, tarta y anillo con SVG. Los tooltips
indican valor y porcentaje.

`exportacion.js` permite exportar los datos visibles a impresión, HTML, JSON o
CSV. Se reutiliza desde tablas y dashboard para no duplicar controles.

## 12. Estilos y vistas

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
4. Ejecutar el esquema o usar el instalador con `modeloDeDatos.txt`. El
   instalador puede crear además el primer administrador; solo lo hace si la
   tabla `usuarios` está vacía. Una vez que hay usuarios, el instalador
   responde 403 salvo a un administrador con la sesión iniciada. El instalador
   no carga los valores iniciales de los catálogos: para eso, usar `schema.sql`.
5. Cargar `demo.sql` si se necesitan datos de prueba. La contraseña de cada
   usuario de prueba es `ingeriar-<numero>` (por ejemplo, `usuario1@ingeriar.test`
   / `ingeriar-1`).
6. Comprobar que existe la carpeta `storage/documentos/biblioteca/` y la
   circular fija.
7. Dar permisos de escritura al servidor web sobre `storage/documentos/`,
   `storage/facturas/`, `storage/galeria/` y `storage/configuracion/`.
8. Para subir varias fotos a la vez, revisar en `php.ini` `upload_max_filesize`,
   `post_max_size` y `max_file_uploads` (por defecto PHP limita a 2 MB por archivo).
9. En bases ya creadas, ejecutar `server/data/migraciones/2026-11-galeria.sql` y
   `server/data/migraciones/2026-11-bajas-accesos.sql`.

El archivo `.gitignore` excluye del repositorio todo lo que se sube desde la
aplicación (fotos, facturas, entregas y logo), porque puede contener datos reales.

`INGERIAR_DB_PASSWORD` es obligatoria: el código no tiene contraseña por
defecto para que ninguna credencial quede en el repositorio. Si falta, la
aplicación muestra un error de configuración. Una contraseña que haya estado
alguna vez en el repositorio debe considerarse pública y cambiarse.

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
