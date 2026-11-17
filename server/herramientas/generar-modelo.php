<?php

// Genera server/data/modelo.json (el modelo del instalador, E-11) a partir de una base de
// datos ya creada con schema.sql. Así el asistente instala exactamente el mismo esquema.
//
// Uso: INGERIAR_DB_NAME=<base creada con schema.sql> INGERIAR_DB_PASSWORD=... \
//      php server/herramientas/generar-modelo.php
// Hay que volver a ejecutarlo cada vez que cambie schema.sql.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../instalacion/ModeloDatos.php';

$modelo = ModeloDatos::validar(ModeloDatos::desdeBase(obtenerConexion()));
$destino = __DIR__ . '/../data/modelo.json';
file_put_contents($destino, json_encode($modelo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
echo count($modelo['tablas']) . " tablas escritas en server/data/modelo.json\n";
