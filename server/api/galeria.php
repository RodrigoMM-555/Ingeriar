<?php

// Galería de fotografías (E-03).
// Rutas (?accion=):
//   listar  GET   carpetas ronda → tipo de evento → evento → fotos visibles para el usuario
//   ver     GET   devuelve una foto tras comprobar permisos (&id=, &descargar=1 opcional)
//   subir   POST  administrador o scouter: evento + fotos[]
//   borrar  POST  administrador o scouter: id
// Las fotos se guardan en storage/galeria/ con nombre aleatorio y nunca se sirven
// directamente: todo pasa por este archivo.

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../data/secciones.php';
require_once __DIR__ . '/comun.php';
require_once __DIR__ . '/../auth/permisos.php';
require_once __DIR__ . '/../dominio/Educando.php';
require_once __DIR__ . '/../dominio/Evento.php';

const FORMATOS_GALERIA = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
const MAXIMO_FOTOS_POR_SUBIDA = 20;

function directorioGaleria(): string
{
    $directorio = __DIR__ . '/../../storage/galeria';
    if (!is_dir($directorio) && !mkdir($directorio, 0770, true)) {
        responder(['error' => 'No se pudo preparar la carpeta de la galería'], 500);
    }
    return realpath($directorio);
}

// Devuelve una función que indica si el usuario puede ver las fotos de un evento.
// Administrador y scouter ven todo. Un tutor solo ve los eventos que convocan alguna
// sección de sus educandos, calculada con la edad que tenían en la fecha del evento
// (así una foto de hace dos rondas sigue correspondiendo a la sección de entonces).
// Un evento sin secciones convoca a todas.
function filtroVisibilidad(PDO $db, array $usuario): callable
{
    // Quien ve todos los educandos (monitor, administrador) ve todas las fotos.
    if (tienePermiso('educandos.ver_todos')) {
        return static fn(array $evento): bool => true;
    }

    // Todos sus educandos, también los dados de baja: cuentan para los eventos
    // anteriores a su baja.
    $educandos = Educando::deTutor($db, $usuario['id'], false);
    $catalogo = $db->query(
        'SELECT codigo, edad_minima, edad_maxima FROM secciones
         WHERE activo = 1 ORDER BY orden, nombre'
    )->fetchAll();
    $convocatorias = Evento::convocatoriasDeTodos($db);

    return static function (array $evento) use ($educandos, $catalogo, $convocatorias): bool {
        $fecha = new DateTimeImmutable($evento['fecha_inicio']);
        $seccionesTutor = [];
        foreach ($educandos as $educando) {
            $seccion = $educando->estabaActivoEn($fecha) ? $educando->seccion($catalogo, $fecha) : null;
            if ($seccion !== null) {
                $seccionesTutor[] = $seccion;
            }
        }
        return $seccionesTutor
            && Evento::convocaA($convocatorias[(int) $evento['id']] ?? [], $seccionesTutor);
    };
}

// Ronda scout (de septiembre a agosto) a la que pertenece una fecha.
function rondaDeFecha(string $fecha): array
{
    $codigo = rondaActual(new DateTimeImmutable($fecha));
    [$inicio, $fin] = explode('-', $codigo);
    return [
        'codigo' => $codigo,
        'nombre' => 'Ronda 20' . $inicio . '-20' . $fin,
    ];
}

