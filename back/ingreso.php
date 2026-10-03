<?php

require_once('../conn/conn.php');
require_once('../model/functions.php');

$docLogin = $_POST['numdoc']??'';
$correo = $_POST['email']??'';
$rol = $_POST['rol']??'';
$ficha = $_POST['ficha']??'';

if(empty($rol))
{
        if ($docLogin != null && $correo != null) {
        ValidateLogin($conn,$docLogin,$correo,null,null);
    }
    else {
        echo 
                '
                <script>
                alert("Ingresa Datos");
                window.location.href="../";
                </script>
                ';
    }
}
else if($rol == "2")
{
    ValidateLogin($conn,$docLogin,$correo,$rol,$ficha);
}






?>