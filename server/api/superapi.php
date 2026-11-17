<?php

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../data/secciones.php';
require_once __DIR__ . '/../auth/permisos.php';
require_once __DIR__ . '/../auth/contrasenas.php';
require_once __DIR__ . '/../auth/registro.php';

$db = obtenerConexion();
$ruta = $_GET['ruta'] ?? '';

function tablaExiste(PDO $db, string $tabla): bool
{
    $stmt = $db->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$tabla]);
    return (bool) $stmt->fetchColumn();
}
// Usuario con sus cargos y permisos recargados de la base de datos (E-13).
$usuario = exigirUsuario($db);

const CIRCULAR_FIJA = 'biblioteca/circular1.pdf';

function responder($datos, int $estado = 200): void
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function identificadorValido(string $identificador): bool
{
    return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identificador) === 1;
}

function comprobarTabla(PDO $db, string $tabla): void
{
    if (!identificadorValido($tabla)) {
        responder(['error' => 'Nombre de tabla no válido']);
    }

    function guardarImagenFactura(PDO $db, int $facturaId): void
    {
        $imagen = $_FILES['imagen_factura'] ?? null;
        if (!$imagen || $imagen['error'] === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if ($imagen['error'] !== UPLOAD_ERR_OK || $imagen['size'] > 10 * 1024 * 1024) {
            responder(['error' => 'La imagen de la factura no es válida o supera los 10 MB'], 400);
        }

        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($imagen['tmp_name']);
        if (!isset($permitidos[$mime])) {
            responder(['error' => 'Solo se aceptan imágenes JPEG, PNG o WebP'], 415);
        }

        $directorio = __DIR__ . '/../../storage/facturas';
        if (!is_dir($directorio) && !mkdir($directorio, 0750, true)) {
            responder(['error' => 'No se pudo preparar la carpeta de facturas'], 500);
        }
        $nombre = 'factura-' . $facturaId . '-' . bin2hex(random_bytes(12))
            . '.' . $permitidos[$mime];
        if (!move_uploaded_file($imagen['tmp_name'], $directorio . '/' . $nombre)) {
            responder(['error' => 'No se pudo guardar la imagen de la factura'], 500);
        }

        $db->prepare(
            'UPDATE facturas SET imagen_factura = ? WHERE id = ?'
        )->execute([$nombre, $facturaId]);
    }

    $stmt = $db->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$tabla]);
    if (!$stmt->fetchColumn()) {
        responder(['error' => 'La tabla no existe']);
    }
}

function catalogos(PDO $db, bool $soloActivos = false): array
{
    $filtro = $soloActivos ? ' WHERE activo = 1' : '';
    $tipos = $db->query(
        'SELECT codigo, nombre, activo, orden FROM tipos_evento' .
        $filtro . ' ORDER BY orden, nombre'
    )->fetchAll();
    $secciones = $db->query(
        'SELECT codigo, nombre, edad_minima, edad_maxima, activo, orden
         FROM secciones' . $filtro . ' ORDER BY orden, nombre'
    )->fetchAll();
    $categoriasFactura = $db->query(
        'SELECT codigo, nombre, activo, orden FROM categorias_factura' .
        $filtro . ' ORDER BY orden, nombre'
    )->fetchAll();
    return [
        'tipos_evento' => $tipos,
        'secciones' => $secciones,
        'categorias_factura' => $categoriasFactura
    ];
}

function seccionEducando(PDO $db, ?string $nacimiento): ?string
{
    if (!tablaExiste($db, 'secciones')) {
        return seccionDeEducando($nacimiento);
    }
    try {
        $secciones = $db->query(
            'SELECT codigo, edad_minima, edad_maxima FROM secciones
             WHERE activo = 1 ORDER BY orden, nombre'
        )->fetchAll();
    } catch (PDOException) {
        return seccionDeEducando($nacimiento);
    }
    return seccionDeEducandoConCatalogo($nacimiento, $secciones);
}

function obtenerColumnas(PDO $db, string $tabla): array
{
    comprobarTabla($db, $tabla);
    $stmt = $db->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, COLUMN_KEY, IS_NULLABLE,
                COLUMN_DEFAULT, ORDINAL_POSITION
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute([$tabla]);
    $columnas = [];

    foreach ($stmt->fetchAll() as $fila) {
        $opciones = [];
        if (str_starts_with(strtolower($fila['COLUMN_TYPE']), 'enum(')) {
            preg_match_all("/'((?:''|[^'])*)'/", $fila['COLUMN_TYPE'], $valores);
            $opciones = array_map(
                fn(string $valor): string => str_replace("''", "'", $valor),
                $valores[1]
            );
        }
        if ($tabla === 'eventos' && $fila['COLUMN_NAME'] === 'tipo'
            && tablaExiste($db, 'tipos_evento')) {
            $opciones = $db->query(
                'SELECT codigo FROM tipos_evento WHERE activo = 1
                 ORDER BY orden, nombre'
            )->fetchAll(PDO::FETCH_COLUMN);
        }
        if ($tabla === 'facturas' && $fila['COLUMN_NAME'] === 'categoria'
            && tablaExiste($db, 'categorias_factura')) {
            $opciones = $db->query(
                'SELECT codigo FROM categorias_factura WHERE activo = 1
                 ORDER BY orden, nombre'
            )->fetchAll(PDO::FETCH_COLUMN);
        }
        if ($tabla === 'facturas' && $fila['COLUMN_NAME'] === 'estado') {
            $opciones = ['pendiente', 'pagada', 'rechazada'];
        }

        $relacion = null;
        $relacionStmt = $db->prepare(
            'SELECT REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL'
        );
        $relacionStmt->execute([$tabla, $fila['COLUMN_NAME']]);
        $referencia = $relacionStmt->fetch();
        if ($referencia) {
            $relacion = [
                'tabla' => $referencia['REFERENCED_TABLE_NAME'],
                'campo_valor' => $referencia['REFERENCED_COLUMN_NAME'],
            ];
        }
        if (!$relacion) {
            $relacionesConvencionales = [
                'facturas.responsable_id' => ['tabla' => 'usuarios', 'campo_valor' => 'id'],
                'eventos.id_responsable' => ['tabla' => 'usuarios', 'campo_valor' => 'id'],
                'usuarios_educandos.id_usuario' => ['tabla' => 'usuarios', 'campo_valor' => 'id'],
                'usuarios_educandos.id_educando' => ['tabla' => 'educandos', 'campo_valor' => 'id'],
            ];
            $relacion = $relacionesConvencionales[$tabla . '.' . $fila['COLUMN_NAME']]
                ?? null;
        }

        // La contraseña nunca se envía al cliente: el formulario la trata como
        // un campo de contraseña vacío y la tabla no la muestra.
        $esContrasena = $tabla === 'usuarios' && $fila['COLUMN_NAME'] === 'contraseña';

        $columnas[] = [
            'name' => $fila['COLUMN_NAME'],
            'type' => strtoupper($fila['DATA_TYPE']),
            'pk' => $fila['COLUMN_KEY'] === 'PRI' ? 1 : 0,
            'tipo_formulario' => $esContrasena
                ? 'PASSWORD'
                : ($opciones
                ? 'ENUM'
                : (strtolower($fila['COLUMN_TYPE']) === 'tinyint(1)'
                    ? 'BOOLEAN'
                    : strtoupper($fila['DATA_TYPE']))),
            'required' => $fila['IS_NULLABLE'] === 'NO',
            'defaultValue' => $esContrasena ? null : $fila['COLUMN_DEFAULT'],
            'options' => $opciones,
            'relation' => $relacion,
        ];
    }
    return $columnas;
}

function tutorTieneEducando(PDO $db, int $usuarioId, int $educandoId): bool
{
    // Un educando dado de baja deja de estar a cargo del tutor en la aplicación.
    $stmt = $db->prepare(
        'SELECT 1 FROM usuarios_educandos ue
         JOIN educandos e ON e.id = ue.id_educando
         WHERE ue.id_usuario = ? AND ue.id_educando = ? AND e.fecha_baja IS NULL'
    );
    $stmt->execute([$usuarioId, $educandoId]);
    return (bool) $stmt->fetchColumn();
}

