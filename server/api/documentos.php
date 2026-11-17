<?php

// Documentación de inscripción (E-14).
// Rutas (?accion=):
//   plantillas         GET   documentos base activos (todos con &todos=1 y configuracion.gestionar)
//   plantilla          GET   descarga la plantilla en blanco (&codigo=)
//   educando           GET   estado de la documentación de un educando (&id=)
//   ver                GET   abre un documento entregado (&id=)
//   subir              POST  entrega un documento (id_educando, codigo, archivo)
//   revisar            POST  un monitor lo marca revisado o a corregir (id, estado, observaciones)
//   guardar_plantilla  POST  el administrador crea o cambia un documento base y su plantilla
//
// Los documentos entregados se guardan con las circulares, en la carpeta del educando en
// la ronda (storage/documentos/<ronda>/<sección>/<educando>/), y las plantillas en
// storage/documentos/biblioteca/. Nada se sirve directamente: todo pasa por aquí.

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../data/secciones.php';
require_once __DIR__ . '/../auth/permisos.php';
require_once __DIR__ . '/../auth/registro.php';

const FORMATOS_DOCUMENTO = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];

function responder($datos, int $estado = 200): never
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function baseDocumentos(): string
{
    return realpath(__DIR__ . '/../../storage/documentos');
}

function tamanoMaximoDocumento(): int
{
    $ruta = __DIR__ . '/../data/config.php';
    $configuracion = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
    return max(1, (int) ($configuracion['tamano_maximo_archivos_mb'] ?? 10)) * 1024 * 1024;
}

// Educando activo a cargo del tutor.
function esTutorDe(PDO $db, int $usuarioId, int $educandoId): bool
{
    $stmt = $db->prepare(
        'SELECT 1 FROM usuarios_educandos ue
         JOIN educandos e ON e.id = ue.id_educando
         WHERE ue.id_usuario = ? AND ue.id_educando = ? AND e.fecha_baja IS NULL'
    );
    $stmt->execute([$usuarioId, $educandoId]);
    return (bool) $stmt->fetchColumn();
}

// ¿Puede ver la documentación de este educando? Monitores y administración, o su tutor.
function puedeVerEducando(PDO $db, array $usuario, int $educandoId): bool
{
    return tieneAlgunPermiso(['documentos.ver_todos', 'documentos.revisar'])
        || (tienePermiso('documentos.subir_propios') && esTutorDe($db, $usuario['id'], $educandoId));
}

// Comprueba el archivo subido (tipo real y tamaño) y devuelve su extensión.
function validarArchivo(string $campo, array $formatos): string
{
    $archivo = $_FILES[$campo] ?? null;
    if (!$archivo || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        responder(['error' => 'No se ha recibido ningún archivo'], 400);
    }
    $maximo = tamanoMaximoDocumento();
    if (in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $archivo['size'] > $maximo) {
        responder(['error' => 'El archivo supera el tamaño máximo de ' . intdiv($maximo, 1024 * 1024) . ' MB'], 413);
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        responder(['error' => 'No se pudo recibir el archivo'], 400);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if (!isset($formatos[$mime])) {
        responder(['error' => 'Formato no admitido: usa PDF, JPEG, PNG o DOCX'], 415);
    }
    return $formatos[$mime];
}

// Ruta física de un archivo dentro de storage/documentos, o null si no existe o sale de ella.
function rutaSegura(?string $relativa): ?string
{
    if (!$relativa) {
        return null;
    }
    $base = baseDocumentos();
    $ruta = realpath($base . '/' . ltrim($relativa, '/'));
    if ($ruta === false || !is_file($ruta) || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $ruta;
}

function servirArchivo(string $ruta, string $nombre, bool $descargar): never
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($ruta));
    header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $nombre . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($ruta);
    exit;
}

function documentoBase(PDO $db, string $codigo): ?array
{
    $stmt = $db->prepare('SELECT * FROM documentos_base WHERE codigo = ?');
    $stmt->execute([$codigo]);
    return $stmt->fetch() ?: null;
}

