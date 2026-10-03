-- ⚠️  NO USAR ESTE ARCHIVO EN OTRO SERVIDOR (ej. cPanel).
--     Los nombres de las llaves foráneas de aquí abajo son los de la base
--     local y en otros servidores cambian, así que la migración se aplica
--     A MEDIAS y deja la tabla rota (error "Duplicate entry '0' for key 'PRIMARY'").
--     Usa en su lugar:  conn/aplicar_migracion.php  (detecta los nombres reales,
--     se puede repetir sin riesgo y repara estados a medias).
--
-- ============================================================
-- Migración: permitir repetir el número de ficha entre años
-- ============================================================
-- Problema: id_formacion era a la vez el número de ficha Y la llave
-- primaria, por lo que MySQL lo obligaba a ser único de forma global.
-- Una ficha que participó en 2025 no podía registrarse de nuevo para
-- 2026, aunque sus aprendices sí puedan votar este año.
--
-- Solución: separar la ficha de la llave primaria. La ficha pasa a ser
-- una columna normal y la unicidad real es (ficha + jornada), así el
-- mismo número puede existir en jornadas de años distintos.
--
-- Ejecutar UNA sola vez por base de datos:
--   mysql -u root eleccion_representantes < conn/migracion_ficha.sql
-- ============================================================

-- 1. Nueva columna para el número de ficha, conservando los actuales
ALTER TABLE formacion ADD COLUMN ficha BIGINT NOT NULL DEFAULT 0 AFTER id_formacion;
UPDATE formacion SET ficha = id_formacion;

-- 2. id_formacion pasa a ser un id interno autogenerado.
--    Hay que soltar las llaves foráneas que lo referencian para modificarlo.
ALTER TABLE aprendiz DROP FOREIGN KEY aprendiz_ibfk_5;
ALTER TABLE candidatos DROP FOREIGN KEY candidatos_ibfk_2;

ALTER TABLE formacion MODIFY id_formacion BIGINT NOT NULL AUTO_INCREMENT;

ALTER TABLE aprendiz ADD CONSTRAINT aprendiz_ibfk_5
    FOREIGN KEY (id_formacion) REFERENCES formacion (id_formacion)
    ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE candidatos ADD CONSTRAINT candidatos_ibfk_2
    FOREIGN KEY (id_formacion) REFERENCES formacion (id_formacion)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- 3. Unicidad real: la misma ficha no puede repetirse dentro de la misma
--    jornada, pero sí en jornadas distintas (o sea, en años distintos).
CREATE UNIQUE INDEX ux_formacion_ficha_jornada ON formacion (ficha, id_jornada);
