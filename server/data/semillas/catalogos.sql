-- Catálogos iniciales: tipos de evento, secciones (con sus edades) y categorías de factura.
-- Se puede ejecutar varias veces (INSERT IGNORE). Lo usa el instalador; schema.sql incluye lo mismo.

INSERT IGNORE INTO tipos_evento (codigo, nombre, activo, orden) VALUES
    ('reunión de familias', 'Reunión de familias', TRUE, 10),
    ('sabado', 'Sábado', TRUE, 20),
    ('festival', 'Festival', TRUE, 30),
    ('excursion', 'Excursión', TRUE, 40),
    ('acampada', 'Acampada', TRUE, 50),
    ('otro', 'Otro', TRUE, 60);

INSERT IGNORE INTO secciones
    (codigo, nombre, edad_minima, edad_maxima, activo, orden)
VALUES
    ('colonia', 'Colonia', 5, 6, TRUE, 10),
    ('manada', 'Manada', 7, 9, TRUE, 20),
    ('tropa', 'Tropa', 10, 12, TRUE, 30),
    ('posta', 'Posta', 13, 15, TRUE, 40),
    ('rutas', 'Rutas', 16, 19, TRUE, 50);

INSERT IGNORE INTO categorias_factura (codigo, nombre, activo, orden) VALUES
    ('envíos', 'Envíos', TRUE, 10),
    ('comida', 'Comida', TRUE, 20),
    ('material', 'Material', TRUE, 30),
    ('gasolina', 'Gasolina', TRUE, 40),
    ('otros', 'Otros', TRUE, 50),
    ('agua', 'Agua', TRUE, 60),
    ('luz', 'Luz', TRUE, 70),
    ('gas', 'Gas', TRUE, 80);
