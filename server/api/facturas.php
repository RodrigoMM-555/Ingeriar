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
require_once __DIR__ . '/../data/almacenamiento.php';
require_once __DIR__ . '/comun.php';
require_once __DIR__ . '/../auth/permisos.php';
require_once __DIR__ . '/../dominio/Factura.php';
const FORMATOS_FACTURA = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

// Ruta física de la imagen, comprobando que no sale de storage/facturas/.
function rutaImagenFactura(?string $archivo): ?string
{
    return rutaEnAlmacen('facturas', $archivo);
}

// Comprueba la imagen subida (tipo real, tamaño) y devuelve su extensión.
// Devuelve null si no se ha enviado ninguna.
function validarImagenFactura(): ?string
{
    $imagen = $_FILES['imagen'] ?? null;
    if (!$imagen || $imagen['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $maximo = tamanoMaximoArchivos();
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

// Guarda la imagen con un nombre aleatorio en la carpeta de la ronda de la fecha de compra
// (E-17) y devuelve su ruta relativa.
function guardarImagenFactura(int $facturaId, string $extension, string $fechaCompra): string
{
    $nombre = 'factura-' . $facturaId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
    try {
        return guardarSubidoEn('facturas', carpetaFactura($fechaCompra), $nombre, $_FILES['imagen']['tmp_name']);
    } catch (RuntimeException) {
        responder(['error' => 'No se pudo guardar la imagen de la factura'], 500);
    }
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
        $facturas = Factura::listadoPara($db, $usuario);

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
        $factura = Factura::buscar($db, (int) ($_GET['id'] ?? 0));
        if ($factura === null || !$factura->puedeVerla($usuario)) {
            responder(['error' => 'No tienes permisos para esta factura'], 403);
        }
        $ruta = rutaImagenFactura($factura->imagen);
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
            . $factura->id . '.' . FORMATOS_FACTURA[$mime] . '"');
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
        $factura = (new Factura())->rellenar($datos);
        $factura->estado = 'pendiente';

        // La fila se crea primero para que el nombre de la imagen lleve su id.
        $db->beginTransaction();
        $factura->guardar($db);
        $factura->imagen = guardarImagenFactura($factura->id, $extension, $factura->fechaCompra);
        $factura->guardar($db);
        $db->commit();
        responder(['ok' => true, 'id' => $factura->id]);

    case 'editar':
        $factura = Factura::buscar($db, (int) ($_POST['id'] ?? 0));
        if ($factura === null || !$factura->puedeVerla($usuario)) {
            responder(['error' => 'No tienes permisos para esta factura'], 403);
        }
        $motivo = $factura->motivoNoEditable($usuario);
        if ($motivo !== null) {
            responder(['error' => $motivo], $factura->estado === 'pagada' ? 409 : 403);
        }
        $factura->rellenar(leerDatosFactura($db, $usuario));
        $extension = validarImagenFactura();

        $db->beginTransaction();
        $anterior = null;
        if ($extension !== null) {
            $anterior = $factura->imagen;
            $factura->imagen = guardarImagenFactura($factura->id, $extension, $factura->fechaCompra);
        }
        $factura->guardar($db);
        $db->commit();
        if ($anterior !== null) {
            borrarDeAlmacen('facturas', $anterior);
        }
        // Si ha cambiado la fecha de compra a otra ronda, la imagen se mueve a su carpeta.
        recolocarImagenFactura($db, $factura->id);
        responder(['ok' => true]);

    case 'borrar':
        $factura = Factura::buscar($db, (int) ($_POST['id'] ?? 0));
        if ($factura === null || !$factura->puedeVerla($usuario)) {
            responder(['error' => 'No tienes permisos para esta factura'], 403);
        }
        $motivo = $factura->motivoNoEditable($usuario);
        if ($motivo !== null) {
            responder(['error' => $motivo], $factura->estado === 'pagada' ? 409 : 403);
        }
        $imagen = $factura->imagen;
        $factura->borrar($db);
        borrarDeAlmacen('facturas', $imagen);
        responder(['ok' => true]);

    case 'estado':
        // Solo facturas.cambiar_estado, también sobre las propias.
        exigirPermiso('facturas.cambiar_estado');
        $factura = Factura::buscar($db, (int) ($_POST['id'] ?? 0));
        $estado = (string) ($_POST['estado'] ?? '');
        if ($factura === null) {
            responder(['error' => 'No existe la factura'], 404);
        }
        if (!in_array($estado, Factura::ESTADOS, true)) {
            responder(['error' => 'Estado no válido'], 400);
        }
        $factura->cambiarEstado($db, $estado, $usuario['id']);
        responder(['ok' => true]);

    default:
        responder(['error' => 'Acción no válida'], 400);
}
