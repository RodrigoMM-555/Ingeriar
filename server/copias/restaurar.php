<?php

// Restauración de una copia de seguridad (E-12).
//
//   php server/copias/restaurar.php <copia.zip|copia.zip.cifrado> --base=<base vacía> --confirmar
//       [--storage=<carpeta>]   destino de los archivos (por defecto, storage/ del proyecto)
//       [--sobrescribir]        permite restaurar sobre una base o una carpeta con datos
//
// Si la copia está cifrada, la clave se toma de INGERIAR_COPIAS_CLAVE. La conexión con
// MySQL usa las mismas variables de entorno que la aplicación.
// Procedimiento completo y recomendaciones: noteas.md, apartado «Copias de seguridad».

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/comun-copias.php';

$copia = null;
$opciones = [];
foreach (array_slice($argv, 1) as $argumento) {
    if (str_starts_with($argumento, '--')) {
        [$clave, $valor] = array_pad(explode('=', substr($argumento, 2), 2), 2, true);
        $opciones[$clave] = $valor;
    } else {
        $copia = $argumento;
    }
}
if ($copia === null || !is_file($copia) || empty($opciones['base']) || !isset($opciones['confirmar'])) {
    fwrite(STDERR, "Uso: php server/copias/restaurar.php <copia> --base=<base de datos> --confirmar"
        . " [--storage=<carpeta>] [--sobrescribir]\n");
    exit(2);
}
$base = (string) $opciones['base'];
$destinoStorage = (string) ($opciones['storage'] ?? __DIR__ . '/../../storage');
$sobrescribir = isset($opciones['sobrescribir']);
if (preg_match('/^[A-Za-z0-9_]+$/', $base) !== 1) {
    fwrite(STDERR, "Nombre de base de datos no válido\n");
    exit(2);
}

// ¿Tiene la carpeta algún archivo aparte de .gitkeep y .htaccess?
function carpetaConDatos(string $carpeta): bool
{
    if (!is_dir($carpeta)) {
        return false;
    }
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS));
    foreach ($iterador as $fichero) {
        if ($fichero->isFile() && !in_array($fichero->getFilename(), ['.gitkeep', '.htaccess'], true)) {
            return true;
        }
    }
    return false;
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

$trabajo = sys_get_temp_dir() . '/ingeriar-restaurar-' . bin2hex(random_bytes(6));
mkdir($trabajo, 0700);
$ficheroOpciones = ficheroOpcionesMysql();
try {
    // 1. Descifrar si hace falta.
    $zip = $copia;
    if (str_ends_with($copia, '.cifrado')) {
        $clave = variableEntorno('INGERIAR_COPIAS_CLAVE');
        if ($clave === '') {
            throw new RuntimeException('La copia está cifrada: define INGERIAR_COPIAS_CLAVE');
        }
        $zip = $trabajo . '/copia.zip';
        descifrarFichero($copia, $zip, $clave);
        echo "Copia descifrada.\n";
    }

    // 2. Extraer.
    $extraida = $trabajo . '/extraida';
    (new PharData($zip))->extractTo($extraida);
    if (!is_file($extraida . '/base-de-datos.sql')) {
        throw new RuntimeException('La copia no contiene base-de-datos.sql');
    }

    // 3. Comprobaciones antes de tocar nada.
    $pdo = new PDO(
        'mysql:host=' . variableEntorno('INGERIAR_DB_HOST', 'localhost') . ';port='
            . (int) variableEntorno('INGERIAR_DB_PORT', '3306') . ';dbname=' . $base . ';charset=utf8mb4',
        variableEntorno('INGERIAR_DB_USER', 'ingeriar_app'),
        variableEntorno('INGERIAR_DB_PASSWORD', 'contraseña1234$'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $tablas = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
    if ($tablas > 0 && !$sobrescribir) {
        throw new RuntimeException("La base «{$base}» no está vacía ({$tablas} tablas). Usa una base vacía o --sobrescribir.");
    }
    if (carpetaConDatos($destinoStorage) && !$sobrescribir) {
        throw new RuntimeException("La carpeta {$destinoStorage} ya tiene archivos. Usa otra carpeta o --sobrescribir.");
    }

    // 4. Base de datos.
    [$salida, $error] = ejecutar(
        ['mysql', '--defaults-extra-file=' . $ficheroOpciones, '--default-character-set=utf8mb4', $base],
        $extraida . '/base-de-datos.sql'
    );
    if ($salida !== 0) {
        throw new RuntimeException('mysql falló al importar: ' . $error);
    }
    echo "Base de datos restaurada en «{$base}».\n";

    // 5. Archivos de storage/.
    $restaurados = 0;
    if (is_dir($extraida . '/storage')) {
        $origen = realpath($extraida . '/storage');
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origen, FilesystemIterator::SKIP_DOTS));
        foreach ($iterador as $fichero) {
            if (!$fichero->isFile()) {
                continue;
            }
            $destino = $destinoStorage . '/' . substr($fichero->getPathname(), strlen($origen) + 1);
            if (!is_dir(dirname($destino))) {
                mkdir(dirname($destino), 0770, true);
            }
            copy($fichero->getPathname(), $destino);
            $restaurados++;
        }
    }
    echo "{$restaurados} archivos restaurados en {$destinoStorage}.\n";
    $codigo = 0;
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
    $codigo = 1;
} finally {
    unlink($ficheroOpciones);
    borrarCarpeta($trabajo);
}
exit($codigo);
