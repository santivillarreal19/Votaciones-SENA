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

$idEvento = $_GET['id_evento'] ?? '';

if ($idEvento == null) {
    echo
    '
    <script>
    window.location.href="../vistas/eventos.php";
    </script>
    ';
    exit();
}

EliminarEvento($conn,$idEvento);
?>
