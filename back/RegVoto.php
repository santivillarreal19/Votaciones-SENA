<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');

$idaprendiz = $_POST['id_aprendiz']??'';
$idcandidato = $_POST['id_candidato']??'';


if ($idaprendiz != null && $idcandidato != null) {
    RegistrarVoto($conn,$idaprendiz,$idcandidato);
}
else {
    echo 
            '
            <script>
            alert("No puedes Acceder de Esta Manera");
            window.location.href="../";
            </script>
            ';
}


?>