<?php
/* ============================================================
   Lectura de los archivos que se suben al sistema
   ------------------------------------------------------------
   Aquí vive todo lo que convierte un archivo subido (.csv, .xls
   o .xlsx) en una lista de filas, más el diagnóstico de la
   subida en sí. Es un módulo aparte a propósito: no toca la
   base de datos, así que la página de diagnóstico puede usarlo
   aunque MySQL esté caído.
   ============================================================ */

require_once(__DIR__ . '/lector_xls.php');
require_once(__DIR__ . '/lector_zip.php');


/* ------------------------------------------------------------
   Diagnóstico de la subida
   ------------------------------------------------------------
   Si el archivo pesa más que post_max_size, PHP descarta $_POST
   y $_FILES completos y el formulario parece llegar vacío. Sin
   este chequeo el usuario solo ve "selecciona un archivo" y no
   hay forma de adivinar que el problema es el límite del hosting.
   ------------------------------------------------------------ */

/* Convierte los tamaños del php.ini ("8M", "1G") a bytes */
function IniATamanoBytes($valor)
{
    $valor = trim((string)$valor);
    if ($valor === '') { return 0; }

    $numero = (int)$valor;
    $sufijo = strtolower(substr($valor, -1));

    if ($sufijo === 'g') { return $numero * 1024 * 1024 * 1024; }
    if ($sufijo === 'm') { return $numero * 1024 * 1024; }
    if ($sufijo === 'k') { return $numero * 1024; }

    return $numero;
}

/* Devuelve null si la subida llegó bien, o el motivo si falló */
function MotivoErrorSubida($campo)
{
    $limitePost = IniATamanoBytes(ini_get('post_max_size'));
    $enviado = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;

    /* $_FILES vacío con contenido enviado = se pasó de post_max_size */
    if (empty($_FILES) && $enviado > 0 && $limitePost > 0 && $enviado > $limitePost) {
        return 'El archivo pesa más de lo que este servidor acepta por envío ('
             . ini_get('post_max_size') . '). Súbelo en partes o pide al hosting subir post_max_size.';
    }

    if (!isset($_FILES[$campo])) {
        return 'No llegó ningún archivo al servidor. Si el archivo es grande, '
             . 'puede haber superado el límite del hosting (' . ini_get('post_max_size') . ').';
    }

    $codigo = $_FILES[$campo]['error'];

    if ($codigo === UPLOAD_ERR_OK) {
        /* El archivo dice estar bien, pero el temporal debe existir */
        if (!is_uploaded_file($_FILES[$campo]['tmp_name'])) {
            return 'El archivo temporal de la subida no está disponible en el servidor.';
        }
        if ($_FILES[$campo]['size'] === 0) {
            return 'El archivo subido está vacío (0 bytes).';
        }
        return null;
    }

    switch ($codigo) {
        case UPLOAD_ERR_INI_SIZE:
            return 'El archivo supera el máximo por archivo de este servidor ('
                 . ini_get('upload_max_filesize') . ').';
        case UPLOAD_ERR_FORM_SIZE:
            return 'El archivo supera el máximo permitido por el formulario.';
        case UPLOAD_ERR_PARTIAL:
            return 'La subida se cortó a medias. Vuelve a intentarlo.';
        case UPLOAD_ERR_NO_FILE:
            return 'No seleccionaste ningún archivo.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'El servidor no tiene carpeta temporal para recibir archivos. '
                 . 'Avisa al hosting: falta upload_tmp_dir.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'El servidor no pudo escribir el archivo en disco. '
                 . 'Puede ser falta de espacio o de permisos.';
        case UPLOAD_ERR_EXTENSION:
            return 'Una extensión de PHP bloqueó la subida de este archivo.';
    }

    return 'La subida falló con el código de error ' . (int)$codigo . '.';
}

function ColumnaExcelAIndice($referencia)
{
    preg_match('/^([A-Z]+)/', strtoupper((string)$referencia), $coincidencias);
    $letras = $coincidencias[1] ?? 'A';

    $indice = 0;
    for ($i = 0; $i < strlen($letras); $i++) {
        $indice = $indice * 26 + (ord($letras[$i]) - 64);
    }

    return $indice - 1;
}

/* Abre el .xlsx y devuelve los XML que interesan, usando
   ZipArchive si está disponible y, si no, el lector propio.
   $motivo queda con la causa exacta cuando algo falla. */
