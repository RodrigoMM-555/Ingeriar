<?php

// Funciones comunes de las API (E-02): antes había una copia en cada archivo.

// Responde en JSON y termina la petición. También la usan sesion.php y permisos.php
// para los errores 401 y 403.
function responder($datos, int $estado = 200): never
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Tamaño máximo de un archivo subido, en bytes, según la configuración del grupo.
function tamanoMaximoArchivos(): int
{
    $ruta = __DIR__ . '/../data/config.php';
    $configuracion = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
    return max(1, (int) ($configuracion['tamano_maximo_archivos_mb'] ?? 10)) * 1024 * 1024;
}

// Borra una carpeta con todo su contenido, solo si está dentro de $base (E-16, rondas
// vencidas). Devuelve cuántos archivos ha borrado.
function borrarCarpetaDentroDe(string $carpeta, string $base): int
{
    $carpeta = realpath($carpeta);
    $base = realpath($base);
    if ($carpeta === false || $base === false || !is_dir($carpeta)
        || !str_starts_with($carpeta, $base . DIRECTORY_SEPARATOR)) {
        return 0;
    }
    $borrados = 0;
    $elementos = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($elementos as $elemento) {
        if ($elemento->isDir() && !$elemento->isLink()) {
            rmdir($elemento->getPathname());
        } elseif (unlink($elemento->getPathname())) {
            $borrados++;
        }
    }
    rmdir($carpeta);
    return $borrados;
}
