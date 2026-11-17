<?php

// Copia de seguridad completa de la base de datos en JSON (RNF-04).
// Solo para administradores: contiene datos personales de usuarios y educandos.

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../api/comun.php';
require_once __DIR__ . '/../auth/permisos.php';
require_once __DIR__ . '/../auth/registro.php';

// La sesión se comprueba antes de abrir la base de datos; el permiso, al recargarlo.
if (usuarioActual() === null) {
    responder(['error' => 'No autenticado'], 401);
}
$db = obtenerConexion();
$usuario = exigirUsuario($db);
exigirPermiso('copia.descargar');

registrarAcceso($db, $usuario, 'descargar', 'copia_completa');
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
