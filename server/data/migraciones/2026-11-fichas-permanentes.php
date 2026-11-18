<?php

// E-16: mueve las fichas de inscripción ya entregadas desde la carpeta de la ronda
// (<ronda>/<sección>/<educando>/) a la carpeta permanente del educando
// (permanente/<educando>/) y actualiza la ruta en documentos_educando.
//
// Se ejecuta una vez en cada instalación con fichas anteriores a E-16, con el usuario
// del servidor web (dueño de storage/) y desde la copia que sirve Apache:
//   sudo -u www-data php /var/www/html/Ingeriar/server/data/migraciones/2026-11-fichas-permanentes.php --confirmar
// Sin --confirmar solo muestra lo que haría. Se puede repetir: lo ya movido se salta.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../secciones.php';

$confirmar = in_array('--confirmar', $argv, true);
$base = realpath(__DIR__ . '/../../../storage/documentos');
if ($base === false) {
    fwrite(STDERR, "No existe storage/documentos junto a este script.\n");
    exit(1);
}

$db = obtenerConexion();
$filas = $db->query(
    "SELECT d.id, d.archivo, e.id AS id_educando, e.nombre, e.apellidos
     FROM documentos_educando d
     JOIN educandos e ON e.id = d.id_educando
     WHERE d.archivo NOT LIKE 'permanente/%'"
)->fetchAll();

$actualizar = $db->prepare('UPDATE documentos_educando SET archivo = ? WHERE id = ?');
$movidas = 0;
$faltan = 0;
foreach ($filas as $fila) {
    $origen = realpath($base . '/' . ltrim($fila['archivo'], '/'));
    $nueva = 'permanente/'
        . nombreCarpetaEducando((int) $fila['id_educando'], $fila['nombre'], $fila['apellidos'])
        . '/' . basename($fila['archivo']);
    if ($origen === false || !str_starts_with($origen, $base . DIRECTORY_SEPARATOR) || !is_file($origen)) {
        echo "Falta el archivo de la ficha {$fila['id']} ({$fila['archivo']}): solo se actualiza la ruta.\n";
        $faltan++;
    } else {
        echo "{$fila['archivo']} → {$nueva}\n";
    }
    if (!$confirmar) {
        continue;
    }
    $destino = $base . '/' . $nueva;
    if (!is_dir(dirname($destino)) && !mkdir(dirname($destino), 0770, true)) {
        fwrite(STDERR, "No se pudo crear " . dirname($nueva) . "\n");
        exit(1);
    }
    if ($origen !== false && is_file($origen) && !rename($origen, $destino)) {
        fwrite(STDERR, "No se pudo mover {$fila['archivo']}\n");
        exit(1);
    }
    $actualizar->execute([$nueva, $fila['id']]);
    $movidas++;
    // Se quitan las carpetas que quedan vacías (educando, sección y ronda).
    $carpeta = $origen !== false ? dirname($origen) : null;
    while ($carpeta !== null && $carpeta !== $base && str_starts_with($carpeta, $base . DIRECTORY_SEPARATOR)
        && is_dir($carpeta) && count(scandir($carpeta)) === 2) {
        rmdir($carpeta);
        $carpeta = dirname($carpeta);
    }
}

echo count($filas) . " fichas fuera de la carpeta permanente"
    . ($confirmar ? ", {$movidas} actualizadas" : " (sin --confirmar no se ha cambiado nada)")
    . ($faltan ? ", {$faltan} sin archivo" : '') . ".\n";
