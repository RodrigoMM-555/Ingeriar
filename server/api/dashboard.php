<?php

// Dashboard (revisado con el usuario el 2026-11-17). Cada bloque aparece según los permisos:
//   proximas      todos        próximos sábados, acampadas y otros eventos (el tutor, solo los
//                              que convocan a las secciones de sus educandos)
//   sabado        monitores    respuestas del próximo sábado por sección, con nombres y circular
//   cumpleanos    monitores    educandos y monitores que cumplen años esta semana (lunes-domingo)
//   facturas      tesorería    facturas pendientes de revisar y de pagar
//   tutor         tutores      asistencias pendientes, fichas básicas y circulares de actividades
//                              pendientes, y calendario de la ronda (se ve por trimestres)

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../data/secciones.php';
require_once __DIR__ . '/comun.php';
require_once __DIR__ . '/../auth/permisos.php';
require_once __DIR__ . '/../dominio/Educando.php';
require_once __DIR__ . '/../dominio/Evento.php';

const TIPOS_ACAMPADA = ['acampada', 'campamento'];
const PROXIMOS_POR_CATEGORIA = 3;

$db = obtenerConexion();
$usuario = exigirUsuario($db);

$consultar = function (string $sql, array $parametros = []) use ($db): array {
    $stmt = $db->prepare($sql);
    $stmt->execute($parametros);
    return $stmt->fetchAll();
};
$catalogoSecciones = $db->query(
    'SELECT codigo, edad_minima, edad_maxima FROM secciones WHERE activo = 1 ORDER BY orden, nombre'
)->fetchAll();
$convocatorias = Evento::convocatoriasDeTodos($db);
$nombreCompleto = fn(array $fila): string => trim($fila['nombre'] . ' ' . $fila['apellidos']);

// Educandos del tutor con su sección actual (solo si no ve todos los educandos).
$soloPropios = !tienePermiso('educandos.ver_todos');
$educandosPropios = [];
if ($soloPropios) {
    foreach (Educando::deTutor($db, $usuario['id']) as $educando) {
        $educandosPropios[] = ['educando' => $educando, 'seccion' => $educando->seccion($catalogoSecciones)];
    }
}
$seccionesPropias = array_values(array_unique(array_filter(array_column($educandosPropios, 'seccion'))));
// ¿Puede ver este evento? El tutor solo los que convocan a las secciones de sus educandos.
$visible = fn(array $evento): bool => !$soloPropios
    || ($seccionesPropias && Evento::convocaA($convocatorias[(int) $evento['id']] ?? [], $seccionesPropias));

$datos = ['rol' => $usuario['rol_basico'], 'graficas' => []];

// ---------- Próximas actividades (todos) ----------
$proximos = array_values(array_filter($consultar(
    'SELECT id, nombre, fecha_inicio, fecha_fin, tipo, lugar, circular
     FROM eventos WHERE fecha_fin >= NOW() ORDER BY fecha_inicio'
), $visible));
$categoria = fn(array $evento): string => match (true) {
    $evento['tipo'] === 'sabado' => 'sabados',
    in_array(strtolower($evento['tipo']), TIPOS_ACAMPADA, true) => 'acampadas',
    default => 'otros',
};
$datos['proximas'] = ['sabados' => [], 'acampadas' => [], 'otros' => []];
foreach ($proximos as $evento) {
    $grupo = $categoria($evento);
    if (count($datos['proximas'][$grupo]) < PROXIMOS_POR_CATEGORIA) {
        $evento['circular'] = (bool) $evento['circular'];
        $datos['proximas'][$grupo][] = $evento;
    }
}

