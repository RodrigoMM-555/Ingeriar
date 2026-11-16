<?php

function obtenerConexion(bool $incluirBase = true): PDO
{
    $obtenerVariable = static function (string $nombre, string $porDefecto = ''): string {
        $valor = getenv($nombre);
        if ($valor === false || $valor === '') {
            $valor = $_SERVER[$nombre] ?? $porDefecto;
        }
        return (string) $valor;
    };

    $host = $obtenerVariable('INGERIAR_DB_HOST', 'localhost');
    $puerto = $obtenerVariable('INGERIAR_DB_PORT', '3306');
    $nombre = $obtenerVariable('INGERIAR_DB_NAME', 'ingeriar');
    $usuario = $obtenerVariable('INGERIAR_DB_USER', 'ingeriar_app');
    // Sin valor por defecto: la contraseña de MySQL solo puede venir de la
    // configuración del servidor (variable de entorno), nunca del repositorio.
    $contrasena = $obtenerVariable('INGERIAR_DB_PASSWORD');

    if ($contrasena === '') {
        throw new RuntimeException(
            'Falta INGERIAR_DB_PASSWORD. Configúrala en el VirtualHost de Apache '
            . 'y reinicia Apache.'
        );
    }

    $dsn = "mysql:host={$host};port={$puerto};"
        . ($incluirBase ? "dbname={$nombre};" : '')
        . 'charset=utf8mb4';
    return new PDO($dsn, $usuario, $contrasena, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
