<?php
/* ============================================================
   Lector de contenedores ZIP sin la extensión ZipArchive
   ------------------------------------------------------------
   Un .xlsx es un ZIP con varios XML adentro. En muchos hostings
   compartidos la extensión php-zip viene apagada, y entonces
   ZipArchive no existe y la importación se cae sin explicar por
   qué. Este lector abre el ZIP a mano: busca el directorio
   central al final del archivo, ubica cada entrada y la
   descomprime con gzinflate (zlib, que sí viene de fábrica).

   Solo necesita zlib. No requiere php-zip ni librerías externas.
   ============================================================ */

/* ------------------------------------------------------------
   1. Directorio central: el índice que el ZIP guarda al final
   ------------------------------------------------------------ */

function ZipLeerDirectorio($datos)
{
    $largo = strlen($datos);
    if ($largo < 22) { return null; }

    /* El EOCD está al final, pero puede llevar hasta 64 KB de
       comentario detrás, así que se busca hacia atrás. */
    $eocd = -1;
    $tope = min($largo - 22, 65535);
    for ($i = 0; $i <= $tope; $i++) {
        $pos = $largo - 22 - $i;
        if (substr($datos, $pos, 4) === "PK\x05\x06") { $eocd = $pos; break; }
    }
    if ($eocd < 0) { return null; }

    $entradas   = unpack('v', substr($datos, $eocd + 10, 2))[1];
    $inicioDir  = unpack('V', substr($datos, $eocd + 16, 4))[1];

    /* Los marcadores 0xFFFF/0xFFFFFFFF significan ZIP64: el
       índice real vive en otra cabecera, un poco más atrás. */
    if ($entradas === 0xFFFF || $inicioDir === 0xFFFFFFFF) {
        $loc = $eocd - 20;
        if ($loc < 0 || substr($datos, $loc, 4) !== "PK\x06\x07") { return null; }

        $z64 = ZipLeerEntero64($datos, $loc + 8);
        if ($z64 === null || substr($datos, $z64, 4) !== "PK\x06\x06") { return null; }

        $entradas  = ZipLeerEntero64($datos, $z64 + 32);
        $inicioDir = ZipLeerEntero64($datos, $z64 + 48);
        if ($entradas === null || $inicioDir === null) { return null; }
    }

    $catalogo = [];
    $pos = $inicioDir;

    for ($i = 0; $i < $entradas; $i++) {
        if ($pos + 46 > $largo || substr($datos, $pos, 4) !== "PK\x01\x02") { break; }

        $metodo      = unpack('v', substr($datos, $pos + 10, 2))[1];
        $tamComprim  = unpack('V', substr($datos, $pos + 20, 4))[1];
        $tamOriginal = unpack('V', substr($datos, $pos + 24, 4))[1];
        $largoNombre = unpack('v', substr($datos, $pos + 28, 2))[1];
        $largoExtra  = unpack('v', substr($datos, $pos + 30, 2))[1];
        $largoNota   = unpack('v', substr($datos, $pos + 32, 2))[1];
        $desplaLocal = unpack('V', substr($datos, $pos + 42, 4))[1];

        $nombre = substr($datos, $pos + 46, $largoNombre);
        $extra  = substr($datos, $pos + 46 + $largoNombre, $largoExtra);

        /* Cuando un campo llega en 0xFFFFFFFF, su valor verdadero
           está en el bloque extra 0x0001, en enteros de 8 bytes y
           siempre en este orden. */
        if ($tamOriginal === 0xFFFFFFFF || $tamComprim === 0xFFFFFFFF || $desplaLocal === 0xFFFFFFFF) {
            $bloque = ZipBuscarExtra($extra, 0x0001);
            $cursor = 0;
            if ($tamOriginal === 0xFFFFFFFF) { $tamOriginal = ZipLeerEntero64($bloque, $cursor); $cursor += 8; }
            if ($tamComprim  === 0xFFFFFFFF) { $tamComprim  = ZipLeerEntero64($bloque, $cursor); $cursor += 8; }
            if ($desplaLocal === 0xFFFFFFFF) { $desplaLocal = ZipLeerEntero64($bloque, $cursor); }
            if ($tamOriginal === null || $tamComprim === null || $desplaLocal === null) { return null; }
        }

        $catalogo[$nombre] = [
            'metodo'   => $metodo,
            'comprim'  => $tamComprim,
            'original' => $tamOriginal,
            'local'    => $desplaLocal,
        ];

        $pos += 46 + $largoNombre + $largoExtra + $largoNota;
    }

    return empty($catalogo) ? null : $catalogo;
}

/* Un entero de 8 bytes, rechazado si no cabe en el PHP de turno */
function ZipLeerEntero64($datos, $pos)
{
    if ($datos === '' || $pos + 8 > strlen($datos)) { return null; }

    $bajo = unpack('V', substr($datos, $pos, 4))[1];
    $alto = unpack('V', substr($datos, $pos + 4, 4))[1];

    if ($alto > 0 && PHP_INT_SIZE < 8) { return null; }

    return $alto * 4294967296 + $bajo;
}

/* Recorre los bloques extra buscando uno por su identificador */
function ZipBuscarExtra($extra, $identificador)
{
    $pos = 0;
    $largo = strlen($extra);

    while ($pos + 4 <= $largo) {
        $id  = unpack('v', substr($extra, $pos, 2))[1];
        $tam = unpack('v', substr($extra, $pos + 2, 2))[1];
        if ($id === $identificador) { return substr($extra, $pos + 4, $tam); }
        $pos += 4 + $tam;
    }

    return '';
}

/* ------------------------------------------------------------
   2. Sacar un archivo concreto del contenedor
   ------------------------------------------------------------ */

function ZipExtraer($datos, $catalogo, $nombre)
{
    if (!isset($catalogo[$nombre])) { return null; }

    $entrada = $catalogo[$nombre];
    $pos = $entrada['local'];

    if ($pos + 30 > strlen($datos) || substr($datos, $pos, 4) !== "PK\x03\x04") { return null; }

    /* La cabecera local repite nombre y extra con largos propios,
       así que los datos empiezan después de ellos. Los tamaños se
       toman del directorio central, que siempre los trae bien. */
    $largoNombre = unpack('v', substr($datos, $pos + 26, 2))[1];
    $largoExtra  = unpack('v', substr($datos, $pos + 28, 2))[1];

    $crudo = substr($datos, $pos + 30 + $largoNombre + $largoExtra, $entrada['comprim']);

    if ($entrada['metodo'] === 0) { return $crudo; }          /* guardado tal cual */

    if ($entrada['metodo'] === 8) {                            /* deflate */
        if (!function_exists('gzinflate')) { return null; }
        $salida = @gzinflate($crudo);
        return $salida === false ? null : $salida;
    }

    return null;                                               /* método no soportado */
}

/* Lista los nombres que empiezan por un prefijo dado */
function ZipNombresQueEmpiezanCon($catalogo, $prefijo)
{
    $encontrados = [];
    foreach (array_keys($catalogo) as $nombre) {
        if (strpos($nombre, $prefijo) === 0) { $encontrados[] = $nombre; }
    }
    sort($encontrados);
    return $encontrados;
}
