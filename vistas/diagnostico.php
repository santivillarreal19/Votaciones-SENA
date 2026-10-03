<?php
/* ============================================================
   Diagnóstico del servidor
   ------------------------------------------------------------
   Página de apoyo para cuando una importación falla en el
   hosting y funciona en local. Muestra qué extensiones tiene el
   PHP del servidor, qué límites de subida aplica, y permite
   probar un archivo real para ver en qué punto exacto se rompe.

   A propósito NO depende de la base de datos: usa solo el
   módulo de lectura de archivos, así sigue sirviendo aunque
   MySQL esté caído.

   Cuando termines de diagnosticar, borra este archivo del
   servidor: muestra detalles de configuración que no hace falta
   dejar publicados.
   ============================================================ */

require_once('../model/lector_archivos.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    echo '<script>alert("Solo el administrador puede ver el diagnóstico");window.location.href="../";</script>';
    exit();
}

/* ------------------------------------------------------------
   Qué necesita el sistema y para qué
   ------------------------------------------------------------ */

$requisitos = [
    ['zlib',      function_exists('gzinflate'),             true,  'Descomprimir archivos .xlsx sin la extensión zip'],
    ['simplexml', function_exists('simplexml_load_string'), true,  'Interpretar el contenido de los .xlsx'],
    ['iconv',     function_exists('iconv'),                 true,  'Leer los .xls antiguos de SofiaPlus'],
    ['mbstring',  function_exists('mb_strtolower'),         false, 'Ya no se usa: el sistema normaliza los textos por su cuenta'],
    ['mysqli',    function_exists('mysqli_connect'),        true,  'Conectar con la base de datos'],
    ['zip',       class_exists('ZipArchive'),               false, 'Acelera la lectura de .xlsx (hay respaldo si falta)'],
];

$faltanCriticos = [];
foreach ($requisitos as $requisito) {
    if ($requisito[2] && !$requisito[1]) {
        $faltanCriticos[] = $requisito[0];
    }
}

/* ------------------------------------------------------------
   Límites de subida
   ------------------------------------------------------------ */

$tmpDir  = (string)ini_get('upload_tmp_dir');
$tmpReal = $tmpDir !== '' ? $tmpDir : sys_get_temp_dir();

$postMenorQueArchivo = IniATamanoBytes(ini_get('post_max_size'))
                     < IniATamanoBytes(ini_get('upload_max_filesize'));

$limites = [
    ['file_uploads',        ini_get('file_uploads') ? 'On' : 'Off',
        ini_get('file_uploads') ? null : 'Las subidas están desactivadas en este servidor.'],
    ['upload_max_filesize', ini_get('upload_max_filesize'), null],
    ['post_max_size',       ini_get('post_max_size'),
        $postMenorQueArchivo ? 'Es menor que upload_max_filesize: manda este valor, el más bajo.' : null],
    ['memory_limit',        ini_get('memory_limit'), null],
    ['max_execution_time',  ini_get('max_execution_time') . ' s', null],
    ['carpeta temporal',    $tmpReal,
        is_writable($tmpReal) ? null : 'No se puede escribir en la carpeta temporal.'],
];

/* ------------------------------------------------------------
   Prueba con un archivo real
   ------------------------------------------------------------ */

$prueba = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prueba = ['fase' => '', 'ok' => false, 'detalle' => '', 'filas' => [], 'total' => 0];

    $falla = MotivoErrorSubida('prueba');

    if ($falla !== null) {
        $prueba['fase']    = 'Subida al servidor';
        $prueba['detalle'] = $falla;
    } else {
        $nombre = $_FILES['prueba']['name'];
        $motivo = null;
        $filas  = LeerFilasArchivo($_FILES['prueba']['tmp_name'], $nombre, $motivo);

        $prueba['fase'] = 'Lectura del archivo';

        if ($filas === null) {
            $prueba['detalle'] = $motivo ?: 'No se pudo interpretar el archivo.';
        } else {
            $prueba['ok']      = true;
            $prueba['total']   = count($filas);
            $prueba['filas']   = array_slice($filas, 0, 6);
            $prueba['detalle'] = 'El archivo "' . $nombre . '" se leyó correctamente.';
        }
    }
}

