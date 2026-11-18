# Ingeriar · Documentación técnica

Documento de referencia para quien vaya a trabajar en el código: qué hace la aplicación,
cómo está construida, por qué se tomó cada decisión y dónde está cada cosa. Al final está
el diario de cambios (§21). El detalle de cada encargo está en `docs/ENCARGOS.md` y el de
cada cambio en `docs/REGISTRO-CAMBIOS.md`; las pruebas, en `docs/PLAN-PRUEBAS.md`.

Índice:

1. Qué es Ingeriar
2. Conceptos del dominio
3. Arquitectura
4. Tecnologías y motivos
5. Estructura de carpetas
6. Apache, despliegue y configuración del servidor
7. Flujo de una petición y arranque del cliente
8. Seguridad
9. Cargos y permisos
10. Base de datos
11. Almacenamiento de archivos (`storage/`)
12. Referencia de la API del servidor
13. Capa de dominio y funciones comunes del servidor
14. Cliente: módulos JavaScript
15. Funcionalidades
16. Estilos y criterios de interfaz
17. Instalación y migraciones
18. Copias de seguridad
19. Pruebas y datos de ejemplo
20. Forma de trabajar y temas pendientes
21. Diario de cambios

---

## 1. Qué es Ingeriar

Aplicación web de gestión interna del Grupo Scout Seeonee (proyecto intermodular de DAM,
2.º curso). Sustituye hojas de cálculo y papeles sueltos por una sola herramienta:

- usuarios con cargos (administración, monitores, familias, tesorería) y permisos;
- educandos (los niños y jóvenes), sus familias y su sección por edad;
- calendario de eventos, asistencias y circulares de autorización;
- documentación de inscripción (fichas) con su revisión;
- galería de fotos por ronda y evento;
- facturas de los monitores con su flujo de revisión y pago;
- inventario de material y listas de material para las salidas;
- conservación y borrado de datos personales según plazos;
- copias de seguridad, registro de accesos e instalador.

Cada persona ve un **dashboard** distinto según sus permisos.

## 2. Conceptos del dominio

| Concepto | Significado en la aplicación |
|---|---|
| **Ronda** | Curso scout, de septiembre a agosto. Se escribe `26-27`. La calcula `rondaActual($fecha)` (`server/data/secciones.php`). |
| **Sección** | Grupo por edades: colonia (5-6), manada (7-9), tropa (10-12), posta (13-15), rutas (16-19). Es un catálogo editable (`secciones`). |
| **Sección de un educando** | No se guarda: se calcula con su fecha de nacimiento (`naz`) y su edad **al empezar la ronda** de la fecha que interese (`seccionDeEducandoConCatalogo`). Así cambia sola cada septiembre y una foto antigua sigue siendo de la sección de entonces. |
| **Educando** | Niño o joven del grupo (`educandos`). Activo si `fecha_baja` es NULL. |
| **Tutor** | Familiar con cuenta de cargo `tutor`, vinculado a sus educandos en `usuarios_educandos`. |
| **Monitor (scouter)** | Educador del grupo. En el código el cargo se llama `scouter` y se muestra como «Monitor». |
| **Evento** | Actividad del calendario (sábado, acampada, excursión, festival, reunión…). Convoca a unas secciones (`eventos_secciones`); sin filas, convoca a todas. |
| **Circular** | Autorización de participación que las familias entregan para un evento con `circular = 1`. La plantilla en blanco es el documento base de tipo `participacion`. |
| **Ficha (documentación de inscripción)** | Documento permanente del educando (ficha sanitaria, inscripción, autorizaciones…). Catálogo `documentos_base` de tipo `inscripcion`; entregas en `documentos_educando`. |
| **Baja** | Fecha en que el educando deja el grupo. Sus datos se conservan un plazo (5 años por defecto) y luego se borran. |
| **Ronda vencida** | Ronda que terminó hace más que el plazo: se borran sus circulares y asistencias. |

## 3. Arquitectura

```text
Navegador (HTML + CSS + JavaScript sin frameworks)
   │  fetch (JSON o FormData), cookie de sesión PHP
   ▼
Apache ── DocumentRoot: public/
   │  public/api/*.php  → solo hacen require del archivo real
   ▼
server/api/*.php  (PHP 8, sin frameworks)
   │  sesión → exigirUsuario() → permisos → validación
   ├── PDO (consultas preparadas) ──► MySQL / MariaDB (base relacional)
   ├── AlmacenJson (flock) ─────────► storage/inventario/*.json (base documental)
   └── almacenamiento.php / finfo ──► storage/ (archivos privados, nunca servidos directamente)
```

Principios que se repiten en todo el código:

- **El servidor decide**: los permisos, qué está vencido, de quién es cada cosa. El cliente
  solo oculta botones para que la interfaz sea clara (`utilidades.tienePermiso`).
- **Nada se sirve directamente de `storage/`**: todo archivo pasa por un PHP que comprueba
  sesión, permiso y que la ruta real (`realpath`) está dentro de la carpeta esperada.
- **Sin dependencias externas** (ni Composer ni npm): se ve cómo funciona todo y solo se usa
  lo que se estudia en DAM2.
- **Datos derivados, no duplicados**: la sección, la ronda o el árbol de la galería se
  calculan; no se guardan copias que puedan desincronizarse.

## 4. Tecnologías y motivos

| Tecnología | Uso | Motivo |
|---|---|---|
| HTML, CSS, JavaScript (ES2022) | Toda la interfaz, en una sola página (`index.html`) con clases por vista | Proyecto educativo: se ve el DOM, los eventos y `fetch` sin capas intermedias; no hay compilación ni dependencias. |
| PHP 8 | API, sesiones, validación, archivos | Encaja con Apache y con el temario. Se usan tipos declarados, `match` y `never`. |
| PDO + MySQL 8 / MariaDB | Datos relacionales | Claves foráneas, transacciones, consultas preparadas (`ATTR_EMULATE_PREPARES = false`). |
| `information_schema` | Formularios y tablas genéricas | Descubre columnas, tipos, ENUM y claves foráneas para generar formularios automáticos. |
| JSON en ficheros + `flock` | Inventario y listas de material | Requisito del proyecto: una parte en base documental. Lectura con bloqueo compartido, escritura con exclusivo y `rename` atómico. |
| SVG | Gráficas del dashboard (`graficas.js`) | Sin librerías; tooltips y colores con el DOM. |
| Web Worker | Miniaturas de imágenes y PDF (`documento-preview-worker.js`) | No bloquea la interfaz. Máximo cuatro a la vez. |
| `pdftoppm` (poppler) | Primera página de un PDF como imagen | Previsualizar circulares y fichas en PDF. |
| `finfo` | Tipo real de cada archivo subido | No fiarse de la extensión ni del tipo que dice el navegador. |
| `mysqldump`, `PharData`, OpenSSL | Copias de seguridad | Zip sin la extensión zip; cifrado AES-256-CBC + HMAC con clave por PBKDF2. |
| Apache + `.htaccess` | Servidor web | Entorno habitual; `.htaccess` deniega `server/`, `storage/` y `copias/`. |

