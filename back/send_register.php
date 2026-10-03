<?php

require_once('../conn/conn.php');
require_once('../model/functions.php');

$nombre = trim($_POST['nombreReg'] ?? '');
$numDoc = trim($_POST['docRegister'] ?? '');
$correo = trim($_POST['correoRegister'] ?? '');
$formacion = $_POST['formacion'] ?? '';

if ($nombre === '' || $numDoc === '' || $correo === '' || $formacion === '') {
    echo
    '
    <script>
    alert("Rellena todos los campos");
    window.location.href="../vistas/jornada.php";
    </script>
    ';
    exit;
}

RegistrarAprendiz($conn,$nombre,$numDoc,$formacion,$correo);
?>
