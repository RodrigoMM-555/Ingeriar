<?php

// Funciones comunes de las copias de seguridad (E-12): copia.php, restaurar.php y la API.
//
// Configuración por variables de entorno (las mismas que usa Apache para la base de datos):
//   INGERIAR_COPIAS_DESTINO   carpeta de las copias (por defecto, copias/ en la raíz del
//                             proyecto, fuera de public/ y con acceso denegado)
//   INGERIAR_COPIAS_RETENER   cuántas copias se conservan (por defecto 10)
//   INGERIAR_COPIAS_CLAVE     si existe, la copia se cifra con AES-256

function variableEntorno(string $nombre, string $porDefecto = ''): string
{
    $valor = getenv($nombre);
    if ($valor === false || $valor === '') {
        $valor = $_SERVER[$nombre] ?? $porDefecto;
    }
    return (string) $valor;
}

function carpetaCopias(): string
{
    $carpeta = variableEntorno('INGERIAR_COPIAS_DESTINO', __DIR__ . '/../../copias');
    if (!is_dir($carpeta) && !mkdir($carpeta, 0700, true)) {
        throw new RuntimeException("No se pudo crear la carpeta de copias: {$carpeta}");
    }
    return realpath($carpeta);
}

// Nombre válido de una copia: ingeriar-copia-AAAA-MM-DD-HHMMSS.zip (o .zip.cifrado).
function esNombreDeCopia(string $nombre): bool
{
    return preg_match('/^ingeriar-copia-\d{4}-\d{2}-\d{2}-\d{6}\.zip(\.cifrado)?$/', $nombre) === 1;
}

// Copias existentes, de la más reciente a la más antigua.
function listarCopias(string $carpeta): array
{
    $copias = [];
    foreach (scandir($carpeta) as $nombre) {
        if (esNombreDeCopia($nombre)) {
            $ruta = $carpeta . '/' . $nombre;
            $copias[] = [
                'nombre' => $nombre,
                'tamano' => filesize($ruta),
                'fecha' => date('Y-m-d H:i:s', filemtime($ruta)),
                'cifrada' => str_ends_with($nombre, '.cifrado'),
            ];
        }
    }
    usort($copias, fn($a, $b) => strcmp($b['nombre'], $a['nombre']));
    return $copias;
}

// Fichero temporal de opciones de MySQL (permisos 0600) para que la contraseña no aparezca
// en la línea de órdenes (la vería cualquiera con «ps»). Hay que borrarlo al terminar.
function ficheroOpcionesMysql(): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'ingeriar-mysql-');
    chmod($ruta, 0600);
    $escapar = fn(string $valor) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $valor) . '"';
    file_put_contents($ruta, "[client]\n"
        . 'host=' . $escapar(variableEntorno('INGERIAR_DB_HOST', 'localhost')) . "\n"
        . 'port=' . (int) variableEntorno('INGERIAR_DB_PORT', '3306') . "\n"
        . 'user=' . $escapar(variableEntorno('INGERIAR_DB_USER', 'ingeriar_app')) . "\n"
        // Misma contraseña provisional que database.php mientras E-10 siga revertido.
        . 'password=' . $escapar(variableEntorno('INGERIAR_DB_PASSWORD', 'contraseña1234$')) . "\n");
    return $ruta;
}