Extensiones PHP necesarias: `pdo_mysql`, `fileinfo`, `curl` (solo scripts de prueba),
`openssl`, `phar`, `iconv`. No se usa `mbstring` ni `gd` (las longitudes se cuentan con
expresiones regulares UTF-8 y las imágenes de ejemplo se dibujan en PHP puro).

## 5. Estructura de carpetas

```text
Ingeriar/
├── CLAUDE.md                     Reglas del proyecto (stack, seguridad, forma de trabajar)
├── noteas.md                     Este documento
├── docs/                         ENCARGOS.md, REGISTRO-CAMBIOS.md, PLAN-PRUEBAS.md
├── public/                       ÚNICA carpeta accesible desde Apache (DocumentRoot)
│   ├── .htaccess                 Sin listados; deniega .env, .ini, .log, .sql, .db, .bak
│   ├── index.html                Aplicación (carga todos los scripts con ?v=<versión>)
│   ├── login.html, login.js      Acceso
│   ├── instalador.html, instalador.js   Instalador en cuatro pasos (E-11)
│   ├── codigo.js                 Arranque: crea los objetos de cada vista y el menú
│   ├── estilo.css                Todos los estilos, por secciones
│   ├── api/*.php                 Puntos de entrada: require de server/api/<mismo>.php
│   ├── servicios/salida.php      Entrada de la exportación JSON
│   └── nucleo/                   Clases del cliente (ver §14)
├── server/                       Código del servidor (denegado por .htaccess)
│   ├── api/                      Una API por área (ver §12) + comun.php
│   ├── auth/                     sesion.php, permisos.php, contrasenas.php, registro.php
│   ├── dominio/                  Modelo, Educando, Evento, Factura, Usuario, AlmacenJson
│   ├── data/
│   │   ├── database.php          obtenerConexion() con variables de entorno
│   │   ├── config.php            Configuración del grupo (JSON, aunque termine en .php)
│   │   ├── secciones.php         Ronda, sección por edad, nombre de carpeta del educando
│   │   ├── almacenamiento.php    Rutas de galería y facturas en storage/ (E-17)
│   │   ├── schema.sql            Esquema completo + datos iniciales
│   │   ├── demo.sql              Datos de prueba sencillos (usuarioN, educandoN…)
│   │   ├── modelo.json           Modelo del instalador (generado desde schema.sql)
│   │   ├── semillas/             catalogos.sql, roles-permisos.sql, documentos-base.sql
│   │   └── migraciones/          Scripts para bases antiguas (SQL y PHP)
│   ├── instalacion/ModeloDatos.php   Valida el modelo y genera el SQL del instalador
│   ├── herramientas/generar-modelo.php
│   ├── copias/                   copia.php (cron), restaurar.php, comun-copias.php
│   ├── servicios/salida.php      Exportación JSON de todas las tablas (sin contraseñas)
│   └── pruebas/                  Pruebas automáticas y scripts de datos de ejemplo (§19)
├── storage/                      Archivos privados (denegado; contenido fuera de git)
└── copias/                       Copias de seguridad (denegado; fuera de git)
```

## 6. Apache, despliegue y configuración del servidor

**DocumentRoot**: debe ser `public/`. Si por comodidad se publica la carpeta del proyecto
entera (como en la instalación local `http://localhost/Ingeriar/public`), los `.htaccess`
de `server/`, `storage/` y `copias/` (`Require all denied`) impiden el acceso; para eso
Apache necesita `AllowOverride All` en esa carpeta.

**Variables de entorno** (en el VirtualHost, con `mod_env`):

```apache
SetEnv INGERIAR_DB_HOST localhost
SetEnv INGERIAR_DB_PORT 3306
SetEnv INGERIAR_DB_NAME ingeriar
SetEnv INGERIAR_DB_USER ingeriar_app
SetEnv INGERIAR_DB_PASSWORD <clave>
# Opcionales
SetEnv INGERIAR_TOKEN_INSTALACION <token>     # obliga a escribirlo en el instalador
SetEnv INGERIAR_COPIAS_CLAVE <clave>          # cifra las copias de seguridad
SetEnv INGERIAR_COPIAS_DESTINO /ruta/copias   # por defecto copias/ del proyecto
SetEnv INGERIAR_COPIAS_RETENER 10
```

`obtenerConexion()` lee cada variable con `getenv()` o `$_SERVER`. **Provisional**: si falta
`INGERIAR_DB_PASSWORD` usa la contraseña de desarrollo escrita en `database.php` (decisión
del usuario, E-10 revertido); en un servidor real hay que definir la variable y usar otra
contraseña, porque la del código es pública. Nunca se conecta como `root`.

**Usuario de MySQL**: `ingeriar_app` con permisos solo sobre `ingeriar.*` (y
`ingeriar_instalacion.*` si se usan las pruebas del instalador).

**Permisos de archivos**: el usuario de Apache (`www-data`) debe poder escribir en
`storage/` y sus subcarpetas (`chgrp -R www-data storage && chmod -R g+rwX storage`). Lo
que crea Apache queda con su usuario; los scripts que mueven archivos (migraciones) se
ejecutan con `sudo -u www-data`.

**PHP** (`php.ini`): `upload_max_filesize`, `post_max_size` y `max_file_uploads`
suficientes para subir varias fotos (la aplicación limita a su vez con
`tamano_maximo_archivos_mb`). `session.cookie_httponly` lo fuerza `sesion.php`.

**Despliegue local de desarrollo**: el repositorio se copia a Apache sin `storage/` ni
`copias/` (que son de la instalación):

```bash
rsync -a --exclude='.git' --exclude='/storage/' --exclude='/copias/' ./ /var/www/html/Ingeriar/
```

**Caché del navegador**: `index.html`, `login.html` e `instalador.html` cargan scripts y
`estilo.css` con `?v=<fecha+letra>`. Al cambiar cualquier JS o CSS hay que subir ese
número en las tres páginas; si no, el navegador puede mezclar archivos nuevos y viejos.

## 7. Flujo de una petición y arranque del cliente

**Inicio de sesión**

