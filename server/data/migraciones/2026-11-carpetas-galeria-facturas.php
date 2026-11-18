<?php

// E-17: mueve las fotos de la galería y las imágenes de las facturas que están sueltas en
// storage/galeria/ y storage/facturas/ a su carpeta:
//   storage/galeria/<ronda>/<id>-<evento>/      (ronda de la fecha de inicio del evento)
//   storage/facturas/<ronda>/                   (ronda de la fecha de compra)
// y actualiza la ruta en la base de datos.
//
// Se ejecuta una vez en cada instalación con archivos anteriores a E-17, con el usuario del
// servidor web (dueño de storage/) y desde la copia que sirve Apache:
//   sudo -u www-data php /var/www/html/Ingeriar/server/data/migraciones/2026-11-carpetas-galeria-facturas.php --confirmar
// Sin --confirmar solo cuenta lo que movería. Se puede repetir: lo que ya está en su sitio se salta.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../almacenamiento.php';

$confirmar = in_array('--confirmar', $argv, true);
$db = obtenerConexion();

// Galería
$fotosPendientes = 0;
$fotosMovidas = 0;
$eventos = $db->query(
    'SELECT DISTINCT e.id, e.nombre, e.fecha_inicio FROM eventos e JOIN galeria_fotos g ON g.id_evento = e.id'
)->fetchAll();
foreach ($eventos as $evento) {
    $carpeta = carpetaGaleriaEvento((int) $evento['id'], $evento['nombre'], $evento['fecha_inicio']);
    $stmt = $db->prepare('SELECT archivo FROM galeria_fotos WHERE id_evento = ?');
    $stmt->execute([$evento['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $archivo) {
        if ($archivo !== $carpeta . '/' . basename($archivo)) {
            $fotosPendientes++;
        }
    }
    if ($confirmar) {
        $fotosMovidas += recolocarFotosEvento($db, (int) $evento['id']);
    }
}

// Facturas
$facturasPendientes = 0;
$facturasMovidas = 0;
$facturas = $db->query('SELECT id, imagen_factura, fecha_compra FROM facturas WHERE imagen_factura IS NOT NULL')->fetchAll();
foreach ($facturas as $factura) {
    $esperada = carpetaFactura($factura['fecha_compra']) . '/' . basename($factura['imagen_factura']);
    if ($factura['imagen_factura'] !== $esperada) {
        $facturasPendientes++;
    }
    if ($confirmar && recolocarImagenFactura($db, (int) $factura['id'])) {
        $facturasMovidas++;
    }
}

echo "Galería: {$fotosPendientes} fotos fuera de su carpeta"
    . ($confirmar ? ", {$fotosMovidas} movidas" : '') . ".\n";
echo "Facturas: {$facturasPendientes} imágenes fuera de su carpeta"
    . ($confirmar ? ", {$facturasMovidas} movidas" : '') . ".\n";
if (!$confirmar) {
    echo "Sin --confirmar no se ha cambiado nada.\n";
} elseif ($fotosMovidas < $fotosPendientes || $facturasMovidas < $facturasPendientes) {
    echo "Las que no se han movido no tienen el archivo en el disco (se dejan como estaban).\n";
}
