<?php
/* ============================================================
   Migración de ids internos (formacion y candidatos)
   ------------------------------------------------------------
   Aplica, de forma SEGURA y REPETIBLE, los cambios que permiten:
     - repetir el número de FICHA en años electorales distintos
     - repetir el número de TARJETÓN en años electorales distintos

   A diferencia de los archivos .sql, este script:
     - detecta los nombres reales de las llaves foráneas
       (varían entre servidores)
     - se puede ejecutar varias veces sin romper nada
     - repara un estado aplicado a medias
     - corrige filas que quedaron con id = 0 por un INSERT fallido

   USO: inicia sesión como administrador y abre en el navegador
        https://tu-dominio/conn/aplicar_migracion.php
   ============================================================ */

function DescubrirLlavesForaneas($conn, $tabla, $columnaId)
{
    $baseDatos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT DATABASE() AS db"))['db'];

    $sql = "SELECT k.TABLE_NAME, k.COLUMN_NAME, k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS r
              ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
            WHERE k.CONSTRAINT_SCHEMA = '$baseDatos'
              AND k.REFERENCED_TABLE_NAME = '$tabla'
              AND k.REFERENCED_COLUMN_NAME = '$columnaId'";

    $llaves = [];
    $res = mysqli_query($conn, $sql);
    while ($fila = mysqli_fetch_assoc($res)) {
        $llaves[] = $fila;
    }

    return $llaves;
}