// Permisos necesarios para cada tabla de la API genérica (E-13). Basta con uno de la lista.
// «menu»: permisos para que la tabla aparezca en el menú (si no se indica, los de lectura).
// «borrado»: permisos para borrar (si no se indica, los de escritura).
// Las tablas que no están aquí solo las maneja quien tiene configuracion.gestionar.
const PERMISOS_TABLAS = [
    'usuarios' => [
        'lectura' => ['usuarios.ver', 'usuarios.gestionar'],
        'escritura' => ['usuarios.gestionar'],
        'menu' => ['usuarios.gestionar'],
    ],
    'usuarios_educandos' => [
        'lectura' => ['usuarios.gestionar'],
        'escritura' => ['usuarios.gestionar'],
    ],
    'educandos' => [
        'lectura' => ['educandos.ver_todos', 'educandos.ver_propios'],
        'escritura' => ['educandos.gestionar'],
        'borrado' => ['educandos.borrar'],
    ],
    'eventos' => [
        'lectura' => ['eventos.ver'],
        'escritura' => ['eventos.gestionar'],
        'menu' => ['eventos.gestionar'],
    ],
    'educandos_asistencias' => [
        'lectura' => ['asistencia.ver_todas'],
        'escritura' => ['asistencia.gestionar'],
    ],
    'facturas' => [
        'lectura' => ['facturas.ver_todas', 'facturas.subir_propias'],
        'escritura' => ['facturas.subir_propias', 'facturas.gestionar_todas'],
    ],
    // El registro de accesos solo lo escribe el servidor: nadie lo edita ni lo borra.
    'registro_accesos' => [
        'lectura' => ['registro.ver'],
        'escritura' => [],
    ],
];

function permisosTabla(string $tabla, string $operacion): array
{
    $definicion = PERMISOS_TABLAS[$tabla] ?? null;
    if ($definicion === null) {
        return ['configuracion.gestionar'];
    }
    return $definicion[$operacion]
        ?? ($operacion === 'borrado' ? $definicion['escritura'] : $definicion['lectura']);
}

function exigirPermisoTabla(array $usuario, string $tabla, string $operacion): void
{
    if (!tieneAlgunPermiso(permisosTabla($tabla, $operacion))) {
        responder(['error' => $tabla === 'registro_accesos' && $operacion !== 'lectura'
            ? 'El registro de accesos no se puede modificar'
            : 'No tienes permisos para esta operación'], 403);
    }
}

// Sin facturas.gestionar_todas solo se pueden editar o borrar las facturas propias.
function exigirFacturaPropia(PDO $db, array $usuario, $facturaId): void
{
    if (tienePermiso('facturas.gestionar_todas')) {
        return;
    }
    $stmt = $db->prepare('SELECT responsable_id FROM facturas WHERE id = ?');
    $stmt->execute([$facturaId]);
    if ((int) $stmt->fetchColumn() !== (int) $usuario['id']) {
        responder(['error' => 'Solo puedes modificar tus propias facturas'], 403);
    }
}

// Cargos de los usuarios indicados: [id => ['codigos' => [...], 'nombres' => '...']].
function rolesDeUsuarios(PDO $db): array
{
    $roles = [];
    foreach ($db->query(
        'SELECT ur.id_usuario, r.codigo, r.nombre, r.basico
         FROM usuarios_roles ur JOIN roles r ON r.codigo = ur.codigo_rol
         ORDER BY r.basico DESC, r.orden'
    ) as $fila) {
        $id = (int) $fila['id_usuario'];
        $roles[$id]['codigos'][] = $fila['codigo'];
        $roles[$id]['nombres'][] = $fila['nombre'];
        if ($fila['basico']) {
            $roles[$id]['basico'] = $fila['codigo'];
        }
    }
    return $roles;
}

