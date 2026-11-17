USE ingeriar;

-- 30 usuarios de prueba. La contraseña de cada uno es ingeriar-<numero>
-- (por ejemplo, usuario1@ingeriar.test / ingeriar-1).
-- Los hashes se han generado con password_hash() de PHP (bcrypt, con sal).
INSERT INTO usuarios
    (nombre, apellidos, email, telefono, `contraseña`)
VALUES
    ('Usuario 1', 'Apellido 1', 'usuario1@ingeriar.test', '600000001', '$2y$12$a27ESG0Hk1cWm1LLoVyga.o9KGC6BiI225xUCtEGRKjFRXMUeZxmS'),
    ('Usuario 2', 'Apellido 2', 'usuario2@ingeriar.test', '600000002', '$2y$12$9Y5tck53fKEd0JA4PwyVkOcOlX6DHm.vhNQoJLXqer2j.Mv68EalC'),
    ('Usuario 3', 'Apellido 3', 'usuario3@ingeriar.test', '600000003', '$2y$12$0MFeGb958XARr8jfSdldg.yUW6A8T5s9/SpimAb4odm2iS0Auvx5.'),
    ('Usuario 4', 'Apellido 4', 'usuario4@ingeriar.test', '600000004', '$2y$12$5C/nP4RUEEun9bNBlnDOjuYQTZWKLwHJdGPwD6zb5t1NsjA6HgaMy'),
    ('Usuario 5', 'Apellido 5', 'usuario5@ingeriar.test', '600000005', '$2y$12$Lidozffc7bztPlxrPl59heHNm1wciCO28gMtG/qE4PxS11ESLioZS'),
    ('Usuario 6', 'Apellido 6', 'usuario6@ingeriar.test', '600000006', '$2y$12$./eKsoeO4fuPY08ESVhrWefgm6bVwAkPXxQ1.okOxLZwCrr3.xbwW'),
    ('Usuario 7', 'Apellido 7', 'usuario7@ingeriar.test', '600000007', '$2y$12$003FqjEcx3OU0A72kC5nf.W3Ntd74eFPVmjj.dqzzbrDDtwjAvvEq'),
    ('Usuario 8', 'Apellido 8', 'usuario8@ingeriar.test', '600000008', '$2y$12$ZmiMJTIPUmd4oDep/mMfYuj74SduScINW9sYUAorxPvWXI0iXuu1a'),
    ('Usuario 9', 'Apellido 9', 'usuario9@ingeriar.test', '600000009', '$2y$12$a8RdTUKRzneSWSRGmZXrOOvrE7ksFgI/AypPGgS6ImuWwRc6FY9Ga'),
    ('Usuario 10', 'Apellido 10', 'usuario10@ingeriar.test', '600000010', '$2y$12$B53mW8SU2hpev7uMH/dKGuwkWy9IIEJerZ01xFBXAFKIXsbi8Ifvy'),
    ('Usuario 11', 'Apellido 11', 'usuario11@ingeriar.test', '600000011', '$2y$12$ZiiYIvZmNipxuDDXlJdfYe5lanP568kFHqpebO7wvrKu5NqIvUVUu'),
    ('Usuario 12', 'Apellido 12', 'usuario12@ingeriar.test', '600000012', '$2y$12$8PEQXTw33KVPPSECJEtrQuDX3UZ4lhVkVz1mJxAIXO0UrEbTq/l2W'),
    ('Usuario 13', 'Apellido 13', 'usuario13@ingeriar.test', '600000013', '$2y$12$CtPZcLlzzjdXQi0k3LBG9.3D6U.xWz45DQRwMgj5k9z2zJ/dQZsFW'),
    ('Usuario 14', 'Apellido 14', 'usuario14@ingeriar.test', '600000014', '$2y$12$gW7ap/xyd/.crUc7iLrYeuFF..EwC1g/frNc1PTXHqSmYi4qHyvNy'),
    ('Usuario 15', 'Apellido 15', 'usuario15@ingeriar.test', '600000015', '$2y$12$MYA1qMsEBuj7F8SvSKLTNOXrNGvRG93CT289qRUt83UwKP3GI0kxS'),
    ('Usuario 16', 'Apellido 16', 'usuario16@ingeriar.test', '600000016', '$2y$12$FD.Hv76E2UNhUUtzeNfEge4P2659gnez6GhxiSq.QxSHNlwrjQDTa'),
    ('Usuario 17', 'Apellido 17', 'usuario17@ingeriar.test', '600000017', '$2y$12$I3Cw6QMf8uH8pYpKaNDfIOBVxwK401aKRtX/aQC0jixY4JuIKLK.2'),
    ('Usuario 18', 'Apellido 18', 'usuario18@ingeriar.test', '600000018', '$2y$12$TnP3P94YfFpr9jJX6OtfMO1P2M9fmyPmAu.Eu/jLXZ1ASqrkyYYmu'),
    ('Usuario 19', 'Apellido 19', 'usuario19@ingeriar.test', '600000019', '$2y$12$d8tT3J/AMsskFvl0Y1vSuOYrgJZuSUISC4a8H565P436xsIH8l5me'),
    ('Usuario 20', 'Apellido 20', 'usuario20@ingeriar.test', '600000020', '$2y$12$6Xr4WHSHBVDjaGRADgV44usQqtDl7aH.KQeJKMwj1KS0Jjmo1LRGy'),
    ('Usuario 21', 'Apellido 21', 'usuario21@ingeriar.test', '600000021', '$2y$12$gNTxW495TeLmHSrlSilKbuqPIunTzv7nLFDV9U616pkMuJQbsIXVi'),
    ('Usuario 22', 'Apellido 22', 'usuario22@ingeriar.test', '600000022', '$2y$12$UtcvBWsJPsdA8PjOJRIn3OEjcSYoCTK80gv7Yhg.1dpQ5A5aPpdua'),
    ('Usuario 23', 'Apellido 23', 'usuario23@ingeriar.test', '600000023', '$2y$12$LgtiwxDl.4sCnMlaAlnBzOZR4AQQPFjQBb7cmZ6kGE5wUEhZG/CWa'),
    ('Usuario 24', 'Apellido 24', 'usuario24@ingeriar.test', '600000024', '$2y$12$FGdQpmDJF8KCbNDbyUFMKOyQbarSLwFylcKN/LgjPPU6WzED3fZ6u'),
    ('Usuario 25', 'Apellido 25', 'usuario25@ingeriar.test', '600000025', '$2y$12$6EdV3AT1GZNTYWMQLHBRBuFHnr1JPvRYiqqo6krwy4xqzTiljMhca'),
    ('Usuario 26', 'Apellido 26', 'usuario26@ingeriar.test', '600000026', '$2y$12$HVAgOP/GtVLmfCG3aUZhpuGTTcokHOW9krxNhZy0Yv5TpaTN22QZq'),
    ('Usuario 27', 'Apellido 27', 'usuario27@ingeriar.test', '600000027', '$2y$12$NPLHLWc/NfI.bMKy2/Ke3OnyWZiSL/yu8AU.9OnDg6CWsrb5/dLAi'),
    ('Usuario 28', 'Apellido 28', 'usuario28@ingeriar.test', '600000028', '$2y$12$PKZIlZEFkNdEKNJ07W3xCOjrFo8DK5hJyMBM.jVAp2NB.R0PsRzdC'),
    ('Usuario 29', 'Apellido 29', 'usuario29@ingeriar.test', '600000029', '$2y$12$7dGuvp40ZipEM4OI8oBgWuixPtTbBxhIsBReNDf1alspb5u1vPe6.'),
    ('Usuario 30', 'Apellido 30', 'usuario30@ingeriar.test', '600000030', '$2y$12$LmyPpc/pf1N8ss9FDwhWIeRTfohwbnjVNixozIxRjhVlwMGqTXW3C');