1. `login.html` → `login.js` envía `{email, contrasena}` a `api/login.php`.
2. `login.php` busca el usuario con PDO, comprueba con `verificarContrasena` (bcrypt; las
   antiguas en sha256 se reescriben al entrar) y guarda en `$_SESSION` el id y los datos
   básicos. Si el correo no existe calcula un hash igualmente (mismo tiempo de respuesta).
3. Redirige a `index.html`.

**Arranque de `index.html`** (`codigo.js`)

1. Crea un objeto por vista: `IngeriarTabla`, `IngeriarFormulario`, `IngeriarCalendario`,
   `IngeriarDashboard`, `IngeriarGaleria`, `IngeriarFacturas`, `IngeriarInventario`,
   `IngeriarConfiguracion`, `IngeriarDocumentos`, `IngeriarExportacion`.
2. Pide `superapi.php?ruta=usuario_actual` (usuario, cargos y permisos). Sin sesión → login.
3. `configuracion.cargar()` aplica nombre, logo y colores del grupo (variables CSS).
4. `cargarEntidades()` pide `ruta=entidades` (tablas que el usuario puede ver en el menú) y
   añade los enlaces fijos: Dashboard, Calendario, Galería, Facturas e Inventario (estos
   dos, solo con su permiso). El botón ⚙ de Configuración aparece con
   `configuracion.gestionar`.
5. Se muestra el dashboard.

**Una petición cualquiera**

```text
fetch("api/<area>.php?...")            utilidades.obtenerJson / enviarJson / enviarFormulario
 → public/api/<area>.php                require del archivo real
 → server/api/<area>.php
     obtenerConexion()                  PDO
     exigirUsuario($db)                 401 sin sesión; recarga cargos y permisos (una consulta)
     exigirPermiso('…') / reglas        403 si no puede
     validación de datos                400 / 404 / 409 / 413 / 415 con {"error": "…"}
     consulta preparada / archivo
     responder([...])                   JSON con el código HTTP
 → utilidades convierte un error HTTP o {"error"} en una excepción con el mensaje
```

Los cambios de cargos o permisos de un usuario tienen efecto en su siguiente petición,
porque `exigirUsuario()` los vuelve a cargar siempre.

## 8. Seguridad

- **Contraseñas**: `password_hash` (bcrypt con sal) y `password_verify`
  (`server/auth/contrasenas.php`). Política: 8 caracteres o más y al menos un número o
  símbolo, comprobada en el servidor. El hash nunca sale del servidor (la ruta `tabla` no
  lo devuelve; al editar, vacío = se conserva). Las sha256 antiguas se migran al entrar;
  para ver cuántas quedan: ``SELECT COUNT(*) FROM usuarios WHERE `contraseña` NOT LIKE '$2%'``.
- **Sesión**: cookie `HttpOnly`, `SameSite=Lax` y `Secure` con HTTPS (`sesion.php`).
- **Permisos siempre en el servidor** (§9). Ocultar botones en el cliente es solo comodidad.
- **SQL**: consultas preparadas con PDO; los nombres de tabla o columna que no pueden ir
  como parámetro salen del esquema (`information_schema`) o de constantes, nunca del
  usuario, y se escriben entre comillas invertidas escapadas.
- **Archivos subidos**: tipo real con `finfo`, tamaño máximo de la configuración, nombre
  aleatorio (`random_bytes`), guardados en `storage/` y servidos solo por PHP con
  `X-Content-Type-Options: nosniff`. Las rutas se comprueban con `realpath` dentro de la
  carpeta base antes de leer, mover o borrar (evita `../`).
- **Registro de accesos** (`registro_accesos`): quién, qué, sobre qué educando, cuándo y
  desde qué IP; solo se inserta desde PHP y nadie lo puede editar ni borrar. Se registran
  ver y previsualizar entregas, subirlas, descargar copias y borrados por conservación.
- **Instalador**: con usuarios ya creados solo lo usa un administrador con sesión; con
  `INGERIAR_TOKEN_INSTALACION` además pide el token. El SQL lo genera el servidor.
- **Protección de datos**: plazos de conservación (§15.9) y copias cifradas (§18). El
  repositorio es público: solo datos ficticios (dominios `.test`) y nada de `storage/`.
- **Pendiente (E-10)**: quitar la contraseña de desarrollo de `database.php`.

## 9. Cargos y permisos

Control de acceso basado en roles (E-13):

```text
usuarios ─< usuarios_roles >─ roles ─< roles_permisos >─ permisos
```

- **Cargo básico** (`roles.basico = 1`): administrador, monitor (`scouter`) o tutor. Todo
  usuario tiene exactamente uno (`validarRolesUsuario`).
- **Cargos adicionales**: tesorería y los que cree el administrador. Se suman al básico.
- **Permisos de un usuario** = unión de los permisos de sus cargos activos
  (`cargarPermisosUsuario`). `tienePermiso()`, `tieneAlgunPermiso()` y `exigirPermiso()`
  son las únicas comprobaciones; lo que depende de «ser tutor de» se comprueba además con
  `usuarios_educandos` (`Educando::esTutorDe`, solo educandos activos).
- **Tablas genéricas**: `superapi.php` tiene `PERMISOS_TABLAS` (tabla → permisos de
  lectura, escritura, borrado y menú). Una tabla que no esté en el mapa exige
  `configuracion.gestionar`.
- **Para no bloquearse**: los permisos del administrador no se editan, los cargos básicos
  no se desactivan y se rechaza cualquier cambio que deje a nadie con `roles.gestionar`
  (`usuariosQueGestionanRoles`).

