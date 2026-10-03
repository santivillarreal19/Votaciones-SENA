<?php
$s = "localhost";
$u = "root";
$p = "";
$db = "eleccion_representantes";

$conn = mysqli_connect($s,$u,$p,$db) or die ("ERROR EN LA CONEXIÓN");

mysqli_set_charset($conn, "utf8mb4");
?>