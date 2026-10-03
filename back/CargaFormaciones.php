<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    echo
    '
    <script>
    alert("No puedes acceder como administrador");
    window.location.href="../";
    </script>
    ';
    exit();
}

$idEvento = EventoActivoId($conn);

if ($idEvento === null) {
    echo
    '
    <script>
    alert("No hay un año electoral activo. Activa uno primero en la sección Años Electorales.");
    window.location.href="../vistas/eventos.php";
    </script>
    ';
    exit();
}

$fallaSubida = MotivoErrorSubida('archivo');

if ($fallaSubida !== null) {
    $aviso = addslashes($fallaSubida);
    echo
    '
    <script>
    alert("'.$aviso.'");
    window.location.href="../vistas/AddJornada.php";
    </script>
    ';
    exit();
}

$nombreOriginal = $_FILES['archivo']['name'];
$extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if (!in_array($extension, ['csv','xls','xlsx'], true)) {
    echo
    '
    <script>
    alert("Solo se admiten archivos .csv, .xls o .xlsx");
    window.location.href="../vistas/AddJornada.php";
    </script>
    ';
    exit();
}

/* Un error de SQL lanzaría una excepción que, sin capturar,
   el servidor devuelve como un error 500 en blanco. */
try {
    $resultado = CargarFormacionesArchivo($conn,$idEvento,$_FILES['archivo']['tmp_name'],$nombreOriginal);
} catch (Throwable $e) {
    $aviso = addslashes(MotivoErrorBaseDatos($e));
    echo '<script>alert("'.$aviso.'");window.location.href="../vistas/AddJornada.php";</script>';
    exit();
}

$mensaje = "Carga finalizada. Insertados: {$resultado['insertados']}. Actualizados: {$resultado['actualizados']}. Errores: " . count($resultado['errores']) . ".";
if (count($resultado['errores']) > 0) {
    $mensaje .= "\\n\\nDetalle de errores:\\n" . implode("\\n", array_slice($resultado['errores'], 0, 20));
    if (count($resultado['errores']) > 20) {
        $mensaje .= "\\n... y " . (count($resultado['errores']) - 20) . " más.";
    }
}
$mensaje = addslashes($mensaje);

echo
'
<script>
alert("'.$mensaje.'");
window.location.href="../vistas/AddJornada.php";
</script>
';
?>
