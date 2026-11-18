<?php

// Fotos de ejemplo para probar la galería y las facturas.
//
// - Galería: paisajes de acampada dibujados por el script (varias fotos por evento).
// - Facturas: un tique dibujado para cada factura que todavía no tiene imagen.
//
// Las imágenes se generan en PHP puro (formato PNG, sin la extensión gd), así que son
// ficticias y no contienen datos de nadie. Se guardan en storage/ como si se hubieran
// subido desde la aplicación (nombre aleatorio y fila en la base de datos).
//
// Uso: INGERIAR_DB_PASSWORD=... php server/pruebas/fotos-ejemplo.php --confirmar [--eventos=12]

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../data/almacenamiento.php';
require_once __DIR__ . '/dibujos.php';

if (!in_array('--confirmar', $argv, true)) {
    fwrite(STDERR, "Uso: php server/pruebas/fotos-ejemplo.php --confirmar [--eventos=12]\n"
        . "Añade fotos de ejemplo a la galería e imágenes a las facturas que no la tienen.\n");
    exit(2);
}
$numeroEventos = 12;
foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--eventos=')) {
        $numeroEventos = max(1, (int) substr($argumento, 10));
    }
}

// Guarda la imagen en storage/<tipo>/<carpeta>/ (E-17) y devuelve la ruta relativa.
function guardar(string $tipo, string $carpeta, string $prefijo, string $contenido): string
{
    $directorio = baseAlmacen($tipo) . '/' . $carpeta;
    if (!is_dir($directorio)) {
        mkdir($directorio, 0770, true);
    }
    $nombre = $prefijo . bin2hex(random_bytes(16)) . '.png';
    file_put_contents($directorio . '/' . $nombre, $contenido);
    return $carpeta . '/' . $nombre;
}

// ---------- Inserción ----------

$db = obtenerConexion();
$autor = $db->query(
    "SELECT ur.id_usuario FROM usuarios_roles ur WHERE ur.codigo_rol = 'administrador' ORDER BY ur.id_usuario LIMIT 1"
)->fetchColumn() ?: null;

// Galería: eventos repartidos por las fechas y los tipos, sin fotos todavía.
$eventos = $db->query(
    'SELECT e.id, e.nombre, e.fecha_inicio FROM eventos e
     WHERE NOT EXISTS (SELECT 1 FROM galeria_fotos g WHERE g.id_evento = e.id)
     ORDER BY e.fecha_inicio'
)->fetchAll();
$paso = max(1, intdiv(count($eventos), $numeroEventos));
$elegidos = array_slice(array_values(array_filter(
    $eventos,
    fn($indice) => $indice % $paso === 0,
    ARRAY_FILTER_USE_KEY
)), 0, $numeroEventos);
$insertarFoto = $db->prepare(
    'INSERT INTO galeria_fotos (id_evento, archivo, nombre_original, id_usuario) VALUES (?, ?, ?, ?)'
);
$fotos = 0;
foreach ($elegidos as $evento) {
    $cantidad = mt_rand(3, 6);
    for ($i = 1; $i <= $cantidad; $i++) {
        $archivo = guardar('galeria', carpetaGaleriaEvento((int) $evento['id'], $evento['nombre'], $evento['fecha_inicio']),
            '', dibujarPaisaje((int) $evento['id'] * 100 + $i));
        $insertarFoto->execute([$evento['id'], $archivo, 'foto-' . $i . '.png', $autor]);
        $fotos++;
    }
    echo "  Galería: {$cantidad} fotos en «{$evento['nombre']}»\n";
}

// Facturas: una imagen para cada factura que no la tiene.
$facturas = $db->query('SELECT id, fecha_compra FROM facturas WHERE imagen_factura IS NULL')->fetchAll();
$actualizar = $db->prepare('UPDATE facturas SET imagen_factura = ? WHERE id = ?');
foreach ($facturas as $factura) {
    $id = (int) $factura['id'];
    $archivo = guardar('facturas', carpetaFactura($factura['fecha_compra']), 'factura-' . $id . '-', dibujarTique($id));
    $actualizar->execute([$archivo, $id]);
}

echo "\n{$fotos} fotos añadidas a la galería en " . count($elegidos) . " eventos.\n"
    . count($facturas) . " facturas con imagen nueva.\n";
