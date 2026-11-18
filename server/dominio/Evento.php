<?php

require_once __DIR__ . '/Modelo.php';

// Evento del calendario (tabla eventos) y sus convocatorias por sección (eventos_secciones).
class Evento extends Modelo
{
    protected const TABLA = 'eventos';
    protected const COLUMNAS = [
        'nombre' => 'nombre',
        'fecha_inicio' => 'fechaInicio',
        'fecha_fin' => 'fechaFin',
        'id_responsable' => 'idResponsable',
        'lugar' => 'lugar',
        'municipio' => 'municipio',
        'comunidad' => 'comunidad',
        'tipo' => 'tipo',
        'circular' => 'circular',
        'apertura_dias' => 'aperturaDias',
        'cierre_horas' => 'cierreHoras',
    ];

    public string $nombre = '';
    public string $fechaInicio = '';
    public string $fechaFin = '';
    public ?int $idResponsable = null;
    public ?string $lugar = null;
    public ?string $municipio = null;
    public ?string $comunidad = null;
    public string $tipo = '';
    public bool $circular = false;
    public ?int $aperturaDias = null;
    public ?int $cierreHoras = null;

    // Secciones convocadas de todos los eventos: [id_evento => [sección, …]].
    // Una sola consulta en lugar de una por evento.
    public static function convocatoriasDeTodos(PDO $db): array
    {
        $convocatorias = [];
        foreach ($db->query('SELECT id_evento, seccion FROM eventos_secciones ORDER BY seccion') as $fila) {
            $convocatorias[(int) $fila['id_evento']][] = $fila['seccion'];
        }
        return $convocatorias;
    }

    // ¿Convoca a alguna de estas secciones? Un evento sin secciones convoca a todas.
    public static function convocaA(array $convocadas, array $secciones): bool
    {
        return !$convocadas || (bool) array_intersect($convocadas, $secciones);
    }

    // Plazo en que las familias pueden responder y entregar la circular (E-18). Usa los días y
    // horas del evento o, si no tiene, los de su tipo: se abre «apertura» días antes del
    // inicio (sin valor, siempre abierto) y se cierra «cierre» horas antes del inicio (sin
    // valor, cuando termina el evento). Devuelve null si el evento no existe.
    public static function ventanaRespuestas(PDO $db, int $eventoId): ?array
    {
        try {
            $stmt = $db->prepare(
                'SELECT e.fecha_inicio, e.fecha_fin,
                        COALESCE(e.apertura_dias, t.apertura_dias) AS apertura,
                        COALESCE(e.cierre_horas, t.cierre_horas) AS cierre
                 FROM eventos e LEFT JOIN tipos_evento t ON t.codigo = e.tipo
                 WHERE e.id = ?'
            );
            $stmt->execute([$eventoId]);
        } catch (PDOException) {
            // Base sin la migración de E-18: abierto hasta que termina.
            $stmt = $db->prepare('SELECT fecha_inicio, fecha_fin, NULL AS apertura, NULL AS cierre FROM eventos WHERE id = ?');
            $stmt->execute([$eventoId]);
        }
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }
        $inicio = new DateTimeImmutable($fila['fecha_inicio']);
        $desde = $fila['apertura'] === null ? null : $inicio->modify('-' . (int) $fila['apertura'] . ' days');
        $hasta = $fila['cierre'] === null
            ? new DateTimeImmutable($fila['fecha_fin'])
            : $inicio->modify('-' . (int) $fila['cierre'] . ' hours');
        $ahora = new DateTimeImmutable();
        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'abierta' => ($desde === null || $ahora >= $desde) && $ahora <= $hasta,
        ];
    }

    // Mensaje para quien intenta responder fuera de plazo (o null si está abierto).
    public static function motivoVentanaCerrada(?array $ventana): ?string
    {
        if ($ventana === null) {
            return 'El evento no existe';
        }
        if ($ventana['abierta']) {
            return null;
        }
        $ahora = new DateTimeImmutable();
        return $ventana['desde'] !== null && $ahora < $ventana['desde']
            ? 'Las respuestas de este evento se abren el ' . $ventana['desde']->format('d/m/Y \a \l\a\s H:i')
            : 'El plazo para responder terminó el ' . $ventana['hasta']->format('d/m/Y \a \l\a\s H:i');
    }

    // ¿Ya ha terminado? (no se pueden registrar asistencias ni entregas)
    public function haTerminado(): bool
    {
        return new DateTimeImmutable($this->fechaFin) < new DateTimeImmutable();
    }

    // Nombres de los archivos de sus fotos en la galería (para borrarlos con el evento).
    public function archivosGaleria(PDO $db): array
    {
        if (!$db->query("SHOW TABLES LIKE 'galeria_fotos'")->fetchColumn()) {
            return [];
        }
        $stmt = $db->prepare('SELECT archivo FROM galeria_fotos WHERE id_evento = ?');
        $stmt->execute([$this->id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
