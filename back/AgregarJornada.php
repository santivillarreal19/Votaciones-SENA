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

$idEvento = $_POST['id_evento'] ?? '';
$nombreJornada = trim($_POST['nombre_jornada'] ?? '');
$horaIni = $_POST['hora_inicio'] ?? '';
$horaFin = $_POST['hora_fin'] ?? '';

if ($idEvento == null || $nombreJornada === '' || $horaIni == null || $horaFin == null) {
    echo
    '
    <script>
    alert("Rellena todos los campos");
    window.location.href="../vistas/gestion_jornadas.php?id_evento='.(int)$idEvento.'";
    </script>
    ';
    exit();
}

if (strtolower($nombreJornada) === 'administrador') {
    echo
    '
    <script>
    alert("Ese nombre está reservado, elige otro");
    window.location.href="../vistas/gestion_jornadas.php?id_evento='.(int)$idEvento.'";
    </script>
    ';
    exit();
}

if ($horaIni >= $horaFin) {
    echo
    '
    <script>
    alert("La hora de inicio debe ser menor a la hora de fin");
    window.location.href="../vistas/gestion_jornadas.php?id_evento='.(int)$idEvento.'";
    </script>
    ';
    exit();
}

AgregarJornada($conn,$idEvento,$nombreJornada,$horaIni,$horaFin);
?>
