<?php

// Instalador interactivo (E-11). API JSON que usa public/instalador.js.
// Rutas (?accion=):
//   estado        GET   base configurada, si está en uso y si hace falta token
//   modelo        GET   modelo de Ingeriar (server/data/modelo.json)
//   vista_previa  POST  {modelo}                    SQL que se ejecutaría
//   instalar      POST  {modelo, semillas, administrador, token}
//   instalar_sql  POST  multipart: archivo schema.sql, semillas[], administrador[...], token
//
// Seguridad:
//   - Instalación en uso (hay usuarios): solo con una sesión con configuracion.gestionar (E-09).
//   - Si existe INGERIAR_TOKEN_INSTALACION, hay que escribirlo: no basta con llegar a la URL.
//   - En el asistente el SQL lo genera el servidor a partir del modelo validado.
//   - Un schema.sql subido solo puede tener CREATE TABLE, INSERT y ALTER TABLE … ADD.
//   - La contraseña de MySQL sigue viniendo de la configuración del servidor.

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/comun.php';
require_once __DIR__ . '/../auth/contrasenas.php';
require_once __DIR__ . '/../auth/permisos.php';
require_once __DIR__ . '/../instalacion/ModeloDatos.php';

const SEMILLAS = [
    'catalogos' => ['archivo' => 'catalogos.sql', 'tabla' => 'categorias_factura',
        'nombre' => 'Catálogos: tipos de evento, secciones y categorías de factura'],
    'roles' => ['archivo' => 'roles-permisos.sql', 'tabla' => 'roles_permisos',
        'nombre' => 'Cargos y permisos (administrador, monitor, tutor, tesorería)'],
    'documentos' => ['archivo' => 'documentos-base.sql', 'tabla' => 'documentos_base',
        'nombre' => 'Documentos base (fichas de inscripción y circular de participación)'],
];
const TAMANO_MAXIMO_SQL = 1048576;

function nombreBase(): string
{
    $nombre = getenv('INGERIAR_DB_NAME') ?: ($_SERVER['INGERIAR_DB_NAME'] ?? 'ingeriar');
    return preg_match('/^[A-Za-z0-9_]+$/', $nombre) ? $nombre : 'ingeriar';
}

// Aplicación en uso = existe la tabla usuarios y tiene filas.
function instalacionEnUso(): bool
{
    try {
        $db = obtenerConexion();
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) === 1049) {
            return false;
        }
        throw $error;
    }
    if (!$db->query("SHOW TABLES LIKE 'usuarios'")->fetchColumn()) {
        return false;
    }
    return (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
}

function puedeReinstalar(): bool
{
    if (usuarioActual() === null) {
        return false;
    }
    try {
        $permisos = cargarPermisosUsuario(obtenerConexion(), (int) usuarioActual()['id']);
        return in_array('configuracion.gestionar', $permisos['permisos'] ?? [], true);
    } catch (Throwable $error) {
        return false;
    }
}

function tokenRequerido(): string
{
    return (string) (getenv('INGERIAR_TOKEN_INSTALACION') ?: ($_SERVER['INGERIAR_TOKEN_INSTALACION'] ?? ''));
}

// Corta con 403 si no se puede instalar ahora.
function exigirAcceso(string $token): void
{
    if (instalacionEnUso() && !puedeReinstalar()) {
        responder(['error' => 'La aplicación ya está instalada. Solo un administrador con la sesión iniciada '
            . 'puede volver a ejecutar el instalador.'], 403);
    }
    $requerido = tokenRequerido();
    if ($requerido !== '' && !hash_equals($requerido, $token)) {
        responder(['error' => 'El token de instalación no es correcto'], 403);
    }
}

