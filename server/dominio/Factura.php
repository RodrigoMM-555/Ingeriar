<?php

require_once __DIR__ . '/Modelo.php';
require_once __DIR__ . '/../auth/permisos.php';

// Factura (tabla facturas, E-05) con sus reglas: quién la ve, quién la edita y el
// cambio de estado pendiente → revisada → pagada.
class Factura extends Modelo
{
    public const ESTADOS = ['pendiente', 'revisada', 'pagada'];

    protected const TABLA = 'facturas';
    protected const COLUMNAS = [
        'responsable_id' => 'responsableId',
        'numero_fact' => 'numero',
        'CIF_prov' => 'cifProveedor',
        'fecha_emision' => 'fechaEmision',
        'fecha_compra' => 'fechaCompra',
        'concepto' => 'concepto',
        'precio' => 'precio',
        'categoria' => 'categoria',
        'estado' => 'estado',
        'imagen_factura' => 'imagen',
        'estado_cambiado_por' => 'estadoCambiadoPor',
        'estado_cambiado_en' => 'estadoCambiadoEn',
    ];

    public int $responsableId = 0;
    public int $numero = 0;
    public string $cifProveedor = '';
    public string $fechaEmision = '';
    public string $fechaCompra = '';
    public string $concepto = '';
    public string $precio = '0.00';
    public string $categoria = '';
    public string $estado = 'pendiente';
    public ?string $imagen = null;
    public ?int $estadoCambiadoPor = null;
    public ?string $estadoCambiadoEn = null;

    public function esDe(array $usuario): bool
    {
        return $this->responsableId === (int) $usuario['id'];
    }

    // La ve su responsable o quien tiene facturas.ver_todas.
    public function puedeVerla(array $usuario): bool
    {
        return tienePermiso('facturas.ver_todas') || $this->esDe($usuario);
    }

    // ¿Se pueden cambiar los datos o borrarla? Devuelve null si sí, o el motivo.
    // Pagada: nadie. Con facturas.gestionar_todas: cualquiera. Si no, la propia y pendiente.
    public function motivoNoEditable(array $usuario): ?string
    {
        if ($this->estado === 'pagada') {
            return 'Una factura pagada no se puede modificar';
        }
        if (tienePermiso('facturas.gestionar_todas')) {
            return null;
        }
        if (!$this->esDe($usuario) || !tienePermiso('facturas.subir_propias')) {
            return 'Solo puedes modificar tus propias facturas';
        }
        if ($this->estado !== 'pendiente') {
            return 'Solo se pueden modificar las facturas pendientes';
        }
        return null;
    }

    // Cambia el estado y anota quién y cuándo. El permiso lo comprueba quien llama.
    public function cambiarEstado(PDO $db, string $estado, int $usuarioId): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado no válido');
        }
        $this->estado = $estado;
        $this->estadoCambiadoPor = $usuarioId;
        $this->estadoCambiadoEn = date('Y-m-d H:i:s');
        $this->guardar($db);
    }

    // Facturas visibles para el usuario, con el nombre del responsable y de quien cambió
    // el estado (por eso devuelve filas y no objetos: incluye datos de otras tablas).
    public static function listadoPara(PDO $db, array $usuario): array
    {
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
        $stmt = $db->prepare($sql . ' ORDER BY f.fecha_compra DESC, f.id DESC');
        $stmt->execute($parametros);
        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $factura = static::desdeFila($fila);
            $fila['tiene_imagen'] = (bool) $fila['tiene_imagen'];
            $fila['propia'] = $factura->esDe($usuario);
            $fila['editable'] = $factura->motivoNoEditable($usuario) === null;
        }
        unset($fila);
        return $filas;
    }
}
