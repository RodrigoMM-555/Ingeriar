<?php

// Inventario de material y listas de material (E-04).
// Datos en JSON (storage/inventario/inventario.json y listas.json) a través de AlmacenJson.
//
// Rutas (?accion=), todas con inventario.gestionar:
//   datos              GET   ubicaciones, materiales, listas y plantillas
//   guardar_ubicacion  POST  {id?, nombre, padre}          crear, renombrar o mover
//   borrar_ubicacion   POST  {id}                         solo si está vacía
//   guardar_material   POST  {id?, nombre, unidad, notas, existencias:[{ubicacion, cantidad}]}
//   borrar_material    POST  {id}
//   mover_material     POST  {material, origen, destino, cantidad}   pasa unidades de una
//                            ubicación a otra (plano del inventario, E-15)
//   guardar_lista      POST  {id?, nombre, es_plantilla, origen, lineas, compras}
//                            líneas: {material, necesarios, tomas:[{ubicacion, cantidad}], preferidas:[ubicación]}
//   copiar_lista       POST  {id, nombre, es_plantilla}   plantilla ↔ lista, o versión nueva
//   borrar_lista       POST  {id}
//
// Una lista no descuenta del inventario: indica qué coger y de dónde y qué hay que comprar
// (necesarios − lo que se coge). Desde E-15 el cliente reparte solo (primero las
// ubicaciones preferidas, después donde más hay) y aquí se comprueba que no se pase.

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/comun.php';
require_once __DIR__ . '/../auth/permisos.php';
require_once __DIR__ . '/../dominio/AlmacenJson.php';

const INVENTARIO_VACIO = ['ubicaciones' => [], 'materiales' => []];
const LISTAS_VACIAS = ['listas' => []];
const CANTIDAD_MAXIMA = 1000000;

// Error de validación con su código HTTP: se lanza dentro del bloqueo y se responde fuera.
class ErrorPeticion extends RuntimeException
{
}

function fallar(string $mensaje, int $codigo = 400): never
{
    throw new ErrorPeticion($mensaje, $codigo);
}

function textoValido($valor, string $campo, int $maximo = 100, bool $obligatorio = true): string
{
    $texto = trim((string) $valor);
    preg_match_all('/./su', $texto, $caracteres);
    if (($obligatorio && $texto === '') || count($caracteres[0]) > $maximo) {
        fallar("Revisa {$campo} (obligatorio, máximo {$maximo} caracteres)");
    }
    return $texto;
}

function cantidadValida($valor, string $campo): int
{
    $cantidad = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => CANTIDAD_MAXIMA]]);
    if ($cantidad === false) {
        fallar("La cantidad de {$campo} debe ser un número entero entre 0 y " . CANTIDAD_MAXIMA);
    }
    return $cantidad;
}

// Índice id => elemento de una lista de elementos con «id».
function porId(array $elementos): array
{
    return array_column($elementos, null, 'id');
}

// ¿Es $posibleDescendiente igual a $id o está dentro de él? (para no crear ciclos al mover)
function estaDentroDe(array $ubicaciones, ?string $posibleDescendiente, string $id): bool
{
    $indice = porId($ubicaciones);
    $actual = $posibleDescendiente;
    while ($actual !== null) {
        if ($actual === $id) {
            return true;
        }
        $actual = $indice[$actual]['padre'] ?? null;
    }
    return false;
}

$db = obtenerConexion();
$usuario = exigirUsuario($db);
exigirPermiso('inventario.gestionar');
$accion = $_GET['accion'] ?? '';
$almacen = new AlmacenJson();
$datos = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : [];
if ($accion !== 'datos' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido'], 405);
}

