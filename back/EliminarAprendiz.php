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

$id = $_GET['id'] ?? '';

if ($id == null) {
    echo
    '
    <script>
    window.location.href="../vistas/gestion_aprendices.php";
    </script>
    ';
    exit();
}

EliminarAprendiz($conn,$id);
?>