-- Cargo básico de cada usuario (E-13): 1, 4, 7… administradores; 2, 5, 8… monitores;
-- 3, 6, 9… tutores. Los ids coinciden porque la tabla se acaba de crear.
INSERT INTO usuarios_roles (id_usuario, codigo_rol) VALUES
    (1, 'administrador'),
    (2, 'scouter'),
    (3, 'tutor'),
    (4, 'administrador'),
    (5, 'scouter'),
    (6, 'tutor'),
    (7, 'administrador'),
    (8, 'scouter'),
    (9, 'tutor'),
    (10, 'administrador'),
    (11, 'scouter'),
    (12, 'tutor'),
    (13, 'administrador'),
    (14, 'scouter'),
    (15, 'tutor'),
    (16, 'administrador'),
    (17, 'scouter'),
    (18, 'tutor'),
    (19, 'administrador'),
    (20, 'scouter'),
    (21, 'tutor'),
    (22, 'administrador'),
    (23, 'scouter'),
    (24, 'tutor'),
    (25, 'administrador'),
    (26, 'scouter'),
    (27, 'tutor'),
    (28, 'administrador'),
    (29, 'scouter'),
    (30, 'tutor');

-- Cargo adicional de ejemplo: el usuario 2 es monitor y lleva la tesorería.
INSERT INTO usuarios_roles (id_usuario, codigo_rol) VALUES (2, 'tesoreria');

