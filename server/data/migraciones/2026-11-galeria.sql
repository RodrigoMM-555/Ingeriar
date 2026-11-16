-- E-03: galería de fotografías. Ejecutar en bases creadas antes de esta versión.
-- Cada foto pertenece a un evento; la galería se organiza por ronda y tipo de evento
-- a partir de la fecha y el tipo del evento, así que no hace falta guardar carpetas.
CREATE TABLE IF NOT EXISTS galeria_fotos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_evento INT UNSIGNED NOT NULL,
    archivo VARCHAR(255) NOT NULL UNIQUE,
    nombre_original VARCHAR(255) NOT NULL,
    id_usuario INT UNSIGNED NULL,
    fecha_subida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_evento) REFERENCES eventos(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;
