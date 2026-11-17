# Encargos de desarrollo

Estados: `pendiente`, `en curso`, `hecho` (rama sin fusionar), `fusionado`.
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

## E-05 · Mejora del módulo de facturas
- Requisitos: RF-05. Pendiente de definir con el usuario qué mejoras concretas (preguntar antes de empezar).
- Estado: pendiente

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