// Conexión a la base configurada, creándola si no existe.
function conexionInstalacion(): PDO
{
    $db = obtenerConexion(false);
    $base = nombreBase();
    $db->exec('CREATE DATABASE IF NOT EXISTS `' . $base . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->exec('USE `' . $base . '`');
    return $db;
}

function validarAdministrador(array $datos): ?array
{
    $administrador = [
        'nombre' => trim((string) ($datos['nombre'] ?? '')),
        'apellidos' => trim((string) ($datos['apellidos'] ?? '')),
        'email' => trim((string) ($datos['email'] ?? '')),
        'telefono' => trim((string) ($datos['telefono'] ?? '')),
        'contrasena' => (string) ($datos['contrasena'] ?? ''),
    ];
    if (implode('', $administrador) === '') {
        return null;
    }
    if (in_array('', $administrador, true)) {
        throw new InvalidArgumentException('Para crear el administrador hay que rellenar todos sus campos');
    }
    if (filter_var($administrador['email'], FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('El email del administrador no es válido');
    }
    $error = validarContrasena($administrador['contrasena']);
    if ($error !== null) {
        throw new InvalidArgumentException($error);
    }
    return $administrador;
}

// Ejecuta las semillas elegidas y crea el administrador (si no hay usuarios).
// Devuelve los mensajes para el resumen.
function datosIniciales(PDO $db, array $semillas, ?array $administrador): array
{
    $mensajes = [];
    $db->beginTransaction();
    try {
        foreach ($semillas as $codigo) {
            if (!isset(SEMILLAS[$codigo])) {
                continue;
            }
            $semilla = SEMILLAS[$codigo];
            if (!$db->query("SHOW TABLES LIKE " . $db->quote($semilla['tabla']))->fetchColumn()) {
                throw new InvalidArgumentException("No se pueden cargar «{$semilla['nombre']}»: falta la tabla {$semilla['tabla']}");
            }
            foreach (ModeloDatos::dividirSentencias(file_get_contents(__DIR__ . '/../data/semillas/' . $semilla['archivo'])) as $sentencia) {
                $db->exec($sentencia);
            }
            $mensajes[] = "Cargados: {$semilla['nombre']}.";
        }
        if ($administrador !== null) {
            if (!$db->query("SHOW TABLES LIKE 'usuarios_roles'")->fetchColumn()
                || !$db->query("SELECT 1 FROM roles WHERE codigo = 'administrador'")->fetchColumn()) {
                throw new InvalidArgumentException('Para crear el administrador hacen falta las tablas y los cargos (marca «Cargos y permisos»)');
            }
            if ((int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) {
                $mensajes[] = 'Ya existen usuarios: no se ha creado el administrador.';
            } else {
                $db->prepare('INSERT INTO usuarios (nombre, apellidos, email, telefono, `contraseña`) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$administrador['nombre'], $administrador['apellidos'], $administrador['email'],
                        $administrador['telefono'], cifrarContrasena($administrador['contrasena'])]);
                $db->prepare("INSERT INTO usuarios_roles (id_usuario, codigo_rol) VALUES (?, 'administrador')")
                    ->execute([(int) $db->lastInsertId()]);
                $mensajes[] = "Se ha creado el administrador {$administrador['email']}.";
            }
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
    return $mensajes;
}

// Ejecuta las sentencias una a una. MySQL confirma cada CREATE/ALTER al momento (no se
// pueden deshacer con una transacción), así que si una falla se informa de cuál.
function ejecutarSentencias(PDO $db, array $sentencias): void
{
    foreach ($sentencias as $numero => $sentencia) {
        try {
            $db->exec($sentencia);
        } catch (PDOException $error) {
            throw new RuntimeException('Falló la sentencia ' . ($numero + 1) . ': ' . $error->getMessage());
        }
    }
}

$accion = $_GET['accion'] ?? '';
$esJson = str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
$datos = $esJson ? (json_decode(file_get_contents('php://input'), true) ?? []) : $_POST;

try {
    switch ($accion) {
        case 'estado':
            $enUso = instalacionEnUso();
            responder([
                'base' => nombreBase(),
                'en_uso' => $enUso,
                'puede_instalar' => !$enUso || puedeReinstalar(),
                'token_requerido' => tokenRequerido() !== '',
                'semillas' => array_map(fn($codigo, $semilla) => ['codigo' => $codigo, 'nombre' => $semilla['nombre']],
                    array_keys(SEMILLAS), SEMILLAS),
            ]);

        case 'modelo':
            exigirAcceso((string) ($_GET['token'] ?? ''));
            responder(json_decode(file_get_contents(__DIR__ . '/../data/modelo.json'), true));

        case 'vista_previa':
            exigirAcceso((string) ($datos['token'] ?? ''));
            $modelo = ModeloDatos::validar((array) ($datos['modelo'] ?? []));
            $sentencias = ModeloDatos::generarSql(obtenerConexion(false), $modelo);
            responder(['sql' => implode(";\n\n", $sentencias) . ";\n", 'sentencias' => count($sentencias)]);

        case 'instalar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responder(['error' => 'Método no permitido'], 405);
            }
            exigirAcceso((string) ($datos['token'] ?? ''));
            $modelo = ModeloDatos::validar((array) ($datos['modelo'] ?? []));
            $administrador = validarAdministrador((array) ($datos['administrador'] ?? []));
            $db = conexionInstalacion();
            $sentencias = ModeloDatos::generarSql($db, $modelo);
            ejecutarSentencias($db, $sentencias);
            $mensajes = datosIniciales($db, (array) ($datos['semillas'] ?? []), $administrador);
            responder(['ok' => true, 'mensajes' => [
                'Base de datos «' . nombreBase() . '»: ' . count($modelo['tablas']) . ' tablas y '
                    . (count($sentencias) - count($modelo['tablas'])) . ' claves foráneas.',
                ...$mensajes,
            ]]);

        case 'instalar_sql':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responder(['error' => 'Método no permitido'], 405);
            }
            exigirAcceso((string) ($datos['token'] ?? ''));
            $archivo = $_FILES['archivo'] ?? null;
            if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
                responder(['error' => 'No se ha recibido el archivo schema.sql'], 400);
            }
            if ($archivo['size'] > TAMANO_MAXIMO_SQL) {
                responder(['error' => 'El archivo supera el máximo de 1 MB'], 413);
            }
            $sql = (string) file_get_contents($archivo['tmp_name']);
            if (!esUtf8Valido($sql)) {
                responder(['error' => 'El archivo debe estar en UTF-8'], 400);
            }
            // Se valida el archivo entero antes de ejecutar nada.
            $sentencias = [];
            $omitidas = 0;
            foreach (ModeloDatos::dividirSentencias($sql) as $sentencia) {
                if (ModeloDatos::clasificarSentencia($sentencia) === 'ejecutar') {
                    $sentencias[] = $sentencia;
                } else {
                    $omitidas++;
                }
            }
            if (!$sentencias) {
                responder(['error' => 'El archivo no contiene sentencias que ejecutar'], 400);
            }
            $administrador = validarAdministrador((array) ($datos['administrador'] ?? []));
            $db = conexionInstalacion();
            ejecutarSentencias($db, $sentencias);
            $mensajes = datosIniciales($db, (array) ($datos['semillas'] ?? []), $administrador);
            responder(['ok' => true, 'mensajes' => [
                count($sentencias) . ' sentencias ejecutadas en «' . nombreBase() . '»'
                    . ($omitidas ? " ({$omitidas} CREATE DATABASE / USE omitidas: se usa la base configurada)." : '.'),
                ...$mensajes,
            ]]);

        default:
            responder(['error' => 'Acción no válida'], 400);
    }
} catch (InvalidArgumentException $error) {
    responder(['error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1698) {
        responder(['error' => 'No se pudo autenticar MySQL. No uses root desde PHP; crea el usuario '
            . 'ingeriar_app y configura INGERIAR_DB_USER e INGERIAR_DB_PASSWORD.'], 500);
    }
    responder(['error' => $error->getMessage()], 500);
}

// Comprueba UTF-8 sin depender de la extensión mbstring.
function esUtf8Valido(string $texto): bool
{
    return preg_match('//u', $texto) === 1;
}
