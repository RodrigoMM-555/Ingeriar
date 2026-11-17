<?php

// Copia de seguridad completa (E-12): base de datos + carpeta storage/ en un único .zip.
// Pensado para programarlo con cron en el servidor del grupo, por ejemplo cada noche:
//
//   0 3 * * *  INGERIAR_DB_PASSWORD=... INGERIAR_COPIAS_CLAVE=... php /ruta/ingeriar/server/copias/copia.php
//
// Pasos: bloqueo (no dos copias a la vez) → volcado con mysqldump (la contraseña va en un
// fichero de opciones, no en la orden) → zip con el volcado y storage/ → cifrado AES-256
// si hay clave → permisos 0600 → retención (se borran las más antiguas) → registro.
// Variables de entorno: ver comun-copias.php.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/comun-copias.php';

$carpeta = carpetaCopias();
$registro = $carpeta . '/registro-copias.log';
$retener = max(1, (int) variableEntorno('INGERIAR_COPIAS_RETENER', '10'));
$clave = variableEntorno('INGERIAR_COPIAS_CLAVE');
$baseDatos = variableEntorno('INGERIAR_DB_NAME', 'ingeriar');
$storage = realpath(__DIR__ . '/../../storage');

function anotar(string $registro, string $texto): void
{
    file_put_contents($registro, date('Y-m-d H:i:s') . ' ' . $texto . "\n", FILE_APPEND | LOCK_EX);
    echo $texto . "\n";
}

// 1. Bloqueo: si ya hay una copia en marcha, esta termina sin hacer nada.
$bloqueo = fopen($carpeta . '/.copia.bloqueo', 'c');
if (!flock($bloqueo, LOCK_EX | LOCK_NB)) {
    anotar($registro, 'OMITIDA: ya hay otra copia en marcha');
    exit(3);
}

$temporales = [];
$codigo = 0;
try {
    $nombre = 'ingeriar-copia-' . date('Y-m-d-His') . '.zip';
    $trabajo = sys_get_temp_dir() . '/ingeriar-copia-' . bin2hex(random_bytes(6));
    mkdir($trabajo, 0700);
    $temporales[] = $trabajo;

    // 2. Volcado de la base de datos.
    $opciones = ficheroOpcionesMysql();
    $temporales[] = $opciones;
    $volcado = $trabajo . '/base-de-datos.sql';
    [$salida, $error] = ejecutar([
        'mysqldump', '--defaults-extra-file=' . $opciones, '--single-transaction', '--routines',
        '--no-tablespaces', '--default-character-set=utf8mb4', $baseDatos,
    ], null, $volcado);
    if ($salida !== 0 || filesize($volcado) === 0) {
        throw new RuntimeException('mysqldump falló: ' . $error);
    }

    // 3. Zip con el volcado, storage/ (documentos, facturas, galería, configuración,
    //    inventario) y un fichero con la descripción de la copia.
    $zip = $trabajo . '/' . $nombre;
    $archivo = new PharData($zip, 0, null, Phar::ZIP);
    $archivo->addFile($volcado, 'base-de-datos.sql');
    $archivos = 0;
    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterador as $fichero) {
        $relativa = substr($fichero->getPathname(), strlen($storage) + 1);
        // Se omiten los ficheros de bloqueo; los .gitkeep y .htaccess sí se copian.
        if ($fichero->isFile() && !str_ends_with($relativa, '.bloqueo')) {
            $archivo->addFile($fichero->getPathname(), 'storage/' . $relativa);
            $archivos++;
        }
    }
    $archivo->addFromString('LEEME.txt', "Copia de seguridad de Ingeriar\n"
        . 'Fecha: ' . date('Y-m-d H:i:s') . "\n"
        . "Base de datos: {$baseDatos} (base-de-datos.sql)\n"
        . "Archivos de storage/: {$archivos}\n"
        . "Restaurar: php server/copias/restaurar.php <copia> --base=<base vacía> --confirmar\n");
    // Compresión deflate (el volcado SQL se reduce mucho; las fotos ya vienen comprimidas).
    $archivo->compressFiles(Phar::GZ);
    unset($archivo);

    // 4. Cifrado opcional y permisos.
    if ($clave !== '') {
        $final = $carpeta . '/' . $nombre . '.cifrado';
        cifrarFichero($zip, $final, $clave);
    } else {
        $final = $carpeta . '/' . $nombre;
        if (!rename($zip, $final)) {
            copy($zip, $final);
        }
        chmod($final, 0600);
    }

    // 5. Retención: se conservan las $retener copias más recientes.
    $borradas = 0;
    foreach (array_slice(listarCopias($carpeta), $retener) as $antigua) {
        unlink($carpeta . '/' . $antigua['nombre']);
        $borradas++;
    }

    anotar($registro, sprintf(
        'CORRECTA: %s (%s, %d archivos de storage, %.1f MB)%s%s',
        basename($final),
        $clave !== '' ? 'cifrada con AES-256' : 'SIN CIFRAR',
        $archivos,
        filesize($final) / 1048576,
        $borradas ? ", {$borradas} copias antiguas borradas" : '',
        $clave !== '' ? '' : ' · AVISO: contiene datos de menores; define INGERIAR_COPIAS_CLAVE para cifrarla'
    ));
} catch (Throwable $error) {
    anotar($registro, 'ERROR: ' . $error->getMessage());
    $codigo = 1;
} finally {
    foreach (array_reverse($temporales) as $temporal) {
        if (is_dir($temporal)) {
            array_map('unlink', glob($temporal . '/*') ?: []);
            rmdir($temporal);
        } elseif (is_file($temporal)) {
            unlink($temporal);
        }
    }
    flock($bloqueo, LOCK_UN);
    fclose($bloqueo);
}
exit($codigo);
