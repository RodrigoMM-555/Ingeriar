<?php

// Facturas en tablero (E-05).
// Rutas (?accion=):
//   listar  GET   facturas visibles: las propias, o todas con facturas.ver_todas
//   imagen  GET   imagen de una factura visible (&id=, &descargar=1 opcional)
//   crear   POST  nueva factura con imagen obligatoria (facturas.subir_propias)
//   editar  POST  editar datos o cambiar la imagen
//   borrar  POST  borrar una factura y su imagen
//   estado  POST  cambiar el estado (solo facturas.cambiar_estado)
//
// Reglas: el monitor edita o borra sus facturas solo mientras están pendientes; una
// factura pagada no la edita ni la borra nadie (tesorería puede devolverla a revisada);
// nadie cambia un estado sin facturas.cambiar_estado, ni siquiera en las suyas.

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../auth/permisos.php';

const ESTADOS_FACTURA = ['pendiente', 'revisada', 'pagada'];
const FORMATOS_FACTURA = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

function responder($datos, int $estado = 200): never
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function directorioFacturas(): string
{
    $directorio = __DIR__ . '/../../storage/facturas';
    if (!is_dir($directorio) && !mkdir($directorio, 0770, true)) {
        responder(['error' => 'No se pudo preparar la carpeta de facturas'], 500);
    }
    return realpath($directorio);
}

function tamanoMaximoFactura(): int
{
    $ruta = __DIR__ . '/../data/config.php';
    $configuracion = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
    return max(1, (int) ($configuracion['tamano_maximo_archivos_mb'] ?? 10)) * 1024 * 1024;
}