/* ------------------------------------------------------------
   Tipo de hosting
   ------------------------------------------------------------ */

$esCpanel = is_dir('/usr/local/cpanel') || strpos(__DIR__, '/public_html/') !== false;

$bloqueadas = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
$sinShell   = in_array('exec', $bloqueadas, true) || in_array('shell_exec', $bloqueadas, true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico del servidor · Votaciones SENA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500;600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/sena.css?v=<?= @filemtime(dirname(__DIR__) . '/css/sena.css') ?: date('YmdH') ?>">
    <style>
        .diag{max-width:940px;margin:0 auto;padding:30px 18px 60px;}
        .diag h2{margin:34px 0 12px;font-size:1.05rem;}
        .diag table{width:100%;border-collapse:collapse;font-size:.88rem;}
        .diag th,.diag td{text-align:left;padding:9px 10px;border-bottom:1px solid rgba(0,0,0,.08);vertical-align:top;}
        .diag th{font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;}
        .diag code{font-family:'IBM Plex Mono',monospace;font-size:.85em;}
        .marca{font-family:'IBM Plex Mono',monospace;font-weight:600;white-space:nowrap;}
        .bien{color:#0a7d34;}
        .mal{color:#b4231d;}
        .tibio{color:#9a6a00;}
        .nota{font-size:.82rem;opacity:.75;margin-top:4px;}
        .panel{border:1px solid rgba(0,0,0,.12);border-radius:10px;padding:16px 18px;margin:16px 0;background:#fff;}
        .panel.ok{border-left:4px solid #0a7d34;}
        .panel.error{border-left:4px solid #b4231d;}
        .panel.aviso{border-left:4px solid #9a6a00;}
        .rejilla{overflow-x:auto;margin-top:12px;}
        .rejilla td{border:1px solid rgba(0,0,0,.12);font-family:'IBM Plex Mono',monospace;font-size:.78rem;white-space:nowrap;}
    </style>
</head>
<body>

<div class="diag">

    <h1 style="font-size:1.5rem;margin-bottom:4px;">Diagnóstico del servidor</h1>
    <p class="nota">Compara lo que necesita el sistema con lo que ofrece este servidor.</p>

    <?php if (empty($faltanCriticos)): ?>
        <div class="panel ok">
            <strong>Este servidor tiene todo lo necesario para importar archivos.</strong>
            <div class="nota">Si una importación aún falla, usa la prueba de abajo con el archivo exacto que da problemas.</div>
        </div>
    <?php else: ?>
        <div class="panel error">
            <strong>Faltan extensiones obligatorias: <?= htmlspecialchars(implode(', ', $faltanCriticos)) ?>.</strong>
            <div class="nota">En cPanel se activan en <em>Select PHP Version → Extensions</em>. Marca las que falten y guarda.</div>
        </div>
    <?php endif; ?>

    <h2>Extensiones de PHP</h2>
    <table>
        <thead><tr><th>Extensión</th><th>Estado</th><th>Para qué se usa</th></tr></thead>
        <tbody>
        <?php foreach ($requisitos as $requisito): ?>
            <?php list($nombre, $presente, $obligatoria, $uso) = $requisito; ?>
            <tr>
                <td><code><?= htmlspecialchars($nombre) ?></code></td>
                <td class="marca <?= $presente ? 'bien' : ($obligatoria ? 'mal' : 'tibio') ?>">
                    <?= $presente ? '&#10003; activa' : ($obligatoria ? '&#10007; FALTA' : '&ndash; ausente') ?>
                </td>
                <td>
                    <?= htmlspecialchars($uso) ?>
                    <?php if (!$presente && !$obligatoria): ?>
                        <div class="nota">No es un problema: el sistema usa su propio lector en PHP puro.</div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Límites de subida de archivos</h2>
    <table>
        <thead><tr><th>Ajuste</th><th>Valor</th><th>Observación</th></tr></thead>
        <tbody>
        <?php foreach ($limites as $limite): ?>
            <?php list($nombre, $valor, $aviso) = $limite; ?>
            <tr>
                <td><code><?= htmlspecialchars($nombre) ?></code></td>
                <td class="marca"><?= htmlspecialchars((string)$valor) ?></td>
                <td class="<?= $aviso ? 'mal' : '' ?>"><?= $aviso ? htmlspecialchars($aviso) : '&mdash;' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="nota">
        El límite real de un archivo es el menor entre <code>upload_max_filesize</code> y <code>post_max_size</code>.
        En cPanel se suben desde <em>Select PHP Version → Options</em>.
    </p>

    <h2>Probar un archivo</h2>
    <p class="nota">Sube aquí el mismo archivo que falla al importar. Esta prueba solo lo lee: no escribe nada en la base de datos.</p>

    <form method="post" enctype="multipart/form-data" class="filtros" style="margin-top:12px;">
        <div>
            <label for="prueba">Archivo (.csv, .xls o .xlsx)</label>
            <input type="file" id="prueba" name="prueba" accept=".csv,.xls,.xlsx" required>
        </div>
        <button type="submit" class="btn">Probar lectura</button>
    </form>

    <?php if ($prueba !== null): ?>
        <div class="panel <?= $prueba['ok'] ? 'ok' : 'error' ?>">
            <strong><?= $prueba['ok'] ? '&#10003;' : '&#10007;' ?> <?= htmlspecialchars($prueba['fase']) ?></strong>
            <div class="nota"><?= htmlspecialchars($prueba['detalle']) ?></div>

            <?php if ($prueba['ok']): ?>
                <div class="nota">
                    Se leyeron <strong><?= (int)$prueba['total'] ?></strong> filas.
                    Estas son las primeras, tal como las ve el sistema:
                </div>
                <div class="rejilla">
                    <table>
                        <tbody>
                        <?php foreach ($prueba['filas'] as $fila): ?>
                            <tr>
                            <?php foreach ($fila as $celda): ?>
                                <td><?= htmlspecialchars($celda) ?></td>
                            <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="nota" style="margin-top:10px;">
                    Si las columnas salen corridas o con tildes rotas, el problema está en el archivo, no en el servidor.
                    Para formaciones el orden debe ser <code>ficha, formacion, jornada</code>.
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2>Entorno</h2>
    <table>
        <tbody>
            <tr><td>Versión de PHP</td><td class="marca"><?= htmlspecialchars(PHP_VERSION) ?></td></tr>
            <tr><td>Servidor web</td><td class="marca"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'desconocido') ?></td></tr>
            <tr><td>Sistema</td><td class="marca"><?= htmlspecialchars(PHP_OS_FAMILY) ?></td></tr>
            <tr><td>Enteros de 64 bits</td><td class="marca <?= PHP_INT_SIZE >= 8 ? 'bien' : 'tibio' ?>"><?= PHP_INT_SIZE >= 8 ? 'sí' : 'no (32 bits)' ?></td></tr>
            <tr><td>Ruta del proyecto</td><td class="marca"><?= htmlspecialchars(dirname(__DIR__)) ?></td></tr>
            <tr><td>Tipo de hosting</td><td class="marca"><?= $esCpanel ? 'cPanel' : 'no parece cPanel' ?></td></tr>
            <tr><td>Funciones de shell</td><td class="marca <?= $sinShell ? 'tibio' : '' ?>"><?= $sinShell ? 'bloqueadas (hosting compartido)' : 'disponibles' ?></td></tr>
        </tbody>
    </table>

    <?php if ($esCpanel && $sinShell): ?>
        <div class="panel aviso">
            <strong>Esto es un cPanel compartido.</strong>
            <div class="nota">
                No se puede instalar Docker aquí: haría falta acceso root al servidor. El sistema está preparado
                para funcionar sin él, con los lectores de archivos en PHP puro.
            </div>
        </div>
    <?php endif; ?>

    <div class="panel aviso" style="margin-top:28px;">
        <strong>Borra este archivo cuando termines.</strong>
        <div class="nota">
            <code>vistas/diagnostico.php</code> expone detalles de configuración del servidor.
            No hace falta dejarlo publicado.
        </div>
    </div>

    <p style="margin-top:24px;"><a href="AddJornada.php">&larr; Volver a Programas de formación</a></p>

</div>

</body>
</html>
