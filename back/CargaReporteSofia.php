<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    echo '<script>alert("No puedes acceder como administrador");window.location.href="../";</script>';
    exit();
}

$idEvento = EventoActivoId($conn);

if ($idEvento === null) {
    echo '<script>alert("No hay un año electoral activo. Activa uno primero.");window.location.href="../vistas/eventos.php";</script>';
    exit();
}

/* La subida se revisa primero: si el archivo supera post_max_size,
   PHP vacía también $_POST y la jornada parecería no haberse elegido. */
$fallaSubida = MotivoErrorSubida('archivo');

if ($fallaSubida !== null) {
    $aviso = addslashes($fallaSubida);
    echo '<script>alert("'.$aviso.'");window.location.href="../vistas/gestion_aprendices.php";</script>';
    exit();
}

$idJornada = $_POST['jornada'] ?? '';

if ($idJornada == null) {
    echo '<script>alert("Selecciona la jornada a la que pertenece la ficha");window.location.href="../vistas/gestion_aprendices.php";</script>';
    exit();
}

/* La jornada debe pertenecer al año activo */
$stmt = mysqli_prepare($conn,"SELECT id_jornada FROM jornada WHERE id_jornada = ? AND id_evento = ?");
mysqli_stmt_bind_param($stmt,"ii",$idJornada,$idEvento);
mysqli_stmt_execute($stmt);

if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
    echo '<script>alert("Esa jornada no pertenece al año electoral activo");window.location.href="../vistas/gestion_aprendices.php";</script>';
    exit();
}

$nombreOriginal = $_FILES['archivo']['name'];
$extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if (!in_array($extension, ['xls','xlsx','csv'], true)) {
    echo '<script>alert("Solo se admiten archivos .xls, .xlsx o .csv");window.location.href="../vistas/gestion_aprendices.php";</script>';
    exit();
}

/* Un error de SQL lanzaría una excepción que, sin capturar,
   el servidor devuelve como un error 500 en blanco. */
try {
    $resultado = CargarReporteSofia($conn,$idEvento,$idJornada,$_FILES['archivo']['tmp_name'],$nombreOriginal);
} catch (Throwable $e) {
    $aviso = addslashes(MotivoErrorBaseDatos($e));
    echo '<script>alert("'.$aviso.'");window.location.href="../vistas/gestion_aprendices.php";</script>';
    exit();
}

if ($resultado['ficha'] === null) {
    $mensaje = "No se pudo procesar el reporte.";
} else {
    $mensaje = "Ficha {$resultado['ficha']} · {$resultado['programa']}";
    $mensaje .= $resultado['ficha_creada'] ? "\\n(ficha creada en este año)" : "\\n(la ficha ya existía)";
    $mensaje .= "\\n\\nAprendices nuevos: {$resultado['insertados']}";
    $mensaje .= "\\nActualizados: {$resultado['actualizados']}";
}

foreach ($resultado['avisos'] as $aviso) {
    $mensaje .= "\\n\\nAviso: " . $aviso;
}

if (count($resultado['errores']) > 0) {
    $mensaje .= "\\n\\nErrores (" . count($resultado['errores']) . "):\\n" . implode("\\n", array_slice($resultado['errores'], 0, 15));
    if (count($resultado['errores']) > 15) {
        $mensaje .= "\\n... y " . (count($resultado['errores']) - 15) . " más.";
    }
}

$mensaje = addslashes($mensaje);

echo '
<script>
alert("'.$mensaje.'");
window.location.href="../vistas/gestion_aprendices.php";
</script>
';
?>
