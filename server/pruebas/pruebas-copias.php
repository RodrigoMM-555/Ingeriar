<?php

// Pruebas de las copias de seguridad (E-12): copia, retención, bloqueo, cifrado y una
// restauración completa en una base vacía.
//
// Uso (con las mismas variables de entorno de base de datos que la aplicación):
//   php server/pruebas/pruebas-copias.php --base-vacia=<base de datos vacía> --confirmar
//
// La base vacía debe existir y el usuario de la aplicación debe poder escribir en ella.
// Las copias se generan en una carpeta temporal; nada se escribe en copias/ ni en storage/.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/../copias/comun-copias.php';

$baseVacia = null;
foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--base-vacia=')) {
        $baseVacia = substr($argumento, 13);
    }
}
if (!in_array('--confirmar', $argv, true) || !$baseVacia) {
    fwrite(STDERR, "Uso: php server/pruebas/pruebas-copias.php --base-vacia=<base vacía> --confirmar\n");
    exit(2);
}

$resultados = [];
function prueba(string $id, string $descripcion, callable $comprobacion): void
{
    global $resultados;
    try {
        $detalle = $comprobacion();
        $superada = $detalle === true;
        $mensaje = is_string($detalle) ? $detalle : '';
    } catch (Throwable $error) {
        $superada = false;
        $mensaje = $error->getMessage();
    }
    $resultados[] = $superada;
    echo ($superada ? '  OK     ' : '  FALLO  ') . "{$id}  {$descripcion}" . ($superada ? '' : "  → {$mensaje}") . "\n";
}

// Ejecuta un script PHP con variables de entorno adicionales. Devuelve [código, salida].
function ejecutarScript(string $script, array $argumentos, array $entorno): array
{
    $proceso = proc_open(
        [PHP_BINARY, __DIR__ . '/../copias/' . $script, ...$argumentos],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $tuberias,
        null,
        $entorno + getenv()
    );
    $salida = stream_get_contents($tuberias[1]) . stream_get_contents($tuberias[2]);
    fclose($tuberias[1]);
    fclose($tuberias[2]);
    return [proc_close($proceso), $salida];
}