switch ($ruta) {
    case 'usuario_actual':
        responder(['usuario' => $usuario]);

    case 'dashboard':
        $consultar = function (string $sql, array $parametros = []) use ($db): array {
            $stmt = $db->prepare($sql);
            $stmt->execute($parametros);
            return $stmt->fetch() ?: [];
        };
        $consultarTodos = function (string $sql, array $parametros = []) use ($db): array {
            $stmt = $db->prepare($sql);
            $stmt->execute($parametros);
            return $stmt->fetchAll();
        };

        $datos = [
            'rol' => $usuario['rol_basico'],
            'indicadores' => [],
            'graficas' => [],
        ];
        $eventosDashboard = $consultarTodos(
            "SELECT id, nombre, fecha_inicio, fecha_fin, tipo, lugar
             FROM eventos
             WHERE fecha_fin >= NOW()
             ORDER BY fecha_inicio"
        );
        $convocatoriasDashboard = [];
        if (tablaExiste($db, 'eventos_secciones')) {
            foreach ($consultarTodos(
                'SELECT id_evento, seccion FROM eventos_secciones'
            ) as $convocatoria) {
                $convocatoriasDashboard[$convocatoria['id_evento']][] =
                    $convocatoria['seccion'];
            }
        }
        // Quien no ve todos los educandos (el tutor) solo ve lo que afecta a los suyos.
        $soloPropios = !tienePermiso('educandos.ver_todos');
        $seccionesUsuario = [];
        if ($soloPropios) {
            $educandosUsuario = $consultarTodos(
                'SELECT naz FROM educandos e
                 JOIN usuarios_educandos ue ON ue.id_educando = e.id
                 WHERE ue.id_usuario = ? AND e.fecha_baja IS NULL',
                [$usuario['id']]
            );
            foreach ($educandosUsuario as $educando) {
                $seccion = seccionEducando($db, $educando['naz'] ?? null);
                if ($seccion) {
                    $seccionesUsuario[] = $seccion;
                }
            }
        }
        $eventoConvoca = function (array $evento) use (
            $soloPropios, $seccionesUsuario, $convocatoriasDashboard
        ): bool {
            if (!$soloPropios) {
                return true;
            }
            $convocatorias = $convocatoriasDashboard[$evento['id']] ?? [];
            return !$convocatorias
                || array_intersect($convocatorias, $seccionesUsuario);
        };
        $eventosVisibles = array_values(array_filter(
            $eventosDashboard,
            $eventoConvoca
        ));
        $proximoEvento = $eventosVisibles[0] ?? null;
        $inicioMes = new DateTimeImmutable('first day of this month 00:00:00');
        $finMes = $inicioMes->modify('last day of this month 23:59:59');
        $sabadosMes = array_values(array_filter(
            $eventosVisibles,
            fn($evento): bool => $evento['tipo'] === 'sabado'
                && new DateTimeImmutable($evento['fecha_inicio']) >= $inicioMes
                && new DateTimeImmutable($evento['fecha_inicio']) <= $finMes
        ));
        $sabadosProximos = array_values(array_filter(
            $eventosVisibles,
            fn($evento): bool => $evento['tipo'] === 'sabado'
        ));
        $acampadas = array_values(array_filter(
            $eventosVisibles,
            fn($evento): bool => in_array(
                strtolower($evento['tipo']),
                ['campamento', 'acampada'],
                true
            )
        ));
        $cumples = [];
        $hoy = new DateTimeImmutable('today');
        $finSemana = $hoy->modify('+7 days');
        foreach ($consultarTodos(
            'SELECT nombre, apellidos, naz FROM educandos
             WHERE naz IS NOT NULL AND fecha_baja IS NULL'
        ) as $educando) {
            $nacimiento = new DateTimeImmutable($educando['naz']);
            $cumple = new DateTimeImmutable(
                $hoy->format('Y') . '-' . $nacimiento->format('m-d')
            );
            if ($cumple < $hoy) {
                $cumple = $cumple->modify('+1 year');
            }
            if ($cumple <= $finSemana) {
                $cumples[] = [
                    'nombre' => $educando['nombre'] . ' ' . $educando['apellidos'],
                    'fecha' => $cumple->format('Y-m-d'),
                ];
            }
        }
        $datos['resumen'] = [
            'proximo_evento' => $proximoEvento,
            'sabados_mes' => $sabadosMes,
            'proxima_acampada' => $acampadas[0] ?? null,
            'cumpleanos' => $cumples,
        ];
        $datos['mini_calendario'] = array_map(
            fn($evento): array => [
                'fecha' => substr($evento['fecha_inicio'], 0, 10),
                'nombre' => $evento['nombre'],
            ],
            $eventosVisibles
        );
        $eventosPendientes = (int) ($consultar(
            'SELECT COUNT(*) AS cantidad FROM eventos WHERE fecha_fin >= NOW()'
        )['cantidad'] ?? 0);
        $eventosProximos = $consultarTodos(
            "SELECT tipo, COUNT(*) AS cantidad
             FROM eventos
             WHERE fecha_fin >= NOW()
             GROUP BY tipo
             ORDER BY cantidad DESC, tipo"
        );
        $nombresTiposEvento = [];
        if (tablaExiste($db, 'tipos_evento')) {
            $nombresTiposEvento = $db->query(
                'SELECT codigo, nombre FROM tipos_evento'
            )->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        $datos['graficas'][] = [
            'tipo' => 'anillo',
            'titulo' => 'Próximos eventos por tipo',
            'etiquetas' => array_map(
                fn($tipo): string => $nombresTiposEvento[$tipo] ?? $tipo,
                array_column($eventosProximos, 'tipo')
            ),
            'valores' => array_map('intval', array_column($eventosProximos, 'cantidad')),
        ];

        // Bloques del dashboard según los permisos (E-13). Con varios cargos se suman los
        // bloques de cada uno; los indicadores comunes aparecen una sola vez.
        $indicadores = [];
        if (tienePermiso('usuarios.gestionar')) {
            $usuariosPorCargo = $consultarTodos(
                'SELECT r.nombre AS cargo, COUNT(*) AS cantidad
                 FROM usuarios_roles ur JOIN roles r ON r.codigo = ur.codigo_rol
                 GROUP BY r.codigo, r.nombre, r.orden ORDER BY r.orden'
            );
            $indicadores[] = ['nombre' => 'Usuarios', 'valor' => $consultar('SELECT COUNT(*) AS cantidad FROM usuarios')['cantidad'] ?? 0, 'detalle' => 'Cuentas registradas'];
            $datos['graficas'][] = [
                'tipo' => 'barras',
                'titulo' => 'Usuarios por cargo',
                'etiquetas' => array_column($usuariosPorCargo, 'cargo'),
                'valores' => array_map('intval', array_column($usuariosPorCargo, 'cantidad')),
            ];
        }
        if (tienePermiso('educandos.ver_todos')) {
            $indicadores[] = ['nombre' => 'Educandos', 'valor' => $consultar('SELECT COUNT(*) AS cantidad FROM educandos WHERE fecha_baja IS NULL')['cantidad'] ?? 0, 'detalle' => 'Personas inscritas'];
        }
        if (tienePermiso('asistencia.responder_propios')) {
            $educandosPropios = $consultar(
                'SELECT COUNT(*) AS cantidad FROM usuarios_educandos ue
                 JOIN educandos educ ON educ.id = ue.id_educando
                 WHERE ue.id_usuario = ? AND educ.fecha_baja IS NULL',
                [$usuario['id']]
            );
            $indicadores[] = ['nombre' => 'Mis educandos', 'valor' => $educandosPropios['cantidad'] ?? 0, 'detalle' => 'Personas vinculadas'];
        }
        $indicadores[] = ['nombre' => 'Eventos próximos', 'valor' => $eventosPendientes, 'detalle' => 'Actividades pendientes'];

        if (tienePermiso('facturas.ver_todas')) {
            $facturas = $consultar(
                'SELECT COUNT(*) AS cantidad, COALESCE(SUM(precio), 0) AS total FROM facturas'
            );
            $indicadores[] = ['nombre' => 'Gasto registrado', 'valor' => number_format((float) $facturas['total'], 2, ',', '.') . ' €', 'detalle' => $facturas['cantidad'] . ' facturas'];
        } elseif (tienePermiso('facturas.subir_propias')) {
            $facturas = $consultar(
                'SELECT COUNT(*) AS cantidad, COALESCE(SUM(precio), 0) AS total
                 FROM facturas WHERE responsable_id = ?',
                [$usuario['id']]
            );
            $indicadores[] = ['nombre' => 'Mis facturas', 'valor' => $facturas['cantidad'], 'detalle' => 'Facturas registradas'];
            $indicadores[] = ['nombre' => 'Mi gasto', 'valor' => number_format((float) $facturas['total'], 2, ',', '.') . ' €', 'detalle' => 'Total de mis facturas'];
        }

        if (tienePermiso('asistencia.ver_todas')) {
            $sabado = $sabadosProximos[0] ?? null;
            $asistenciaSabado = [];
            if ($sabado) {
                $educandos = $consultarTodos(
                    'SELECT e.id, e.nombre, e.apellidos, e.naz
                     FROM educandos e WHERE e.fecha_baja IS NULL
                     ORDER BY e.apellidos, e.nombre'
                );
                $estados = $consultarTodos(
                    'SELECT id_educando, estado FROM educandos_asistencias
                     WHERE id_evento = ?',
                    [$sabado['id']]
                );
                $estadosPorEducando = array_column($estados, 'estado', 'id_educando');
                foreach ($educandos as $educando) {
                    $seccion = seccionEducando($db, $educando['naz'] ?? null);
                    $convocatorias = $convocatoriasDashboard[$sabado['id']] ?? [];
                    if ($convocatorias && !in_array($seccion, $convocatorias, true)) {
                        continue;
                    }
                    $estado = $estadosPorEducando[$educando['id'] ?? 0] ?? 'pendiente';
                    $asistenciaSabado[$seccion ?: 'sin_seccion'][$estado] =
                        ($asistenciaSabado[$seccion ?: 'sin_seccion'][$estado] ?? 0) + 1;
                }
            }
            $datos['asistencias_sabado'] = [
                'evento' => $sabado,
                'secciones' => $asistenciaSabado,
            ];
        }

        if (tienePermiso('asistencia.responder_propios')) {
            $asistencias = $consultar(
                "SELECT
                    SUM(ea.estado = 'si') AS confirmadas,
                    SUM(ea.estado = 'no') AS ausencias,
                    SUM(ea.estado = 'pendiente' OR ea.estado IS NULL) AS pendientes
                 FROM usuarios_educandos ue
                 JOIN educandos educ ON educ.id = ue.id_educando
                 CROSS JOIN eventos e
                 LEFT JOIN educandos_asistencias ea
                    ON ea.id_educando = ue.id_educando AND ea.id_evento = e.id
                 WHERE ue.id_usuario = ? AND e.fecha_fin >= NOW()
                   AND educ.fecha_baja IS NULL",
                [$usuario['id']]
            );
            $indicadores[] = ['nombre' => 'Asistencias pendientes', 'valor' => $asistencias['pendientes'] ?? 0, 'detalle' => 'Requieren respuesta'];
            $indicadores[] = ['nombre' => 'Confirmadas', 'valor' => $asistencias['confirmadas'] ?? 0, 'detalle' => 'Asistencias registradas'];
            $datos['graficas'][] = [
                'tipo' => 'tarta',
                'titulo' => 'Estado de próximas asistencias',
                'etiquetas' => ['Confirmadas', 'No asisten', 'Pendientes'],
                'valores' => array_map('intval', [
                    $asistencias['confirmadas'] ?? 0,
                    $asistencias['ausencias'] ?? 0,
                    $asistencias['pendientes'] ?? 0,
                ]),
            ];
            $eventosVisiblesIds = array_map(
                'intval',
                array_column($eventosVisibles, 'id')
            );
            $pendientesCirculares = $consultarTodos(
                "SELECT e.id AS evento_id
                 FROM usuarios_educandos ue
                 JOIN educandos educ ON educ.id = ue.id_educando
                 JOIN eventos e ON e.fecha_fin >= NOW() AND e.circular = 1
                 LEFT JOIN educandos_asistencias ea
                   ON ea.id_evento = e.id AND ea.id_educando = ue.id_educando
                 WHERE ue.id_usuario = ? AND educ.fecha_baja IS NULL
                   AND (ea.estado IS NULL OR ea.estado <> 'si')",
                [$usuario['id']]
            );
            $datos['tutor'] = [
                'circulares_pendientes' => count(array_filter(
                    $pendientesCirculares,
                    fn($fila): bool => in_array(
                        (int) $fila['evento_id'],
                        $eventosVisiblesIds,
                        true
                    )
                )),
                'asistencias_pendientes' => (int) ($asistencias['pendientes'] ?? 0),
            ];
        }
        $datos['indicadores'] = $indicadores;

        responder($datos);

    case 'asistencia_tutor':
        if (!tienePermiso('asistencia.responder_propios')) {
            responder(['error' => 'Solo los tutores pueden usar esta ruta'], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $eventoId = (int) ($_GET['evento'] ?? 0);
            if ($eventoId < 1) {
                responder(['error' => 'Evento no válido'], 400);
            }
            $stmt = $db->prepare(
                "SELECT e.id, e.nombre, e.circular,
                        educ.id AS educando_id,
                        educ.nombre, educ.apellidos, educ.naz,
                        COALESCE(ea.estado, 'pendiente') AS estado,
                        ea.archivo_entrega
                 FROM usuarios_educandos ue
                 JOIN educandos educ ON educ.id = ue.id_educando
                 JOIN eventos e ON e.id = ?
                 LEFT JOIN educandos_asistencias ea
                    ON ea.id_evento = e.id AND ea.id_educando = educ.id
                 WHERE ue.id_usuario = ? AND educ.fecha_baja IS NULL
                 ORDER BY educ.naz IS NULL, educ.naz DESC,
                          educ.apellidos, educ.nombre"
            );
            $stmt->execute([$eventoId, $usuario['id']]);
            $asistencias = $stmt->fetchAll();
            $seccionesEvento = [];
            if (tablaExiste($db, 'eventos_secciones')) {
                $seccionesStmt = $db->prepare(
                    'SELECT seccion FROM eventos_secciones WHERE id_evento = ?'
                );
                $seccionesStmt->execute([$eventoId]);
                $seccionesEvento = $seccionesStmt->fetchAll(PDO::FETCH_COLUMN);
            }
            $asistenciasFiltradas = [];
            foreach ($asistencias as $asistencia) {
                $asistencia['seccion'] = seccionEducando($db, $asistencia['naz']);
                if (
                    !$seccionesEvento
                    || in_array($asistencia['seccion'], $seccionesEvento, true)
                ) {
                    $asistenciasFiltradas[] = $asistencia;
                }
            }
            responder($asistenciasFiltradas);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $datos = json_decode(file_get_contents('php://input'), true);
            $eventoId = (int) ($datos['evento'] ?? 0);
            $educandoId = (int) ($datos['educando'] ?? 0);
            $estado = $datos['estado'] ?? '';
            if (
                $eventoId < 1 || $educandoId < 1
                || !in_array($estado, ['pendiente', 'si', 'no'], true)
                || !tutorTieneEducando($db, $usuario['id'], $educandoId)
            ) {
                responder(['error' => 'Datos de asistencia no válidos'], 400);
            }
            $eventoStmt = $db->prepare(
                'SELECT fecha_fin FROM eventos WHERE id = ? AND fecha_fin >= NOW()'
            );
            $eventoStmt->execute([$eventoId]);
            if (!$eventoStmt->fetchColumn()) {
                responder(['error' => 'No se puede rellenar la asistencia de un evento pasado'], 409);
            }
            if ($estado === 'si') {
                $stmt = $db->prepare(
                    'INSERT INTO educandos_asistencias
                        (id_educando, id_evento, estado)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE estado = VALUES(estado)'
                );
                $stmt->execute([$educandoId, $eventoId, $estado]);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO educandos_asistencias
                        (id_educando, id_evento, estado, archivo_entrega)
                     VALUES (?, ?, ?, NULL)
                     ON DUPLICATE KEY UPDATE estado = VALUES(estado),
                        archivo_entrega = NULL'
                );
                $stmt->execute([$educandoId, $eventoId, $estado]);
            }
            responder(['ok' => true]);
        }
        responder(['error' => 'Método no permitido'], 405);

    case 'educando_documentos':
        $educandoId = (int) ($_GET['educando'] ?? 0);
        $puedeConsultar = tienePermiso('documentos.ver_todos')
            || (tienePermiso('asistencia.responder_propios')
                && tutorTieneEducando($db, $usuario['id'], $educandoId));
        if ($educandoId < 1 || !$puedeConsultar) {
            responder(['error' => 'Educando no válido'], 403);
        }
        $stmt = $db->prepare(
            "SELECT e.id AS evento_id, e.nombre AS evento, e.fecha_inicio,
                    e.fecha_fin, ea.archivo_entrega
             FROM educandos_asistencias ea
             JOIN eventos e ON e.id = ea.id_evento
             WHERE ea.id_educando = ? AND ea.archivo_entrega IS NOT NULL
             ORDER BY e.fecha_inicio DESC"
        );
        $stmt->execute([$educandoId]);
        responder($stmt->fetchAll());

    case 'catalogos':
        if (!tablaExiste($db, 'tipos_evento')
            || !tablaExiste($db, 'secciones')
            || !tablaExiste($db, 'categorias_factura')) {
            responder(['error' => 'Faltan los catálogos. Ejecuta la migración de catálogos.'], 500);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Quien no gestiona eventos ni configuración (el tutor) solo ve los activos.
            responder(catalogos($db, !tieneAlgunPermiso(['eventos.gestionar', 'configuracion.gestionar'])));
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST'
            || !tienePermiso('configuracion.gestionar')) {
            responder(['error' => 'No tienes permisos para modificar catálogos'], 403);
        }
        $recibida = json_decode(file_get_contents('php://input'), true);
        if (!is_array($recibida)
            || !isset($recibida['tipos_evento'], $recibida['secciones'],
                $recibida['categorias_factura'])
            || !is_array($recibida['tipos_evento'])
            || !is_array($recibida['secciones'])
            || !is_array($recibida['categorias_factura'])) {
            responder(['error' => 'Los catálogos no son válidos'], 400);
        }
        $validarCodigo = static function ($codigo): string {
            $codigo = trim((string) $codigo);
            if ($codigo === '' || strlen($codigo) > 50
                || preg_match('/^[\p{L}][\p{L}\p{N}_ -]*$/u', $codigo) !== 1) {
                responder(['error' => 'El código de catálogo no es válido'], 400);
            }
            return $codigo;
        };
        $guardarCatalogo = static function (
            PDO $db,
            string $tabla,
            array $elementos
        ) use ($validarCodigo): void {
            $codigos = [];
            foreach ($elementos as $indice => $elemento) {
                if (!is_array($elemento)) {
                    responder(['error' => 'Elemento inválido en ' . $tabla], 400);
                }
                $codigo = $validarCodigo($elemento['codigo'] ?? '');
                if (isset($codigos[$codigo])) {
                    responder(['error' => 'Hay códigos repetidos en ' . $tabla], 400);
                }
                $codigos[$codigo] = true;
                $nombre = trim((string) ($elemento['nombre'] ?? ''));
                if ($nombre === '' || strlen($nombre) > 100) {
                    responder(['error' => 'El nombre de catálogo no es válido'], 400);
                }
                $activo = !empty($elemento['activo']) ? 1 : 0;
                $orden = max(0, (int) ($elemento['orden'] ?? $indice));
                if ($tabla === 'tipos_evento' || $tabla === 'categorias_factura') {
                    $tablaSql = $tabla === 'categorias_factura'
                        ? 'categorias_factura' : 'tipos_evento';
                    $stmt = $db->prepare(
                        'INSERT INTO ' . $tablaSql . '
                         (codigo, nombre, activo, orden) VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre),
                         activo = VALUES(activo), orden = VALUES(orden)'
                    );
                    $stmt->execute([$codigo, $nombre, $activo, $orden]);
                } else {
                    $minima = $elemento['edad_minima'] === '' ||
                        $elemento['edad_minima'] === null
                        ? null : max(0, min(120, (int) $elemento['edad_minima']));
                    $maxima = $elemento['edad_maxima'] === '' ||
                        $elemento['edad_maxima'] === null
                        ? null : max(0, min(120, (int) $elemento['edad_maxima']));
                    if ($minima !== null && $maxima !== null && $minima > $maxima) {
                        responder(['error' => 'El intervalo de edades no es válido'], 400);
                    }
                    $stmt = $db->prepare(
                        'INSERT INTO secciones
                         (codigo, nombre, edad_minima, edad_maxima, activo, orden)
                         VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre),
                         edad_minima = VALUES(edad_minima),
                         edad_maxima = VALUES(edad_maxima),
                         activo = VALUES(activo), orden = VALUES(orden)'
                    );
                    $stmt->execute([
                        $codigo, $nombre, $minima, $maxima, $activo, $orden
                    ]);
                }
            }
        };
        $db->beginTransaction();
        try {
            $guardarCatalogo($db, 'tipos_evento', $recibida['tipos_evento']);
            $guardarCatalogo($db, 'secciones', $recibida['secciones']);
            $guardarCatalogo($db, 'categorias_factura', $recibida['categorias_factura']);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            responder(['error' => 'No se pudieron guardar los catálogos'], 500);
        }
        responder(['ok' => true, 'catalogos' => catalogos($db)]);

    case 'configuracion_logo':
    case 'configuracion':
        $rutaConfiguracion = __DIR__ . '/../data/config.php';
        if ($_SERVER['REQUEST_METHOD'] === 'GET'
            && $ruta === 'configuracion_logo') {
            $configuracion = json_decode(
                file_get_contents($rutaConfiguracion),
                true
            );
            $archivo = $configuracion['logo_archivo'] ?? '';
            $rutaLogo = __DIR__ . '/../../storage/configuracion/' . basename($archivo);
            if (!$archivo || !is_file($rutaLogo)) {
                responder(['error' => 'No hay logo configurado'], 404);
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($rutaLogo);
            header('Content-Type: ' . $mime);
            readfile($rutaLogo);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $configuracion = json_decode(file_get_contents($rutaConfiguracion), true);
            if (is_array($configuracion)
                && !isset($configuracion['nombres_tipos_evento'])) {
                $configuracion['nombres_tipos_evento'] = [
                    'reunión de familias' => 'Reunión de familias',
                    'sabado' => 'Sábado',
                    'festival' => 'Festival',
                    'excursion' => 'Excursión',
                    'acampada' => 'Acampada',
                    'otro' => 'Otro'
                ];
            }
            if (is_array($configuracion) && tablaExiste($db, 'tipos_evento')
                && tablaExiste($db, 'secciones')
                && tablaExiste($db, 'categorias_factura')) {
                $configuracion['catalogos'] = catalogos($db);
            }
            responder(is_array($configuracion)
                ? $configuracion
                : ['error' => 'La configuración no es válida']);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            exigirPermiso('configuracion.gestionar');
            $recibida = isset($_POST['configuracion'])
                ? json_decode($_POST['configuracion'], true)
                : json_decode(file_get_contents('php://input'), true);
            if (!is_array($recibida)) {
                responder(['error' => 'La configuración no es válida']);
            }
            $actual = json_decode(file_get_contents($rutaConfiguracion), true);
            $configuracion = is_array($actual) ? $actual : [];
            $camposTexto = [
                'nombre_grupo', 'color', 'color_secundario', 'cif',
                'direccion', 'municipio', 'provincia', 'telefono', 'correo',
                'coordinador', 'formato_nombre',
                'formatos_entrega'
            ];
            foreach ($camposTexto as $campo) {
                if (array_key_exists($campo, $recibida)) {
                    $configuracion[$campo] = trim((string) $recibida[$campo]);
                }
            }
            $tiposValidos = [
                'reunión de familias', 'sabado', 'festival', 'excursion',
                'acampada', 'otro'
            ];
            if (isset($recibida['nombres_tipos_evento'])
                && is_array($recibida['nombres_tipos_evento'])) {
                $nombres = [];
                foreach ($tiposValidos as $tipo) {
                    $nombre = trim((string) (
                        $recibida['nombres_tipos_evento'][$tipo] ?? $tipo
                    ));
                    $nombres[$tipo] = $nombre !== '' ? $nombre : $tipo;
                }
                $configuracion['nombres_tipos_evento'] = $nombres;
            }
            foreach (['modo_oscuro', 'mostrar_educandos_sin_seccion',
                'permitir_asistencia_pasada'] as $campo) {
                $configuracion[$campo] = filter_var(
                    $recibida[$campo] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );
            }
            if (isset($recibida['plazo_conservacion_anos'])) {
                $configuracion['plazo_conservacion_anos'] = max(
                    1,
                    min(30, (int) $recibida['plazo_conservacion_anos'])
                );
            }
            if (isset($recibida['tamano_maximo_archivos_mb'])) {
                $configuracion['tamano_maximo_archivos_mb'] = max(
                    1,
                    min(50, (int) $recibida['tamano_maximo_archivos_mb'])
                );
            }
            $logo = $_FILES['logo'] ?? null;
            if ($logo && $logo['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($logo['error'] !== UPLOAD_ERR_OK || $logo['size'] > 5 * 1024 * 1024) {
                    responder(['error' => 'El logo no es válido o supera los 5 MB'], 400);
                }
                $permitidos = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($logo['tmp_name']);
                if (!isset($permitidos[$mime])) {
                    responder(['error' => 'El logo debe ser JPEG, PNG o WebP'], 415);
                }
                $directorio = __DIR__ . '/../../storage/configuracion';
                if (!is_dir($directorio) && !mkdir($directorio, 0750, true)) {
                    responder(['error' => 'No se pudo preparar el almacenamiento del logo'], 500);
                }
                $nombre = 'logo-' . bin2hex(random_bytes(12))
                    . '.' . $permitidos[$mime];
                if (!move_uploaded_file($logo['tmp_name'], $directorio . '/' . $nombre)) {
                    responder(['error' => 'No se pudo guardar el logo'], 500);
                }
                $configuracion['logo_archivo'] = $nombre;
            }
            if (file_put_contents(
                $rutaConfiguracion,
                json_encode($configuracion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) === false) {
                responder(['error' => 'No se pudo guardar la configuración']);
            }
            responder(['ok' => true]);
        }
        responder(['error' => 'Método no permitido']);

    case 'conservacion':
        // E-06: educandos dados de baja cuyo plazo de conservación ha vencido.
        // GET lista lo que se borraría; POST lo borra (archivos y registro).
        exigirPermiso('conservacion.gestionar');
        $configuracion = json_decode(
            (string) file_get_contents(__DIR__ . '/../data/config.php'),
            true
        );
        $plazo = max(1, (int) ($configuracion['plazo_conservacion_anos'] ?? 5));
        $limite = (new DateTimeImmutable('today'))->modify("-{$plazo} years")->format('Y-m-d');
        $vencidosStmt = $db->prepare(
            'SELECT id, nombre, apellidos, fecha_baja FROM educandos
             WHERE fecha_baja IS NOT NULL AND fecha_baja <= ?
             ORDER BY fecha_baja, apellidos, nombre'
        );
        $vencidosStmt->execute([$limite]);
        $vencidos = $vencidosStmt->fetchAll();
        $idsVencidos = array_map('intval', array_column($vencidos, 'id'));

        $archivosDe = function (int $educandoId) use ($db): array {
            $stmt = $db->prepare(
                'SELECT archivo_entrega FROM educandos_asistencias
                 WHERE id_educando = ? AND archivo_entrega IS NOT NULL'
            );
            $stmt->execute([$educandoId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        };
        // Tutores cuyos educandos están todos vencidos: se quedarían sin ninguno.
        // Solo se ofrecen los que no tienen ningún otro cargo (un tutor que además
        // lleva un cargo del comité sigue haciendo falta).
        $tutoresSinEducandos = [];
        if ($idsVencidos) {
            $marcas = implode(',', array_fill(0, count($idsVencidos), '?'));
            $tutoresStmt = $db->prepare(
                "SELECT u.id, u.nombre, u.apellidos, u.email
                 FROM usuarios u
                 WHERE EXISTS (SELECT 1 FROM usuarios_roles ur
                               WHERE ur.id_usuario = u.id AND ur.codigo_rol = 'tutor')
                   AND NOT EXISTS (SELECT 1 FROM usuarios_roles ur
                                   WHERE ur.id_usuario = u.id AND ur.codigo_rol <> 'tutor')
                   AND EXISTS (SELECT 1 FROM usuarios_educandos ue
                               WHERE ue.id_usuario = u.id AND ue.id_educando IN ({$marcas}))
                   AND NOT EXISTS (SELECT 1 FROM usuarios_educandos ue
                                   WHERE ue.id_usuario = u.id AND ue.id_educando NOT IN ({$marcas}))
                 ORDER BY u.apellidos, u.nombre"
            );
            $tutoresStmt->execute([...$idsVencidos, ...$idsVencidos]);
            $tutoresSinEducandos = $tutoresStmt->fetchAll();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            foreach ($vencidos as &$vencido) {
                $vencido['archivos'] = count($archivosDe((int) $vencido['id']));
            }
            unset($vencido);
            responder([
                'plazo_anos' => $plazo,
                'fecha_limite' => $limite,
                'educandos' => $vencidos,
                'tutores_sin_educandos' => $tutoresSinEducandos,
            ]);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['error' => 'Método no permitido'], 405);
        }

        $datos = json_decode(file_get_contents('php://input'), true);
        // Solo se borra lo que el servidor confirma como vencido, aunque el cliente envíe otros ids.
        $borrarEducandos = array_values(array_intersect(
            $idsVencidos,
            array_map('intval', (array) ($datos['educandos'] ?? []))
        ));
        $borrarTutores = array_values(array_intersect(
            array_map('intval', array_column($tutoresSinEducandos, 'id')),
            array_map('intval', (array) ($datos['tutores'] ?? []))
        ));
        if (!$borrarEducandos && !$borrarTutores) {
            responder(['error' => 'No hay nada vencido seleccionado'], 400);
        }

        $archivos = [];
        foreach ($borrarEducandos as $educandoId) {
            $archivos = [...$archivos, ...$archivosDe($educandoId)];
        }
        $db->beginTransaction();
        try {
            $borrarEducando = $db->prepare('DELETE FROM educandos WHERE id = ?');
            foreach ($borrarEducandos as $educandoId) {
                // Las asistencias y los vínculos con tutores se borran en cascada.
                $borrarEducando->execute([$educandoId]);
            }
            $borrarTutor = $db->prepare(
                "DELETE FROM usuarios WHERE id = ?
                 AND NOT EXISTS (SELECT 1 FROM usuarios_educandos WHERE id_usuario = ?)
                 AND NOT EXISTS (SELECT 1 FROM usuarios_roles
                                 WHERE id_usuario = usuarios.id AND codigo_rol <> 'tutor')"
            );
            foreach ($borrarTutores as $tutorId) {
                $borrarTutor->execute([$tutorId, $tutorId]);
            }
            if (usuariosQueGestionanRoles($db) === 0) {
                throw new RuntimeException('el sistema se quedaría sin nadie que gestione los cargos');
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            responder(['error' => 'No se pudo completar el borrado: ' . $error->getMessage()], 500);
        }

        // Los archivos se borran después de confirmar el borrado en la base de datos.
        $baseDocumentos = realpath(__DIR__ . '/../../storage/documentos');
        $archivosBorrados = 0;
        foreach ($archivos as $archivo) {
            $ruta = $baseDocumentos !== false
                ? realpath($baseDocumentos . '/' . ltrim((string) $archivo, '/'))
                : false;
            if ($ruta && is_file($ruta)
                && str_starts_with($ruta, $baseDocumentos . DIRECTORY_SEPARATOR)
                && unlink($ruta)) {
                $archivosBorrados++;
                // Se quitan las carpetas que quedan vacías (educando, sección y ronda).
                $carpeta = dirname($ruta);
                while ($carpeta !== $baseDocumentos
                    && str_starts_with($carpeta, $baseDocumentos . DIRECTORY_SEPARATOR)
                    && count(scandir($carpeta)) === 2) {
                    rmdir($carpeta);
                    $carpeta = dirname($carpeta);
                }
            }
        }
        registrarAcceso(
            $db,
            $usuario,
            'eliminar',
            'datos_vencidos',
            null,
            count($borrarEducandos) . ' educandos, ' . count($borrarTutores)
                . ' tutores, ' . $archivosBorrados . ' archivos'
        );
        responder([
            'ok' => true,
            'educandos' => count($borrarEducandos),
            'tutores' => count($borrarTutores),
            'archivos' => $archivosBorrados,
        ]);

    case 'roles':
        // E-13: cargos y permisos. GET: lista (también para el formulario de usuarios).
        // POST: crear o editar un cargo y sus permisos (roles.gestionar).
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            exigirPermiso('roles.gestionar', 'usuarios.gestionar');
            $roles = $db->query(
                'SELECT codigo, nombre, basico, activo, orden FROM roles ORDER BY basico DESC, orden, nombre'
            )->fetchAll();
            $permisosPorRol = [];
            foreach ($db->query('SELECT codigo_rol, codigo_permiso FROM roles_permisos') as $fila) {
                $permisosPorRol[$fila['codigo_rol']][] = $fila['codigo_permiso'];
            }
            foreach ($roles as &$rol) {
                $rol['basico'] = (bool) $rol['basico'];
                $rol['activo'] = (bool) $rol['activo'];
                $rol['permisos'] = $permisosPorRol[$rol['codigo']] ?? [];
            }
            unset($rol);
            responder([
                'roles' => $roles,
                'permisos' => $db->query('SELECT codigo, descripcion FROM permisos ORDER BY codigo')->fetchAll(),
            ]);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['error' => 'Método no permitido'], 405);
        }
        exigirPermiso('roles.gestionar');
        $datos = json_decode(file_get_contents('php://input'), true);
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $activo = filter_var($datos['activo'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $orden = max(0, min(65535, (int) ($datos['orden'] ?? 100)));
        $permisosRol = array_values(array_unique(array_map('strval', (array) ($datos['permisos'] ?? []))));
        if (preg_match('/^[a-z][a-z0-9_]{1,49}$/', $codigo) !== 1) {
            responder(['error' => 'El código del cargo solo admite minúsculas, números y guiones bajos'], 400);
        }
        if ($nombre === '') {
            responder(['error' => 'El cargo necesita un nombre'], 400);
        }
        $actualStmt = $db->prepare('SELECT basico FROM roles WHERE codigo = ?');
        $actualStmt->execute([$codigo]);
        $basicoActual = $actualStmt->fetchColumn();
        $esBasico = $basicoActual !== false && (bool) $basicoActual;
        if ($codigo === 'administrador') {
            // Evitar el bloqueo: el administrador conserva siempre sus permisos y no se desactiva.
            responder(['error' => 'Los permisos del administrador no se pueden cambiar'], 400);
        }
        if ($esBasico && !$activo) {
            responder(['error' => 'Los cargos básicos no se pueden desactivar'], 400);
        }
        if ($permisosRol) {
            $marcas = implode(',', array_fill(0, count($permisosRol), '?'));
            $existentes = $db->prepare("SELECT COUNT(*) FROM permisos WHERE codigo IN ({$marcas})");
            $existentes->execute($permisosRol);
            if ((int) $existentes->fetchColumn() !== count($permisosRol)) {
                responder(['error' => 'Algún permiso no existe'], 400);
            }
        }

        $db->beginTransaction();
        if ($basicoActual === false) {
            // Los cargos nuevos son siempre adicionales: los básicos son fijos.
            $db->prepare('INSERT INTO roles (codigo, nombre, basico, activo, orden) VALUES (?, ?, 0, ?, ?)')
                ->execute([$codigo, $nombre, $activo ? 1 : 0, $orden]);
        } else {
            $db->prepare('UPDATE roles SET nombre = ?, activo = ?, orden = ? WHERE codigo = ?')
                ->execute([$nombre, $activo ? 1 : 0, $orden, $codigo]);
        }
        $db->prepare('DELETE FROM roles_permisos WHERE codigo_rol = ?')->execute([$codigo]);
        $insertarPermiso = $db->prepare('INSERT INTO roles_permisos (codigo_rol, codigo_permiso) VALUES (?, ?)');
        foreach ($permisosRol as $permiso) {
            $insertarPermiso->execute([$codigo, $permiso]);
        }
        if (usuariosQueGestionanRoles($db) === 0) {
            $db->rollBack();
            responder(['error' => 'No se puede: nadie podría gestionar los cargos'], 400);
        }
        $db->commit();
        responder(['ok' => true]);

    case 'modulos':
        responder([]);

    case 'entidades':
        // Tablas del menú según los permisos (E-13).
        $entidades = $db->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME NOT IN (
                   'eventos_secciones',
                   'tipos_evento',
                   'secciones',
                   'categorias_factura',
                   'galeria_fotos',
                   'roles',
                   'permisos',
                   'usuarios_roles',
                   'roles_permisos'
               )
             ORDER BY TABLE_NAME"
        )->fetchAll(PDO::FETCH_COLUMN);
        responder(array_values(array_filter(
            $entidades,
            fn(string $tabla): bool => tieneAlgunPermiso(permisosTabla($tabla, 'menu'))
        )));

    case 'tabla':
        $tabla = $_GET['tabla'] ?? '';
        if ($tabla === '') {
            responder(['error' => 'No se ha indicado ninguna tabla']);
        }
        exigirPermisoTabla($usuario, $tabla, 'lectura');
        $columnas = obtenerColumnas($db, $tabla);
        $clavePrimaria = null;
        foreach ($columnas as $columna) {
            if ($columna['pk']) {
                $clavePrimaria = $columna['name'];
                break;
            }
        }
        $sql = $tabla === 'facturas'
            ? 'SELECT f.*, CONCAT(u.nombre, \' \', u.apellidos) AS responsable_nombre
               FROM facturas f
               JOIN usuarios u ON u.id = f.responsable_id'
            : ($tabla === 'eventos'
                ? 'SELECT e.*, CONCAT(u.nombre, \' \', u.apellidos)
                       AS responsable_nombre
                   FROM eventos e
                   LEFT JOIN usuarios u ON u.id = e.id_responsable'
                : 'SELECT * FROM `' . str_replace('`', '``', $tabla) . '`');
        $parametros = [];
        if ($tabla === 'educandos' && !tienePermiso('educandos.ver_todos')) {
            $sql .= ' WHERE fecha_baja IS NULL
                      AND id IN (SELECT id_educando FROM usuarios_educandos WHERE id_usuario = ?)';
            $parametros[] = $usuario['id'];
        }
        if ($tabla === 'facturas' && !tienePermiso('facturas.ver_todas')) {
            // Sin facturas.ver_todas cada uno ve solo las suyas.
            $sql .= ' WHERE f.responsable_id = ?';
            $parametros[] = $usuario['id'];
        }
        if ($tabla === 'educandos') {
            // Primero los activos; los dados de baja quedan al final.
            $sql .= ' ORDER BY fecha_baja IS NOT NULL, naz IS NULL, naz DESC, apellidos, nombre';
        }
        if ($tabla === 'registro_accesos') {
            $sql .= ' ORDER BY fecha DESC, id DESC';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);
        $registros = $stmt->fetchAll();
        if ($tabla === 'usuarios') {
            // Se quita el hash de la contraseña: no debe salir nunca del servidor.
            // La columna antigua «rol» (hasta la segunda migración de E-13) tampoco se muestra:
            // los cargos se añaden desde usuarios_roles.
            $columnas = array_values(array_filter(
                $columnas,
                fn($columna) => $columna['tipo_formulario'] !== 'PASSWORD' && $columna['name'] !== 'rol'
            ));
            $cargos = rolesDeUsuarios($db);
            foreach ($registros as &$registro) {
                unset($registro['contraseña'], $registro['rol']);
                $id = (int) $registro['id'];
                $registro['cargos'] = implode(' · ', $cargos[$id]['nombres'] ?? []);
                $registro['roles_codigos'] = $cargos[$id]['codigos'] ?? [];
                $registro['rol_basico'] = $cargos[$id]['basico'] ?? null;
                $registro['puede_tener_facturas'] = usuarioTienePermiso($db, $id, 'facturas.subir_propias');
            }
            unset($registro);
        }
        if ($tabla === 'eventos' && tablaExiste($db, 'eventos_secciones')) {
            $seccionesStmt = $db->query(
                'SELECT id_evento, seccion FROM eventos_secciones ORDER BY seccion'
            );
            $seccionesPorEvento = [];
            foreach ($seccionesStmt->fetchAll() as $filaSeccion) {
                $seccionesPorEvento[$filaSeccion['id_evento']][] =
                    $filaSeccion['seccion'];
            }
            foreach ($registros as &$registro) {
                $registro['convocatorias'] =
                    $seccionesPorEvento[$registro['id']] ?? [];
            }
            unset($registro);
        }
        if ($tabla === 'educandos') {
            foreach ($registros as &$registro) {
                $registro['seccion'] = seccionEducando($db, $registro['naz'] ?? null);
            }
            unset($registro);
        }
        responder([
            'tabla' => $tabla,
            'columnas' => $columnas,
            'registros' => $registros,
            'primaryKey' => $clavePrimaria,
        ]);

    case 'columnas':
        $tabla = $_GET['tabla'] ?? '';
        exigirPermisoTabla($usuario, $tabla, 'lectura');
        if ($tabla === '') {
            responder(['error' => 'No se ha indicado ninguna tabla']);
        }
        $columnas = obtenerColumnas($db, $tabla);
        if ($tabla === 'usuarios') {
            // Los cargos se eligen con un control propio, no con la columna antigua.
            $columnas = array_values(array_filter($columnas, fn($columna) => $columna['name'] !== 'rol'));
        }
        if ($tabla === 'facturas' && !tienePermiso('facturas.gestionar_todas')) {
            // El responsable es siempre quien sube la factura.
            $columnas = array_values(array_filter($columnas, fn($columna) => $columna['name'] !== 'responsable_id'));
        }
        if ($tabla === 'facturas' && !tienePermiso('facturas.cambiar_estado')) {
            $columnas = array_values(array_filter($columnas, fn($columna) => $columna['name'] !== 'estado'));
        }
        responder($columnas);

    case 'educandos_usuario':
        exigirPermiso('usuarios.gestionar');
        $usuarioId = (int) ($_GET['usuario'] ?? 0);
        $stmt = $db->prepare(
            'SELECT e.id, e.nombre, e.apellidos
             FROM educandos e
             JOIN usuarios_educandos ue ON ue.id_educando = e.id
             WHERE ue.id_usuario = ?
             ORDER BY e.naz IS NULL, e.naz, e.apellidos, e.nombre'
        );
        $stmt->execute([$usuarioId]);
        responder($stmt->fetchAll());

    case 'crear':
    case 'editar':
        $tabla = $_POST['tabla'] ?? '';
        $datos = json_decode($_POST['datos'] ?? '{}', true);
        if ($tabla === '' || !is_array($datos)) {
            responder(['error' => 'Datos incorrectos']);
        }
        if ($tabla === 'facturas') {
            // Crear exige poder tener facturas; para editar basta con poder cambiar el
            // estado (tesorería), y entonces solo se guarda el estado.
            if ($ruta === 'crear') {
                exigirPermiso('facturas.subir_propias', 'facturas.gestionar_todas');
            } else {
                exigirPermiso('facturas.subir_propias', 'facturas.gestionar_todas', 'facturas.cambiar_estado');
            }
        } else {
            exigirPermisoTabla($usuario, $tabla, 'escritura');
        }
        if ($tabla === 'eventos' && !tablaExiste($db, 'eventos_secciones')) {
            responder([
                'error' => 'Falta crear la tabla eventos_secciones. Ejecuta la migración de noteas.md'
            ], 500);
        }
        $educandosRelacionados = $tabla === 'usuarios'
            ? array_values(array_filter(
                $datos['educandos_relacionados'] ?? [],
                fn($id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false
            ))
            : [];
        $convocatorias = $tabla === 'eventos'
            ? ($datos['convocatorias'] ?? [])
            : [];
        // Cargos del usuario (E-13): null si no se envían al editar (se conservan).
        $rolesUsuario = $tabla === 'usuarios' && isset($datos['roles']) && is_array($datos['roles'])
            ? array_values(array_unique(array_map('strval', $datos['roles'])))
            : null;
        unset($datos['educandos_relacionados']);
        unset($datos['convocatorias']);
        unset($datos['roles']);
        $columnas = obtenerColumnas($db, $tabla);
        $permitidas = array_column(array_filter($columnas, fn($columna) => !$columna['pk']), 'name');
        $datosValidos = array_intersect_key($datos, array_flip($permitidas));
        if ($tabla === 'eventos') {
            if (!isset($datosValidos['tipo'])
                || !is_string($datosValidos['tipo'])) {
                responder(['error' => 'El tipo de evento es obligatorio'], 400);
            }
            $tipoStmt = $db->prepare(
                'SELECT 1 FROM tipos_evento WHERE codigo = ?'
            );
            $tipoStmt->execute([$datosValidos['tipo']]);
            if (!$tipoStmt->fetchColumn()) {
                responder(['error' => 'El tipo de evento no existe'], 400);
            }
            if (array_key_exists('circular', $datosValidos)) {
                $datosValidos['circular'] = filter_var(
                    $datosValidos['circular'],
                    FILTER_VALIDATE_BOOLEAN
                ) ? 1 : 0;
            }
        }
        foreach ($columnas as $columna) {
            if (
                $columna['tipo_formulario'] === 'BOOLEAN'
                && array_key_exists($columna['name'], $datosValidos)
            ) {
                $datosValidos[$columna['name']] = in_array(
                    strtolower((string) $datosValidos[$columna['name']]),
                    ['1', 'true', 'si', 'sí', 'yes'],
                    true
                ) ? 1 : 0;
            }
        }
        if ($tabla === 'usuarios') {
            // La contraseña se guarda siempre con password_hash. Al editar, si llega
            // vacía se conserva la actual; al crear es obligatoria.
            $contrasena = (string) ($datosValidos['contraseña'] ?? '');
            unset($datosValidos['contraseña']);
            if ($contrasena === '' && $ruta === 'crear') {
                responder(['error' => 'La contraseña es obligatoria'], 400);
            }
            if ($contrasena !== '') {
                $errorContrasena = validarContrasena($contrasena);
                if ($errorContrasena !== null) {
                    responder(['error' => $errorContrasena], 400);
                }
                $datosValidos['contraseña'] = cifrarContrasena($contrasena);
            }
            unset($datosValidos['rol']);
            if ($rolesUsuario === null && $ruta === 'crear') {
                responder(['error' => 'El usuario debe tener un cargo básico'], 400);
            }
            if ($rolesUsuario !== null) {
                $errorRoles = validarRolesUsuario($db, $rolesUsuario);
                if ($errorRoles !== null) {
                    responder(['error' => $errorRoles], 400);
                }
                // Mientras exista la columna antigua usuarios.rol (hasta la segunda
                // migración) se mantiene igual que el cargo básico.
                if (existeColumnaRolAntigua($db)) {
                    $basicoStmt = $db->prepare(
                        'SELECT codigo FROM roles WHERE basico = 1 AND codigo IN ('
                        . implode(',', array_fill(0, count($rolesUsuario), '?')) . ')'
                    );
                    $basicoStmt->execute($rolesUsuario);
                    $datosValidos['rol'] = $basicoStmt->fetchColumn();
                }
            }
        }
        if ($tabla === 'facturas') {
            $puedeTodas = tienePermiso('facturas.gestionar_todas');
            if ($ruta === 'editar' && !$puedeTodas) {
                $responsableActual = $db->prepare('SELECT responsable_id FROM facturas WHERE id = ?');
                $responsableActual->execute([$_POST['id'] ?? 0]);
                $esPropia = (int) $responsableActual->fetchColumn() === (int) $usuario['id'];
                if (!$esPropia || !tienePermiso('facturas.subir_propias')) {
                    // Factura de otro: solo quien puede cambiar estados, y solo el estado.
                    if (!tienePermiso('facturas.cambiar_estado') || !array_key_exists('estado', $datosValidos)) {
                        responder(['error' => 'Solo puedes modificar tus propias facturas'], 403);
                    }
                    $datosValidos = ['estado' => $datosValidos['estado']];
                }
            }
            // Nadie cambia el estado sin facturas.cambiar_estado, ni en sus propias facturas.
            if (!tienePermiso('facturas.cambiar_estado')) {
                unset($datosValidos['estado']);
                if ($ruta === 'crear') {
                    $datosValidos['estado'] = 'pendiente';
                }
            }
            if (!$puedeTodas) {
                // El responsable es siempre quien sube la factura y no se puede cambiar.
                unset($datosValidos['responsable_id']);
                if ($ruta === 'crear') {
                    $datosValidos['responsable_id'] = $usuario['id'];
                }
            } elseif (array_key_exists('responsable_id', $datosValidos)
                && !usuarioTienePermiso($db, (int) $datosValidos['responsable_id'], 'facturas.subir_propias')) {
                responder([
                    'error' => 'El responsable de una factura debe poder tener facturas (monitor)'
                ], 400);
            }
        }

        // Todo el guardado va en una transacción: si algo falla (imagen no válida,
        // cargos que dejarían el sistema sin administrador…), no queda a medias.
        $db->beginTransaction();

        if ($ruta === 'crear') {
            $nombres = array_keys($datosValidos);
            $sql = 'INSERT INTO `' . str_replace('`', '``', $tabla) . '` (`'
                . implode('`,`', array_map(fn($nombre) => str_replace('`', '``', $nombre), $nombres))
                . '`) VALUES (' . implode(',', array_fill(0, count($nombres), '?')) . ')';
            $db->prepare($sql)->execute(array_values($datosValidos));
            $registroId = (int) $db->lastInsertId();
        } else {
            $id = $_POST['id'] ?? '';
            $pk = array_values(array_filter($columnas, fn($columna) => $columna['pk']))[0]['name'] ?? 'id';
            $cambios = array_map(
                fn($nombre) => '`' . str_replace('`', '``', $nombre) . '` = ?',
                array_keys($datosValidos)
            );
            $sql = 'UPDATE `' . str_replace('`', '``', $tabla) . '` SET '
                . implode(',', $cambios) . ' WHERE `' . $pk . '` = ?';
            $db->prepare($sql)->execute([...array_values($datosValidos), $id]);
            $registroId = (int) $id;
        }
        if ($tabla === 'usuarios') {
            $db->prepare('DELETE FROM usuarios_educandos WHERE id_usuario = ?')
                ->execute([$registroId]);
            $relacion = $db->prepare(
                'INSERT INTO usuarios_educandos (id_usuario, id_educando)
                 VALUES (?, ?)'
            );
            foreach (array_unique($educandosRelacionados) as $educandoId) {
                $relacion->execute([$registroId, $educandoId]);
            }
            if ($rolesUsuario !== null) {
                $db->prepare('DELETE FROM usuarios_roles WHERE id_usuario = ?')->execute([$registroId]);
                $insertarRol = $db->prepare('INSERT INTO usuarios_roles (id_usuario, codigo_rol) VALUES (?, ?)');
                foreach ($rolesUsuario as $codigoRol) {
                    $insertarRol->execute([$registroId, $codigoRol]);
                }
            }
            if (usuariosQueGestionanRoles($db) === 0) {
                $db->rollBack();
                responder(['error' => 'No se puede: nadie podría gestionar los cargos'], 400);
            }
        }
        if ($tabla === 'facturas') {
            guardarImagenFactura($db, $registroId);
        }
        if ($tabla === 'eventos') {
            $seccionesValidas = $db->query(
                'SELECT codigo FROM secciones'
            )->fetchAll(PDO::FETCH_COLUMN);
            $convocatorias = array_values(array_intersect(
                $seccionesValidas,
                array_unique(array_map('strval', is_array($convocatorias)
                    ? $convocatorias : []))
            ));
            $db->prepare('DELETE FROM eventos_secciones WHERE id_evento = ?')
                ->execute([$registroId]);
            $seccionStmt = $db->prepare(
                'INSERT INTO eventos_secciones (id_evento, seccion) VALUES (?, ?)'
            );
            foreach ($convocatorias as $seccion) {
                $seccionStmt->execute([$registroId, $seccion]);
            }
        }
        $db->commit();
        responder(['ok' => true]);

    case 'borrar':
        $tabla = $_POST['tabla'] ?? '';
        $id = $_POST['id'] ?? '';
        exigirPermisoTabla($usuario, $tabla, 'borrado');
        if ($tabla === 'facturas') {
            exigirFacturaPropia($db, $usuario, $id);
        }
        $columnas = obtenerColumnas($db, $tabla);
        $pk = array_values(array_filter($columnas, fn($columna) => $columna['pk']))[0]['name'] ?? 'id';
        // Al borrar un evento, sus fotos se borran en cascada en la base de datos;
        // se guardan antes los nombres para borrar también los archivos.
        $fotosEvento = [];
        if ($tabla === 'eventos' && tablaExiste($db, 'galeria_fotos')) {
            $fotosStmt = $db->prepare('SELECT archivo FROM galeria_fotos WHERE id_evento = ?');
            $fotosStmt->execute([$id]);
            $fotosEvento = $fotosStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        $sql = 'DELETE FROM `' . str_replace('`', '``', $tabla) . '` WHERE `' . $pk . '` = ?';
        $db->beginTransaction();
        $db->prepare($sql)->execute([$id]);
        if ($tabla === 'usuarios' && usuariosQueGestionanRoles($db) === 0) {
            $db->rollBack();
            responder(['error' => 'No se puede borrar: nadie podría gestionar los cargos'], 400);
        }
        $db->commit();
        foreach ($fotosEvento as $archivo) {
            $rutaFoto = __DIR__ . '/../../storage/galeria/' . basename($archivo);
            if (is_file($rutaFoto)) {
                unlink($rutaFoto);
            }
        }
        responder(['ok' => true]);

    default:
        responder(['error' => 'Ruta no encontrada']);
}
