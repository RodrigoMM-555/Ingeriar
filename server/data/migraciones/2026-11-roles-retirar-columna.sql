-- E-13 (paso 2 de 2): retirar usuarios.rol. Ejecutar solo después de 2026-11-roles.sql
-- y de comprobar que las pruebas pasan: a partir de aquí el cargo está en usuarios_roles.
ALTER TABLE usuarios DROP COLUMN rol;