// ---------- Próximo sábado (monitores) ----------
if (tienePermiso('asistencia.ver_todas')) {
    $sabado = array_values(array_filter($proximos, fn($evento) => $evento['tipo'] === 'sabado'))[0] ?? null;
    $secciones = [];
    $totales = ['si' => 0, 'no' => 0, 'pendiente' => 0];
    if ($sabado) {
        $convocadas = $convocatorias[(int) $sabado['id']] ?? [];
        $filas = $consultar(
            "SELECT e.id, e.nombre, e.apellidos, e.naz, COALESCE(ea.estado, 'pendiente') AS estado,
                    ea.archivo_entrega IS NOT NULL AS entregada
             FROM educandos e
             LEFT JOIN educandos_asistencias ea ON ea.id_educando = e.id AND ea.id_evento = ?
             WHERE e.fecha_baja IS NULL
             ORDER BY e.apellidos, e.nombre",
            [$sabado['id']]
        );
        foreach ($filas as $fila) {
            $seccion = seccionDeEducandoConCatalogo($fila['naz'], $catalogoSecciones) ?? 'sin_seccion';
            if ($convocadas && !in_array($seccion, $convocadas, true)) {
                continue;
            }
            $secciones[$seccion] ??= ['si' => [], 'no' => [], 'pendiente' => []];
            $persona = ['nombre' => $nombreCompleto($fila)];
            // Si el sábado lleva circular, se indica si la ha entregado quien va.
            if ($sabado['circular'] && $fila['estado'] === 'si') {
                $persona['circular'] = $fila['entregada'] ? 'entregada' : 'falta';
            }
            $secciones[$seccion][$fila['estado']][] = $persona;
            $totales[$fila['estado']]++;
        }
        // Mismo orden que el catálogo de secciones.
        $orden = array_flip(array_column($catalogoSecciones, 'codigo'));
        uksort($secciones, fn($a, $b) => ($orden[$a] ?? 99) <=> ($orden[$b] ?? 99));
        $datos['graficas'][] = [
            'tipo' => 'tarta',
            'titulo' => 'Respuestas del próximo sábado',
            'etiquetas' => ['Sí', 'No', 'Pendientes'],
            'valores' => array_values($totales),
        ];
    }
    $datos['sabado'] = ['evento' => $sabado, 'secciones' => $secciones, 'totales' => $totales];
}

// ---------- Cumpleaños de la semana (monitores) ----------
if (tienePermiso('educandos.ver_todos')) {
    $lunes = new DateTimeImmutable('monday this week');
    $dias = [];
    for ($i = 0; $i < 7; $i++) {
        $dia = $lunes->modify("+{$i} days");
        $dias[$dia->format('m-d')] = $dia;
    }
    $cumpleEstaSemana = function (?string $nacimiento) use ($dias): ?array {
        if (!$nacimiento) {
            return null;
        }
        $fecha = new DateTimeImmutable($nacimiento);
        $dia = $dias[$fecha->format('m-d')] ?? null;
        return $dia ? ['fecha' => $dia->format('Y-m-d'), 'edad' => (int) $dia->format('Y') - (int) $fecha->format('Y')] : null;
    };
    $educandos = [];
    foreach ($consultar('SELECT nombre, apellidos, naz FROM educandos WHERE naz IS NOT NULL AND fecha_baja IS NULL') as $fila) {
        if ($cumple = $cumpleEstaSemana($fila['naz'])) {
            $educandos[] = ['nombre' => $nombreCompleto($fila),
                'seccion' => seccionDeEducandoConCatalogo($fila['naz'], $catalogoSecciones)] + $cumple;
        }
    }
    $monitores = [];
    $hayFechaUsuarios = (bool) $db->query("SHOW COLUMNS FROM usuarios LIKE 'fecha_nacimiento'")->fetchColumn();
    if ($hayFechaUsuarios) {
        foreach ($consultar(
            "SELECT DISTINCT u.nombre, u.apellidos, u.fecha_nacimiento
             FROM usuarios u JOIN usuarios_roles ur ON ur.id_usuario = u.id
             WHERE ur.codigo_rol IN ('scouter', 'administrador') AND u.fecha_nacimiento IS NOT NULL"
        ) as $fila) {
            if ($cumple = $cumpleEstaSemana($fila['fecha_nacimiento'])) {
                $monitores[] = ['nombre' => $nombreCompleto($fila)] + $cumple;
            }
        }
    }
    $porFecha = fn($a, $b) => strcmp($a['fecha'], $b['fecha']);
    usort($educandos, $porFecha);
    usort($monitores, $porFecha);
    $datos['cumpleanos'] = [
        'desde' => $lunes->format('Y-m-d'),
        'hasta' => $lunes->modify('+6 days')->format('Y-m-d'),
        'educandos' => $educandos,
        'monitores' => $monitores,
    ];
}

