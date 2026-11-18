<?php

// Inventario y listas de material de ejemplo (E-04, E-15) para probar el plano y las listas.
//
// Se cargan a través de la API (api/inventario.php), como si lo hiciera un monitor desde la
// aplicación: así pasan las mismas validaciones y el mismo bloqueo de fichero, y no hace
// falta tener permiso de escritura en storage/. Los datos son ficticios.
// Lo que ya existe (ubicación con el mismo nombre en el mismo sitio, material o lista con
// el mismo nombre) no se duplica: se puede ejecutar varias veces.
//
// Uso: php server/pruebas/inventario-ejemplo.php <url-base> <correo> <contraseña> --confirmar
// Ejemplo: php server/pruebas/inventario-ejemplo.php http://localhost/Ingeriar/public usuario2@ingeriar.test demo-2 --confirmar

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

$argumentos = array_values(array_filter(array_slice($argv, 1), fn($argumento) => !str_starts_with($argumento, '--')));
if (count($argumentos) !== 3 || !in_array('--confirmar', $argv, true)) {
    fwrite(STDERR, "Uso: php server/pruebas/inventario-ejemplo.php <url-base> <correo> <contraseña> --confirmar\n"
        . "Añade ubicaciones, materiales y listas de ejemplo con un usuario que pueda gestionar el inventario.\n");
    exit(2);
}
[$base, $correo, $contrasena] = $argumentos;
$base = rtrim($base, '/');
$cookies = tempnam(sys_get_temp_dir(), 'ingeriar-inventario');

function peticion(string $url, ?array $datos = null): array
{
    global $cookies;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookies,
        CURLOPT_COOKIEFILE => $cookies,
    ]);
    if ($datos !== null) {
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($datos),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
    }
    $texto = (string) curl_exec($curl);
    $codigo = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $json = json_decode($texto, true);
    if ($codigo !== 200) {
        fwrite(STDERR, "Error {$codigo} en {$url}: {$texto}\n");
        exit(1);
    }
    return $json ?? [];
}

function accion(string $accion, array $datos = []): array
{
    global $base;
    return $accion === 'datos'
        ? peticion("{$base}/api/inventario.php?accion=datos")
        : peticion("{$base}/api/inventario.php?accion={$accion}", $datos);
}

peticion("{$base}/api/login.php", ['email' => $correo, 'contrasena' => $contrasena]);
$datos = accion('datos');

// ---------- Ubicaciones (árbol) ----------
// [nombre, [hijas...]]
$arbol = [
    ['Local', [
        ['Armario de secciones', [['Balda Colonia', []], ['Balda Manada', []], ['Balda Tropa', []], ['Balda Posta', []]]],
        ['Despacho', []],
        ['Cocina del local', []],
    ]],
    ['Trastero', [
        ['Zona A (material general)', [['Estantería metálica 1', []], ['Estantería metálica 2', []]]],
        ['Zona B (tiendas y lonas)', []],
        ['Zona C (cocina de campamento)', [['Cajas de menaje', []]]],
    ]],
    ['Furgoneta del grupo', []],
    ['Casa de colonias (préstamo)', [['Almacén de la casa', []]]],
];

$ids = [];
$crearUbicaciones = function (array $nodos, ?string $padre) use (&$crearUbicaciones, &$datos, &$ids): void {
    foreach ($nodos as [$nombre, $hijas]) {
        $existente = array_values(array_filter($datos['inventario']['ubicaciones'],
            fn($ubicacion) => $ubicacion['nombre'] === $nombre && $ubicacion['padre'] === $padre))[0] ?? null;
        if (!$existente) {
            $respuesta = accion('guardar_ubicacion', ['nombre' => $nombre, 'padre' => $padre ?? '']);
            $datos['inventario'] = $respuesta['inventario'];
            $existente = end($datos['inventario']['ubicaciones']);
            echo "Ubicación: {$nombre}\n";
        }
        $ids[$nombre] = $existente['id'];
        $crearUbicaciones($hijas, $existente['id']);
    }
};
$crearUbicaciones($arbol, null);

