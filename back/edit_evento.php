<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');

date_default_timezone_set('America/Bogota');
$fecha = date("Y-m-d");

$idevento = $_POST['id_evento']??'';
$inicio = $_POST['fecha_inicio']??'';
$fin = $_POST['fecha_fin']??'';

if ($fecha == $inicio || $fecha > $inicio) {
    echo
    '
    <script>
    alert("Solo puedes modificar antes que inicie el evento");
    window.location.href="../vistas/eventos.php";
    </script>
    ';
    exit();
}
elseif ($fecha > $fin) {
   echo
    '
    <script>
    alert("El evento ya ha terminado");
    window.location.href="../vistas/eventos.php";
    </script>
    '; 
    exit();
}

EditCandidato($conn,$idevento,$inicio,$fin);



?>