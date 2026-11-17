-- E-14: documentación de inscripción (fichas base y entregas por educando).

CREATE TABLE IF NOT EXISTS documentos_base (
    codigo VARCHAR(50) PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    -- participacion: la circular que se adjunta a los eventos (solo una activa).
    -- inscripcion: fichas que el tutor entrega una vez por educando.
    tipo ENUM('participacion', 'inscripcion') NOT NULL DEFAULT 'inscripcion',
    archivo_plantilla VARCHAR(255) NULL,
    obligatorio BOOLEAN NOT NULL DEFAULT TRUE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documentos_educando (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_educando INT UNSIGNED NOT NULL,
    codigo_documento VARCHAR(50) NOT NULL,
    archivo VARCHAR(255) NOT NULL,
    subido_por INT UNSIGNED NULL,
    fecha_subida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- pendiente: falta que un monitor lo revise; corregir: el monitor pide cambios.
    estado ENUM('pendiente', 'revisado', 'corregir') NOT NULL DEFAULT 'pendiente',
    observaciones VARCHAR(255) NULL,
    revisado_por INT UNSIGNED NULL,
    revisado_en DATETIME NULL,
    UNIQUE KEY unico_documento_educando (id_educando, codigo_documento),
    FOREIGN KEY (id_educando) REFERENCES educandos(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (codigo_documento) REFERENCES documentos_base(codigo)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (subido_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (revisado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO documentos_base
    (codigo, nombre, tipo, archivo_plantilla, obligatorio, activo, orden)
VALUES
    ('participacion', 'Autorización de participación en actividades', 'participacion', NULL, FALSE, TRUE, 0),
    ('ficha_inscripcion', 'Ficha de inscripción en el grupo (menor de edad)', 'inscripcion', NULL, TRUE, TRUE, 10),
    ('ficha_sanitaria_menor', 'Ficha sanitaria (menor de edad)', 'inscripcion', NULL, TRUE, TRUE, 20),
    ('ficha_sanitaria_mayor', 'Ficha sanitaria (mayor de edad)', 'inscripcion', NULL, FALSE, TRUE, 30),
    ('exclusion_responsabilidad', 'Exclusión de responsabilidad', 'inscripcion', NULL, TRUE, TRUE, 40),
    ('autorizacion_ausencia', 'Autorización para ausentarse de actividades', 'inscripcion', NULL, TRUE, TRUE, 50),
    ('autorizacion_imagen_menor', 'Autorización de uso de imagen (menor de edad)', 'inscripcion', NULL, TRUE, TRUE, 60),
    ('autorizacion_imagen_mayor', 'Autorización de uso de imagen (mayor de edad)', 'inscripcion', NULL, FALSE, TRUE, 70),
    ('autorizacion_whatsapp', 'Autorización para grupos de WhatsApp', 'inscripcion', NULL, TRUE, TRUE, 80),
    ('autorizacion_vehiculo', 'Autorización de viaje en vehículo privado', 'inscripcion', NULL, TRUE, TRUE, 90),
    ('autorizacion_volver_solo', 'Autorización para volver solo a casa', 'inscripcion', NULL, TRUE, TRUE, 100);

-- Permisos nuevos (las bases creadas antes de E-14 ya tienen los demás).
INSERT IGNORE INTO permisos (codigo, descripcion) VALUES
    ('documentos.subir_propios', 'Entregar la documentación de inscripción de sus educandos'),
    ('documentos.revisar', 'Revisar la documentación de inscripción y entregarla en nombre de las familias');

INSERT IGNORE INTO roles_permisos (codigo_rol, codigo_permiso) VALUES
    ('administrador', 'documentos.revisar'),
    ('scouter', 'documentos.revisar'),
    ('tutor', 'documentos.subir_propios');
