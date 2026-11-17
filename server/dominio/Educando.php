<?php

require_once __DIR__ . '/Modelo.php';
require_once __DIR__ . '/../data/secciones.php';

// Educando (tabla educandos). Reúne las reglas que antes estaban repetidas en varias rutas:
// su sección, si sigue activo, si un usuario es su tutor y qué archivos tiene.
class Educando extends Modelo
{
    protected const TABLA = 'educandos';
    protected const COLUMNAS = [
        'nombre' => 'nombre',
        'apellidos' => 'apellidos',
        'DNI' => 'dni',
        'naz' => 'nacimiento',
        'fecha_baja' => 'fechaBaja',
    ];

    public string $nombre = '';
    public string $apellidos = '';
    public string $dni = '';
    public ?string $nacimiento = null;
    public ?string $fechaBaja = null;

    // Activo = no se ha dado de baja (E-06).
    public function estaActivo(): bool
    {
        return $this->fechaBaja === null;
    }

    // ¿Seguía en el grupo en esta fecha? (para eventos y fotos de rondas pasadas)
    public function estabaActivoEn(DateTimeImmutable $fecha): bool
    {
        return $this->fechaBaja === null || $fecha->format('Y-m-d') <= $this->fechaBaja;
    }

    // Sección según su edad al empezar la ronda de la fecha indicada (hoy por defecto).
    public function seccion(array $catalogoSecciones, ?DateTimeImmutable $fecha = null): ?string
    {
        return seccionDeEducandoConCatalogo($this->nacimiento, $catalogoSecciones, $fecha);
    }

    // Carpeta de sus archivos dentro de la ronda y la sección: «12-ana-garcia-lopez».
    public function carpeta(): string
    {
        return nombreCarpetaEducando((int) $this->id, $this->nombre, $this->apellidos);
    }

    // ¿Es este usuario tutor de este educando activo?
    public static function esTutorDe(PDO $db, int $usuarioId, int $educandoId): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM usuarios_educandos ue
             JOIN educandos e ON e.id = ue.id_educando
             WHERE ue.id_usuario = ? AND ue.id_educando = ? AND e.fecha_baja IS NULL'
        );
        $stmt->execute([$usuarioId, $educandoId]);
        return (bool) $stmt->fetchColumn();
    }

    // Educandos vinculados a un tutor (por defecto, solo los activos).
    public static function deTutor(PDO $db, int $usuarioId, bool $soloActivos = true): array
    {
        return static::donde(
            $db,
            '`id` IN (SELECT id_educando FROM usuarios_educandos WHERE id_usuario = ?)'
                . ($soloActivos ? ' AND `fecha_baja` IS NULL' : ''),
            [$usuarioId],
            '`naz` IS NULL, `naz` DESC, `apellidos`, `nombre`'
        );
    }

    // Dados de baja en o antes de la fecha límite: su plazo de conservación ha vencido.
    public static function vencidos(PDO $db, string $fechaLimite): array
    {
        return static::donde(
            $db,
            '`fecha_baja` IS NOT NULL AND `fecha_baja` <= ?',
            [$fechaLimite],
            '`fecha_baja`, `apellidos`, `nombre`'
        );
    }

    // Rutas (relativas a storage/documentos) de todos sus archivos: entregas de
    // circulares y documentación de inscripción (E-14).
    public function archivos(PDO $db): array
    {
        $stmt = $db->prepare(
            'SELECT archivo_entrega FROM educandos_asistencias
             WHERE id_educando = ? AND archivo_entrega IS NOT NULL'
        );
        $stmt->execute([$this->id]);
        $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($db->query("SHOW TABLES LIKE 'documentos_educando'")->fetchColumn()) {
            $stmt = $db->prepare('SELECT archivo FROM documentos_educando WHERE id_educando = ?');
            $stmt->execute([$this->id]);
            $archivos = [...$archivos, ...$stmt->fetchAll(PDO::FETCH_COLUMN)];
        }
        return $archivos;
    }

    // Borra el educando y todo lo que depende de él. Se borra explícitamente porque en
    // bases creadas con el instalador antiguo no hay claves foráneas en cascada.
    // Los archivos los borra quien llama, después de confirmar la transacción.
    public function borrarConDependencias(PDO $db): void
    {
        if ($db->query("SHOW TABLES LIKE 'documentos_educando'")->fetchColumn()) {
            $db->prepare('DELETE FROM documentos_educando WHERE id_educando = ?')->execute([$this->id]);
        }
        $db->prepare('DELETE FROM educandos_asistencias WHERE id_educando = ?')->execute([$this->id]);
        $db->prepare('DELETE FROM usuarios_educandos WHERE id_educando = ?')->execute([$this->id]);
        $this->borrar($db);
    }
}
