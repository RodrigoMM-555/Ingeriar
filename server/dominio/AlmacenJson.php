<?php

// Almacén de documentos JSON en ficheros (E-04): la «base documental» del inventario.
//
// - Los ficheros están en storage/inventario/, fuera de la carpeta pública: solo PHP
//   los lee y escribe.
// - Leer usa un bloqueo compartido (varios lectores a la vez) y modificar un bloqueo
//   exclusivo (flock) sobre un fichero de bloqueo: si dos monitores guardan a la vez,
//   el segundo espera al primero y trabaja sobre los datos ya guardados.
// - La escritura es atómica: se escribe un fichero temporal y se renombra, así que un
//   corte a mitad nunca deja un JSON a medias.

class AlmacenJson
{
    private string $carpeta;

    public function __construct(?string $carpeta = null)
    {
        $this->carpeta = $carpeta ?? __DIR__ . '/../../storage/inventario';
        if (!is_dir($this->carpeta) && !mkdir($this->carpeta, 0770, true)) {
            throw new RuntimeException('No se pudo crear la carpeta del inventario');
        }
    }

    private function ruta(string $nombre): string
    {
        if (preg_match('/^[a-z0-9_-]+$/', $nombre) !== 1) {
            throw new InvalidArgumentException('Nombre de documento no válido');
        }
        return $this->carpeta . '/' . $nombre . '.json';
    }

    // Abre el fichero de bloqueo y espera a obtener el bloqueo pedido.
    private function bloquear(int $tipo)
    {
        $bloqueo = fopen($this->carpeta . '/.bloqueo', 'c');
        if ($bloqueo === false || !flock($bloqueo, $tipo)) {
            throw new RuntimeException('No se pudo bloquear el inventario');
        }
        return $bloqueo;
    }

    private function leerSinBloqueo(string $nombre, array $porDefecto): array
    {
        $ruta = $this->ruta($nombre);
        if (!is_file($ruta)) {
            return $porDefecto;
        }
        $datos = json_decode((string) file_get_contents($ruta), true);
        if (!is_array($datos)) {
            throw new RuntimeException("El fichero {$nombre}.json está dañado");
        }
        return $datos + $porDefecto;
    }

    public function leer(string $nombre, array $porDefecto = []): array
    {
        $bloqueo = $this->bloquear(LOCK_SH);
        try {
            return $this->leerSinBloqueo($nombre, $porDefecto);
        } finally {
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    // Lee el documento, se lo pasa a $cambio (que devuelve el documento modificado) y
    // lo guarda, todo con el bloqueo exclusivo. Devuelve lo que devuelva $cambio.
    public function modificar(string $nombre, array $porDefecto, callable $cambio): array
    {
        $bloqueo = $this->bloquear(LOCK_EX);
        try {
            $datos = $cambio($this->leerSinBloqueo($nombre, $porDefecto));
            $ruta = $this->ruta($nombre);
            $temporal = $ruta . '.' . bin2hex(random_bytes(4)) . '.tmp';
            $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false || file_put_contents($temporal, $json) === false || !rename($temporal, $ruta)) {
                if (is_file($temporal)) {
                    unlink($temporal);
                }
                throw new RuntimeException('No se pudo guardar el inventario');
            }
            return $datos;
        } finally {
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    // Identificador corto y único para elementos nuevos (por ejemplo «u-3f9a2c»).
    public static function nuevoId(string $prefijo): string
    {
        return $prefijo . '-' . bin2hex(random_bytes(5));
    }
}