// ---------- Materiales ----------
// [nombre, unidad, notas, [ubicación => cantidad]]
$materiales = [
    ['Tienda canadiense 4 plazas', 'uds', 'Revisar varillas antes de cada salida', ['Zona B (tiendas y lonas)' => 6, 'Furgoneta del grupo' => 2]],
    ['Tienda iglú 2 plazas', 'uds', '', ['Zona B (tiendas y lonas)' => 8]],
    ['Lona 4x6', 'uds', '', ['Zona B (tiendas y lonas)' => 5, 'Almacén de la casa' => 2]],
    ['Piquetas', 'uds', 'Bolsas de 20', ['Zona B (tiendas y lonas)' => 120, 'Furgoneta del grupo' => 40]],
    ['Cuerda de 10 m', 'rollos', '', ['Estantería metálica 1' => 12, 'Balda Tropa' => 4]],
    ['Cuerda de pionerismo', 'rollos', 'Para construcciones', ['Estantería metálica 1' => 6]],
    ['Linterna frontal', 'uds', '', ['Estantería metálica 2' => 15, 'Despacho' => 3]],
    ['Pilas AA', 'uds', '', ['Estantería metálica 2' => 48, 'Despacho' => 12]],
    ['Botiquín grande', 'uds', 'Revisar caducidades', ['Despacho' => 1, 'Furgoneta del grupo' => 1]],
    ['Botiquín de mochila', 'uds', '', ['Despacho' => 4, 'Balda Posta' => 2]],
    ['Hornillo de gas', 'uds', '', ['Zona C (cocina de campamento)' => 3, 'Cocina del local' => 1]],
    ['Bombona de gas', 'uds', '', ['Zona C (cocina de campamento)' => 4]],
    ['Olla grande 30 l', 'uds', '', ['Zona C (cocina de campamento)' => 2, 'Almacén de la casa' => 1]],
    ['Sartén paellera', 'uds', '', ['Zona C (cocina de campamento)' => 1]],
    ['Platos de plástico', 'uds', '', ['Cajas de menaje' => 60, 'Cocina del local' => 30]],
    ['Vasos de plástico', 'uds', '', ['Cajas de menaje' => 70]],
    ['Cubiertos (juego)', 'uds', 'Cuchara, tenedor y cuchillo', ['Cajas de menaje' => 50, 'Cocina del local' => 20]],
    ['Garrafa de agua 25 l', 'uds', '', ['Zona C (cocina de campamento)' => 4, 'Furgoneta del grupo' => 2]],
    ['Mesa plegable', 'uds', '', ['Zona A (material general)' => 4, 'Almacén de la casa' => 6]],
    ['Silla plegable', 'uds', '', ['Zona A (material general)' => 10]],
    ['Pañoleta de repuesto', 'uds', '', ['Despacho' => 15]],
    ['Brújula', 'uds', '', ['Balda Tropa' => 8, 'Balda Posta' => 6]],
    ['Mapa topográfico de la sierra', 'uds', '', ['Balda Posta' => 3]],
    ['Pinturas de cara', 'cajas', '', ['Balda Colonia' => 3, 'Balda Manada' => 2]],
    ['Cartulinas', 'paquetes', '', ['Balda Colonia' => 4, 'Balda Manada' => 3, 'Despacho' => 2]],
    ['Rotuladores', 'cajas', '', ['Balda Colonia' => 5, 'Balda Manada' => 4]],
    ['Balón', 'uds', '', ['Balda Manada' => 3, 'Balda Tropa' => 2]],
    ['Silbato', 'uds', '', ['Despacho' => 10]],
    ['Megáfono', 'uds', 'Pilas aparte', ['Despacho' => 1]],
    ['Hacha de mano', 'uds', 'Solo con monitor', ['Estantería metálica 1' => 2]],
    ['Sierra de arco', 'uds', '', ['Estantería metálica 1' => 3]],
    ['Bolsas de basura grandes', 'rollos', '', []],
    ['Repelente de mosquitos', 'uds', '', []],
];

$porNombre = array_column($datos['inventario']['materiales'], null, 'nombre');
foreach ($materiales as [$nombre, $unidad, $notas, $existencias]) {
    if (isset($porNombre[$nombre])) {
        continue;
    }
    $respuesta = accion('guardar_material', [
        'nombre' => $nombre,
        'unidad' => $unidad,
        'notas' => $notas,
        'existencias' => array_map(
            fn($ubicacion, $cantidad) => ['ubicacion' => $ids[$ubicacion], 'cantidad' => $cantidad],
            array_keys($existencias),
            array_values($existencias)
        ),
    ]);
    $datos['inventario'] = $respuesta['inventario'];
    echo "Material: {$nombre}\n";
}
$porNombre = array_column($datos['inventario']['materiales'], null, 'nombre');