| Permiso | Para qué sirve | Admin | Monitor | Tutor | Tesorería |
|---|---|:-:|:-:|:-:|:-:|
| `usuarios.ver` | Ver usuarios (elegir responsables) | ✓ | ✓ | | |
| `usuarios.gestionar` | Crear, editar y borrar usuarios y asignar cargos | ✓ | | | |
| `educandos.ver_propios` | Ver solo sus educandos | | | ✓ | |
| `educandos.ver_todos` | Ver todos los educandos | ✓ | ✓ | | |
| `educandos.gestionar` | Crear y editar educandos (también la baja) | ✓ | ✓ | | |
| `educandos.borrar` | Borrar educandos | ✓ | | | |
| `eventos.ver` / `eventos.gestionar` | Ver el calendario / crear, editar y borrar eventos | ✓ | ✓ / ✓ | ✓ / | |
| `asistencia.responder_propios` | Responder asistencias y subir circulares de sus educandos | | | ✓ | |
| `asistencia.ver_todas` | Ver todas las asistencias | ✓ | ✓ | | |
| `asistencia.gestionar` | Editar la tabla de asistencias | ✓ | | | |
| `documentos.ver_todos` | Ver la documentación de cualquier educando | ✓ | ✓ | | |
| `documentos.subir_propios` | Entregar las fichas de sus educandos | | | ✓ | |
| `documentos.revisar` | Revisar fichas y entregarlas por las familias | ✓ | ✓ | | |
| `galeria.ver` / `galeria.subir` | Ver la galería / subir y borrar fotos | ✓ | ✓ / ✓ | ✓ / | |
| `facturas.subir_propias` | Subir y gestionar sus facturas | ✓ | ✓ | | |
| `facturas.ver_todas` | Ver las facturas de todos | ✓ | | | ✓ |
| `facturas.cambiar_estado` | Revisar y pagar facturas | ✓ | | | ✓ |
| `facturas.gestionar_todas` | Editar y borrar facturas de otros | ✓ | | | |
| `inventario.gestionar` | Inventario y listas de material | ✓ | ✓ | | |
| `configuracion.gestionar` | Configuración del grupo y catálogos | ✓ | | | |
| `roles.gestionar` | Crear cargos y editar permisos | ✓ | | | |
| `copia.descargar` | Copias de seguridad y exportación JSON | ✓ | | | |
| `conservacion.gestionar` | Borrar datos vencidos y rondas vencidas | ✓ | | | |
| `registro.ver` | Consultar el registro de accesos | ✓ | | | |

El administrador tiene todos menos los «propios» (que describen una relación, no una
capacidad). Los cargos y su tabla de permisos se editan en Configuración → «Cargos y
permisos».

## 10. Base de datos

Base `ingeriar`, motor InnoDB, `utf8mb4`. Esquema completo en `server/data/schema.sql`.

```text
usuarios ─< usuarios_educandos >─ educandos ─< educandos_asistencias >─ eventos ─< eventos_secciones
   │                                  │                                     │
   ├─< usuarios_roles >─ roles ─< roles_permisos >─ permisos              └─< galeria_fotos
   ├─< facturas (responsable_id, estado_cambiado_por)
   ├─< documentos_educando (subido_por, revisado_por) >─ educandos
   └─< registro_accesos                              documentos_base ─< documentos_educando
Catálogos: tipos_evento, secciones, categorias_factura, documentos_base
```

| Tabla | Contenido y columnas clave |
|---|---|
| `usuarios` | Cuentas: nombre, apellidos, `email` y `telefono` únicos, `contraseña` (hash). |
| `roles`, `permisos`, `usuarios_roles`, `roles_permisos` | Cargos (`basico`, `activo`, `orden`), permisos y sus asignaciones. |
| `educandos` | nombre, apellidos, `DNI` (único), `naz` (nacimiento), `fecha_baja`. |
| `usuarios_educandos` | Vínculo tutor–educando (un educando puede tener varios tutores). |
| `eventos` | nombre, `fecha_inicio`, `fecha_fin`, `id_responsable`, lugar, municipio, comunidad, `tipo` (código de `tipos_evento`), `circular`. |
| `eventos_secciones` | Secciones convocadas por evento (PK compuesta). Sin filas = todas. |
| `educandos_asistencias` | Respuesta por educando y evento: `estado` (`pendiente`, `si`, `no`) y `archivo_entrega` (circular). Única por (educando, evento). |
| `galeria_fotos` | Foto: `id_evento`, `archivo` (ruta relativa en `storage/galeria/`), nombre original, quién la subió. |
| `facturas` | responsable, número, CIF, fechas de emisión y compra, concepto, precio, categoría, `estado` (`pendiente`, `revisada`, `pagada`), `imagen_factura`, quién y cuándo cambió el estado. |
| `documentos_base` | Catálogo de documentos: `tipo` (`participacion` = circular de eventos, `inscripcion` = ficha), plantilla en blanco, obligatorio, activo, orden. |
| `documentos_educando` | Ficha entregada: educando, documento, archivo, quién la subió, `estado` (`pendiente`, `revisado`, `corregir`), observaciones, quién y cuándo la revisó. Única por (educando, documento). |
| `registro_accesos` | Registro inalterable de accesos a documentación sensible. |
| `tipos_evento`, `secciones`, `categorias_factura` | Catálogos con `codigo` estable, nombre visible, `activo` y `orden` (las secciones, también edades). Retirar = `activo = 0`, para conservar el histórico. |

Las claves foráneas usan `ON DELETE CASCADE` donde lo dependiente no tiene sentido solo
(asistencias, fotos, vínculos) y `SET NULL` en referencias a usuarios que pueden borrarse
(responsable de un evento, quién revisó). `Educando::borrarConDependencias()` borra a mano
lo dependiente por si la base se creó con el instalador antiguo sin claves foráneas.

**Configuración del grupo** (`server/data/config.php`, JSON): `nombre_grupo`,
`logo_archivo`, `color`, `color_secundario`, `modo_oscuro`, datos de contacto (`cif`,
`direccion`, `municipio`, `provincia`, `telefono`, `correo`, `coordinador`),
`formato_nombre`, `mostrar_educandos_sin_seccion`, `permitir_asistencia_pasada`,
`tamano_maximo_archivos_mb`, `formatos_entrega` y `plazo_conservacion_anos`.

## 11. Almacenamiento de archivos (`storage/`)

```text
storage/
├── .htaccess                         Require all denied
├── configuracion/                    Logo del grupo
├── documentos/
│   ├── biblioteca/                   Plantillas en blanco de documentos_base (nombre aleatorio)
│   ├── permanente/<id>-<educando>/   Fichas de inscripción (E-16): no dependen de la ronda
│   └── <ronda>/<sección>/<id>-<educando>/   Circulares de eventos (ronda y sección del evento)
├── galeria/<ronda>/<id>-<evento>/    Fotos (E-17): ronda de la fecha de inicio del evento
├── facturas/<ronda>/                 Imágenes de facturas (E-17): ronda de la fecha de compra
└── inventario/                       inventario.json, listas.json y .bloqueo (E-04)
```

- En la base de datos se guarda la **ruta relativa** a la carpeta de su tipo.
- `server/data/almacenamiento.php` reúne las rutas de galería y facturas: `baseAlmacen`,
  `rutaEnAlmacen` (ruta segura), `carpetaGaleriaEvento`, `carpetaFactura`,
  `guardarSubidoEn`, `borrarDeAlmacen` (quita carpetas vacías), `recolocarEnAlmacen`,
  `recolocarFotosEvento` y `recolocarImagenFactura`. Al cambiar la fecha o el nombre de un
  evento, o la fecha de compra de una factura, el archivo se mueve a su carpeta nueva.
