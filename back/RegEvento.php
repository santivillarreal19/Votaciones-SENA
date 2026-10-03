<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');

date_default_timezone_set('America/Bogota');
$fecha = date("Y-m-d");

$evento = $_POST['event_name'];
$anio = date("Y");
$fechaini = $_POST['fechaini'];
$fechafin = $_POST['fechafin'];

if ($evento == null || $fechaini == null || $fechafin == null) {
    echo 
    '
    <script>
    alert("Rellena todos los campos");
    window.location.href="../vistas/administracion.php";
    </script>
    ';
    exit;
    
}
if ($fecha > $fechaini) {
    echo 
    '
    <script>
    alert("La fecha no puede ser mayor al inicio del evento");
    window.location.href="../vistas/administracion.php";
    </script>
    ';
    exit;
}elseif ($fechafin < $fecha) {
    echo 
    '
    <script>
    alert("La fecha no puede ser menor al fin del evento");
    window.location.href="../vistas/administracion.php";
    </script>
    ';
    exit;
}
elseif ($fechaini > $fechafin) {
    echo 
    '
    <script>
    alert("La fecha de inicio no puede ser mayor al final");
    window.location.href="../vistas/administracion.php";
    </script>
    ';
    exit;
}

AddEvent($conn,$evento,$anio,$fechaini,$fechafin);
?>