function buscarFoto(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT g.id, g.archivo, g.nombre_original, e.id AS id_evento, e.fecha_inicio
         FROM galeria_fotos g
         JOIN eventos e ON e.id = g.id_evento
         WHERE g.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// Ruta física de una foto, comprobando que no sale de storage/galeria/.
function rutaFoto(string $archivo): ?string
{
    $base = directorioGaleria();
    $ruta = realpath($base . DIRECTORY_SEPARATOR . basename($archivo));
    if ($ruta === false || !is_file($ruta)
        || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $ruta;
}

// Nombre original apto para mostrar y para la cabecera de descarga.
function limpiarNombreOriginal(string $nombre): string
{
    $nombre = basename(str_replace('\\', '/', $nombre));
    $nombre = preg_replace('/[\x00-\x1F\x7F"]/u', '', $nombre) ?? '';
    $nombre = trim($nombre);
    return $nombre === '' ? 'foto' : recortarTexto($nombre, 200);
}

// Recorta una cadena UTF-8 sin depender de la extensión mbstring.
function recortarTexto(string $texto, int $maximo): string
{
    preg_match_all('/./su', $texto, $caracteres);
    return implode('', array_slice($caracteres[0], 0, $maximo));
}

// Convierte $_FILES['fotos'] (subida múltiple) en una lista de archivos.
function archivosSubidos(): array
{
    $fotos = $_FILES['fotos'] ?? null;
    if (!$fotos || !is_array($fotos['name'] ?? null)) {
        return [];
    }
    $lista = [];
    foreach (array_keys($fotos['name']) as $indice) {
        if ($fotos['error'][$indice] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $lista[] = [
            'name' => (string) $fotos['name'][$indice],
            'tmp_name' => (string) $fotos['tmp_name'][$indice],
            'error' => (int) $fotos['error'][$indice],
            'size' => (int) $fotos['size'][$indice],
        ];
    }
    return $lista;
}

$db = obtenerConexion();
$usuario = exigirUsuario($db);
exigirPermiso('galeria.ver');
$accion = $_GET['accion'] ?? '';
$puedeEditar = tienePermiso('galeria.subir');

switch ($accion) {
    case 'listar':
        $fotos = $db->query(
            'SELECT g.id, g.nombre_original, g.id_evento,
                    e.nombre AS evento, e.fecha_inicio, e.tipo
             FROM galeria_fotos g
             JOIN eventos e ON e.id = g.id_evento
             ORDER BY e.fecha_inicio DESC, g.id'
        )->fetchAll();

        $tipos = [];
        foreach ($db->query('SELECT codigo, nombre, orden FROM tipos_evento') as $tipo) {
            $tipos[$tipo['codigo']] = $tipo;
        }

        $esVisible = filtroVisibilidad($db, $usuario);
        $visibilidadEvento = [];
        $rondas = [];
        foreach ($fotos as $foto) {
            $idEvento = (int) $foto['id_evento'];
            $visibilidadEvento[$idEvento] ??= $esVisible([
                'id' => $idEvento,
                'fecha_inicio' => $foto['fecha_inicio'],
            ]);
            if (!$visibilidadEvento[$idEvento]) {
                continue;
            }

            $ronda = rondaDeFecha($foto['fecha_inicio']);
            $codigoTipo = (string) $foto['tipo'];
            $rondas[$ronda['codigo']] ??= $ronda + ['tipos' => []];
            // Los tipos que no están en el catálogo (datos antiguos) tienen su propia carpeta.
            $rondas[$ronda['codigo']]['tipos'][$codigoTipo] ??= [
                'codigo' => $codigoTipo,
                'nombre' => $tipos[$codigoTipo]['nombre'] ?? ucfirst($codigoTipo),
                'orden' => (int) ($tipos[$codigoTipo]['orden'] ?? PHP_INT_MAX),
                'eventos' => [],
            ];
            $rondas[$ronda['codigo']]['tipos'][$codigoTipo]['eventos'][$idEvento] ??= [
                'id' => $idEvento,
                'nombre' => $foto['evento'],
                'fecha_inicio' => $foto['fecha_inicio'],
                'fotos' => [],
            ];
            $rondas[$ronda['codigo']]['tipos'][$codigoTipo]['eventos'][$idEvento]['fotos'][] = [
                'id' => (int) $foto['id'],
                'nombre' => $foto['nombre_original'],
            ];
        }

        // Rondas de la más reciente a la más antigua; carpetas en el orden del catálogo.
        krsort($rondas);
        $resultado = [];
        foreach ($rondas as $ronda) {
            $carpetas = array_values($ronda['tipos']);
            usort($carpetas, fn($a, $b) => [$a['orden'], $a['nombre']] <=> [$b['orden'], $b['nombre']]);
            foreach ($carpetas as &$carpeta) {
                $carpeta['eventos'] = array_values($carpeta['eventos']);
                unset($carpeta['orden']);
            }
            unset($carpeta);
            $ronda['tipos'] = $carpetas;
            $resultado[] = $ronda;
        }

        responder([
            'puede_editar' => $puedeEditar,
            'rondas' => $resultado,
        ]);

    case 'ver':
        $foto = buscarFoto($db, (int) ($_GET['id'] ?? 0));
        if ($foto === null) {
            responder(['error' => 'No existe la foto'], 404);
        }
        $esVisible = filtroVisibilidad($db, $usuario);
        if (!$esVisible(['id' => $foto['id_evento'], 'fecha_inicio' => $foto['fecha_inicio']])) {
            responder(['error' => 'No tienes permisos para ver esta foto'], 403);
        }
        $ruta = rutaFoto($foto['archivo']);
        if ($ruta === null) {
            responder(['error' => 'No se encuentra el archivo de la foto'], 404);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
        if (!isset(FORMATOS_GALERIA[$mime])) {
            responder(['error' => 'El archivo guardado no es una imagen válida'], 415);
        }
        $disposicion = !empty($_GET['descargar']) ? 'attachment' : 'inline';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: ' . $disposicion . '; filename="'
            . limpiarNombreOriginal($foto['nombre_original']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($ruta);
        exit;

    case 'subir':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['error' => 'Método no permitido'], 405);
        }
        exigirPermiso('galeria.subir');

        $eventoId = (int) ($_POST['evento'] ?? 0);
        $eventoStmt = $db->prepare('SELECT 1 FROM eventos WHERE id = ?');
        $eventoStmt->execute([$eventoId]);
        if (!$eventoStmt->fetchColumn()) {
            responder(['error' => 'Selecciona un evento válido'], 400);
        }

        $archivos = archivosSubidos();
        if (!$archivos) {
            responder(['error' => 'No se ha recibido ninguna foto'], 400);
        }
        if (count($archivos) > MAXIMO_FOTOS_POR_SUBIDA) {
            responder(['error' => 'Se pueden subir como máximo '
                . MAXIMO_FOTOS_POR_SUBIDA . ' fotos a la vez'], 400);
        }

        // Primero se validan todas; si una falla, no se guarda ninguna.
        $maximo = tamanoMaximoArchivos();
        $tipoReal = new finfo(FILEINFO_MIME_TYPE);
        foreach ($archivos as &$archivo) {
            $nombre = limpiarNombreOriginal($archivo['name']);
            if (in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                || $archivo['size'] > $maximo) {
                responder(['error' => "«{$nombre}» supera el tamaño máximo de "
                    . intdiv($maximo, 1024 * 1024) . ' MB'], 413);
            }
            if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
                responder(['error' => "No se pudo recibir «{$nombre}»"], 400);
            }
            $mime = $tipoReal->file($archivo['tmp_name']);
            if (!isset(FORMATOS_GALERIA[$mime])) {
                responder(['error' => "«{$nombre}» no es una imagen JPEG, PNG o WebP"], 415);
            }
            $archivo['nombre_limpio'] = $nombre;
            $archivo['extension'] = FORMATOS_GALERIA[$mime];
        }
        unset($archivo);

        $directorio = directorioGaleria();
        $insertar = $db->prepare(
            'INSERT INTO galeria_fotos (id_evento, archivo, nombre_original, id_usuario)
             VALUES (?, ?, ?, ?)'
        );
        $guardadas = 0;
        foreach ($archivos as $archivo) {
            // Nombre aleatorio: evita colisiones y que el nombre original llegue al disco.
            $nombreDisco = bin2hex(random_bytes(16)) . '.' . $archivo['extension'];
            $destino = $directorio . DIRECTORY_SEPARATOR . $nombreDisco;
            if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
                responder(['error' => 'No se pudo guardar «' . $archivo['nombre_limpio']
                    . '». Se han guardado ' . $guardadas . ' fotos.'], 500);
            }
            try {
                $insertar->execute([$eventoId, $nombreDisco, $archivo['nombre_limpio'], $usuario['id']]);
            } catch (Throwable $error) {
                unlink($destino);
                throw $error;
            }
            $guardadas++;
        }
        responder(['ok' => true, 'subidas' => $guardadas]);

    case 'borrar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['error' => 'Método no permitido'], 405);
        }
        exigirPermiso('galeria.subir');
        $foto = buscarFoto($db, (int) ($_POST['id'] ?? 0));
        if ($foto === null) {
            responder(['error' => 'No existe la foto'], 404);
        }
        $db->prepare('DELETE FROM galeria_fotos WHERE id = ?')->execute([$foto['id']]);
        $ruta = rutaFoto($foto['archivo']);
        if ($ruta !== null) {
            unlink($ruta);
        }
        responder(['ok' => true]);

    default:
        responder(['error' => 'Acción no válida'], 400);
}
