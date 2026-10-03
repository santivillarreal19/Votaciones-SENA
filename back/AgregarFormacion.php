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

$idFicha = trim($_POST['ficha'] ?? '');
$nombreFormacion = trim($_POST['formacion'] ?? '');
$idJornada = $_POST['jornada'] ?? '';

if ($idFicha === '' || $nombreFormacion === '' || $idJornada == null) {
    echo
    '
    <script>
    alert("Rellena todos los campos");
    window.location.href="../vistas/AddJornada.php";
    </script>
    ';
    exit();
}

if (!ctype_digit($idFicha)) {
    echo
    '
    <script>
    alert("El número de ficha debe ser numérico");
    window.location.href="../vistas/AddJornada.php";
    </script>
    ';
    exit();
}

$idEvento = EventoActivoId($conn);
$stmt = mysqli_prepare($conn,"SELECT id_jornada FROM jornada WHERE id_jornada = ? AND id_evento = ?");
mysqli_stmt_bind_param($stmt,"ii",$idJornada,$idEvento);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!mysqli_fetch_assoc($res)) {
    echo
    '
    <script>
    alert("Esa jornada no pertenece al año electoral activo");
    window.location.href="../vistas/AddJornada.php";
    </script>
    ';
    exit();
}

AgregarFormacion($conn,$idFicha,$nombreFormacion,$idJornada);
?>