- Los nombres de carpeta llevan el id delante (`45-acampada-de-otono`): no chocan aunque
  dos elementos se llamen igual.
- Las fotos no se borran con las rondas vencidas (decisión del usuario); las circulares sí.
- Todo `storage/` (salvo los `.gitkeep`) está en `.gitignore`: puede contener datos reales.

## 12. Referencia de la API del servidor

Todas responden JSON (salvo las descargas) y exigen sesión salvo `login.php` y el
instalador vacío. Entre paréntesis, el permiso necesario.

**`login.php`** (POST `{email, contrasena}`) y **`logout.php`**.

**`superapi.php?ruta=`** (API general):

| Ruta | Método | Qué hace |
|---|---|---|
| `usuario_actual` | GET | Usuario, cargos y permisos de la sesión. |
| `dashboard` | GET | Datos del dashboard según permisos (§15.1). |
| `entidades` | GET | Tablas del menú que el usuario puede ver. |
| `tabla&tabla=` | GET | Columnas y registros de una tabla (lectura de `PERMISOS_TABLAS`). Usuarios sin hash; educandos con su sección; eventos con sus convocatorias; tutores solo ven lo suyo. |
| `columnas&tabla=` | GET | Columnas con tipo, ENUM y relaciones (formularios). |
| `crear`, `editar` | POST `tabla`, `id`, `datos` (JSON) | Alta y edición genéricas (escritura). Reglas especiales: usuarios (contraseña, cargos, educandos vinculados), eventos (tipo válido, convocatorias, recolocar fotos). Las facturas no se escriben aquí. |
| `borrar` | POST `tabla`, `id` | Borrado genérico (borrado). Al borrar un evento borra también sus fotos del disco. |
| `catalogos` | GET / POST | Tipos de evento, secciones y categorías (POST: `configuracion.gestionar`). |
| `configuracion`, `configuracion_logo` | GET / POST | Configuración del grupo y logo (POST: `configuracion.gestionar`). |
| `roles` | GET / POST | Cargos y permisos (`roles.gestionar`). |
| `asistencia_tutor` | GET `evento` / POST `{evento, educando, estado}` | Respuestas de un tutor para sus educandos (`asistencia.responder_propios`); no en eventos pasados. |
| `educando_documentos&educando=` | GET | Circulares entregadas de un educando. |
| `educandos_usuario&usuario=` | GET | Educandos vinculados a un usuario (`usuarios.gestionar`). |
| `conservacion` | GET / POST | Educandos con baja vencida y tutores que se quedarían sin educandos; POST los borra (`conservacion.gestionar`). |
| `conservacion_rondas` | GET / POST | Rondas vencidas; POST borra su carpeta y sus asistencias (`conservacion.gestionar`). |

**`archivo.php`** (circulares de eventos):
GET `tipo=circular&evento=` (plantilla en blanco), GET `tipo=entrega&evento=&educando=`
(ver la entregada) y `tipo=preview` (miniatura; PDF con `pdftoppm`); POST `tipo=entrega`
con `archivo` (tutor del educando, evento con circular y sin terminar).

**`documentos.php?accion=`** (documentación de inscripción): `plantillas` (catálogo;
`&todos=1` para configuración), `plantilla&codigo=` (descargar en blanco), `educando&id=`
(estado de cada ficha), `ver&id=`, `subir` (POST `id_educando`, `codigo`, `archivo`),
`revisar` (POST `id`, `estado`, `observaciones`; `documentos.revisar`) y
`guardar_plantilla` (POST; `configuracion.gestionar`).

**`galeria.php?accion=`**: `listar` (árbol ronda › tipo › evento con las fotos visibles),
`ver&id=` (`&descargar=1`), `subir` (POST `evento`, `fotos[]`; `galeria.subir`) y
`borrar` (POST `id`).

**`facturas.php?accion=`**: `listar`, `imagen&id=`, `crear`, `editar`, `borrar` (POST con
los campos y la `imagen`) y `estado` (POST `id`, `estado`; `facturas.cambiar_estado`).

**`inventario.php?accion=`** (`inventario.gestionar`): `datos`, `guardar_ubicacion`,
`borrar_ubicacion`, `guardar_material`, `borrar_material`, `mover_material`,
`guardar_lista`, `copiar_lista`, `borrar_lista` (detalle en la cabecera del archivo).

**`copias.php?accion=`** (`copia.descargar`): `listar` y `descargar&nombre=`.

**`instalar.php?accion=`**: `estado`, `modelo`, `vista_previa`, `instalar`, `instalar_sql`.

**`servicios/salida.php`** (`copia.descargar`): todas las tablas en JSON, sin contraseñas.

## 13. Capa de dominio y funciones comunes del servidor

**Modelo (Active Record hecho a mano, `server/dominio/`)**. Cada subclase declara su tabla
y el mapa columna → propiedad; `Modelo` aporta `desdeFila`, `rellenar`, `buscar`, `donde`,
`aFila`, `guardar` (INSERT o UPDATE), `borrar` y `jsonSerialize`. Es lo que hace un ORM
(Doctrine, Hibernate) con anotaciones, sin dependencias.

| Clase | Reglas que concentra |
|---|---|
| `Educando` | `estaActivo`, `estabaActivoEn(fecha)`, `seccion(catálogo, fecha)`, `carpeta()`, `esTutorDe`, `deTutor`, `vencidos(fechaLímite)`, `archivos()` (circulares y fichas), `borrarConDependencias()`. |
| `Evento` | `convocatoriasDeTodos`, `convocaA(convocadas, secciones)`, `haTerminado`, `archivosGaleria`. |
| `Factura` | `esDe`, `puedeVerla`, `motivoNoEditable` (pagada, de otro…), `cambiarEstado` (anota quién y cuándo), `listadoPara(usuario)`. |
| `Usuario` | `nombreCompleto`, `cargosDeTodos`, `asignarCargos`, `asignarEducandos`; el JSON nunca incluye la contraseña. |
| `AlmacenJson` | `leer` (bloqueo compartido), `modificar` (bloqueo exclusivo, valida dentro, escribe en temporal y `rename`), `nuevoId`. |

Las rutas genéricas de `superapi.php` siguen trabajando con filas (se adaptan a cualquier
tabla); las reglas de negocio están en estas clases.

**Funciones comunes**:

- `server/api/comun.php`: `responder($datos, $código)`, `tamanoMaximoArchivos()`,
  `borrarCarpetaDentroDe($carpeta, $base)`.
