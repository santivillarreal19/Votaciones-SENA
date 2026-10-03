<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['rol'])) {
    echo
    '
    <script>
    alert("No puedes acceder como administrador");
    window.location.href="../";
    </script>
    ';
    exit();
}



$nombre = $_POST['candidatoName'];
$idjornada = $_POST['idjornada'];
$formacion = $_POST['formacion'];
$correo = $_POST['candidatoCorreo'];
$tarj = $_POST['candidatoTarj'];
$slogan = $_POST['Slogan'];
$foto = '../fotos/' .$_FILES['foto']['name'];
move_uploaded_file($_FILES['foto']['tmp_name'],$foto);



RegistrarCandidato($conn,$nombre,$idjornada,$formacion,$correo,$slogan,$tarj,$foto);
?>