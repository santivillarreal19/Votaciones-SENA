<?php
require('../conn/conn.php');
require('../model/functions.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
} else {
    // Limpia cualquier rol previo
    unset($_SESSION['rol']);
}

$rol_escogido = $_POST['rolEscogido']??'';

if(empty($rol_escogido))
{
    echo 
    '
    <script>
    alert("Debes escoger un rol");
    window.location.href="../";
    </script>
    ';
    exit();
}

$_SESSION['rol'] = $rol_escogido;
if($rol_escogido === "1")
{
    echo 
    '
    <script>
    alert("A continuación continuaras al inicio de sesión de Administrador");
    window.location.href="../vistas/LoginCorreo.php";
    </script>
    ';
    
}
else if($rol_escogido === "2")
{
    echo 
    '
    <script>
    alert("A continuación continuaras al inicio de sesión de aprendices");
    window.location.href="../vistas/LoginFicha.php";
    </script>
    ';
}
else if($rol_escogido === "3")
{
    echo 
    '
    <script>
    alert("A continuación continuaras al inicio de sesión de Auditor");
    window.location.href="../vistas/LoginCorreo.php";
    </script>
    ';
}
?>