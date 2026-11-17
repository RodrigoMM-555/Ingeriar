-- E-05: estados de factura pendiente → revisada → pagada (se elimina «rechazada»)
-- y registro de quién cambió el estado y cuándo.

-- Antes de migrar, comprobar cuántas facturas rechazadas hay (pasarán a pendiente):
--   SELECT COUNT(*) FROM facturas WHERE estado = 'rechazada';

-- 1. Se amplía el ENUM para que convivan los valores antiguos y los nuevos.
ALTER TABLE facturas MODIFY COLUMN estado
    ENUM('pendiente', 'revisada', 'pagada', 'rechazada') NOT NULL DEFAULT 'pendiente';

-- 2. Las rechazadas vuelven a pendiente.
UPDATE facturas SET estado = 'pendiente' WHERE estado = 'rechazada';

-- 3. Se deja el ENUM definitivo.
ALTER TABLE facturas MODIFY COLUMN estado
    ENUM('pendiente', 'revisada', 'pagada') NOT NULL DEFAULT 'pendiente';

-- 4. Quién cambió el estado por última vez y cuándo.
ALTER TABLE facturas
    ADD COLUMN estado_cambiado_por INT UNSIGNED NULL,
    ADD COLUMN estado_cambiado_en DATETIME NULL,
    ADD CONSTRAINT fk_facturas_estado_cambiado_por
        FOREIGN KEY (estado_cambiado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE;
