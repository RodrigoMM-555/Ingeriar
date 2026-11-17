CREATE DATABASE IF NOT EXISTS ingeriar
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE ingeriar;

CREATE TABLE IF NOT EXISTS tipos_evento (
    codigo VARCHAR(50) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT IGNORE INTO tipos_evento (codigo, nombre, activo, orden) VALUES
    ('reunión de familias', 'Reunión de familias', TRUE, 10),
    ('sabado', 'Sábado', TRUE, 20),
    ('festival', 'Festival', TRUE, 30),
    ('excursion', 'Excursión', TRUE, 40),
    ('acampada', 'Acampada', TRUE, 50),
    ('otro', 'Otro', TRUE, 60);

CREATE TABLE IF NOT EXISTS secciones (
    codigo VARCHAR(50) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    edad_minima TINYINT UNSIGNED NULL,
    edad_maxima TINYINT UNSIGNED NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT IGNORE INTO secciones
    (codigo, nombre, edad_minima, edad_maxima, activo, orden)
VALUES
    ('colonia', 'Colonia', 5, 6, TRUE, 10),
    ('manada', 'Manada', 7, 9, TRUE, 20),
    ('tropa', 'Tropa', 10, 12, TRUE, 30),
    ('posta', 'Posta', 13, 15, TRUE, 40),
    ('rutas', 'Rutas', 16, 19, TRUE, 50);

CREATE TABLE IF NOT EXISTS categorias_factura (
    codigo VARCHAR(50) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT IGNORE INTO categorias_factura (codigo, nombre, activo, orden) VALUES
    ('envíos', 'Envíos', TRUE, 10),
    ('comida', 'Comida', TRUE, 20),
    ('material', 'Material', TRUE, 30),
    ('gasolina', 'Gasolina', TRUE, 40),
    ('otros', 'Otros', TRUE, 50),
    ('agua', 'Agua', TRUE, 60),
    ('luz', 'Luz', TRUE, 70),
    ('gas', 'Gas', TRUE, 80);

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    telefono VARCHAR(20) NOT NULL UNIQUE,
    -- Hash de password_hash() (bcrypt, 60 caracteres). VARCHAR(255) deja margen
    -- para algoritmos futuros. Nunca se guarda la contraseña en claro.
    `contraseña` VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- Roles y permisos (E-13). El cargo de cada usuario está en usuarios_roles.
CREATE TABLE IF NOT EXISTS roles (
    codigo VARCHAR(50) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    basico BOOLEAN NOT NULL DEFAULT FALSE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permisos (
    codigo VARCHAR(60) PRIMARY KEY,
    descripcion VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios_roles (
    id_usuario INT UNSIGNED NOT NULL,
    codigo_rol VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_usuario, codigo_rol),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (codigo_rol) REFERENCES roles(codigo)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles_permisos (
    codigo_rol VARCHAR(50) NOT NULL,
    codigo_permiso VARCHAR(60) NOT NULL,
    PRIMARY KEY (codigo_rol, codigo_permiso),
    FOREIGN KEY (codigo_rol) REFERENCES roles(codigo)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (codigo_permiso) REFERENCES permisos(codigo)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
-- Cargos básicos (basico = TRUE): todo usuario tiene exactamente uno.
-- Cargos adicionales (basico = FALSE): se suman al básico y solo añaden permisos nuevos.

INSERT IGNORE INTO roles (codigo, nombre, basico, activo, orden) VALUES
    ('administrador', 'Administrador', TRUE, TRUE, 10),
    ('scouter', 'Monitor', TRUE, TRUE, 20),
    ('tutor', 'Tutor', TRUE, TRUE, 30),
    ('tesoreria', 'Tesorería', FALSE, TRUE, 40);

INSERT IGNORE INTO permisos (codigo, descripcion) VALUES
    ('usuarios.ver', 'Ver la lista de usuarios (para elegir responsables)'),
    ('usuarios.gestionar', 'Crear, editar y borrar usuarios y asignar cargos'),
    ('educandos.ver_propios', 'Ver solo los educandos vinculados'),
    ('educandos.ver_todos', 'Ver todos los educandos'),
    ('educandos.gestionar', 'Crear y editar educandos, incluida la baja'),
    ('educandos.borrar', 'Borrar educandos definitivamente'),
    ('eventos.ver', 'Ver el calendario de eventos'),
    ('eventos.gestionar', 'Crear, editar y borrar eventos'),
    ('asistencia.responder_propios', 'Responder asistencias y subir entregas de sus educandos'),
    ('asistencia.ver_todas', 'Ver las asistencias de todos los educandos'),
    ('asistencia.gestionar', 'Editar directamente la tabla de asistencias'),
    ('documentos.ver_todos', 'Ver la documentación entregada de cualquier educando'),
    ('galeria.ver', 'Ver la galería de fotos'),
    ('galeria.subir', 'Subir y borrar fotos de la galería'),
    ('facturas.subir_propias', 'Subir y gestionar sus propias facturas'),
    ('facturas.ver_todas', 'Ver las facturas de todos'),
    ('facturas.cambiar_estado', 'Cambiar el estado de cualquier factura'),
    ('facturas.gestionar_todas', 'Editar y borrar facturas de otros'),
    ('configuracion.gestionar', 'Cambiar la configuración del grupo y los catálogos'),
    ('roles.gestionar', 'Crear cargos y editar sus permisos'),
    ('copia.descargar', 'Descargar copias de seguridad'),
    ('conservacion.gestionar', 'Eliminar los datos vencidos de educandos dados de baja'),
    ('registro.ver', 'Consultar el registro de accesos'),
    ('documentos.subir_propios', 'Entregar la documentación de inscripción de sus educandos'),
    ('documentos.revisar', 'Revisar la documentación de inscripción y entregarla en nombre de las familias');

-- El administrador tiene todos los permisos salvo los de tutor («sus» educandos), que
-- describen una relación y no una capacidad: con ellos vería el panel de tutor.
INSERT IGNORE INTO roles_permisos (codigo_rol, codigo_permiso)
SELECT 'administrador', codigo FROM permisos
WHERE codigo NOT IN ('educandos.ver_propios', 'asistencia.responder_propios', 'documentos.subir_propios');

INSERT IGNORE INTO roles_permisos (codigo_rol, codigo_permiso) VALUES
    ('scouter', 'usuarios.ver'),
    ('scouter', 'educandos.ver_todos'),
    ('scouter', 'educandos.gestionar'),
    ('scouter', 'eventos.ver'),
    ('scouter', 'eventos.gestionar'),
    ('scouter', 'asistencia.ver_todas'),
    ('scouter', 'documentos.ver_todos'),
    ('scouter', 'galeria.ver'),
    ('scouter', 'galeria.subir'),
    ('scouter', 'facturas.subir_propias'),
    ('scouter', 'documentos.revisar'),
    ('tutor', 'educandos.ver_propios'),
    ('tutor', 'eventos.ver'),
    ('tutor', 'asistencia.responder_propios'),
    ('tutor', 'galeria.ver'),
    ('tutor', 'documentos.subir_propios'),
    ('tesoreria', 'facturas.ver_todas'),
    ('tesoreria', 'facturas.cambiar_estado');

CREATE TABLE IF NOT EXISTS educandos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(150) NOT NULL,
    DNI CHAR(9) NOT NULL UNIQUE,
    naz DATE,
    -- Fecha en la que deja el grupo (NULL = activo). Sus datos se borran
    -- cuando pasa el plazo de conservación de la configuración.
    fecha_baja DATE NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS facturas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    responsable_id INT UNSIGNED NOT NULL,
    numero_fact INT NOT NULL,
    CIF_prov VARCHAR(20) NOT NULL,
    fecha_emision DATETIME NOT NULL,
    fecha_compra DATETIME NOT NULL,
    concepto TEXT NOT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    -- E-05: pendiente → revisada → pagada. Solo facturas.cambiar_estado lo cambia.
    estado ENUM('pendiente', 'revisada', 'pagada') NOT NULL
        DEFAULT 'pendiente',
    imagen_factura VARCHAR(255) NULL,
    estado_cambiado_por INT UNSIGNED NULL,
    estado_cambiado_en DATETIME NULL,
    CONSTRAINT fk_facturas_responsable
        FOREIGN KEY (responsable_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_facturas_estado_cambiado_por
        FOREIGN KEY (estado_cambiado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios_educandos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    id_educando INT UNSIGNED NOT NULL,
    UNIQUE KEY unique_usuario_educando (id_usuario, id_educando),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_educando) REFERENCES educandos(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eventos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    id_responsable INT UNSIGNED,
    lugar VARCHAR(150),
    municipio VARCHAR(100),
    comunidad VARCHAR(100),
    tipo VARCHAR(50) NOT NULL,
    circular BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (id_responsable) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eventos_secciones (
    id_evento INT UNSIGNED NOT NULL,
    seccion VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_evento, seccion),
    FOREIGN KEY (id_evento) REFERENCES eventos(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS educandos_asistencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_educando INT UNSIGNED NOT NULL,
    id_evento INT UNSIGNED NOT NULL,
    estado ENUM('pendiente', 'si', 'no') NOT NULL DEFAULT 'pendiente',
    archivo_entrega VARCHAR(255),
    UNIQUE KEY unique_educando_asistencia (id_educando, id_evento),
    FOREIGN KEY (id_educando) REFERENCES educandos(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_evento) REFERENCES eventos(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Galería de fotografías (E-03). El archivo se guarda en storage/galeria/ con un
-- nombre aleatorio; la ronda y la carpeta se calculan a partir del evento.
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

-- Registro de accesos a documentación sensible (E-06). Solo se inserta desde PHP.
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

-- Documentación de inscripción (E-14).
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
    ('participacion', 'Circular de participación en actividades', 'participacion', 'circular1.pdf', FALSE, TRUE, 0),
    ('ficha_sanitaria', 'Ficha sanitaria', 'inscripcion', NULL, TRUE, TRUE, 10),
    ('hoja_inscripcion', 'Hoja de inscripción', 'inscripcion', NULL, TRUE, TRUE, 20),
    ('exclusion_responsabilidad', 'Exclusión de responsabilidad', 'inscripcion', NULL, TRUE, TRUE, 30),
    ('autorizacion_ausencia', 'Autorización para ausentarse de actividades', 'inscripcion', NULL, TRUE, TRUE, 40);
