<?php

// Carpetas de los archivos subidos de la galería y las facturas (E-17).
//
//   storage/galeria/<ronda>/<id>-<nombre-del-evento>/<aleatorio>.<ext>
//   storage/facturas/<ronda>/factura-<id>-<aleatorio>.<ext>
//
// La ronda de una foto es la de la fecha de inicio de su evento; la de una factura, la de
// su fecha de compra. En la base de datos se guarda la ruta relativa a storage/<tipo>/.
// Si cambian la fecha o el nombre del evento, o la fecha de compra, el archivo se mueve a
// su carpeta nueva (recolocarFotosEvento, recolocarImagenFactura).
// Las funciones lanzan RuntimeException si falla el disco: así las usan igual la API y los
// scripts de línea de comandos.

require_once __DIR__ . '/secciones.php';

const TIPOS_ALMACEN = ['galeria', 'facturas'];

function baseAlmacen(string $tipo): string
{
    if (!in_array($tipo, TIPOS_ALMACEN, true)) {
        throw new InvalidArgumentException("Tipo de almacén desconocido: {$tipo}");
    }
    $directorio = __DIR__ . '/../../storage/' . $tipo;
    if (!is_dir($directorio) && !mkdir($directorio, 0770, true)) {
        throw new RuntimeException("No se pudo preparar la carpeta de {$tipo}");
    }
    return realpath($directorio);
}

// Ruta física de un archivo, solo si existe y está dentro de storage/<tipo>/.
function rutaEnAlmacen(string $tipo, ?string $relativa): ?string
{
    if (!$relativa) {
        return null;
    }
    $base = baseAlmacen($tipo);
    $ruta = realpath($base . DIRECTORY_SEPARATOR . ltrim($relativa, '/'));
    if ($ruta === false || !is_file($ruta) || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $ruta;
}

// «45-acampada-de-otono»: id delante (no se repite) y nombre sin acentos ni símbolos.
function carpetaConNombre(int $id, string $nombre): string
{
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre) ?: $nombre;
    $texto = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($texto)), '-');
    return $id . ($texto !== '' ? '-' . substr($texto, 0, 60) : '');
}

function carpetaGaleriaEvento(int $id, string $nombre, string $fechaInicio): string
{
    return rondaActual(new DateTimeImmutable($fechaInicio)) . '/' . carpetaConNombre($id, $nombre);
}

function carpetaFactura(string $fechaCompra): string
{
    return rondaActual(new DateTimeImmutable($fechaCompra));
}

// Guarda un archivo recién subido en storage/<tipo>/<carpeta>/<nombre> y devuelve la ruta relativa.
function guardarSubidoEn(string $tipo, string $carpeta, string $nombre, string $temporal): string
{
    $directorio = baseAlmacen($tipo) . DIRECTORY_SEPARATOR . $carpeta;
    if (!is_dir($directorio) && !mkdir($directorio, 0770, true)) {
        throw new RuntimeException('No se pudo preparar la carpeta ' . $carpeta);
    }
    if (!move_uploaded_file($temporal, $directorio . DIRECTORY_SEPARATOR . $nombre)) {
        throw new RuntimeException('No se pudo guardar el archivo');
    }
    return $carpeta . '/' . $nombre;
}

// Quita la carpeta y las de encima mientras estén vacías (sin salir de storage/<tipo>/).
function quitarCarpetasVacias(string $tipo, string $carpeta): void
{
    $base = baseAlmacen($tipo);
    while ($carpeta !== $base && str_starts_with($carpeta, $base . DIRECTORY_SEPARATOR)
        && is_dir($carpeta) && count(scandir($carpeta)) === 2) {
        rmdir($carpeta);
        $carpeta = dirname($carpeta);
    }
}

function borrarDeAlmacen(string $tipo, ?string $relativa): bool
{
    $ruta = rutaEnAlmacen($tipo, $relativa);
    if ($ruta === null || !unlink($ruta)) {
        return false;
    }
    quitarCarpetasVacias($tipo, dirname($ruta));
    return true;
}

// Mueve un archivo a la carpeta que le corresponde. Devuelve su ruta relativa nueva (la
// misma si ya estaba bien o si el archivo no existe).
function recolocarEnAlmacen(string $tipo, string $relativa, string $carpeta): string
{
    $nueva = $carpeta . '/' . basename($relativa);
    $origen = rutaEnAlmacen($tipo, $relativa);
    if ($nueva === $relativa || $origen === null) {
        return $relativa;
    }
    $destino = baseAlmacen($tipo) . DIRECTORY_SEPARATOR . $nueva;
    if (!is_dir(dirname($destino)) && !mkdir(dirname($destino), 0770, true)) {
        throw new RuntimeException('No se pudo preparar la carpeta ' . $carpeta);
    }
    if (!rename($origen, $destino)) {
        throw new RuntimeException('No se pudo mover ' . $relativa);
    }
    quitarCarpetasVacias($tipo, dirname($origen));
    return $nueva;
}

// Pone las fotos de un evento en su carpeta (tras cambiar su fecha o su nombre).
// Devuelve cuántas se han movido.
function recolocarFotosEvento(PDO $db, int $eventoId): int
{
    if (!$db->query("SHOW TABLES LIKE 'galeria_fotos'")->fetchColumn()) {
        return 0;
    }
    $stmt = $db->prepare('SELECT id, nombre, fecha_inicio FROM eventos WHERE id = ?');
    $stmt->execute([$eventoId]);
    $evento = $stmt->fetch();
    if (!$evento) {
        return 0;
    }
    $carpeta = carpetaGaleriaEvento((int) $evento['id'], $evento['nombre'], $evento['fecha_inicio']);
    $fotos = $db->prepare('SELECT id, archivo FROM galeria_fotos WHERE id_evento = ?');
    $fotos->execute([$eventoId]);
    $actualizar = $db->prepare('UPDATE galeria_fotos SET archivo = ? WHERE id = ?');
    $movidas = 0;
    foreach ($fotos->fetchAll() as $foto) {
        $nueva = recolocarEnAlmacen('galeria', $foto['archivo'], $carpeta);
        if ($nueva !== $foto['archivo']) {
            $actualizar->execute([$nueva, $foto['id']]);
            $movidas++;
        }
    }
    return $movidas;
}

// Pone la imagen de una factura en la carpeta de la ronda de su fecha de compra.
function recolocarImagenFactura(PDO $db, int $facturaId): bool
{
    $stmt = $db->prepare('SELECT imagen_factura, fecha_compra FROM facturas WHERE id = ?');
    $stmt->execute([$facturaId]);
    $factura = $stmt->fetch();
    if (!$factura || !$factura['imagen_factura']) {
        return false;
    }
    $nueva = recolocarEnAlmacen('facturas', $factura['imagen_factura'], carpetaFactura($factura['fecha_compra']));
    if ($nueva === $factura['imagen_factura']) {
        return false;
    }
    $db->prepare('UPDATE facturas SET imagen_factura = ? WHERE id = ?')->execute([$nueva, $facturaId]);
    return true;
}