// ---------- Facturas (tesorería) ----------
if (tienePermiso('facturas.cambiar_estado')) {
    $resumenFacturas = function (string $estado) use ($consultar, $nombreCompleto): array {
        $filas = $consultar(
            "SELECT f.concepto, f.precio, f.fecha_compra, u.nombre, u.apellidos
             FROM facturas f JOIN usuarios u ON u.id = f.responsable_id
             WHERE f.estado = ? ORDER BY f.fecha_compra, f.id",
            [$estado]
        );
        return [
            'cantidad' => count($filas),
            'total' => round(array_sum(array_column($filas, 'precio')), 2),
            // Las más antiguas primero: son las que llevan más tiempo esperando.
            'antiguas' => array_map(fn($fila) => [
                'concepto' => $fila['concepto'], 'precio' => (float) $fila['precio'],
                'fecha' => $fila['fecha_compra'], 'responsable' => $nombreCompleto($fila),
            ], array_slice($filas, 0, 5)),
        ];
    };
    $datos['facturas'] = [
        'por_revisar' => $resumenFacturas('pendiente'),
        'por_pagar' => $resumenFacturas('revisada'),
    ];
}

// ---------- Mis educandos (tutores) ----------
if (tienePermiso('asistencia.responder_propios')) {
    $asistenciasPendientes = [];
    $circulares = [];
    foreach ($educandosPropios as ['educando' => $educando, 'seccion' => $seccion]) {
        $respuestas = [];
        foreach ($consultar(
            'SELECT id_evento, estado, archivo_entrega FROM educandos_asistencias WHERE id_educando = ?',
            [$educando->id]
        ) as $fila) {
            $respuestas[(int) $fila['id_evento']] = $fila;
        }
        foreach ($proximos as $evento) {
            // Solo los eventos que convocan a la sección de este educando.
            if (!$seccion || !Evento::convocaA($convocatorias[(int) $evento['id']] ?? [], [$seccion])) {
                continue;
            }
            $respuesta = $respuestas[(int) $evento['id']] ?? null;
            $entrada = ['evento_id' => (int) $evento['id'], 'evento' => $evento['nombre'],
                'fecha' => $evento['fecha_inicio'], 'educando' => $educando->nombre];
            if (!$respuesta || $respuesta['estado'] === 'pendiente') {
                $asistenciasPendientes[] = $entrada;
            } elseif ($evento['circular'] && $respuesta['estado'] === 'si' && !$respuesta['archivo_entrega']) {
                $circulares[] = $entrada;
            }
        }
    }
    // Fichas básicas obligatorias sin entregar o a corregir (E-14).
    $fichas = [];
    if ($educandosPropios && tablaExisteDashboard($db, 'documentos_educando')) {
        $ids = array_map(fn($fila) => $fila['educando']->id, $educandosPropios);
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        foreach ($consultar(
            "SELECT e.nombre AS educando, b.nombre AS documento, COALESCE(d.estado, 'falta') AS estado
             FROM educandos e
             JOIN documentos_base b ON b.tipo = 'inscripcion' AND b.activo = 1 AND b.obligatorio = 1
             LEFT JOIN documentos_educando d ON d.id_educando = e.id AND d.codigo_documento = b.codigo
             WHERE e.id IN ({$marcas}) AND (d.id IS NULL OR d.estado = 'corregir')
             ORDER BY e.nombre, b.orden",
            $ids
        ) as $fila) {
            $fichas[] = $fila;
        }
    }
    $porFecha = fn($a, $b) => strcmp($a['fecha'], $b['fecha']);
    usort($asistenciasPendientes, $porFecha);
    usort($circulares, $porFecha);

    // Calendario de la ronda (septiembre a agosto) con los eventos de sus secciones.
    $inicioRonda = new DateTimeImmutable((int) date('Y') - ((int) date('n') < 9 ? 1 : 0) . '-09-01');
    $calendario = array_values(array_map(fn($evento) => [
        'fecha' => substr($evento['fecha_inicio'], 0, 10), 'fin' => substr($evento['fecha_fin'], 0, 10),
        'nombre' => $evento['nombre'], 'tipo' => $evento['tipo'],
    ], array_filter($consultar(
        'SELECT id, nombre, fecha_inicio, fecha_fin, tipo FROM eventos
         WHERE fecha_inicio >= ? AND fecha_inicio < ? ORDER BY fecha_inicio',
        [$inicioRonda->format('Y-m-d'), $inicioRonda->modify('+1 year')->format('Y-m-d')]
    ), $visible)));

    $datos['tutor'] = [
        'asistencias_pendientes' => $asistenciasPendientes,
        'circulares_actividades' => $circulares,
        'fichas_basicas' => $fichas,
        'calendario' => $calendario,
        'inicio_ronda' => $inicioRonda->format('Y-m-d'),
    ];
}

responder($datos);

function tablaExisteDashboard(PDO $db, string $tabla): bool
{
    return (bool) $db->query('SHOW TABLES LIKE ' . $db->quote($tabla))->fetchColumn();
}