- `server/auth/sesion.php`: arranca la sesión con cookie segura; `usuarioActual()`.
- `server/auth/permisos.php`: `cargarPermisosUsuario`, `exigirUsuario`, `tienePermiso`,
  `tieneAlgunPermiso`, `exigirPermiso`, `usuarioTienePermiso`, `usuariosQueGestionanRoles`,
  `validarRolesUsuario`.
- `server/auth/contrasenas.php`: `validarContrasena`, `cifrarContrasena`,
  `verificarContrasena`, `contrasenaNecesitaActualizarse`.
- `server/auth/registro.php`: `registrarAcceso($db, $usuario, $acción, $recurso, $educando, $detalle)`.
- `server/data/secciones.php`: `rondaActual(fecha)`, `seccionDeEducando`,
  `seccionDeEducandoConCatalogo`, `nombreCarpetaEducando`.
- `server/data/almacenamiento.php`: ver §11.

## 14. Cliente: módulos JavaScript

Todas las clases están en `public/nucleo/` y se cargan como scripts normales (sin
módulos ES) desde `index.html`. Cada vista pinta dentro de `main > section`.

| Archivo / clase | Responsabilidad |
|---|---|
| `codigo.js` | Arranque, menú lateral (`cargarEntidades`, enlaces fijos), cambio de vista. |
| `utilidades.js` · `IngeriarUtilidades` | `obtenerJson`, `enviarJson`, `enviarFormulario` (convierte errores en excepciones), `tienePermiso`, cola de miniaturas con el Web Worker, textos comunes. |
| `tabla.js` · `IngeriarTabla` | Tablas genéricas: buscador por columna, orden, colores por sección, bajas en cursiva, acciones (ver documentación, editar, borrar) según permisos. |
| `formulario.js` · `IngeriarFormulario` | Formularios automáticos a partir de las columnas: fecha, número, ENUM, sí/no, relaciones con nombres legibles, secciones convocadas, educandos vinculados y cargos. |
| `calendario.js` · `IngeriarCalendario` | Calendario mensual, eventos por día con color por tipo, alta y edición, y la ventana de asistencias (tutor: responder y subir circular; monitor: ver por sección). |
| `dashboard.js` · `IngeriarDashboard` | Bloques por permiso (§15.1). |
| `graficas.js` · `DarkorangeGraficas` | Gráficas SVG: barras, tarta, anillo y el anillo doble agrupado por sección. |
| `galeria.js` · `IngeriarGaleria` | Carpetas ronda › tipo › evento con migas, visor con teclado, subida múltiple y borrado. |
| `facturas.js` · `IngeriarFacturas` | Tablero de tres columnas con arrastrar entre estados, agrupado por responsable para tesorería, detalle e imagen. |
| `documentos.js` · `IngeriarDocumentos` | Fichas de inscripción de un educando (descargar plantilla, entregar, revisar) y el bloque de plantillas de Configuración. |
| `inventario.js` · `IngeriarInventario` | Plano y tabla del inventario, arrastrar y soltar, listas con reparto automático, documento para imprimir. |
| `configuracion.js` · `IngeriarConfiguracion` | Configuración del grupo, catálogos, cargos y permisos, copias, datos vencidos y rondas vencidas. |
| `exportacion.js` · `IngeriarExportacion` | Exportar lo visible a impresión, HTML, JSON o CSV. |
| `documento-preview-worker.js` | Web Worker que genera miniaturas sin bloquear la página. |
| `login.js`, `instalador.js` | Acceso e instalador. |

Convenciones: nombres en español, clases con prefijo `Ingeriar`, sin `innerHTML` con datos
del usuario (se usa `textContent`), los permisos del cliente solo deciden qué se enseña.

## 15. Funcionalidades

### 15.1 Dashboard

`superapi.php?ruta=dashboard` devuelve solo los bloques que el usuario puede ver:

- **Próximas actividades** (todos): próximo sábado, próxima acampada (cuánto falta),
  próximo evento de otro tipo y, salvo para tutores, cumpleaños de la semana (domingo a
  sábado). Para un tutor, solo eventos que convocan a las secciones de sus educandos.
- **Asistencias del sábado** (`asistencia.ver_todas`): anillo doble. Dentro, una porción
  por sección; fuera, sus tramos (asiste con circular, asiste sin ella, sin responder, no
  asiste) en tonos del color de la sección, con una tabla de cifras.
- **Pendientes de este mes** (tutor): una ficha por educando y evento de este mes sin
  terminar en el que falta confirmar la asistencia o, si va, entregar la circular; con el
  color de la sección. **Calendario del trimestre**: próximos eventos hasta fin de trimestre.
- **Facturas** (`facturas.cambiar_estado`): pendientes de revisar y de pagar, con totales
  (las 8 más antiguas de cada una).

### 15.2 Eventos, asistencias y circulares

- Un evento convoca a unas secciones; sin ninguna, a todas. El formulario tiene casillas y
  «Todas las secciones».
- El tutor responde sí o no por cada educando convocado mientras el evento no ha terminado.
  Si el evento lleva circular, la descarga en blanco (plantilla `participacion`) y sube la
  firmada; se guarda en la carpeta de la ronda y la sección **del evento**.
- Los monitores ven las respuestas por sección y las circulares (cada vista queda en el
  registro de accesos). La tabla de asistencias solo la edita quien tiene
  `asistencia.gestionar`.

### 15.3 Educandos y documentación de inscripción

- Tabla de educandos con color por sección; las bajas, al final y en cursiva.
- «Ver documentación» muestra las fichas del catálogo: plantilla en blanco, entregada o
  no, estado de revisión y observaciones, y sus circulares.
- El tutor (o un monitor, con copia en papel) entrega cada ficha una vez; volver a subirla
  la sustituye y la deja pendiente. Un monitor la marca revisada o pide corrección.
- Se guardan en `storage/documentos/permanente/<educando>/`.

### 15.4 Galería

Árbol calculado ronda › tipo de evento › evento. Administración y monitores ven, suben (20
fotos por subida, validadas todas antes de guardar ninguna) y borran. Un tutor solo ve los
eventos que convocaban a la sección que tenían sus educandos en esa fecha y en los que
seguían en el grupo.

### 15.5 Facturas

Tablero Pendientes → Revisadas → Pagadas. Cada monitor ve, edita y borra solo las suyas
mientras están pendientes; la imagen es obligatoria. Tesorería y administración ven todas
(agrupadas por responsable) y cambian el estado con botones o arrastrando; una pagada no
se edita ni se borra. Se guarda quién y cuándo cambió el estado.

### 15.6 Inventario y listas de material

- Base documental en JSON (`inventario.json`: ubicaciones en árbol y materiales con sus
  existencias por ubicación; `listas.json`: listas y plantillas).