$db = obtenerConexion();
$usuario = exigirUsuario($db);
$accion = $_GET['accion'] ?? '';
if (in_array($accion, ['subir', 'revisar', 'guardar_plantilla'], true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido'], 405);
}

switch ($accion) {
    case 'plantillas':
        $todos = !empty($_GET['todos']) && tienePermiso('configuracion.gestionar');
        $documentos = $db->query(
            'SELECT codigo, nombre, tipo, obligatorio, activo, orden,
                    archivo_plantilla IS NOT NULL AS tiene_plantilla
             FROM documentos_base' . ($todos ? '' : ' WHERE activo = 1') . '
             ORDER BY tipo = \'inscripcion\', orden, nombre'
        )->fetchAll();
        foreach ($documentos as &$documento) {
            foreach (['obligatorio', 'activo', 'tiene_plantilla'] as $campo) {
                $documento[$campo] = (bool) $documento[$campo];
            }
            $documento['orden'] = (int) $documento['orden'];
        }
        unset($documento);
        responder($documentos);

    case 'plantilla':
        // Las plantillas están en blanco: cualquier usuario con sesión puede descargarlas.
        $documento = documentoBase($db, (string) ($_GET['codigo'] ?? ''));
        $ruta = $documento ? rutaSegura('biblioteca/' . basename((string) $documento['archivo_plantilla'])) : null;
        if ($documento === null || !$documento['activo'] || $ruta === null) {
            responder(['error' => 'Este documento todavía no tiene plantilla'], 404);
        }
        $extension = pathinfo($ruta, PATHINFO_EXTENSION);
        servirArchivo($ruta, $documento['codigo'] . '.' . $extension, true);

    case 'educando':
        $educandoId = (int) ($_GET['id'] ?? 0);
        if (!puedeVerEducando($db, $usuario, $educandoId)) {
            responder(['error' => 'No tienes permisos para la documentación de este educando'], 403);
        }
        $stmt = $db->prepare(
            "SELECT b.codigo, b.nombre, b.obligatorio, b.archivo_plantilla IS NOT NULL AS tiene_plantilla,
                    d.id, d.fecha_subida, d.estado, d.observaciones, d.revisado_en,
                    CONCAT(s.nombre, ' ', s.apellidos) AS subido_por_nombre,
                    CONCAT(r.nombre, ' ', r.apellidos) AS revisado_por_nombre
             FROM documentos_base b
             LEFT JOIN documentos_educando d ON d.codigo_documento = b.codigo AND d.id_educando = ?
             LEFT JOIN usuarios s ON s.id = d.subido_por
             LEFT JOIN usuarios r ON r.id = d.revisado_por
             WHERE b.tipo = 'inscripcion' AND b.activo = 1
             ORDER BY b.orden, b.nombre"
        );
        $stmt->execute([$educandoId]);
        $documentos = [];
        foreach ($stmt->fetchAll() as $fila) {
            $documentos[] = [
                'codigo' => $fila['codigo'],
                'nombre' => $fila['nombre'],
                'obligatorio' => (bool) $fila['obligatorio'],
                'tiene_plantilla' => (bool) $fila['tiene_plantilla'],
                'entrega' => $fila['id'] === null ? null : [
                    'id' => (int) $fila['id'],
                    'fecha_subida' => $fila['fecha_subida'],
                    'subido_por' => $fila['subido_por_nombre'],
                    'estado' => $fila['estado'],
                    'observaciones' => $fila['observaciones'],
                    'revisado_por' => $fila['revisado_por_nombre'],
                    'revisado_en' => $fila['revisado_en'],
                ],
            ];
        }
        responder([
            'documentos' => $documentos,
            'puede_subir' => tienePermiso('documentos.revisar')
                || (tienePermiso('documentos.subir_propios') && esTutorDe($db, $usuario['id'], $educandoId)),
            'puede_revisar' => tienePermiso('documentos.revisar'),
        ]);

    case 'ver':
        $stmt = $db->prepare('SELECT * FROM documentos_educando WHERE id = ?');
        $stmt->execute([(int) ($_GET['id'] ?? 0)]);
        $entrega = $stmt->fetch();
        if (!$entrega || !puedeVerEducando($db, $usuario, (int) $entrega['id_educando'])) {
            responder(['error' => 'No tienes permisos para este documento'], 403);
        }
        $ruta = rutaSegura($entrega['archivo']);
        if ($ruta === null) {
            responder(['error' => 'No se encuentra el archivo'], 404);
        }
        // Documentación personal del educando: cada acceso queda registrado.
        registrarAcceso($db, $usuario, 'ver', 'documento_inscripcion', (int) $entrega['id_educando'], $entrega['codigo_documento']);
        servirArchivo($ruta, $entrega['codigo_documento'] . '.' . pathinfo($ruta, PATHINFO_EXTENSION), !empty($_GET['descargar']));

    case 'subir':
        $educandoId = (int) ($_POST['id_educando'] ?? 0);
        $codigo = (string) ($_POST['codigo'] ?? '');
        $puede = tienePermiso('documentos.revisar')
            || (tienePermiso('documentos.subir_propios') && esTutorDe($db, $usuario['id'], $educandoId));
        if (!$puede) {
            responder(['error' => 'Solo puedes entregar la documentación de tus educandos'], 403);
        }
        $documento = documentoBase($db, $codigo);
        if ($documento === null || $documento['tipo'] !== 'inscripcion' || !$documento['activo']) {
            responder(['error' => 'Documento no válido'], 400);
        }
        $educandoStmt = $db->prepare('SELECT nombre, apellidos, naz FROM educandos WHERE id = ? AND fecha_baja IS NULL');
        $educandoStmt->execute([$educandoId]);
        $educando = $educandoStmt->fetch();
        if (!$educando) {
            responder(['error' => 'El educando no existe o está dado de baja'], 400);
        }
        $extension = validarArchivo('archivo', FORMATOS_DOCUMENTO);

        // Misma carpeta que las circulares: ronda / sección / educando.
        $secciones = $db->query(
            'SELECT codigo, edad_minima, edad_maxima FROM secciones WHERE activo = 1 ORDER BY orden, nombre'
        )->fetchAll();
        $seccion = seccionDeEducandoConCatalogo($educando['naz'], $secciones) ?? 'sin-seccion';
        $relativa = implode('/', [
            rondaActual(),
            $seccion,
            nombreCarpetaEducando($educandoId, $educando['nombre'], $educando['apellidos']),
            'inscripcion-' . $codigo . '-' . bin2hex(random_bytes(12)) . '.' . $extension,
        ]);
        $destino = baseDocumentos() . '/' . $relativa;
        if (!is_dir(dirname($destino)) && !mkdir(dirname($destino), 0770, true)) {
            responder(['error' => 'No se pudo preparar la carpeta del educando'], 500);
        }
        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
            responder(['error' => 'No se pudo guardar el archivo'], 500);
        }

        // Volver a entregar sustituye al anterior y queda pendiente de revisión.
        $anteriorStmt = $db->prepare('SELECT archivo FROM documentos_educando WHERE id_educando = ? AND codigo_documento = ?');
        $anteriorStmt->execute([$educandoId, $codigo]);
        $anterior = $anteriorStmt->fetchColumn();
        $db->prepare(
            "INSERT INTO documentos_educando (id_educando, codigo_documento, archivo, subido_por, estado)
             VALUES (?, ?, ?, ?, 'pendiente')
             ON DUPLICATE KEY UPDATE archivo = VALUES(archivo), subido_por = VALUES(subido_por),
                 fecha_subida = NOW(), estado = 'pendiente', observaciones = NULL,
                 revisado_por = NULL, revisado_en = NULL"
        )->execute([$educandoId, $codigo, $relativa, $usuario['id']]);
        $rutaAnterior = $anterior ? rutaSegura($anterior) : null;
        if ($rutaAnterior !== null) {
            unlink($rutaAnterior);
        }
        registrarAcceso($db, $usuario, 'subir', 'documento_inscripcion', $educandoId, $codigo);
        responder(['ok' => true]);

    case 'revisar':
        exigirPermiso('documentos.revisar');
        $estado = (string) ($_POST['estado'] ?? '');
        $observaciones = trim((string) ($_POST['observaciones'] ?? ''));
        if (!in_array($estado, ['revisado', 'corregir'], true)) {
            responder(['error' => 'Estado no válido'], 400);
        }
        if ($estado === 'corregir' && $observaciones === '') {
            responder(['error' => 'Indica qué hay que corregir'], 400);
        }
        $stmt = $db->prepare(
            'UPDATE documentos_educando
             SET estado = ?, observaciones = ?, revisado_por = ?, revisado_en = NOW()
             WHERE id = ?'
        );
        // Máximo 255 caracteres (sin depender de mbstring).
        preg_match('/^.{0,255}/su', $observaciones, $recorte);
        $stmt->execute([$estado, $recorte[0] !== '' ? $recorte[0] : null, $usuario['id'], (int) ($_POST['id'] ?? 0)]);
        if ($stmt->rowCount() === 0) {
            responder(['error' => 'No existe el documento'], 404);
        }
        responder(['ok' => true]);

    case 'guardar_plantilla':
        exigirPermiso('configuracion.gestionar');
        $codigo = trim((string) ($_POST['codigo'] ?? ''));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if (preg_match('/^[a-z][a-z0-9_]{1,49}$/', $codigo) !== 1 || $nombre === '') {
            responder(['error' => 'El documento necesita un código (minúsculas, números y _) y un nombre'], 400);
        }
        $actual = documentoBase($db, $codigo);
        $obligatorio = filter_var($_POST['obligatorio'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $activo = filter_var($_POST['activo'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $orden = max(0, min(65535, (int) ($_POST['orden'] ?? 100)));
        if ($actual && $actual['tipo'] === 'participacion' && !$activo) {
            responder(['error' => 'La circular de participación no se puede desactivar'], 400);
        }
        $nuevoArchivo = null;
        if (isset($_FILES['plantilla']) && $_FILES['plantilla']['error'] !== UPLOAD_ERR_NO_FILE) {
            $extension = validarArchivo('plantilla', FORMATOS_DOCUMENTO);
            $nuevoArchivo = $codigo . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
            if (!move_uploaded_file($_FILES['plantilla']['tmp_name'], baseDocumentos() . '/biblioteca/' . $nuevoArchivo)) {
                responder(['error' => 'No se pudo guardar la plantilla'], 500);
            }
        }
        if ($actual === null) {
            $db->prepare(
                "INSERT INTO documentos_base (codigo, nombre, tipo, archivo_plantilla, obligatorio, activo, orden)
                 VALUES (?, ?, 'inscripcion', ?, ?, ?, ?)"
            )->execute([$codigo, $nombre, $nuevoArchivo, $obligatorio, $activo, $orden]);
        } else {
            $db->prepare(
                'UPDATE documentos_base SET nombre = ?, obligatorio = ?, activo = ?, orden = ?,
                     archivo_plantilla = COALESCE(?, archivo_plantilla)
                 WHERE codigo = ?'
            )->execute([$nombre, $obligatorio, $activo, $orden, $nuevoArchivo, $codigo]);
            // La plantilla anterior se borra, salvo la circular de ejemplo del repositorio.
            $anterior = $nuevoArchivo ? rutaSegura('biblioteca/' . basename((string) $actual['archivo_plantilla'])) : null;
            if ($anterior !== null && basename($anterior) !== 'circular1.pdf') {
                unlink($anterior);
            }
        }
        responder(['ok' => true]);

    default:
        responder(['error' => 'Acción no válida'], 400);
}