-- 100 educandos con DNI y fechas de nacimiento únicas.
INSERT INTO educandos (nombre, apellidos, DNI, naz)
WITH RECURSIVE numeros AS (
    SELECT 1 AS numero
    UNION ALL
    SELECT numero + 1 FROM numeros WHERE numero < 100
)
SELECT
    CONCAT('Educando ', numero),
    CONCAT('Familia ', numero),
    CONCAT(LPAD(numero, 8, '0'), CHAR(65 + MOD(numero - 1, 26))),
    DATE_ADD('2010-01-01', INTERVAL MOD(numero * 37, 3000) DAY)
FROM numeros;

-- Cada educando queda vinculado a un tutor o scouter.
INSERT INTO usuarios_educandos (id_usuario, id_educando)
WITH RECURSIVE numeros AS (
    SELECT 1 AS numero
    UNION ALL
    SELECT numero + 1 FROM numeros WHERE numero < 100
)
SELECT 3 + MOD(numero - 1, 28), numero
FROM numeros;

-- 40 eventos repartidos durante 2026 y 2027.
INSERT INTO eventos
    (nombre, fecha_inicio, fecha_fin, id_responsable, lugar, municipio, comunidad,
     tipo, circular)
WITH RECURSIVE numeros AS (
    SELECT 1 AS numero
    UNION ALL
    SELECT numero + 1 FROM numeros WHERE numero < 40
)
SELECT
    CONCAT('Actividad programada ', numero),
    DATE_ADD('2026-01-10 09:00:00', INTERVAL numero * 14 DAY),
    DATE_ADD('2026-01-10 18:00:00', INTERVAL numero * 14 DAY),
    2 + MOD(numero - 1, 28),
    CONCAT('Centro scout ', MOD(numero - 1, 8) + 1),
    CONCAT('Municipio ', MOD(numero - 1, 12) + 1),
    CONCAT('Comunidad ', MOD(numero - 1, 5) + 1),
    CASE MOD(numero, 5)
        WHEN 1 THEN 'Campamento'
        WHEN 2 THEN 'Taller'
        WHEN 3 THEN 'Excursión'
        WHEN 4 THEN 'Convivencia'
        ELSE 'Actividad'
    END,
    MOD(numero, 2) = 0
FROM numeros;

-- 80 facturas repartidas entre los 10 monitores (usuarios 2, 5, 8… 29): solo los
-- usuarios con facturas.subir_propias pueden ser responsables.
INSERT INTO facturas
    (responsable_id, numero_fact, CIF_prov, fecha_emision, fecha_compra,
     concepto, precio, categoria, estado)
WITH RECURSIVE numeros AS (
    SELECT 1 AS numero
    UNION ALL
    SELECT numero + 1 FROM numeros WHERE numero < 80
)
SELECT
    2 + 3 * MOD(numero - 1, 10),
    20260000 + numero,
    CONCAT('B', LPAD(numero, 8, '0')),
    DATE_ADD('2026-01-01 10:00:00', INTERVAL numero * 3 DAY),
    DATE_ADD('2026-01-02 10:00:00', INTERVAL numero * 3 DAY),
    CONCAT('Material para actividad ', numero),
    ROUND(15 + (numero * 13.75), 2),
    CASE MOD(numero, 4)
        WHEN 1 THEN 'material'
        WHEN 2 THEN 'gasolina'
        WHEN 3 THEN 'comida'
        ELSE 'otros'
    END,
    CASE MOD(numero, 3)
        WHEN 1 THEN 'pendiente'
        WHEN 2 THEN 'pagada'
        ELSE 'revisada'
    END
FROM numeros;

-- 160 asistencias de prueba con combinaciones únicas de usuario y evento.
INSERT INTO educandos_asistencias
    (id_educando, id_evento, estado, archivo_entrega)
WITH RECURSIVE numeros AS (
    SELECT 1 AS numero
    UNION ALL
    SELECT numero + 1 FROM numeros WHERE numero < 160
)
SELECT
    1 + MOD(numero - 1, 100),
    1 + MOD(numero - 1, 40),
    CASE MOD(numero, 3)
        WHEN 1 THEN 'si'
        WHEN 2 THEN 'no'
        ELSE 'pendiente'
    END,
    NULL
FROM numeros;
