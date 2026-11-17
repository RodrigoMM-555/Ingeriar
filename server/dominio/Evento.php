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