// ---------- Listas ----------
// El reparto es el mismo que hace la aplicación: primero las ubicaciones preferidas y
// después donde más hay. Lo que no se cubre queda para comprar.
function repartir(array $material, int $necesarios, array $preferidas): array
{
    $existencias = array_values(array_filter($material['existencias'], fn($existencia) => $existencia['cantidad'] > 0));
    $orden = fn($ubicacion) => ($posicion = array_search($ubicacion, $preferidas, true)) === false ? count($preferidas) : $posicion;
    usort($existencias, fn($a, $b) => [$orden($a['ubicacion']), -$a['cantidad']] <=> [$orden($b['ubicacion']), -$b['cantidad']]);
    $tomas = [];
    foreach ($existencias as $existencia) {
        $cantidad = min($necesarios, $existencia['cantidad']);
        if ($cantidad > 0) {
            $tomas[] = ['ubicacion' => $existencia['ubicacion'], 'cantidad' => $cantidad];
            $necesarios -= $cantidad;
        }
    }
    return $tomas;
}

// [nombre, es plantilla, [material => necesarios | [necesarios, ubicación preferida]], [compra => cantidad]]
$listas = [
    ['Acampada de invierno 2026 (Tropa)', false, [
        'Tienda canadiense 4 plazas' => 9,
        'Lona 4x6' => 3,
        'Piquetas' => 180,
        'Cuerda de 10 m' => 10,
        'Cuerda de pionerismo' => 8,
        'Linterna frontal' => 20,
        'Pilas AA' => 40,
        'Botiquín grande' => 1,
        'Hornillo de gas' => 2,
        'Bombona de gas' => 5,
        'Olla grande 30 l' => 2,
        'Platos de plástico' => 35,
        'Vasos de plástico' => 35,
        'Cubiertos (juego)' => 35,
        'Garrafa de agua 25 l' => 4,
        'Hacha de mano' => 2,
        'Sierra de arco' => 2,
        'Bolsas de basura grandes' => 3,
        'Brújula' => 6,
    ], ['Leña (sacos)' => 6, 'Papel de cocina' => 10]],
    ['Excursión de Manada al parque', false, [
        'Balón' => 3,
        'Botiquín de mochila' => 2,
        'Pinturas de cara' => 2,
        'Silbato' => 3,
        'Garrafa de agua 25 l' => 1,
    ], ['Bocadillos' => 28, 'Fruta (kg)' => 4]],
    ['Rutas: travesía de 3 días', false, [
        'Tienda iglú 2 plazas' => 6,
        'Linterna frontal' => 12,
        'Pilas AA' => 24,
        'Botiquín de mochila' => [3, 'Balda Posta'],
        'Brújula' => [6, 'Balda Posta'],
        'Mapa topográfico de la sierra' => 4,
        'Hornillo de gas' => [2, 'Cocina del local'],
        'Repelente de mosquitos' => 6,
    ], ['Barritas energéticas' => 36]],
    ['Actividad en el local (sábado)', false, [
        'Cartulinas' => 8,
        'Rotuladores' => 6,
        'Mesa plegable' => 6,
        'Silla plegable' => 12,
        'Megáfono' => 1,
    ], []],
    ['Campamento de verano (base)', true, [
        'Tienda canadiense 4 plazas' => 12,
        'Tienda iglú 2 plazas' => 6,
        'Lona 4x6' => 6,
        'Piquetas' => 300,
        'Hornillo de gas' => 3,
        'Bombona de gas' => 8,
        'Olla grande 30 l' => 3,
        'Sartén paellera' => 1,
        'Platos de plástico' => 90,
        'Vasos de plástico' => 90,
        'Cubiertos (juego)' => 90,
        'Mesa plegable' => 8,
        'Silla plegable' => 16,
        'Botiquín grande' => 2,
        'Repelente de mosquitos' => 10,
    ], ['Toldo grande (alquiler)' => 1]],
];

$existentes = array_column($datos['listas'], 'nombre');
foreach ($listas as [$nombre, $esPlantilla, $lineas, $compras]) {
    if (in_array($nombre, $existentes, true)) {
        continue;
    }
    $lineasLista = [];
    foreach ($lineas as $material => $valor) {
        [$necesarios, $preferida] = is_array($valor) ? $valor : [$valor, null];
        $preferidas = $preferida ? [$ids[$preferida]] : [];
        $lineasLista[] = [
            'material' => $porNombre[$material]['id'],
            'necesarios' => $necesarios,
            'tomas' => repartir($porNombre[$material], $necesarios, $preferidas),
            'preferidas' => $preferidas,
        ];
    }
    accion('guardar_lista', [
        'nombre' => $nombre,
        'es_plantilla' => $esPlantilla,
        'lineas' => $lineasLista,
        'compras' => array_map(fn($compra, $cantidad) => ['nombre' => $compra, 'cantidad' => $cantidad],
            array_keys($compras), array_values($compras)),
    ]);
    echo ($esPlantilla ? 'Plantilla: ' : 'Lista: ') . "{$nombre}\n";
}

unlink($cookies);
echo "Hecho.\n";
