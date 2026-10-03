-- ============================================================
--  MIGRACIÓN COMPLETA - versión para phpMyAdmin (cPanel)
-- ============================================================
--  Aplica todos los cambios de base de datos del sistema:
--    · eventos    -> columnas anio y activo
--    · estado     -> Habilitado / Inducción / Deserción
--    · aprendiz   -> documento único
--    · formacion  -> ficha separada de la llave primaria
--    · candidatos -> tarjetón separado del la llave primaria
--
--  A diferencia de migracion_ficha.sql y migracion_tarjeton.sql,
--  este script DESCUBRE SOLO los nombres reales de las llaves
--  foráneas, así que funciona en cualquier servidor.
--
--  Se puede ejecutar varias veces sin problema: lo que ya está
--  aplicado simplemente se omite.
--
--  CÓMO USARLO:
--    phpMyAdmin -> selecciona tu base -> pestaña SQL ->
--    pega TODO este archivo -> Continuar
-- ============================================================

-- Evita que MySQL intente "resecuenciar" las filas que tengan id = 0
-- al convertir la columna en AUTO_INCREMENT (error #1062).
SET @@session.sql_mode = CONCAT(@@session.sql_mode, ',NO_AUTO_VALUE_ON_ZERO');


-- ############################################################
-- 1) EVENTOS: columnas anio y activo
-- ############################################################

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'anio');
SET @sql := IF(@existe = 0, 'ALTER TABLE eventos ADD COLUMN anio INT NOT NULL DEFAULT 0', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'activo');
SET @sql := IF(@existe = 0, 'ALTER TABLE eventos ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 0', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE eventos SET anio = YEAR(fecha_inicio) WHERE anio = 0;

-- Si no hay ningún año activo, activa el más reciente
SET @activos := (SELECT COUNT(*) FROM eventos WHERE activo = 1);
SET @sql := IF(@activos = 0,
    'UPDATE eventos SET activo = 1 ORDER BY anio DESC, id_evento DESC LIMIT 1', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ############################################################
-- 2) ESTADO: solo "Habilitado" (id 1) puede votar
-- ############################################################

-- Se trabaja por id (no por nombre) para que no se dupliquen los estados
-- aunque los acentos estén mal guardados en la base.
--   id 1 = Habilitado (ÚNICO estado que permite votar)
--   id 2 = Inducción      id 3 = Deserción
INSERT INTO estado (id_estado, estados) VALUES
    (1, 'Habilitado'),
    (2, 'Inducción'),
    (3, 'Deserción')
ON DUPLICATE KEY UPDATE estados = VALUES(estados);


-- ############################################################
-- 3) APRENDIZ: documento único
-- ############################################################
-- OJO: si hay documentos repetidos, el índice NO se crea y este
-- bloque no borra nada. Para eliminarlos (conservando el registro
-- más antiguo de cada documento) descomenta el DELETE de abajo.

-- DELETE a1 FROM aprendiz a1
-- JOIN aprendiz a2 ON a1.documento = a2.documento AND a1.id_aprendiz > a2.id_aprendiz
-- WHERE a1.id_rol = 2;

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aprendiz'
                  AND INDEX_NAME = 'ux_aprendiz_documento');
SET @dup := (SELECT COUNT(*) FROM (SELECT documento FROM aprendiz
                                   GROUP BY documento HAVING COUNT(*) > 1) d);
SET @sql := IF(@existe = 0 AND @dup = 0,
    'CREATE UNIQUE INDEX ux_aprendiz_documento ON aprendiz (documento)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ############################################################
-- 4) FORMACION: separar la ficha de la llave primaria
-- ############################################################

-- 4.1 Columna ficha
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'formacion' AND COLUMN_NAME = 'ficha');
SET @sql := IF(@existe = 0,
    'ALTER TABLE formacion ADD COLUMN ficha BIGINT NOT NULL DEFAULT 0 AFTER id_formacion', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE formacion SET ficha = id_formacion WHERE ficha = 0 AND id_formacion > 0;

-- 4.2 Limpiar la fila con id 0 que dejó el INSERT fallido.
--     Si duplica una ficha ya registrada en esa jornada, se borra;
--     si no, se le asigna un id válido. En ambos casos NO puede quedar
--     ninguna fila en 0 antes del ALTER, o MySQL falla con el error #1062.
DELETE cero FROM formacion cero
JOIN formacion otra
  ON otra.ficha = cero.ficha AND otra.id_jornada = cero.id_jornada AND otra.id_formacion <> 0
WHERE cero.id_formacion = 0;

SET @nuevo := (SELECT MAX(id_formacion) + 1 FROM formacion);
UPDATE formacion SET id_formacion = @nuevo WHERE id_formacion = 0;

-- 4.3 Soltar las llaves foráneas que apunten a formacion (nombres reales)
SET @drops := (SELECT GROUP_CONCAT(CONCAT('DROP FOREIGN KEY `', CONSTRAINT_NAME, '`') SEPARATOR ', ')
               FROM information_schema.KEY_COLUMN_USAGE
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'aprendiz'
                 AND REFERENCED_TABLE_NAME = 'formacion');
SET @sql := IF(@drops IS NULL, 'DO 0', CONCAT('ALTER TABLE aprendiz ', @drops));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @drops := (SELECT GROUP_CONCAT(CONCAT('DROP FOREIGN KEY `', CONSTRAINT_NAME, '`') SEPARATOR ', ')
               FROM information_schema.KEY_COLUMN_USAGE
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'candidatos'
                 AND REFERENCED_TABLE_NAME = 'formacion');
SET @sql := IF(@drops IS NULL, 'DO 0', CONCAT('ALTER TABLE candidatos ', @drops));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4.4 id_formacion pasa a ser id interno autogenerado
SET @esauto := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'formacion'
                  AND COLUMN_NAME = 'id_formacion' AND EXTRA LIKE '%auto_increment%');