function MigrarTablaAId($conn, $tabla, $columnaId, $columnaNueva, $columnaUnicaExtra, $nombreIndice)
{
    $mensajes = [];
    $baseDatos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT DATABASE() AS db"))['db'];

    // --- 1. ¿Existe ya la columna nueva? ---
    $existeColumna = mysqli_num_rows(mysqli_query($conn,
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '$baseDatos' AND TABLE_NAME = '$tabla'
           AND COLUMN_NAME = '$columnaNueva'")) > 0;

    if (!$existeColumna) {
        mysqli_query($conn, "ALTER TABLE `$tabla` ADD COLUMN `$columnaNueva` BIGINT NOT NULL DEFAULT 0 AFTER `$columnaId`");
        mysqli_query($conn, "UPDATE `$tabla` SET `$columnaNueva` = `$columnaId`");
        $mensajes[] = "[$tabla] Columna '$columnaNueva' creada y rellenada con los valores actuales de '$columnaId'.";
    } else {
        // Si quedó a medias, puede haber filas con la columna nueva en 0
        mysqli_query($conn, "UPDATE `$tabla` SET `$columnaNueva` = `$columnaId` WHERE `$columnaNueva` = 0 AND `$columnaId` > 0");
        $mensajes[] = "[$tabla] La columna '$columnaNueva' ya existía. Sin cambios.";
    }

    // --- 2. Reparar filas que quedaron con id = 0 por un INSERT fallido ---
    $filasCero = mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM `$tabla` WHERE `$columnaId` = 0"));
    if ($filasCero > 0) {
        // ¿La fila con id 0 repite una combinación que ya existe? Entonces es
        // basura del INSERT que falló y se puede borrar, siempre que nadie
        // la esté referenciando.
        $esDuplicada = mysqli_num_rows(mysqli_query($conn,
            "SELECT 1 FROM `$tabla` cero
             JOIN `$tabla` otra
               ON otra.`$columnaNueva` = cero.`$columnaNueva`
              AND otra.`$columnaUnicaExtra` = cero.`$columnaUnicaExtra`
              AND otra.`$columnaId` <> 0
             WHERE cero.`$columnaId` = 0")) > 0;

        $referencias = 0;
        foreach (DescubrirLlavesForaneas($conn, $tabla, $columnaId) as $fk) {
            $referencias += (int)mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COUNT(*) AS n FROM `{$fk['TABLE_NAME']}` WHERE `{$fk['COLUMN_NAME']}` = 0"))['n'];
        }

        if ($esDuplicada && $referencias === 0) {
            mysqli_query($conn, "DELETE FROM `$tabla` WHERE `$columnaId` = 0");
            $mensajes[] = "[$tabla] Se borró $filasCero fila(s) con $columnaId = 0: eran duplicados sin usar, dejados por un INSERT que falló.";
        } else {
            $maximo = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(`$columnaId`) AS m FROM `$tabla`"))['m'];
            mysqli_query($conn, "UPDATE `$tabla` SET `$columnaId` = " . ($maximo + 1) . " WHERE `$columnaId` = 0");
            $mensajes[] = "[$tabla] Se reparó $filasCero fila(s) que había(n) quedado con $columnaId = 0 (nuevo id: " . ($maximo + 1) . ").";
        }
    }

    // --- 3. ¿Ya es AUTO_INCREMENT? ---
    $esAuto = mysqli_num_rows(mysqli_query($conn,
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '$baseDatos' AND TABLE_NAME = '$tabla'
           AND COLUMN_NAME = '$columnaId' AND EXTRA LIKE '%auto_increment%'")) > 0;

    if ($esAuto) {
        $mensajes[] = "[$tabla] '$columnaId' ya era AUTO_INCREMENT. Sin cambios.";
    } else {
        // 3a. Descubrir las llaves foráneas REALES que apuntan a esta columna
        $fks = DescubrirLlavesForaneas($conn, $tabla, $columnaId);

        // 3b. Soltarlas
        foreach ($fks as $fk) {
            mysqli_query($conn, "ALTER TABLE `{$fk['TABLE_NAME']}` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
            $mensajes[] = "[$tabla] Llave foránea '{$fk['CONSTRAINT_NAME']}' de '{$fk['TABLE_NAME']}' soltada temporalmente.";
        }

        // 3c. Convertir a AUTO_INCREMENT.
        //     NO_AUTO_VALUE_ON_ZERO evita que MySQL intente "resecuenciar"
        //     filas con id = 0 y falle con el error #1062.
        mysqli_query($conn, "SET @@session.sql_mode = CONCAT(@@session.sql_mode, ',NO_AUTO_VALUE_ON_ZERO')");
        mysqli_query($conn, "ALTER TABLE `$tabla` MODIFY `$columnaId` BIGINT NOT NULL AUTO_INCREMENT");
        $mensajes[] = "[$tabla] '$columnaId' convertida a AUTO_INCREMENT.";

        // 3d. Recrearlas con las mismas reglas
        foreach ($fks as $fk) {
            mysqli_query($conn, "ALTER TABLE `{$fk['TABLE_NAME']}`
                ADD CONSTRAINT `{$fk['CONSTRAINT_NAME']}`
                FOREIGN KEY (`{$fk['COLUMN_NAME']}`) REFERENCES `$tabla` (`$columnaId`)
                ON DELETE {$fk['DELETE_RULE']} ON UPDATE {$fk['UPDATE_RULE']}");
            $mensajes[] = "[$tabla] Llave foránea '{$fk['CONSTRAINT_NAME']}' restaurada.";
        }
    }

    // --- 4. Índice único (columna nueva + jornada) ---
    $existeIndice = mysqli_num_rows(mysqli_query($conn,
        "SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = '$baseDatos' AND TABLE_NAME = '$tabla'
           AND INDEX_NAME = '$nombreIndice'")) > 0;

    if ($existeIndice) {
        $mensajes[] = "[$tabla] El índice '$nombreIndice' ya existía. Sin cambios.";
    } else {
        $duplicados = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS n FROM (
                SELECT `$columnaNueva`, `$columnaUnicaExtra`
                FROM `$tabla`
                GROUP BY `$columnaNueva`, `$columnaUnicaExtra`
                HAVING COUNT(*) > 1
             ) AS d"))['n'];

        if ((int)$duplicados > 0) {
            $mensajes[] = "[$tabla] AVISO: hay $duplicados combinación(es) repetida(s) de ($columnaNueva + $columnaUnicaExtra). No se creó el índice único; revísalas primero.";
        } else {
            mysqli_query($conn, "CREATE UNIQUE INDEX `$nombreIndice` ON `$tabla` (`$columnaNueva`, `$columnaUnicaExtra`)");
            $mensajes[] = "[$tabla] Índice único '$nombreIndice' creado sobre ($columnaNueva, $columnaUnicaExtra).";
        }
    }

    return $mensajes;
}

function MigrarEventos($conn)
{
    $mensajes = [];
    $baseDatos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT DATABASE() AS db"))['db'];

    foreach ([['anio', "INT NOT NULL DEFAULT 0"], ['activo', "TINYINT(1) NOT NULL DEFAULT 0"]] as $columna) {
        [$nombre, $definicion] = $columna;
        $existe = mysqli_num_rows(mysqli_query($conn,
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = '$baseDatos' AND TABLE_NAME = 'eventos' AND COLUMN_NAME = '$nombre'")) > 0;

        if ($existe) {
            $mensajes[] = "[eventos] La columna '$nombre' ya existía. Sin cambios.";
        } else {
            mysqli_query($conn, "ALTER TABLE `eventos` ADD COLUMN `$nombre` $definicion");
            $mensajes[] = "[eventos] Columna '$nombre' creada.";
        }
    }

    // Rellenar el año a partir de la fecha de inicio si quedó en 0
    mysqli_query($conn, "UPDATE `eventos` SET `anio` = YEAR(fecha_inicio) WHERE `anio` = 0");

    // Debe haber exactamente un evento activo
    $activos = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM eventos WHERE activo = 1"))['n'];
    if ($activos === 0) {
        mysqli_query($conn, "UPDATE eventos SET activo = 1 ORDER BY anio DESC, id_evento DESC LIMIT 1");
        $mensajes[] = "[eventos] No había ningún año activo; se activó el más reciente.";
    } else {
        $mensajes[] = "[eventos] Año(s) activo(s): $activos.";
    }

    return $mensajes;
}

function MigrarEstados($conn)
{
    $mensajes = [];

    // Se trabaja por id (no por nombre) para que no se dupliquen los estados
    // aunque los acentos estén mal guardados en la base.
    //   id 1 = Habilitado (ÚNICO estado que permite votar)
    //   id 2 = Inducción      id 3 = Deserción
    $requeridos = [1 => 'Habilitado', 2 => 'Inducción', 3 => 'Deserción'];

    $actuales = [];
    $res = mysqli_query($conn, "SELECT id_estado, estados FROM estado");
    while ($fila = mysqli_fetch_assoc($res)) {
        $actuales[(int)$fila['id_estado']] = $fila['estados'];
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO estado (id_estado, estados) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE estados = VALUES(estados)");

    foreach ($requeridos as $id => $nombre) {
        mysqli_stmt_bind_param($stmt, "is", $id, $nombre);
        mysqli_stmt_execute($stmt);

        if (!isset($actuales[$id])) {
            $mensajes[] = "[estado] Estado '$nombre' (id $id) agregado.";
        } elseif ($actuales[$id] !== $nombre) {
            $mensajes[] = "[estado] id $id renombrado de '{$actuales[$id]}' a '$nombre'.";
        } else {
            $mensajes[] = "[estado] id $id ya era '$nombre'. Sin cambios.";
        }
    }

    return $mensajes;
}

function MigrarAprendizDocumentoUnico($conn, $borrarDuplicados = false)
{
    $mensajes = [];
    $baseDatos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT DATABASE() AS db"))['db'];

    $existeIndice = mysqli_num_rows(mysqli_query($conn,
        "SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = '$baseDatos' AND TABLE_NAME = 'aprendiz'
           AND INDEX_NAME = 'ux_aprendiz_documento'")) > 0;

    if ($existeIndice) {
        $mensajes[] = "[aprendiz] El índice único de 'documento' ya existía. Sin cambios.";
        return $mensajes;
    }

    $duplicados = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS n FROM (
            SELECT documento FROM aprendiz GROUP BY documento HAVING COUNT(*) > 1
         ) AS d"))['n'];

    if ($duplicados > 0 && !$borrarDuplicados) {
        $mensajes[] = "[aprendiz] AVISO: hay $duplicados documento(s) repetido(s). No se creó el índice único. "
            . "Para borrar los repetidos (se conserva el registro más antiguo de cada uno) vuelve a abrir este script agregando ?borrar_duplicados=1 a la URL.";
        return $mensajes;
    }

    if ($duplicados > 0 && $borrarDuplicados) {
        mysqli_query($conn, "DELETE a1 FROM aprendiz a1
                             JOIN aprendiz a2 ON a1.documento = a2.documento AND a1.id_aprendiz > a2.id_aprendiz
                             WHERE a1.id_rol = 2");
        $borrados = mysqli_affected_rows($conn);
        $mensajes[] = "[aprendiz] Se borraron $borrados registro(s) duplicado(s), conservando el más antiguo de cada documento.";
    }

    mysqli_query($conn, "CREATE UNIQUE INDEX `ux_aprendiz_documento` ON `aprendiz` (`documento`)");
    $mensajes[] = "[aprendiz] Índice único creado sobre 'documento'.";

    return $mensajes;
}

// ============================================================
// Ejecución directa desde el navegador
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'aplicar_migracion.php') {

    require_once(__DIR__ . '/conn.php');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
        die('<p style="font-family:sans-serif">Debes iniciar sesión como <strong>administrador</strong> antes de ejecutar la migración.</p>');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
    echo '<title>Migración - Votaciones SENA</title>';
    echo '<style>body{font-family:Segoe UI,Arial,sans-serif;max-width:820px;margin:40px auto;padding:0 20px;color:#333}';
    echo 'li{margin:6px 0}h1{color:#39A900}.ok{color:#2d8000}.err{color:#c0392b}</style></head><body>';
    echo '<h1>Migración de la base de datos</h1>';

    try {
        $borrarDuplicados = isset($_GET['borrar_duplicados']) && $_GET['borrar_duplicados'] == '1';

        $resultado = [];
        $resultado = array_merge($resultado, MigrarEventos($conn));
        $resultado = array_merge($resultado, MigrarEstados($conn));
        $resultado = array_merge($resultado, MigrarAprendizDocumentoUnico($conn, $borrarDuplicados));
        $resultado = array_merge($resultado, MigrarTablaAId($conn, 'formacion', 'id_formacion', 'ficha', 'id_jornada', 'ux_formacion_ficha_jornada'));
        $resultado = array_merge($resultado, MigrarTablaAId($conn, 'candidatos', 'id_candidato', 'tarjeton', 'id_jornada', 'ux_candidatos_tarjeton_jornada'));

        echo '<ul>';
        foreach ($resultado as $linea) {
            echo '<li>' . htmlspecialchars($linea) . '</li>';
        }
        echo '</ul>';
        echo '<p class="ok"><strong>Migración finalizada correctamente.</strong></p>';
        echo '<p>Por seguridad, borra este archivo del servidor cuando termines.</p>';
        echo '<p><a href="../vistas/administracion.php">Volver al panel</a></p>';
    } catch (Throwable $e) {
        echo '<p class="err"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>No se completó la migración. Copia este mensaje para revisarlo.</p>';
    }

    echo '</body></html>';
}