function buscarFactura(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM facturas WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function esPropia(array $factura, array $usuario): bool
{
    return (int) $factura['responsable_id'] === (int) $usuario['id'];
}

function puedeVerFactura(array $factura, array $usuario): bool
{
    return tienePermiso('facturas.ver_todas') || esPropia($factura, $usuario);
}

// ¿Se pueden cambiar los datos o borrar la factura? Devuelve null si sí, o el motivo.
function motivoNoEditable(array $factura, array $usuario): ?string
{
    if ($factura['estado'] === 'pagada') {
        return 'Una factura pagada no se puede modificar';
    }
    if (tienePermiso('facturas.gestionar_todas')) {
        return null;
    }
    if (!esPropia($factura, $usuario) || !tienePermiso('facturas.subir_propias')) {
        return 'Solo puedes modificar tus propias facturas';
    }
    if ($factura['estado'] !== 'pendiente') {
        return 'Solo se pueden modificar las facturas pendientes';
    }
    return null;
}

function rutaImagenFactura(?string $archivo): ?string
{
    if (!$archivo) {
        return null;
    }
    $base = directorioFacturas();
    $ruta = realpath($base . DIRECTORY_SEPARATOR . basename($archivo));
    if ($ruta === false || !is_file($ruta) || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $ruta;
}

// Comprueba la imagen subida (tipo real, tamaño) y devuelve su extensión.
// Devuelve null si no se ha enviado ninguna.
function validarImagenFactura(): ?string
{
    $imagen = $_FILES['imagen'] ?? null;
    if (!$imagen || $imagen['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $maximo = tamanoMaximoFactura();
    if (in_array($imagen['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $imagen['size'] > $maximo) {
        responder(['error' => 'La imagen supera el tamaño máximo de ' . intdiv($maximo, 1024 * 1024) . ' MB'], 413);
    }
    if ($imagen['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($imagen['tmp_name'])) {
        responder(['error' => 'No se pudo recibir la imagen'], 400);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($imagen['tmp_name']);
    if (!isset(FORMATOS_FACTURA[$mime])) {
        responder(['error' => 'La imagen debe ser JPEG, PNG o WebP'], 415);
    }
    return FORMATOS_FACTURA[$mime];
}

// Guarda la imagen con un nombre aleatorio y devuelve ese nombre.
function guardarImagenFactura(int $facturaId, string $extension): string
{
    $nombre = 'factura-' . $facturaId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], directorioFacturas() . DIRECTORY_SEPARATOR . $nombre)) {
        responder(['error' => 'No se pudo guardar la imagen de la factura'], 500);
    }
    return $nombre;
}

function fechaValida(string $valor): ?string
{
    foreach (['Y-m-d', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'] as $formato) {
        $fecha = DateTimeImmutable::createFromFormat('!' . $formato, $valor);
        if ($fecha && $fecha->format($formato) === $valor) {
            return $fecha->format('Y-m-d H:i:s');
        }
    }
    return null;
}

// Lee y valida los datos del formulario. Devuelve las columnas a guardar.
function leerDatosFactura(PDO $db, array $usuario): array
{
    $numero = filter_var($_POST['numero_fact'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $cif = strtoupper(trim((string) ($_POST['CIF_prov'] ?? '')));
    $emision = fechaValida(trim((string) ($_POST['fecha_emision'] ?? '')));
    $compra = fechaValida(trim((string) ($_POST['fecha_compra'] ?? '')));
    $concepto = trim((string) ($_POST['concepto'] ?? ''));
    $precio = str_replace(',', '.', trim((string) ($_POST['precio'] ?? '')));
    $categoria = trim((string) ($_POST['categoria'] ?? ''));

    $errores = [];
    if ($numero === false) {
        $errores[] = 'el número de factura';
    }
    if ($cif === '' || strlen($cif) > 20) {
        $errores[] = 'el CIF del proveedor';
    }
    if ($emision === null) {
        $errores[] = 'la fecha de emisión';
    }
    if ($compra === null) {
        $errores[] = 'la fecha de compra';
    }
    if ($concepto === '' || strlen($concepto) > 1000) {
        $errores[] = 'el concepto';
    }
    if (preg_match('/^\d{1,8}(\.\d{1,2})?$/', $precio) !== 1) {
        $errores[] = 'el importe';
    }
    $categoriaStmt = $db->prepare('SELECT 1 FROM categorias_factura WHERE codigo = ? AND activo = 1');
    $categoriaStmt->execute([$categoria]);
    if (!$categoriaStmt->fetchColumn()) {
        $errores[] = 'la categoría';
    }
    if ($errores) {
        responder(['error' => 'Revisa ' . implode(', ', $errores) . '.'], 400);
    }

    $datos = [
        'numero_fact' => $numero,
        'CIF_prov' => $cif,
        'fecha_emision' => $emision,
        'fecha_compra' => $compra,
        'concepto' => $concepto,
        'precio' => $precio,
        'categoria' => $categoria,
    ];
    // Responsable: siempre quien la sube, salvo con facturas.gestionar_todas.
    if (tienePermiso('facturas.gestionar_todas') && !empty($_POST['responsable_id'])) {
        $responsable = (int) $_POST['responsable_id'];
        if (!usuarioTienePermiso($db, $responsable, 'facturas.subir_propias')) {
            responder(['error' => 'El responsable debe poder tener facturas (monitor)'], 400);
        }
        $datos['responsable_id'] = $responsable;
    }
    return $datos;
}

$db = obtenerConexion();
$usuario = exigirUsuario($db);
exigirPermiso('facturas.subir_propias', 'facturas.ver_todas');
$accion = $_GET['accion'] ?? '';
if (in_array($accion, ['crear', 'editar', 'borrar', 'estado'], true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido'], 405);
}

switch ($accion) {
    case 'listar':
        $sql = "SELECT f.id, f.numero_fact, f.CIF_prov, f.fecha_emision, f.fecha_compra,
                       f.concepto, f.precio, f.categoria, f.estado, f.responsable_id,
                       f.imagen_factura IS NOT NULL AS tiene_imagen, f.estado_cambiado_en,
                       CONCAT(u.nombre, ' ', u.apellidos) AS responsable_nombre,
                       CONCAT(c.nombre, ' ', c.apellidos) AS estado_cambiado_por_nombre
                FROM facturas f
                JOIN usuarios u ON u.id = f.responsable_id
                LEFT JOIN usuarios c ON c.id = f.estado_cambiado_por";
        $parametros = [];
        if (!tienePermiso('facturas.ver_todas')) {
            $sql .= ' WHERE f.responsable_id = ?';
            $parametros[] = $usuario['id'];
        }
        $sql .= ' ORDER BY f.fecha_compra DESC, f.id DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);
        $facturas = $stmt->fetchAll();
        foreach ($facturas as &$factura) {
            $factura['tiene_imagen'] = (bool) $factura['tiene_imagen'];
            $factura['propia'] = esPropia($factura, $usuario);
            $factura['editable'] = motivoNoEditable($factura, $usuario) === null;
        }
        unset($factura);

        $responsables = [];
        if (tienePermiso('facturas.gestionar_todas')) {
            $responsables = $db->query(
                "SELECT DISTINCT u.id, CONCAT(u.nombre, ' ', u.apellidos) AS nombre
                 FROM usuarios u
                 JOIN usuarios_roles ur ON ur.id_usuario = u.id
                 JOIN roles r ON r.codigo = ur.codigo_rol AND r.activo = 1
                 JOIN roles_permisos rp ON rp.codigo_rol = r.codigo
                 WHERE rp.codigo_permiso = 'facturas.subir_propias'
                 ORDER BY nombre"
            )->fetchAll();
        }
        responder([
            'permisos' => [
                'subir' => tienePermiso('facturas.subir_propias'),
                'ver_todas' => tienePermiso('facturas.ver_todas'),
                'cambiar_estado' => tienePermiso('facturas.cambiar_estado'),
                'gestionar_todas' => tienePermiso('facturas.gestionar_todas'),
            ],
            'facturas' => $facturas,
            'responsables' => $responsables,
        ]);

    case 'imagen':
        $factura = buscarFactura($db, (int) ($_GET['id'] ?? 0));
        if ($factura === null || !puedeVerFactura($factura, $usuario)) {
            responder(['error' => 'No tienes permisos para esta factura'], 403);
        }
        $ruta = rutaImagenFactura($factura['imagen_factura']);
        if ($ruta === null) {
            responder(['error' => 'Esta factura no tiene imagen'], 404);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
        if (!isset(FORMATOS_FACTURA[$mime])) {
            responder(['error' => 'La imagen guardada no es válida'], 415);
        }
        $disposicion = !empty($_GET['descargar']) ? 'attachment' : 'inline';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: ' . $disposicion . '; filename="factura-'
            . (int) $factura['id'] . '.' . FORMATOS_FACTURA[$mime] . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($ruta);
        exit;

    case 'crear':
        exigirPermiso('facturas.subir_propias', 'facturas.gestionar_todas');
        $datos = leerDatosFactura($db, $usuario);
        $extension = validarImagenFactura();
        if ($extension === null) {
            responder(['error' => 'La imagen de la factura es obligatoria'], 400);
        }
        $datos['responsable_id'] ??= $usuario['id'];
        $datos['estado'] = 'pendiente';

        $db->beginTransaction();
        $columnas = array_keys($datos);
        $db->prepare(
            'INSERT INTO facturas (`' . implode('`, `', $columnas) . '`) VALUES ('
            . implode(', ', array_fill(0, count($columnas), '?')) . ')'
        )->execute(array_values($datos));
        $id = (int) $db->lastInsertId();
        $nombre = guardarImagenFactura($id, $extension);
        $db->prepare('UPDATE facturas SET imagen_factura = ? WHERE id = ?')->execute([$nombre, $id]);
        $db->commit();
        responder(['ok' => true, 'id' => $id]);

    case 'editar':
        $factura = buscarFactura($db, (int) ($_POST['id'] ?? 0));
        if ($factura === null || !puedeVerFactura($factura, $usuario)) {
            responder(['error' => 'No tienes permisos para esta factura'], 403);
        }
        $motivo = motivoNoEditable($factura, $usuario);
        if ($motivo !== null) {
            responder(['error' => $motivo], $factura['estado'] === 'pagada' ? 409 : 403);
        }
        $datos = leerDatosFactura($db, $usuario);
        $extension = validarImagenFactura();

        $db->beginTransaction();
        $cambios = array_map(fn($columna) => "`{$columna}` = ?", array_keys($datos));
        $db->prepare('UPDATE facturas SET ' . implode(', ', $cambios) . ' WHERE id = ?')
            ->execute([...array_values($datos), $factura['id']]);
        $anterior = null;
        if ($extension !== null) {
            $nombre = guardarImagenFactura((int) $factura['id'], $extension);
            $db->prepare('UPDATE facturas SET imagen_factura = ? WHERE id = ?')->execute([$nombre, $factura['id']]);
            $anterior = rutaImagenFactura($factura['imagen_factura']);
        }
        $db->commit();
        if ($anterior !== null) {
            unlink($anterior);
        }
        responder(['ok' => true]);

    case 'borrar':
        $factura = buscarFactura($db, (int) ($_POST['id'] ?? 0));
        if ($factura === null || !puedeVerFactura($factura, $usuario)) {
            responder(['error' => 'No tienes permisos para esta factura'], 403);
        }
        $motivo = motivoNoEditable($factura, $usuario);
        if ($motivo !== null) {
            responder(['error' => $motivo], $factura['estado'] === 'pagada' ? 409 : 403);
        }
        $db->prepare('DELETE FROM facturas WHERE id = ?')->execute([$factura['id']]);
        $imagen = rutaImagenFactura($factura['imagen_factura']);
        if ($imagen !== null) {
            unlink($imagen);
        }
        responder(['ok' => true]);

    case 'estado':
        // Solo facturas.cambiar_estado, también sobre las propias.
        exigirPermiso('facturas.cambiar_estado');
        $factura = buscarFactura($db, (int) ($_POST['id'] ?? 0));
        $estado = (string) ($_POST['estado'] ?? '');
        if ($factura === null) {
            responder(['error' => 'No existe la factura'], 404);
        }
        if (!in_array($estado, ESTADOS_FACTURA, true)) {
            responder(['error' => 'Estado no válido'], 400);
        }
        $db->prepare(
            'UPDATE facturas SET estado = ?, estado_cambiado_por = ?, estado_cambiado_en = NOW()
             WHERE id = ?'
        )->execute([$estado, $usuario['id'], $factura['id']]);
        responder(['ok' => true]);

    default:
        responder(['error' => 'Acción no válida'], 400);
}