try {
    switch ($accion) {
        case 'datos':
            responder([
                'inventario' => $almacen->leer('inventario', INVENTARIO_VACIO),
                'listas' => $almacen->leer('listas', LISTAS_VACIAS)['listas'],
            ]);

        case 'guardar_ubicacion':
            $resultado = $almacen->modificar('inventario', INVENTARIO_VACIO, function (array $inventario) use ($datos) {
                $id = $datos['id'] ?? null;
                $nombre = textoValido($datos['nombre'] ?? '', 'el nombre de la ubicación');
                $padre = ($datos['padre'] ?? '') === '' ? null : (string) $datos['padre'];
                $indice = porId($inventario['ubicaciones']);
                if ($padre !== null && !isset($indice[$padre])) {
                    fallar('La ubicación superior no existe');
                }
                if ($id === null) {
                    $inventario['ubicaciones'][] = ['id' => AlmacenJson::nuevoId('u'), 'nombre' => $nombre, 'padre' => $padre];
                    return $inventario;
                }
                if (!isset($indice[$id])) {
                    fallar('La ubicación no existe', 404);
                }
                if ($padre !== null && estaDentroDe($inventario['ubicaciones'], $padre, $id)) {
                    fallar('Una ubicación no puede ir dentro de sí misma');
                }
                foreach ($inventario['ubicaciones'] as &$ubicacion) {
                    if ($ubicacion['id'] === $id) {
                        $ubicacion['nombre'] = $nombre;
                        $ubicacion['padre'] = $padre;
                    }
                }
                unset($ubicacion);
                return $inventario;
            });
            responder(['ok' => true, 'inventario' => $resultado]);

        case 'borrar_ubicacion':
            $resultado = $almacen->modificar('inventario', INVENTARIO_VACIO, function (array $inventario) use ($datos) {
                $id = (string) ($datos['id'] ?? '');
                foreach ($inventario['ubicaciones'] as $ubicacion) {
                    if ($ubicacion['padre'] === $id) {
                        fallar('La ubicación tiene otras ubicaciones dentro: muévelas o bórralas antes', 409);
                    }
                }
                foreach ($inventario['materiales'] as $material) {
                    foreach ($material['existencias'] as $existencia) {
                        if ($existencia['ubicacion'] === $id && $existencia['cantidad'] > 0) {
                            fallar('La ubicación tiene material: muévelo antes de borrarla', 409);
                        }
                    }
                }
                $inventario['ubicaciones'] = array_values(array_filter(
                    $inventario['ubicaciones'],
                    fn($ubicacion) => $ubicacion['id'] !== $id
                ));
                return $inventario;
            });
            responder(['ok' => true, 'inventario' => $resultado]);

        case 'guardar_material':
            $resultado = $almacen->modificar('inventario', INVENTARIO_VACIO, function (array $inventario) use ($datos) {
                $id = $datos['id'] ?? null;
                $ubicaciones = porId($inventario['ubicaciones']);
                $existencias = [];
                foreach ((array) ($datos['existencias'] ?? []) as $existencia) {
                    $ubicacion = (string) ($existencia['ubicacion'] ?? '');
                    if (!isset($ubicaciones[$ubicacion])) {
                        fallar('Alguna ubicación del material no existe');
                    }
                    $cantidad = cantidadValida($existencia['cantidad'] ?? '', 'una ubicación');
                    // Si la misma ubicación aparece dos veces, se suman.
                    $existencias[$ubicacion] = ($existencias[$ubicacion] ?? 0) + $cantidad;
                }
                $material = [
                    'id' => $id ?? AlmacenJson::nuevoId('m'),
                    'nombre' => textoValido($datos['nombre'] ?? '', 'el nombre del material'),
                    'unidad' => textoValido($datos['unidad'] ?? 'uds', 'la unidad', 20, false) ?: 'uds',
                    'notas' => textoValido($datos['notas'] ?? '', 'las notas', 500, false),
                    'existencias' => array_map(
                        fn($ubicacion, $cantidad) => ['ubicacion' => $ubicacion, 'cantidad' => $cantidad],
                        array_keys($existencias),
                        array_values($existencias)
                    ),
                ];
                if ($id === null) {
                    $inventario['materiales'][] = $material;
                    return $inventario;
                }
                if (!isset(porId($inventario['materiales'])[$id])) {
                    fallar('El material no existe', 404);
                }
                foreach ($inventario['materiales'] as &$existente) {
                    if ($existente['id'] === $id) {
                        $existente = $material;
                    }
                }
                unset($existente);
                return $inventario;
            });
            responder(['ok' => true, 'inventario' => $resultado]);

        case 'borrar_material':
            $resultado = $almacen->modificar('inventario', INVENTARIO_VACIO, function (array $inventario) use ($datos) {
                $id = (string) ($datos['id'] ?? '');
                $inventario['materiales'] = array_values(array_filter(
                    $inventario['materiales'],
                    fn($material) => $material['id'] !== $id
                ));
                return $inventario;
            });
            responder(['ok' => true, 'inventario' => $resultado]);

        case 'mover_material':
            // Todo dentro del bloqueo: se comprueba lo que hay en el origen con los datos
            // actuales, así dos movimientos a la vez no pueden dejar cantidades negativas.
            $resultado = $almacen->modificar('inventario', INVENTARIO_VACIO, function (array $inventario) use ($datos) {
                $materialId = (string) ($datos['material'] ?? '');
                $origen = (string) ($datos['origen'] ?? '');
                $destino = (string) ($datos['destino'] ?? '');
                $cantidad = cantidadValida($datos['cantidad'] ?? '', 'lo que se mueve');
                $ubicaciones = porId($inventario['ubicaciones']);
                if (!isset($ubicaciones[$origen], $ubicaciones[$destino])) {
                    fallar('La ubicación de origen o de destino no existe', 404);
                }
                if ($origen === $destino) {
                    fallar('El origen y el destino son la misma ubicación');
                }
                if ($cantidad === 0) {
                    fallar('Indica cuántas unidades se mueven');
                }
                foreach ($inventario['materiales'] as &$material) {
                    if ($material['id'] !== $materialId) {
                        continue;
                    }
                    $hay = 0;
                    foreach ($material['existencias'] as $existencia) {
                        if ($existencia['ubicacion'] === $origen) {
                            $hay = $existencia['cantidad'];
                        }
                    }
                    if ($hay < $cantidad) {
                        fallar("En el origen solo hay {$hay}", 409);
                    }
                    $cantidades = array_column($material['existencias'], 'cantidad', 'ubicacion');
                    $cantidades[$origen] -= $cantidad;
                    $cantidades[$destino] = ($cantidades[$destino] ?? 0) + $cantidad;
                    // Si el origen se queda vacío, deja de aparecer en el material.
                    $material['existencias'] = [];
                    foreach ($cantidades as $ubicacion => $valor) {
                        if ($valor > 0) {
                            $material['existencias'][] = ['ubicacion' => (string) $ubicacion, 'cantidad' => $valor];
                        }
                    }
                    return $inventario;
                }
                fallar('El material no existe', 404);
            });
            responder(['ok' => true, 'inventario' => $resultado]);

        case 'guardar_lista':
            $inventario = $almacen->leer('inventario', INVENTARIO_VACIO);
            $materiales = porId($inventario['materiales']);
            $ubicaciones = porId($inventario['ubicaciones']);
            $lineas = [];
            foreach ((array) ($datos['lineas'] ?? []) as $linea) {
                $materialId = (string) ($linea['material'] ?? '');
                if (!isset($materiales[$materialId])) {
                    fallar('Algún material de la lista ya no existe en el inventario');
                }
                $material = $materiales[$materialId];
                $stock = array_column($material['existencias'], 'cantidad', 'ubicacion');
                $tomas = [];
                foreach ((array) ($linea['tomas'] ?? []) as $toma) {
                    $ubicacion = (string) ($toma['ubicacion'] ?? '');
                    $cantidad = cantidadValida($toma['cantidad'] ?? '', $material['nombre']);
                    if ($cantidad === 0) {
                        continue;
                    }
                    if (!isset($ubicaciones[$ubicacion])) {
                        fallar('Alguna ubicación de la lista ya no existe');
                    }
                    // No se puede coger de un sitio más de lo que hay allí.
                    if ($cantidad > ($stock[$ubicacion] ?? 0)) {
                        fallar("De «{$material['nombre']}» solo hay " . ($stock[$ubicacion] ?? 0)
                            . " en «{$ubicaciones[$ubicacion]['nombre']}»");
                    }
                    $tomas[] = ['ubicacion' => $ubicacion, 'cantidad' => $cantidad];
                }
                // Ubicaciones elegidas arrastrando (E-15): el reparto automático empieza por ellas.
                $preferidas = array_values(array_unique(array_filter(
                    array_map('strval', (array) ($linea['preferidas'] ?? [])),
                    fn($ubicacion) => isset($ubicaciones[$ubicacion])
                )));
                $lineas[] = [
                    'material' => $materialId,
                    'nombre' => $material['nombre'],
                    'necesarios' => cantidadValida($linea['necesarios'] ?? 0, $material['nombre']),
                    'tomas' => $tomas,
                    'preferidas' => $preferidas,
                ];
            }
            $compras = [];
            foreach ((array) ($datos['compras'] ?? []) as $compra) {
                $compras[] = [
                    'nombre' => textoValido($compra['nombre'] ?? '', 'el nombre de la compra'),
                    'cantidad' => cantidadValida($compra['cantidad'] ?? 0, 'una compra'),
                ];
            }
            $nombre = textoValido($datos['nombre'] ?? '', 'el nombre de la lista', 150);
            $id = $datos['id'] ?? null;
            $resultado = $almacen->modificar('listas', LISTAS_VACIAS, function (array $documento) use (
                $id, $nombre, $datos, $lineas, $compras, $usuario
            ) {
                $ahora = date('Y-m-d H:i:s');
                $lista = [
                    'id' => $id ?? AlmacenJson::nuevoId('l'),
                    'nombre' => $nombre,
                    'es_plantilla' => (bool) ($datos['es_plantilla'] ?? false),
                    'origen' => $datos['origen'] ?? null,
                    'lineas' => $lineas,
                    'compras' => $compras,
                    'actualizada' => $ahora,
                    'actualizada_por' => trim($usuario['nombre'] . ' ' . $usuario['apellidos']),
                ];
                if ($id === null) {
                    $lista['creada'] = $ahora;
                    $documento['listas'][] = $lista;
                    return $documento;
                }
                $encontrada = false;
                foreach ($documento['listas'] as &$existente) {
                    if ($existente['id'] === $id) {
                        $lista['creada'] = $existente['creada'] ?? $ahora;
                        $lista['es_plantilla'] = $existente['es_plantilla'];
                        $lista['origen'] = $existente['origen'] ?? null;
                        $existente = $lista;
                        $encontrada = true;
                    }
                }
                unset($existente);
                if (!$encontrada) {
                    fallar('La lista no existe', 404);
                }
                return $documento;
            });
            responder(['ok' => true, 'listas' => $resultado['listas'], 'id' => $id ?? end($resultado['listas'])['id']]);

        case 'copiar_lista':
            // Plantilla → lista nueva, lista → plantilla, o una versión nueva de una plantilla.
            $nombre = textoValido($datos['nombre'] ?? '', 'el nombre', 150);
            $nuevoId = AlmacenJson::nuevoId('l');
            $resultado = $almacen->modificar('listas', LISTAS_VACIAS, function (array $documento) use ($datos, $nombre, $nuevoId, $usuario) {
                $origen = porId($documento['listas'])[(string) ($datos['id'] ?? '')] ?? null;
                if ($origen === null) {
                    fallar('La lista no existe', 404);
                }
                $ahora = date('Y-m-d H:i:s');
                $documento['listas'][] = [
                    'id' => $nuevoId,
                    'nombre' => $nombre,
                    'es_plantilla' => (bool) ($datos['es_plantilla'] ?? false),
                    'origen' => $origen['id'],
                    'lineas' => $origen['lineas'],
                    'compras' => $origen['compras'],
                    'creada' => $ahora,
                    'actualizada' => $ahora,
                    'actualizada_por' => trim($usuario['nombre'] . ' ' . $usuario['apellidos']),
                ];
                return $documento;
            });
            responder(['ok' => true, 'listas' => $resultado['listas'], 'id' => $nuevoId]);

        case 'borrar_lista':
            $resultado = $almacen->modificar('listas', LISTAS_VACIAS, function (array $documento) use ($datos) {
                $id = (string) ($datos['id'] ?? '');
                $documento['listas'] = array_values(array_filter(
                    $documento['listas'],
                    fn($lista) => $lista['id'] !== $id
                ));
                return $documento;
            });
            responder(['ok' => true, 'listas' => $resultado['listas']]);

        default:
            responder(['error' => 'Acción no válida'], 400);
    }
} catch (ErrorPeticion $error) {
    responder(['error' => $error->getMessage()], $error->getCode());
}
