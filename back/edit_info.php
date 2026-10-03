<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');

$idcandidato = $_POST['idcandidato']??'';
$name = $_POST['name']??'';
$foto = '../fotos/'. $_FILES['foto']['name'];
move_uploaded_file($_FILES['foto']['tmp_name'],$foto);


if ($idcandidato != null) {
    
    if ($name != null) {
        ActualizarCandidato($conn,$idcandidato,$name,$foto);
    }
    else {
        echo 
            '
            <script>
            alert("Si Deseas Actualizar Rellena los campos");
            window.location.href="../vistas/administracion.php";
            </script>
            ';
    }

}


?>