// Ejecuta un programa externo con sus argumentos (sin pasar por la shell).
// Devuelve [código de salida, salida de error]. $entrada y $salida son rutas de fichero.
function ejecutar(array $orden, ?string $entrada = null, ?string $salida = null): array
{
    $descriptores = [
        0 => $entrada !== null ? ['file', $entrada, 'r'] : ['pipe', 'r'],
        1 => $salida !== null ? ['file', $salida, 'w'] : ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proceso = proc_open($orden, $descriptores, $tuberias);
    if (!is_resource($proceso)) {
        throw new RuntimeException('No se pudo ejecutar ' . $orden[0]);
    }
    if (isset($tuberias[0])) {
        fclose($tuberias[0]);
    }
    if (isset($tuberias[1])) {
        stream_get_contents($tuberias[1]);
        fclose($tuberias[1]);
    }
    $error = stream_get_contents($tuberias[2]);
    fclose($tuberias[2]);
    return [proc_close($proceso), trim($error)];
}

// ---------- Cifrado AES-256 por bloques ----------
//
// Formato del fichero .cifrado:
//   «INGCOPIA1» (9 bytes) · sal (16) · IV (16) · datos AES-256-CBC · HMAC-SHA256 (32)
// La clave de cifrado y la del HMAC salen de la contraseña con PBKDF2 (200 000 vueltas).
// Se cifra por bloques de 1 MB encadenando el IV, así no hace falta cargar la copia
// entera en memoria. El HMAC (cifrar y después autenticar) detecta cualquier cambio.

const CABECERA_CIFRADO = 'INGCOPIA1';
const BLOQUE_CIFRADO = 1048576;

function clavesCifrado(string $contrasena, string $sal): array
{
    $material = hash_pbkdf2('sha256', $contrasena, $sal, 200000, 64, true);
    return [substr($material, 0, 32), substr($material, 32, 32)];
}

function cifrarFichero(string $origen, string $destino, string $contrasena): void
{
    $sal = random_bytes(16);
    $iv = random_bytes(16);
    [$claveAes, $claveHmac] = clavesCifrado($contrasena, $sal);
    $hmac = hash_init('sha256', HASH_HMAC, $claveHmac);
    $entrada = fopen($origen, 'rb');
    $salida = fopen($destino, 'wb');
    $cabecera = CABECERA_CIFRADO . $sal . $iv;
    fwrite($salida, $cabecera);
    hash_update($hmac, $cabecera);
    $vector = $iv;
    $bloque = fread($entrada, BLOQUE_CIFRADO);
    while (true) {
        $siguiente = feof($entrada) ? '' : fread($entrada, BLOQUE_CIFRADO);
        $ultimo = $siguiente === '';
        // Solo el último bloque lleva relleno PKCS#7; los demás son múltiplos de 16 bytes.
        $opciones = OPENSSL_RAW_DATA | ($ultimo ? 0 : OPENSSL_ZERO_PADDING);
        $cifrado = openssl_encrypt($bloque, 'aes-256-cbc', $claveAes, $opciones, $vector);
        fwrite($salida, $cifrado);
        hash_update($hmac, $cifrado);
        $vector = substr($cifrado, -16);
        if ($ultimo) {
            break;
        }
        $bloque = $siguiente;
    }
    fwrite($salida, hash_final($hmac, true));
    fclose($entrada);
    fclose($salida);
    chmod($destino, 0600);
}

function descifrarFichero(string $origen, string $destino, string $contrasena): void
{
    $tamano = filesize($origen);
    $entrada = fopen($origen, 'rb');
    $cabecera = fread($entrada, 41);
    if (strlen($cabecera) !== 41 || !str_starts_with($cabecera, CABECERA_CIFRADO)) {
        throw new RuntimeException('El fichero no es una copia cifrada de Ingeriar');
    }
    [$claveAes, $claveHmac] = clavesCifrado($contrasena, substr($cabecera, 9, 16));
    // Primero se comprueba el HMAC: si la clave es incorrecta o el fichero se ha
    // modificado, no se descifra nada.
    $hmac = hash_init('sha256', HASH_HMAC, $claveHmac);
    hash_update($hmac, $cabecera);
    $restante = $tamano - 41 - 32;
    while ($restante > 0) {
        $trozo = fread($entrada, min(BLOQUE_CIFRADO, $restante));
        hash_update($hmac, $trozo);
        $restante -= strlen($trozo);
    }
    if (!hash_equals(fread($entrada, 32), hash_final($hmac, true))) {
        throw new RuntimeException('Clave incorrecta o copia dañada');
    }
    fseek($entrada, 41);
    $salida = fopen($destino, 'wb');
    $vector = substr($cabecera, 25, 16);
    $restante = $tamano - 41 - 32;
    while ($restante > 0) {
        $trozo = fread($entrada, min(BLOQUE_CIFRADO, $restante));
        $restante -= strlen($trozo);
        $opciones = OPENSSL_RAW_DATA | ($restante > 0 ? OPENSSL_ZERO_PADDING : 0);
        $claro = openssl_decrypt($trozo, 'aes-256-cbc', $claveAes, $opciones, $vector);
        if ($claro === false) {
            throw new RuntimeException('No se pudo descifrar la copia');
        }
        fwrite($salida, $claro);
        $vector = substr($trozo, -16);
    }
    fclose($entrada);
    fclose($salida);
}