function XlsxAbrirPartes($rutaArchivo,&$motivo = null)
{
    $compartidas = null;   /* xl/sharedStrings.xml */
    $hoja = null;          /* xl/worksheets/sheet1.xml */

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($rutaArchivo) !== true) {
            $motivo = 'El archivo .xlsx está dañado o no se pudo abrir.';
            return null;
        }

        $sst = $zip->getFromName('xl/sharedStrings.xml');
        $compartidas = ($sst === false) ? null : $sst;

        $xmlHoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($xmlHoja === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombre = $zip->getNameIndex($i);
                if (strpos($nombre, 'xl/worksheets/sheet') === 0) {
                    $xmlHoja = $zip->getFromName($nombre);
                    break;
                }
            }
        }
        $zip->close();

        $hoja = ($xmlHoja === false) ? null : $xmlHoja;

    } else {
        /* Sin php-zip: se abre el contenedor a mano */
        if (!function_exists('gzinflate')) {
            $motivo = 'Este servidor no tiene ni la extensión php-zip ni zlib, '
                    . 'así que no puede abrir archivos .xlsx. Guarda el archivo como .csv y súbelo así.';
            return null;
        }

        $datos = @file_get_contents($rutaArchivo);
        if ($datos === false) {
            $motivo = 'No se pudo leer el archivo subido desde el disco del servidor.';
            return null;
        }

        $catalogo = ZipLeerDirectorio($datos);
        if ($catalogo === null) {
            $motivo = 'El archivo no parece un .xlsx válido (no se encontró el índice interno del ZIP).';
            return null;
        }

        $compartidas = ZipExtraer($datos, $catalogo, 'xl/sharedStrings.xml');

        $hoja = ZipExtraer($datos, $catalogo, 'xl/worksheets/sheet1.xml');
        if ($hoja === null) {
            foreach (ZipNombresQueEmpiezanCon($catalogo, 'xl/worksheets/sheet') as $nombre) {
                $hoja = ZipExtraer($datos, $catalogo, $nombre);
                if ($hoja !== null) { break; }
            }
        }
    }

    if ($hoja === null || $hoja === '') {
        $motivo = 'El archivo .xlsx no contiene ninguna hoja de cálculo legible.';
        return null;
    }

    return ['hoja' => $hoja, 'compartidas' => $compartidas];
}

function LeerXlsx($rutaArchivo,&$motivo = null)
{
    if (!function_exists('simplexml_load_string')) {
        $motivo = 'Este servidor no tiene la extensión php-xml activada, necesaria para leer archivos .xlsx. '
                . 'Actívala en cPanel (Select PHP Version → Extensions → xml/simplexml) o sube el archivo en .csv.';
        return null;
    }

    $partes = XlsxAbrirPartes($rutaArchivo,$motivo);
    if ($partes === null) {
        return null;
    }

    /* La tabla de textos: las celdas de texto apuntan aquí por número */
    $compartidas = [];
    if (!empty($partes['compartidas'])) {
        $sst = @simplexml_load_string($partes['compartidas']);
        if ($sst) {
            foreach ($sst->si as $si) {
                $texto = '';
                if (isset($si->t)) {
                    $texto = (string)$si->t;
                } else {
                    foreach ($si->r as $r) {
                        $texto .= (string)$r->t;
                    }
                }
                $compartidas[] = $texto;
            }
        }
    }

    $hoja = @simplexml_load_string($partes['hoja']);
    if (!$hoja || !isset($hoja->sheetData)) {
        $motivo = 'La hoja del archivo .xlsx no se pudo interpretar.';
        return null;
    }

    $filas = [];
    foreach ($hoja->sheetData->row as $row) {
        $celdas = [];
        $posicion = 0;

        foreach ($row->c as $c) {
            $referencia = (string)$c['r'];
            $indice = ($referencia !== '') ? ColumnaExcelAIndice($referencia) : $posicion;
            $posicion = $indice + 1;

            $tipo = (string)$c['t'];
            if ($tipo === 'inlineStr') {
                $valor = isset($c->is->t) ? (string)$c->is->t : '';
            } elseif (!isset($c->v)) {
                $valor = '';
            } elseif ($tipo === 's') {
                $valor = $compartidas[(int)$c->v] ?? '';
            } else {
                $valor = (string)$c->v;
            }

            $celdas[$indice] = trim($valor);
        }

        if (empty($celdas)) {
            continue;
        }

        $maximo = max(array_keys($celdas));
        $ordenada = [];
        for ($i = 0; $i <= $maximo; $i++) {
            $ordenada[] = $celdas[$i] ?? '';
        }

        if (trim(implode('', $ordenada)) === '') {
            continue;
        }

        $filas[] = $ordenada;
    }

    if (empty($filas)) {
        $motivo = 'El archivo .xlsx se abrió pero la primera hoja está vacía.';
        return null;
    }

    return $filas;
}

function LeerCsv($rutaArchivo,&$motivo = null)
{
    $fh = @fopen($rutaArchivo, 'r');
    if (!$fh) {
        $motivo = 'No se pudo abrir el archivo subido desde el disco del servidor.';
        return null;
    }

    $filas = [];
    /* El quinto argumento ($escape) se pasa vacío a propósito: es el
       comportamiento estándar de CSV, el que genera Excel, y además
       evita el aviso de obsolescencia de PHP 8.4 en adelante. */
    while (($datos = fgetcsv($fh, 0, ",", '"', "")) !== false) {
        if (count($datos) === 1 && trim((string)$datos[0]) === '') {
            continue;
        }
        $filas[] = array_map(function ($v) { return trim((string)$v); }, $datos);
    }
    fclose($fh);

    if (empty($filas)) {
        $motivo = 'El archivo .csv está vacío.';
        return null;
    }

    return $filas;
}

function LeerFilasArchivo($rutaArchivo,$nombreOriginal = '',&$motivo = null)
{
    $motivo = null;
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

    if ($extension === 'xlsx') {
        return LeerXlsx($rutaArchivo,$motivo);
    }

    if ($extension === 'xls') {
        return LeerXls($rutaArchivo,$motivo);
    }

    return LeerCsv($rutaArchivo,$motivo);
}
