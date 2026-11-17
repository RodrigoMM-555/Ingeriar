-- E-06: bajas de educandos y registro de accesos. Ejecutar en bases creadas antes de esta versión.

-- Fecha en la que el educando deja el grupo. NULL = educando activo.
ALTER TABLE educandos ADD COLUMN fecha_baja DATE NULL;

-- Registro de accesos a documentación sensible. Solo se inserta desde PHP;
-- la aplicación no permite editar ni borrar filas.
CREATE TABLE IF NOT EXISTS registro_accesos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT UNSIGNED NULL,
    usuario_email VARCHAR(254) NOT NULL,
    accion VARCHAR(30) NOT NULL,
    recurso VARCHAR(30) NOT NULL,
    id_educando INT UNSIGNED NULL,
    detalle VARCHAR(255) NULL,
    ip VARCHAR(45) NULL,
    INDEX indice_registro_fecha (fecha),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_educando) REFERENCES educandos(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;