SET @sql := IF(@esauto = 0,
    'ALTER TABLE formacion MODIFY id_formacion BIGINT NOT NULL AUTO_INCREMENT', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4.5 Restaurar las llaves foráneas
SET @tiene := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'aprendiz'
                 AND REFERENCED_TABLE_NAME = 'formacion');
SET @sql := IF(@tiene = 0,
    'ALTER TABLE aprendiz ADD CONSTRAINT fk_aprendiz_formacion FOREIGN KEY (id_formacion) REFERENCES formacion (id_formacion) ON DELETE CASCADE ON UPDATE CASCADE', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @tiene := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'candidatos'
                 AND REFERENCED_TABLE_NAME = 'formacion');
SET @sql := IF(@tiene = 0,
    'ALTER TABLE candidatos ADD CONSTRAINT fk_candidatos_formacion FOREIGN KEY (id_formacion) REFERENCES formacion (id_formacion) ON DELETE CASCADE ON UPDATE CASCADE', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4.6 Unicidad real: misma ficha no se repite dentro de la misma jornada
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'formacion'
                  AND INDEX_NAME = 'ux_formacion_ficha_jornada');
SET @dup := (SELECT COUNT(*) FROM (SELECT ficha, id_jornada FROM formacion
                                   GROUP BY ficha, id_jornada HAVING COUNT(*) > 1) d);
SET @sql := IF(@existe = 0 AND @dup = 0,
    'CREATE UNIQUE INDEX ux_formacion_ficha_jornada ON formacion (ficha, id_jornada)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ############################################################
-- 5) CANDIDATOS: separar el tarjetón de la llave primaria
-- ############################################################

-- 5.1 Columna tarjeton
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'candidatos' AND COLUMN_NAME = 'tarjeton');
SET @sql := IF(@existe = 0,
    'ALTER TABLE candidatos ADD COLUMN tarjeton BIGINT NOT NULL DEFAULT 0 AFTER id_candidato', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE candidatos SET tarjeton = id_candidato WHERE tarjeton = 0 AND id_candidato > 0;

-- 5.2 Misma limpieza que en formacion: ninguna fila puede quedar con id 0
DELETE cero FROM candidatos cero
JOIN candidatos otra
  ON otra.tarjeton = cero.tarjeton AND otra.id_jornada = cero.id_jornada AND otra.id_candidato <> 0
WHERE cero.id_candidato = 0;

SET @nuevo := (SELECT MAX(id_candidato) + 1 FROM candidatos);
UPDATE candidatos SET id_candidato = @nuevo WHERE id_candidato = 0;

-- 5.3 Soltar las llaves foráneas que apunten a candidatos
SET @drops := (SELECT GROUP_CONCAT(CONCAT('DROP FOREIGN KEY `', CONSTRAINT_NAME, '`') SEPARATOR ', ')
               FROM information_schema.KEY_COLUMN_USAGE
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'votacion'
                 AND REFERENCED_TABLE_NAME = 'candidatos');
SET @sql := IF(@drops IS NULL, 'DO 0', CONCAT('ALTER TABLE votacion ', @drops));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5.4 id_candidato pasa a ser id interno autogenerado
SET @esauto := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'candidatos'
                  AND COLUMN_NAME = 'id_candidato' AND EXTRA LIKE '%auto_increment%');
SET @sql := IF(@esauto = 0,
    'ALTER TABLE candidatos MODIFY id_candidato BIGINT NOT NULL AUTO_INCREMENT', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5.5 Restaurar la llave foránea de votacion
SET @tiene := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'votacion'
                 AND REFERENCED_TABLE_NAME = 'candidatos');
SET @sql := IF(@tiene = 0,
    'ALTER TABLE votacion ADD CONSTRAINT fk_votacion_candidato FOREIGN KEY (id_candidato) REFERENCES candidatos (id_candidato) ON DELETE CASCADE ON UPDATE CASCADE', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5.6 Unicidad del tarjetón dentro de la misma jornada
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'candidatos'
                  AND INDEX_NAME = 'ux_candidatos_tarjeton_jornada');
SET @dup := (SELECT COUNT(*) FROM (SELECT tarjeton, id_jornada FROM candidatos
                                   GROUP BY tarjeton, id_jornada HAVING COUNT(*) > 1) d);
SET @sql := IF(@existe = 0 AND @dup = 0,
    'CREATE UNIQUE INDEX ux_candidatos_tarjeton_jornada ON candidatos (tarjeton, id_jornada)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ############################################################
-- 6) VERIFICACIÓN (debe salir todo en 'OK')
-- ############################################################

SELECT
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='eventos' AND COLUMN_NAME='anio') > 0, 'OK', 'FALTA') AS eventos_anio,
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='formacion' AND COLUMN_NAME='ficha') > 0, 'OK', 'FALTA') AS formacion_ficha,
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='formacion' AND COLUMN_NAME='id_formacion' AND EXTRA LIKE '%auto_increment%') > 0, 'OK', 'FALTA') AS formacion_autoinc,
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='candidatos' AND COLUMN_NAME='tarjeton') > 0, 'OK', 'FALTA') AS candidatos_tarjeton,
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='candidatos' AND COLUMN_NAME='id_candidato' AND EXTRA LIKE '%auto_increment%') > 0, 'OK', 'FALTA') AS candidatos_autoinc,
  (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='formacion') AS fks_formacion,
  (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='candidatos') AS fks_candidatos;
