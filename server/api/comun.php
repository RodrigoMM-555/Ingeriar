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
