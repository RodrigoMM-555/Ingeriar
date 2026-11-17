<?php

// Copias de seguridad desde Configuración (E-12). Las copias las genera el script
// server/copias/copia.php (con cron); aquí solo se listan y se descargan.
// Rutas (?accion=), con copia.descargar:
//   listar     GET  copias existentes y últimas líneas del registro de copias
//   descargar  GET  descarga una copia (&nombre=); queda en registro_accesos

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/comun.php';
require_once __DIR__ . '/../auth/permisos.php';
require_once __DIR__ . '/../auth/registro.php';
require_once __DIR__ . '/../copias/comun-copias.php';

$db = obtenerConexion();
$usuario = exigirUsuario($db);
exigirPermiso('copia.descargar');

try {
    $carpeta = carpetaCopias();
} catch (RuntimeException $error) {
    responder(['error' => $error->getMessage()], 500);
}

switch ($_GET['accion'] ?? '') {
    case 'listar':
        $copias = is_readable($carpeta) ? listarCopias($carpeta) : [];
        $registro = [];
        $rutaRegistro = $carpeta . '/registro-copias.log';
        if (is_readable($rutaRegistro)) {
            $registro = array_slice(file($rutaRegistro, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -15);
        }
        responder([
            'copias' => $copias,
            'registro' => array_reverse($registro),
            'carpeta_legible' => is_readable($carpeta),
        ]);

    case 'descargar':
        $nombre = (string) ($_GET['nombre'] ?? '');
        // Solo nombres de copia: impide rutas como «../config.php».
        if (!esNombreDeCopia($nombre)) {
            responder(['error' => 'Nombre de copia no válido'], 400);
        }
        $ruta = $carpeta . '/' . $nombre;
        if (!is_file($ruta) || !is_readable($ruta)) {
            responder(['error' => 'No existe esa copia'], 404);
        }
        registrarAcceso($db, $usuario, 'descargar', 'copia_seguridad', null, $nombre);
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        readfile($ruta);
        exit;

    default:
        responder(['error' => 'Acción no válida'], 400);
}
