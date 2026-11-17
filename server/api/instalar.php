<?php

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../auth/contrasenas.php';
require_once __DIR__ . '/../auth/permisos.php';
header('Content-Type: text/html; charset=utf-8');

// Indica si la aplicación ya está en uso: existe la base de datos y la tabla
// usuarios tiene al menos una fila.
function instalacionEnUso(): bool
{
    try {
        $db = obtenerConexion();
    } catch (PDOException $error) {
        // 1049: la base de datos todavía no existe.
        if ((int) ($error->errorInfo[1] ?? 0) === 1049) {
            return false;
        }
        throw $error;
    }
    $stmt = $db->prepare(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'"
    );
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        return false;
    }
    return (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
}

// El instalador no pide sesión en una instalación nueva (todavía no hay usuarios),
// pero una vez en uso solo un administrador puede volver a ejecutarlo.
// La comprobación se hace en el servidor antes de leer el modelo.
try {
    $enUso = instalacionEnUso();
} catch (Throwable $error) {
    http_response_code(500);
    exit('No se pudo comprobar el estado de la instalación. Revisa la configuración de MySQL.');
}
// Una vez en uso, hace falta una sesión con configuracion.gestionar (E-13).
$puedeReinstalar = false;
if ($enUso && usuarioActual() !== null) {
    try {
        $permisosSesion = cargarPermisosUsuario(obtenerConexion(), (int) usuarioActual()['id']);
        $puedeReinstalar = in_array('configuracion.gestionar', $permisosSesion['permisos'] ?? [], true);
    } catch (Throwable $error) {
        $puedeReinstalar = false;
    }
}
if ($enUso && !$puedeReinstalar) {
    http_response_code(403);
    exit('La aplicación ya está instalada. Solo un administrador con la sesión iniciada puede volver a ejecutar el instalador.');
}

$modelo = trim($_POST['modelo'] ?? '');
if ($modelo === '') {
    http_response_code(400);
    exit('No se ha recibido ningún modelo de datos.');
}

// Datos opcionales del primer administrador. Si todos están vacíos, solo se crean las tablas.
$administrador = [
    'nombre' => trim($_POST['admin_nombre'] ?? ''),
    'apellidos' => trim($_POST['admin_apellidos'] ?? ''),
    'email' => trim($_POST['admin_email'] ?? ''),
    'telefono' => trim($_POST['admin_telefono'] ?? ''),
    'contrasena' => (string) ($_POST['admin_contrasena'] ?? ''),
];
$crearAdministrador = implode('', $administrador) !== '';
if ($crearAdministrador) {
    if (in_array('', $administrador, true)) {
        http_response_code(400);
        exit('Para crear el administrador hay que rellenar todos sus campos.');
    }
    if (filter_var($administrador['email'], FILTER_VALIDATE_EMAIL) === false) {
        http_response_code(400);
        exit('El email del administrador no es válido.');
    }
    $errorContrasena = validarContrasena($administrador['contrasena']);
    if ($errorContrasena !== null) {
        http_response_code(400);
        exit(htmlspecialchars($errorContrasena) . '.');
    }
}

function identificadorValido(string $valor): bool
{
    return preg_match('/^[\p{L}_][\p{L}\p{N}_]*$/u', $valor) === 1;
}

function crearSqlTabla(string $tabla, array $columnas): string
{
    if (!identificadorValido($tabla)) {
        throw new RuntimeException("Nombre de tabla no válido: {$tabla}");
    }

    // Solo se añade la clave `id` automática si el modelo no declara otra clave
    // primaria (los catálogos usan `codigo` y eventos_secciones una clave compuesta).
    $tienePrimaria = false;
    foreach ($columnas as $columna) {
        if (preg_match('/\bPRIMARY\s+KEY\b/i', $columna)) {
            $tienePrimaria = true;
        }
    }
    $definiciones = $tienePrimaria ? [] : ['`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'];
    foreach ($columnas as $columna) {
        if (preg_match('/^(PRIMARY KEY|UNIQUE(?: KEY)?|FOREIGN KEY|CHECK)\b/i', trim($columna))) {
            $definiciones[] = trim($columna);
            continue;
        }
        $partes = preg_split('/\s+/', trim($columna), 2);
        $nombre = $partes[0];
        if ($nombre === 'id') {
            continue;
        }
        if (!identificadorValido($nombre)) {
            throw new RuntimeException("Nombre de columna no válido: {$nombre}");
        }
        $definicion = trim($partes[1] ?? 'TEXT');
        $definiciones[] = '`' . $nombre . '` ' . $definicion;
    }

    return 'CREATE TABLE IF NOT EXISTS `' . $tabla . '` ('
        . implode(', ', $definiciones) . ') ENGINE=InnoDB';
}

