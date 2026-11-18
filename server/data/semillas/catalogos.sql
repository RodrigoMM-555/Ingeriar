-- Catálogos iniciales: tipos de evento, secciones (con sus edades) y categorías de factura.
-- Se puede ejecutar varias veces (INSERT IGNORE). Lo usa el instalador; schema.sql incluye lo mismo.

INSERT IGNORE INTO tipos_evento
    (codigo, nombre, color, hora_inicio, hora_fin, apertura_dias, cierre_horas, activo, orden)
VALUES
    ('reunión de familias', 'Reunión de familias', '#2563eb', '18:00', '19:30', 30, 0, TRUE, 10),
    ('sabado', 'Sábado', '#7c3aed', '10:30', '13:30', 14, 12, TRUE, 20),
    ('festival', 'Festival', '#db2777', '17:00', '20:00', 30, 24, TRUE, 30),
    ('excursion', 'Excursión', '#059669', '09:00', '18:00', 21, 48, TRUE, 40),
    ('acampada', 'Acampada', '#d97706', '18:00', NULL, 30, 72, TRUE, 50),
    ('otro', 'Otro', '#64748b', NULL, NULL, 30, 24, TRUE, 60);

INSERT IGNORE INTO secciones
    (codigo, nombre, color, edad_minima, edad_maxima, activo, orden)
VALUES
    ('colonia', 'Colonia', '#ef6c00', 5, 6, TRUE, 10),
    ('manada', 'Manada', '#f2b705', 7, 9, TRUE, 20),
    ('tropa', 'Tropa', '#1e63c6', 10, 12, TRUE, 30),
    ('posta', 'Posta', '#d32f2f', 13, 15, TRUE, 40),
    ('rutas', 'Rutas', '#2e7d32', 16, 19, TRUE, 50);

INSERT IGNORE INTO categorias_factura (codigo, nombre, activo, orden) VALUES
    ('envíos', 'Envíos', TRUE, 10),
    ('comida', 'Comida', TRUE, 20),
    ('material', 'Material', TRUE, 30),
    ('gasolina', 'Gasolina', TRUE, 40),
    ('otros', 'Otros', TRUE, 50),
    ('agua', 'Agua', TRUE, 60),
    ('luz', 'Luz', TRUE, 70),
    ('gas', 'Gas', TRUE, 80);
