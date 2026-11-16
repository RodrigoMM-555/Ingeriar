<?php

// Copia de seguridad completa de la base de datos en JSON (RNF-04).
// Solo para administradores: contiene datos personales de usuarios y educandos.

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../auth/sesion.php';

function responderSalida(array $datos, int $estado): never
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// El permiso se comprueba en el servidor antes de abrir la base de datos.
$usuario = usuarioActual();
if ($usuario === null) {
    responderSalida(['error' => 'No autenticado'], 401);
}
if ($usuario['rol'] !== 'administrador') {
    responderSalida(['error' => 'No tienes permisos para esta operación'], 403);
}

$db = obtenerConexion();
$tablas = $db->query(
    "SELECT TABLE_NAME
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
     ORDER BY TABLE_NAME"
)->fetchAll(PDO::FETCH_COLUMN);

$baseDeDatos = [];
foreach ($tablas as $tabla) {
    // El nombre viene de information_schema, no del usuario; se escapa igualmente.
    $stmt = $db->query(
        'SELECT * FROM `' . str_replace('`', '``', $tabla) . '`'
    );
    $filas = $stmt->fetchAll();
    if ($tabla === 'usuarios') {
        // Los hashes de contraseña no forman parte de la copia.
        foreach ($filas as &$fila) {
            unset($fila['contraseña']);
        }
        unset($fila);
    }
    $baseDeDatos[$tabla] = $filas;
}

$nombreArchivo = 'ingeriar-copia-' . date('Y-m-d-His') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: no-store');
echo json_encode($baseDeDatos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
