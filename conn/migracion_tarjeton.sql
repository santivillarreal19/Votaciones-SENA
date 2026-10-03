-- ⚠️  NO USAR ESTE ARCHIVO EN OTRO SERVIDOR (ej. cPanel).
--     Los nombres de las llaves foráneas de aquí abajo son los de la base
--     local y en otros servidores cambian, así que la migración se aplica
--     A MEDIAS y deja la tabla rota (error "Duplicate entry '0' for key 'PRIMARY'").
--     Usa en su lugar:  conn/aplicar_migracion.php  (detecta los nombres reales,
--     se puede repetir sin riesgo y repara estados a medias).
--
-- ============================================================
-- Migración: permitir repetir el número de tarjetón entre años
-- ============================================================
-- Problema: id_candidato era a la vez el número de tarjetón Y la llave
-- primaria, por lo que MySQL lo obligaba a ser único de forma global.
-- Un candidato de 2026 no podía usar el mismo tarjetón que uno de 2025.
--
-- Solución: separar el tarjetón de la llave primaria. El tarjetón pasa a
-- ser una columna normal, y su unicidad por año se valida en PHP
-- (RegistrarCandidato en model/functions.php).
--
-- Ejecutar UNA sola vez por base de datos:
--   mysql -u root eleccion_representantes < conn/migracion_tarjeton.sql
-- ============================================================

-- 1. Nueva columna para el tarjetón, conservando los números actuales
ALTER TABLE candidatos ADD COLUMN tarjeton BIGINT NOT NULL DEFAULT 0 AFTER id_candidato;
UPDATE candidatos SET tarjeton = id_candidato;

-- 2. id_candidato pasa a ser un id interno autogenerado.
--    Hay que soltar la llave foránea de votacion para poder modificarlo.
ALTER TABLE votacion DROP FOREIGN KEY votacion_ibfk_4;
ALTER TABLE candidatos MODIFY id_candidato BIGINT NOT NULL AUTO_INCREMENT;
ALTER TABLE votacion ADD CONSTRAINT votacion_ibfk_4
    FOREIGN KEY (id_candidato) REFERENCES candidatos (id_candidato)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- 3. Índice para acelerar la validación de tarjetón por jornada
CREATE INDEX ix_candidatos_tarjeton ON candidatos (tarjeton, id_jornada);