- **Plano**: cada ubicación es una caja con sus sububicaciones dentro y una ficha por
  material. Arrastrar una ficha a otra caja mueve la cantidad elegida (`mover_material`,
  comprobado dentro del bloqueo). Arrastrar el título de una caja la mete en otra. Una
  caja ocupa tantas columnas como sububicaciones tiene, calculado en JS
  (`ajustarAnchos`). Hay vista de **tabla** y botones para hacerlo sin ratón.
- **Listas**: se dice cuánto se necesita de cada cosa; `repartir` coge primero de las
  ubicaciones elegidas arrastrando y después de donde más hay; lo que falta y lo que no
  está en el inventario sale en «Hay que comprar». La lista no descuenta del inventario y
  genera un documento para imprimir agrupado por ubicación. Las listas se guardan como
  plantilla y se reutilizan.

### 15.7 Configuración

Identidad y apariencia del grupo (nombre, logo, colores, modo oscuro), datos del grupo,
preferencias (formato de nombre, archivos, asistencia en pasados, plazo de conservación),
catálogos (tipos de evento, secciones con edades, categorías de factura), documentos base
y plantillas, cargos y permisos, copias de seguridad, exportación JSON, datos vencidos y
rondas vencidas.

### 15.8 Registro de accesos

Tabla de solo lectura (`registro.ver`). Guarda identificadores, no nombres. No se
registran las fotos de la galería (cada miniatura sería un acceso).

### 15.9 Bajas y conservación de datos

- **Baja**: con `fecha_baja` el educando desaparece de las vistas activas y su tutor ya no
  opera con él; quitar la fecha la deshace.
- **Datos vencidos** (E-06): pasado el plazo desde la baja se borran el educando, sus
  asistencias, vínculos, fichas permanentes y archivos; el administrador decide si borra
  también los tutores que se quedan sin educandos.
- **Rondas vencidas** (E-16): pasado el plazo desde el final de una ronda (31 de agosto)
  se borran su carpeta de circulares y las respuestas de asistencia de sus eventos, de
  todos los educandos. Las fichas permanentes y las fotos se quedan.
- En ambos casos el servidor recalcula qué ha vencido y el borrado queda registrado. Nunca
  es automático: lo confirma el administrador.

## 16. Estilos y criterios de interfaz

`public/estilo.css`, organizado por secciones con comentarios
(`/* ==================== FACTURAS ==================== */`): variables de color en
`:root` (las cambia la configuración del grupo), cabecera, estructura, menús, tablas,
botones, formularios y modales, calendario, dashboard, galería, facturas, inventario,
configuración, responsive e impresión.

Clases comunes: `.vista` (y `.vista-tabla`, `.vista-dashboard`…), `.vista-superficie`
(fondo, borde, radio y sombra), `.vista-formulario`, `.boton` con variantes
(`boton-crear`, `boton-editar`, `boton-borrar`, `boton-cancelar`, `boton-descargar`,
`boton-mini`), `.fila-seccion-<código>` (color de fondo de cada sección).

Criterios acordados con el usuario:

- **Filas alineadas**: en cualquier lista de filas con controles, todas usan la misma
  rejilla CSS (controles con ancho fijo, texto en `1fr`) y crean las mismas celdas aunque
  alguna quede vacía; así los botones y campos quedan en la misma columna y altura. No se
  usa `flex-wrap` para estas filas.
- La celda de acciones de una tabla es una celda normal (`td.acciones` con `width:1%`),
  pegada a la derecha.
- Todo debe funcionar también con teclado: cada arrastre tiene un botón equivalente.

## 17. Instalación y migraciones

1. Crear la base `ingeriar` y el usuario `ingeriar_app` con permisos sobre ella.
2. Configurar Apache (§6): DocumentRoot, `AllowOverride All` y variables de entorno.
3. Crear las tablas con el instalador (`instalador.html`) o con `schema.sql`.
4. Datos de prueba sencillos: `demo.sql` (contraseña `ingeriar-<n>` según el archivo; en la
   instalación local de desarrollo se usan `usuarioN@ingeriar.test` / `demo-N`). Datos
   de ejemplo completos y realistas: §19.
5. Subir las plantillas en blanco (Configuración → «Documentos base y plantillas»).
6. Dar permisos de escritura a Apache sobre `storage/`.

**Instalador (E-11)**: cuatro pasos. (1) Base de datos configurada en el servidor y token
si existe. (2) Tablas: asistente con el modelo de Ingeriar (`modelo.json`; editar tablas,
columnas, índices y claves foráneas; descargar y cargar el modelo) o subir un
`schema.sql` (solo CREATE TABLE, INSERT y ALTER TABLE … ADD). (3) Datos iniciales
(`semillas/`) y primer administrador. (4) Vista previa del SQL e instalación. El SQL lo
genera `ModeloDatos.php`. `modelo.json` se regenera con
`INGERIAR_DB_NAME=<base> php server/herramientas/generar-modelo.php` cuando cambia
`schema.sql` (la prueba INI-05 comprueba que coinciden).

**Migraciones para bases anteriores** (`server/data/migraciones/`), en este orden:
`2025-02-catalogos-eventos-secciones.sql`, `2025-03-categorias-factura.sql`,
`2025-04-estado-facturas-enum.sql`, `2026-11-galeria.sql`, `2026-11-bajas-accesos.sql`,
`2026-11-roles.sql`, `2026-11-facturas-tablero.sql`, `2026-11-claves-foraneas.sql`,
`2026-11-documentacion.sql`, `2026-11-inventario.sql` y, cuando todo funcione,
`2026-11-roles-retirar-columna.sql`. Los archivos subidos antes de E-16 y E-17 se mueven
con el usuario de Apache (sin `--confirmar` solo informan; se pueden repetir):

```bash
sudo -u www-data php <ruta>/server/data/migraciones/2026-11-fichas-permanentes.php --confirmar
sudo -u www-data php <ruta>/server/data/migraciones/2026-11-carpetas-galeria-facturas.php --confirmar
```

## 18. Copias de seguridad

`server/copias/copia.php` crea `ingeriar-copia-AAAA-MM-DD-HHMMSS.zip` con el volcado de
MySQL (`mysqldump`, con la contraseña en un fichero de opciones temporal 0600) y toda la
carpeta `storage/`. Con `INGERIAR_COPIAS_CLAVE` se cifra (AES-256-CBC por bloques + HMAC
SHA-256, claves con PBKDF2) como `.zip.cifrado`. Guarda las últimas
`INGERIAR_COPIAS_RETENER` (10), no se ejecuta dos veces a la vez y anota cada ejecución
en `registro-copias.log`. Se programa con cron **como el usuario de Apache**:

```bash
sudo crontab -u www-data -e
0 3 * * * INGERIAR_DB_PASSWORD=... INGERIAR_COPIAS_CLAVE=... php /var/www/ingeriar/server/copias/copia.php
```

Configuración → «Copias de seguridad» lista y descarga las copias (cada descarga queda
registrada). Restaurar en una base vacía:
`php server/copias/restaurar.php <copia> --base=ingeriar --confirmar` (se niega a
sobrescribir salvo `--sobrescribir`). La carpeta de copias debe copiarse además fuera del
servidor, siempre cifrada y con la clave guardada aparte.

## 19. Pruebas y datos de ejemplo

**Pruebas automáticas** (`docs/PLAN-PRUEBAS.md` tiene la lista y el resultado):

```bash
INGERIAR_DB_HOST=... INGERIAR_DB_PORT=... INGERIAR_DB_PASSWORD=... \
  php server/pruebas/pruebas.php <url> --confirmar        # 124 pruebas por HTTP
php server/pruebas/pruebas-copias.php --base-vacia=<base> --confirmar
INGERIAR_DB_NAME=<base vacía> php server/pruebas/pruebas-instalador.php <url> --confirmar
```

Solo contra una **base de pruebas**: crean y borran sus propios datos (usuarios
`@pruebas.ingeriar.test`, eventos «Prueba automática…», DNI `9999999?Z`) y dejan los JSON
del inventario como estaban. Antes de lanzarlas hay que comprobar a qué base se conectan:
sin las variables de entorno, `database.php` usa la base local de desarrollo. El servidor
de pruebas usa el `storage/` de la carpeta desde la que se arranca:
`php -S 127.0.0.1:8099 -t public`.

**Datos de ejemplo** (ficticios, cargados por la API como si fueran personas usando la
aplicación, así los archivos los escribe Apache y van a sus carpetas):

| Script | Qué crea |
|---|---|
| `datos-ejemplo.php <url> <correo-admin> <contraseña> --confirmar` | Todo: 8 monitores (Marta Rodríguez también de tesorería), 19 familias con 26 educandos en todas las secciones (uno dado de baja hace meses y otro hace 6 años, que aparece en «Datos vencidos»), eventos de la ronda anterior y de la actual con sus secciones y circulares, respuestas de asistencia (algunas pendientes), circulares entregadas, fichas entregadas y revisadas (algunas «a corregir»), una plantilla nueva, fotos en la galería, 20 facturas con tique en los tres estados y el inventario. Si ya están cargados, no hace nada. |
| `inventario-ejemplo.php <url> <correo> <contraseña> --confirmar` | 18 ubicaciones, 33 materiales y 5 listas (una plantilla). No duplica. |
| `fotos-ejemplo.php --confirmar` | Fotos para eventos sin fotos y tiques para facturas sin imagen (escribe directamente en `storage/`). |

Las cuentas de `datos-ejemplo.php` entran con la contraseña `Seeonee-2026`:
`marta.rodriguez@monitores.ingeriar.test` (monitora y tesorería), otros monitores
`<nombre>.<apellido>@monitores.ingeriar.test` y familias
`<nombre>.<apellido>@familias.ingeriar.test` (por ejemplo `ana.garcia@…`, con Hugo en
Colonia y Lola en Manada). Las imágenes y PDF se generan en PHP puro
(`server/pruebas/dibujos.php`).

## 20. Forma de trabajar y temas pendientes

- Reglas en `CLAUDE.md`. Tareas en `docs/ENCARGOS.md`, una rama `feat/E-XX-nombre` por
  encargo, plan y confirmación antes de cambiar código, entrada en
  `docs/REGISTRO-CAMBIOS.md`, línea en el diario (§21) y estado del encargo al terminar.
  El usuario revisa y fusiona en `main`; el «chat del documento» que redacta la memoria
  solo ve `main`.
- Código y comentarios en español; sin frameworks ni librerías nuevas sin consultarlo.
- **Pendiente**: E-10 (credenciales fuera del código y cambiar la contraseña de MySQL, que
  está en el historial público); pruebas manuales MAN-01 a MAN-49; puesta en un servidor
  real (publicar solo `public/`, cron de copias como `www-data`, `INGERIAR_COPIAS_CLAVE`);
  la cabecera de «Cargos y permisos» es estrecha y corta el nombre «Administrador».

## 21. Diario de cambios

### 2026-11-18 (documentación técnica y datos de ejemplo)

- `noteas.md` reescrito como documentación técnica completa (arquitectura, Apache,
  seguridad, permisos, base de datos, almacenamiento, API, clases, módulos del cliente,
  funcionalidades, estilos, instalación, copias y pruebas). El diario se conserva.
- `server/pruebas/datos-ejemplo.php`: datos ficticios completos cargados por la API;
  `dibujos.php` con las imágenes y un generador de PDF sencillo.

### 2026-11-18 (E-17 carpetas de galería y facturas)

- Galería en `<ronda>/<id>-<evento>/` y facturas en `<ronda>/` dentro de `storage/`;
  se mueven al cambiar la fecha o el nombre. `server/data/almacenamiento.php`, migración
  `2026-11-carpetas-galeria-facturas.php`. Pruebas GAL-10, GAL-11 y FAC-13.

### 2026-11-18 (alineación en Configuración)

- Documentos base: cada fila con las mismas cinco columnas (el nombre ocupa el espacio
  libre). Casillas de los formularios a la izquierda y controles alineados abajo.
  «Activo» y «Quitar» de las listas, en las dos últimas columnas.
- Criterio general: en las listas de filas con controles, todas las filas usan la misma
  rejilla, para que los botones y campos queden a la misma altura y en la misma columna.

### 2026-11-18 (E-16 rondas vencidas y fichas permanentes)

- Fichas de inscripción en `permanente/<educando>/`; circulares en la ronda del evento.
- Configuración → «Rondas vencidas»: borra la carpeta de la ronda y sus asistencias.
- Migración `2026-11-fichas-permanentes.php`. Pruebas RON-01 a RON-05.

### 2026-11-18 (columna de acciones)

- Tablas: la celda de acciones vuelve a ser una celda (`td.acciones`), mide lo justo y
  queda pegada a la derecha.

### 2026-11-18 (botones del plano)

- Plano: «+ Ubicación» y «+ Material» juntos a la derecha de la barra (`.botones-plano`).

### 2026-11-18 (cajas anchas en el plano)

- Plano: las ubicaciones con varias sububicaciones se ensanchan, primero a toda la fila y
  después (a petición del usuario) tantas columnas como sububicaciones tienen.

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
