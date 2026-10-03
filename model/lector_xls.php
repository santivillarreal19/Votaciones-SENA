<?php
/* ============================================================
   Lector de archivos .xls antiguos (Excel 97-2003)
   ------------------------------------------------------------
   Un .xls no es ZIP como el .xlsx: es un contenedor OLE2 que
   guarda dentro un flujo llamado "Workbook" con registros BIFF8.
   Este lector hace lo mínimo necesario para los reportes de
   SofiaPlus: abre el contenedor, saca la tabla de textos (SST)
   y reconstruye la cuadrícula de celdas.

   No requiere ninguna extensión ni librería externa.
   ============================================================ */

/* ------------------------------------------------------------
   1. Contenedor OLE2: extraer el flujo "Workbook"
   ------------------------------------------------------------ */

function XlsLeerFlujoWorkbook($datos)
{
    if (strlen($datos) < 512 || substr($datos, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
        return null;
    }

    $u16 = function ($pos) use ($datos) { return unpack('v', substr($datos, $pos, 2))[1]; };
    $u32 = function ($pos) use ($datos) { return unpack('V', substr($datos, $pos, 4))[1]; };

    $tamSector   = 1 << $u16(30);
    $tamMini     = 1 << $u16(32);
    $dirInicio   = $u32(48);
    $corteMini   = $u32(56);
    $miniFatIni  = $u32(60);
    $difatIni    = $u32(68);
    $difatNum    = $u32(72);

    $LIBRE = 0xFFFFFFFF;
    $FIN   = 0xFFFFFFFE;

    $posSector = function ($id) use ($tamSector) { return 512 + $id * $tamSector; };

    /* --- DIFAT: dónde están los sectores de la FAT --- */
    $difat = [];
    for ($i = 0; $i < 109; $i++) {
        $v = $u32(76 + $i * 4);
        if ($v === $FIN || $v === $LIBRE) { break; }
        $difat[] = $v;
    }

    $sector = $difatIni;
    $porSector = intdiv($tamSector, 4) - 1;
    for ($i = 0; $i < $difatNum && $sector !== $FIN && $sector !== $LIBRE; $i++) {
        $base = $posSector($sector);
        for ($j = 0; $j < $porSector; $j++) {
            $v = unpack('V', substr($datos, $base + $j * 4, 4))[1];
            if ($v !== $FIN && $v !== $LIBRE) { $difat[] = $v; }
        }
        $sector = unpack('V', substr($datos, $base + $porSector * 4, 4))[1];
    }

    /* --- FAT: la cadena de sectores --- */
    $fat = [];
    foreach ($difat as $sectorFat) {
        $base = $posSector($sectorFat);
        for ($j = 0; $j < intdiv($tamSector, 4); $j++) {
            $trozo = substr($datos, $base + $j * 4, 4);
            if (strlen($trozo) < 4) { break 2; }
            $fat[] = unpack('V', $trozo)[1];
        }
    }

    /* --- Recorrer una cadena de sectores normales --- */
    $leerCadena = function ($inicio, $limite = null) use ($fat, $datos, $tamSector, $posSector, $FIN, $LIBRE) {
        $buffer = '';
        $sector = $inicio;
        $vueltas = 0;
        while ($sector !== $FIN && $sector !== $LIBRE && isset($fat[$sector]) && $vueltas++ < 200000) {
            $buffer .= substr($datos, $posSector($sector), $tamSector);
            if ($limite !== null && strlen($buffer) >= $limite) { break; }
            $sector = $fat[$sector];
        }
        return $limite !== null ? substr($buffer, 0, $limite) : $buffer;
    };

    /* --- Directorio: lista de flujos guardados --- */
    $directorio = $leerCadena($dirInicio);
    $entradas = intdiv(strlen($directorio), 128);

    $raizInicio = null; $raizTam = 0;
    $wbInicio = null;   $wbTam = 0;

    for ($i = 0; $i < $entradas; $i++) {
        $base = $i * 128;
        $largoNombre = unpack('v', substr($directorio, $base + 64, 2))[1];
        if ($largoNombre < 2) { continue; }

        $nombre = substr($directorio, $base, $largoNombre - 2);
        $nombre = @iconv('UTF-16LE', 'UTF-8//IGNORE', $nombre);
        $tipo = ord($directorio[$base + 66]);
        $inicio = unpack('V', substr($directorio, $base + 116, 4))[1];
        $tam = unpack('V', substr($directorio, $base + 120, 4))[1];

        if ($tipo === 5) {                       /* Root Entry: contiene el mini flujo */
            $raizInicio = $inicio;
            $raizTam = $tam;
        } elseif ($nombre === 'Workbook' || $nombre === 'Book') {
            $wbInicio = $inicio;
            $wbTam = $tam;
        }
    }

    if ($wbInicio === null) { return null; }

    /* --- Flujos grandes van en sectores normales --- */
    if ($wbTam >= $corteMini) {
        return $leerCadena($wbInicio, $wbTam);
    }

    /* --- Flujos pequeños viven dentro del mini flujo --- */
    $miniContenedor = $leerCadena($raizInicio, $raizTam);

    $miniFat = [];
    $crudo = $leerCadena($miniFatIni);
    for ($j = 0; $j < intdiv(strlen($crudo), 4); $j++) {
        $miniFat[] = unpack('V', substr($crudo, $j * 4, 4))[1];
    }

    $buffer = '';
    $sector = $wbInicio;
    $vueltas = 0;
    while ($sector !== $FIN && $sector !== $LIBRE && isset($miniFat[$sector]) && $vueltas++ < 200000) {
        $buffer .= substr($miniContenedor, $sector * $tamMini, $tamMini);
        $sector = $miniFat[$sector];
    }

    return substr($buffer, 0, $wbTam);
}

/* ------------------------------------------------------------
   2. Registros BIFF8
   ------------------------------------------------------------ */

/* Los números "RK" guardan un double comprimido en 4 bytes */
function XlsDecodificarRk($rk)
{
    if ($rk & 0x02) {
        $valor = $rk >> 2;
        if ($valor & 0x20000000) { $valor -= 0x40000000; }   /* signo de 30 bits */
    } else {
        $bits = pack('V2', 0, $rk & 0xFFFFFFFC);
        $valor = unpack('d', $bits)[1];
    }

    return ($rk & 0x01) ? $valor / 100 : $valor;
}

/* La tabla de textos puede partirse en varios registros CONTINUE */
function XlsLeerTablaTextos($trozos)
{
    $textos = [];
    if (empty($trozos)) { return $textos; }

    $indice = 0;
    $datos = $trozos[0];
    $pos = 8;                                   /* saltar los dos contadores */

    $siguienteTrozo = function () use (&$indice, &$datos, &$pos, $trozos) {
        $indice++;
        if (!isset($trozos[$indice])) { return false; }
        $datos = $trozos[$indice];
        $pos = 0;
        return true;
    };

    while (true) {
        if ($pos + 3 > strlen($datos) && !$siguienteTrozo()) { break; }
        if ($pos + 3 > strlen($datos)) { break; }

        $largo = unpack('v', substr($datos, $pos, 2))[1];
        $banderas = ord($datos[$pos + 2]);
        $pos += 3;

        $ancho    = ($banderas & 0x01) ? 2 : 1;   /* 2 = UTF-16LE */
        $rico     = ($banderas & 0x08) ? unpack('v', substr($datos, $pos, 2))[1] : 0;
        if ($banderas & 0x08) { $pos += 2; }
        $extendido = ($banderas & 0x04) ? unpack('V', substr($datos, $pos, 4))[1] : 0;
        if ($banderas & 0x04) { $pos += 4; }

        $texto = '';
        $faltan = $largo;

        while ($faltan > 0) {
            $disponibles = intdiv(strlen($datos) - $pos, $ancho);

            if ($disponibles <= 0) {
                if (!$siguienteTrozo()) { break 2; }
                /* Cada continuación repite la bandera de codificación */
                $ancho = (ord($datos[$pos]) & 0x01) ? 2 : 1;
                $pos += 1;
                continue;
            }

            $toma = min($faltan, $disponibles);
            $bruto = substr($datos, $pos, $toma * $ancho);
            $pos += $toma * $ancho;
            $faltan -= $toma;

            $texto .= ($ancho === 2)
                ? (string)@iconv('UTF-16LE', 'UTF-8//IGNORE', $bruto)
                : (string)@iconv('CP1252', 'UTF-8//IGNORE', $bruto);
        }

        /* Descartar los adornos de formato */
        $pos += $rico * 4 + $extendido;

        $textos[] = $texto;
    }

    return $textos;
}

/* ------------------------------------------------------------
   3. Reconstruir la cuadrícula de la primera hoja
   ------------------------------------------------------------ */

function LeerXls($rutaArchivo,&$motivo = null)
{
    if (!function_exists('iconv')) {
        $motivo = 'Este servidor no tiene la extensión iconv activada, necesaria para leer archivos .xls. '
                . 'Actívala en cPanel (Select PHP Version → Extensions → iconv) o sube el archivo en .csv.';
        return null;
    }

    $datos = @file_get_contents($rutaArchivo);
    if ($datos === false) {
        $motivo = 'No se pudo leer el archivo subido desde el disco del servidor.';
        return null;
    }

    $flujo = XlsLeerFlujoWorkbook($datos);
    if ($flujo === null) {
        $motivo = 'El archivo no parece un .xls de Excel 97-2003 válido.';
        return null;
    }

    $largoFlujo = strlen($flujo);
    $pos = 0;

    $textos = [];
    $celdas = [];        /* [fila][columna] => valor */
    $maxColumna = 0;

    while ($pos + 4 <= $largoFlujo) {
        $tipo  = unpack('v', substr($flujo, $pos, 2))[1];
        $largo = unpack('v', substr($flujo, $pos + 2, 2))[1];
        $cuerpo = substr($flujo, $pos + 4, $largo);
        $pos += 4 + $largo;

        switch ($tipo) {

            case 0x00FC:   /* SST: tabla de textos, con sus CONTINUE */
                $trozos = [$cuerpo];
                while ($pos + 4 <= $largoFlujo) {
                    $tipoSig = unpack('v', substr($flujo, $pos, 2))[1];
                    if ($tipoSig !== 0x003C) { break; }
                    $largoSig = unpack('v', substr($flujo, $pos + 2, 2))[1];
                    $trozos[] = substr($flujo, $pos + 4, $largoSig);
                    $pos += 4 + $largoSig;
                }
                $textos = XlsLeerTablaTextos($trozos);
                break;

            case 0x00FD:   /* LABELSST: celda de texto */
                if ($largo < 10) { break; }
                $fila = unpack('v', substr($cuerpo, 0, 2))[1];
                $col  = unpack('v', substr($cuerpo, 2, 2))[1];
                $idx  = unpack('V', substr($cuerpo, 6, 4))[1];
                $celdas[$fila][$col] = $textos[$idx] ?? '';
                $maxColumna = max($maxColumna, $col);
                break;

            case 0x0204:   /* LABEL: texto incrustado (BIFF antiguo) */
                if ($largo < 8) { break; }
                $fila = unpack('v', substr($cuerpo, 0, 2))[1];
                $col  = unpack('v', substr($cuerpo, 2, 2))[1];
                $n    = unpack('v', substr($cuerpo, 6, 2))[1];
                $celdas[$fila][$col] = (string)@iconv('CP1252', 'UTF-8//IGNORE', substr($cuerpo, 8, $n));
                $maxColumna = max($maxColumna, $col);
                break;

            case 0x027E:   /* RK: número comprimido */
                if ($largo < 10) { break; }
                $fila = unpack('v', substr($cuerpo, 0, 2))[1];
                $col  = unpack('v', substr($cuerpo, 2, 2))[1];
                $rk   = unpack('V', substr($cuerpo, 6, 4))[1];
                $celdas[$fila][$col] = XlsFormatearNumero(XlsDecodificarRk($rk));
                $maxColumna = max($maxColumna, $col);
                break;

            case 0x00BD:   /* MULRK: varios números seguidos */
                if ($largo < 6) { break; }
                $fila = unpack('v', substr($cuerpo, 0, 2))[1];
                $colIni = unpack('v', substr($cuerpo, 2, 2))[1];
                $cuantos = intdiv($largo - 6, 6);
                for ($i = 0; $i < $cuantos; $i++) {
                    $rk = unpack('V', substr($cuerpo, 4 + $i * 6 + 2, 4))[1];
                    $col = $colIni + $i;
                    $celdas[$fila][$col] = XlsFormatearNumero(XlsDecodificarRk($rk));
                    $maxColumna = max($maxColumna, $col);
                }
                break;

            case 0x0203:   /* NUMBER: double de 8 bytes */
                if ($largo < 14) { break; }
                $fila = unpack('v', substr($cuerpo, 0, 2))[1];
                $col  = unpack('v', substr($cuerpo, 2, 2))[1];
                $valor = unpack('d', substr($cuerpo, 6, 8))[1];
                $celdas[$fila][$col] = XlsFormatearNumero($valor);
                $maxColumna = max($maxColumna, $col);
                break;

            case 0x000A:   /* EOF de la primera hoja: ya tenemos lo necesario */
                if (!empty($celdas)) { break 2; }
                break;
        }
    }

    if (empty($celdas)) {
        $motivo = 'El archivo .xls se abrió pero la primera hoja no tiene celdas con datos.';
        return null;
    }

    /* Pasar de [fila][col] a una lista ordenada, rellenando huecos */
    ksort($celdas);
    $filas = [];
    foreach ($celdas as $fila) {
        $ordenada = [];
        for ($c = 0; $c <= $maxColumna; $c++) {
            $ordenada[] = isset($fila[$c]) ? trim((string)$fila[$c]) : '';
        }
        if (trim(implode('', $ordenada)) !== '') {
            $filas[] = $ordenada;
        }
    }

    return $filas;
}

/* Evita que los documentos salgan como 1.007568426E+9 */
function XlsFormatearNumero($valor)
{
    if (is_float($valor) && floor($valor) == $valor && abs($valor) < 1e15) {
        return number_format($valor, 0, '.', '');
    }
    return (string)$valor;
}
