<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');

$id = $_GET['idcandidato']??'';

if ($id == null) {
    echo 
    '
    <script>
    window.location.href("../vistas/administracion.php");
    </script>
    ';
}
else {
    EliminarCandidato($conn,$id);
}
?>