try {
    $lineas = preg_split('/\R/', $modelo);
    $tablas = [];
    $tablaActual = null;
    $columnas = [];

    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }
        if (str_starts_with($linea, '-')) {
            if ($tablaActual === null) {
                throw new RuntimeException('Hay una columna antes de declarar una tabla.');
            }
            $columnas[] = trim(substr($linea, 1));
            continue;
        }
        if ($tablaActual !== null) {
            $tablas[] = crearSqlTabla($tablaActual, $columnas);
        }
        $tablaActual = $linea;
        $columnas = [];
    }
    if ($tablaActual !== null) {
        $tablas[] = crearSqlTabla($tablaActual, $columnas);
    }
    if (!$tablas) {
        throw new RuntimeException('El modelo no contiene ninguna tabla.');
    }

    $db = obtenerConexion(false);
    $nombre = getenv('INGERIAR_DB_NAME') ?: 'ingeriar';
    $db->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $nombre)
        . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->exec('USE `' . str_replace('`', '``', $nombre) . '`');
    foreach ($tablas as $sql) {
        $db->exec($sql);
    }

    // Cargos y permisos iniciales (E-13), si el modelo incluye sus tablas.
    $existeTablaRoles = $db->query("SHOW TABLES LIKE 'roles_permisos'")->fetchColumn();
    if ($existeTablaRoles) {
        $semilla = (string) file_get_contents(__DIR__ . '/../data/semillas/roles-permisos.sql');
        $semilla = preg_replace('/^--.*$/m', '', $semilla);
        foreach (preg_split('/;\s*(\R|$)/', $semilla) as $sentencia) {
            if (trim($sentencia) !== '') {
                $db->exec($sentencia);
            }
        }
    }

    // El administrador inicial solo se crea si todavía no hay ningún usuario.
    // Así el instalador no sirve para darse de alta en una instalación ya en uso.
    $mensajeAdministrador = '';
    if ($crearAdministrador) {
        $hayUsuarios = (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
        if ($hayUsuarios) {
            $mensajeAdministrador = 'Ya existen usuarios: no se ha creado el administrador.';
        } else {
            if (!$existeTablaRoles) {
                throw new RuntimeException('El modelo no incluye las tablas de roles: no se puede crear el administrador.');
            }
            $columnaRol = $db->query("SHOW COLUMNS FROM usuarios LIKE 'rol'")->fetchColumn();
            $db->prepare(
                'INSERT INTO usuarios (nombre, apellidos, email, telefono, `contraseña`'
                . ($columnaRol ? ', rol' : '') . ')
                 VALUES (?, ?, ?, ?, ?' . ($columnaRol ? ", 'administrador'" : '') . ')'
            )->execute([
                $administrador['nombre'],
                $administrador['apellidos'],
                $administrador['email'],
                $administrador['telefono'],
                cifrarContrasena($administrador['contrasena']),
            ]);
            $db->prepare("INSERT INTO usuarios_roles (id_usuario, codigo_rol) VALUES (?, 'administrador')")
                ->execute([(int) $db->lastInsertId()]);
            $mensajeAdministrador = 'Se ha creado el administrador '
                . $administrador['email'] . '.';
        }
    }
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    if ($error instanceof PDOException && (int) $error->errorInfo[1] === 1698) {
        exit(
            'No se pudo autenticar MySQL. No uses root desde PHP; crea el usuario '
            . 'ingeriar_app y configura INGERIAR_DB_USER e INGERIAR_DB_PASSWORD.'
        );
    }
    exit('No se pudo instalar el modelo: ' . htmlspecialchars($error->getMessage()));
}

echo '<h1>Base de datos MySQL creada correctamente</h1>';
echo '<p>Se han procesado ' . count($tablas) . ' tablas.</p>';
if ($mensajeAdministrador !== '') {
    echo '<p>' . htmlspecialchars($mensajeAdministrador) . '</p>';
}
foreach ($tablas as $sql) {
    echo '<pre>' . htmlspecialchars($sql) . ';</pre>';
}