function conexion(string $base): PDO
{
    return new PDO(
        'mysql:host=' . variableEntorno('INGERIAR_DB_HOST', 'localhost') . ';port='
            . (int) variableEntorno('INGERIAR_DB_PORT', '3306') . ";dbname={$base};charset=utf8mb4",
        variableEntorno('INGERIAR_DB_USER', 'ingeriar_app'),
        variableEntorno('INGERIAR_DB_PASSWORD', 'contraseña1234$'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function filasPorTabla(PDO $db): array
{
    $tablas = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $filas = [];
    foreach ($tablas as $tabla) {
        $filas[$tabla] = (int) $db->query('SELECT COUNT(*) FROM `' . $tabla . '`')->fetchColumn();
    }
    ksort($filas);
    return $filas;
}

function borrarCarpeta(string $carpeta): void
{
    if (!is_dir($carpeta)) {
        return;
    }
    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterador as $elemento) {
        $elemento->isDir() ? rmdir($elemento->getPathname()) : unlink($elemento->getPathname());
    }
    rmdir($carpeta);
}

$destino = sys_get_temp_dir() . '/ingeriar-pruebas-copias-' . bin2hex(random_bytes(4));
$storageRestaurado = sys_get_temp_dir() . '/ingeriar-pruebas-storage-' . bin2hex(random_bytes(4));
mkdir($destino, 0700);
$entorno = ['INGERIAR_COPIAS_DESTINO' => $destino, 'INGERIAR_COPIAS_RETENER' => '10', 'INGERIAR_COPIAS_CLAVE' => ''];
$baseOrigen = variableEntorno('INGERIAR_DB_NAME', 'ingeriar');
$storageOrigen = realpath(__DIR__ . '/../../storage');

echo "Pruebas de copias de seguridad (origen «{$baseOrigen}», restauración en «{$baseVacia}»)\n\n";
try {
    if (filasPorTabla(conexion($baseVacia))) {
        throw new RuntimeException("La base «{$baseVacia}» no está vacía");
    }

    prueba('CPS-01', 'La copia genera un zip con la base de datos y storage/, con permisos 0600', function () use ($entorno, $destino, $storageOrigen) {
        [$codigo, $salida] = ejecutarScript('copia.php', [], $entorno);
        $copias = listarCopias($destino);
        if ($codigo !== 0 || count($copias) !== 1) {
            return "código {$codigo}: {$salida}";
        }
        $ruta = $destino . '/' . $copias[0]['nombre'];
        $contenido = [];
        foreach (new RecursiveIteratorIterator(new PharData($ruta)) as $fichero) {
            $contenido[] = str_replace('phar://' . $ruta . '/', '', $fichero->getPathname());
        }
        $enStorage = iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageOrigen, FilesystemIterator::SKIP_DOTS)));
        $copiados = count(array_filter($contenido, fn($nombre) => str_starts_with($nombre, 'storage/')));
        return in_array('base-de-datos.sql', $contenido, true) && in_array('LEEME.txt', $contenido, true)
            && $copiados >= $enStorage - 2 && (fileperms($ruta) & 0777) === 0600
            ?: "contenido " . count($contenido) . ", storage {$copiados}/{$enStorage}, permisos " . decoct(fileperms($ruta) & 0777);
    });

    prueba('CPS-02', 'La retención conserva solo las N copias más recientes', function () use ($entorno, $destino) {
        sleep(1);
        ejecutarScript('copia.php', [], ['INGERIAR_COPIAS_RETENER' => '2'] + $entorno);
        sleep(1);
        ejecutarScript('copia.php', [], ['INGERIAR_COPIAS_RETENER' => '2'] + $entorno);
        $copias = listarCopias($destino);
        return count($copias) === 2 ?: count($copias) . ' copias';
    });

    prueba('CPS-03', 'No se ejecutan dos copias a la vez (bloqueo)', function () use ($entorno, $destino) {
        $bloqueo = fopen($destino . '/.copia.bloqueo', 'c');
        flock($bloqueo, LOCK_EX);
        [$codigo] = ejecutarScript('copia.php', [], $entorno);
        flock($bloqueo, LOCK_UN);
        fclose($bloqueo);
        $registro = file_get_contents($destino . '/registro-copias.log');
        return $codigo === 3 && str_contains($registro, 'OMITIDA') ?: "código {$codigo}";
    });

    prueba('CPS-04', 'Con clave, la copia se cifra con AES-256 y no se puede abrir sin ella', function () use ($entorno, $destino) {
        sleep(1);
        [$codigo] = ejecutarScript('copia.php', [], ['INGERIAR_COPIAS_CLAVE' => 'clave-de-prueba'] + $entorno);
        $cifrada = listarCopias($destino)[0];
        try {
            new PharData($destino . '/' . $cifrada['nombre']);
            $abre = true;
        } catch (Throwable $error) {
            $abre = false;
        }
        return $codigo === 0 && $cifrada['cifrada'] && !$abre ?: json_encode($cifrada);
    });

    prueba('CPS-05', 'El registro anota cada ejecución y avisa de las copias sin cifrar', function () use ($destino) {
        $registro = file_get_contents($destino . '/registro-copias.log');
        return substr_count($registro, 'CORRECTA') >= 4 && str_contains($registro, 'SIN CIFRAR')
            && str_contains($registro, 'cifrada con AES-256') ?: $registro;
    });

    prueba('CPS-06', 'Con una clave incorrecta no se restaura nada', function () use ($entorno, $destino, $baseVacia, $storageRestaurado) {
        $copia = $destino . '/' . listarCopias($destino)[0]['nombre'];
        [$codigo, $salida] = ejecutarScript('restaurar.php', [$copia, "--base={$baseVacia}", "--storage={$storageRestaurado}", '--confirmar'],
            ['INGERIAR_COPIAS_CLAVE' => 'otra-clave'] + $entorno);
        return $codigo === 1 && !filasPorTabla(conexion($baseVacia)) ?: "código {$codigo}: {$salida}";
    });

    prueba('CPS-07', 'Restaurar la copia cifrada en una base vacía reproduce datos y archivos', function () use ($entorno, $destino, $baseVacia, $baseOrigen, $storageRestaurado, $storageOrigen) {
        $copia = $destino . '/' . listarCopias($destino)[0]['nombre'];
        [$codigo, $salida] = ejecutarScript('restaurar.php', [$copia, "--base={$baseVacia}", "--storage={$storageRestaurado}", '--confirmar'],
            ['INGERIAR_COPIAS_CLAVE' => 'clave-de-prueba'] + $entorno);
        if ($codigo !== 0) {
            return "código {$codigo}: {$salida}";
        }
        $origen = filasPorTabla(conexion($baseOrigen));
        $restaurada = filasPorTabla(conexion($baseVacia));
        $archivos = fn($carpeta) => array_map(
            fn($fichero) => [substr($fichero->getPathname(), strlen($carpeta) + 1), md5_file($fichero->getPathname())],
            array_filter(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS)), false),
                fn($fichero) => !str_ends_with($fichero->getFilename(), '.bloqueo'))
        );
        $archivosOrigen = $archivos($storageOrigen);
        $archivosRestaurados = $archivos(realpath($storageRestaurado));
        sort($archivosOrigen);
        sort($archivosRestaurados);
        return $origen === $restaurada && $archivosOrigen === $archivosRestaurados
            ?: 'tablas ' . json_encode(array_diff_assoc($origen, $restaurada)) . ', archivos ' . count($archivosOrigen) . '/' . count($archivosRestaurados);
    });

    prueba('CPS-08', 'No se restaura sobre una base con datos sin --sobrescribir', function () use ($entorno, $destino, $baseVacia, $storageRestaurado) {
        $copia = $destino . '/' . listarCopias($destino)[0]['nombre'];
        [$codigo, $salida] = ejecutarScript('restaurar.php', [$copia, "--base={$baseVacia}", "--storage={$storageRestaurado}-2", '--confirmar'],
            ['INGERIAR_COPIAS_CLAVE' => 'clave-de-prueba'] + $entorno);
        return $codigo === 1 && str_contains($salida, 'no está vacía') ?: "código {$codigo}: {$salida}";
    });
} finally {
    // Se deja la base de restauración vacía otra vez.
    try {
        $db = conexion($baseVacia);
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
            $db->exec('DROP TABLE `' . $tabla . '`');
        }
        foreach ($db->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE()")->fetchAll() as $procedimiento) {
            $db->exec('DROP PROCEDURE `' . $procedimiento['Name'] . '`');
        }
    } catch (Throwable $error) {
        echo "No se pudo vaciar «{$baseVacia}»: {$error->getMessage()}\n";
    }
    borrarCarpeta($destino);
    borrarCarpeta($storageRestaurado);
}

$superadas = count(array_filter($resultados));
echo "\n{$superadas} de " . count($resultados) . " pruebas superadas.\n";
exit($superadas === count($resultados) ? 0 : 1);
