<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');
require_once('_plantilla.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['rol']) || ($_SESSION['rol'] != 1 && $_SESSION['rol'] != 3)) {
    echo '<script>alert("No puedes acceder a esta vista");window.location.href="../";</script>';
    exit();
}

$idEvento = $_GET['id_evento'] ?? '';

if ($idEvento == null) {
    echo '<script>window.location.href="eventos.php";</script>';
    exit();
}

$evento = ObtenerEvento($conn, $idEvento);

if (!$evento) {
    echo '<script>alert("Ese año electoral no existe");window.location.href="eventos.php";</script>';
    exit();
}

if ($evento['fecha_fin'] >= date("Y-m-d")) {
    echo '<script>alert("El historial solo está disponible para años electorales ya finalizados");window.location.href="eventos.php";</script>';
    exit();
}

AbrirPagina('Historial ' . $evento['anio'], $_SESSION['rol'], 'anios');
BarraSuperior('Historial ' . $evento['anio'], $evento['evento'], 'eventos.php', 'Años electorales');
?>

<main class="contenedor">

    <div class="mensaje">
        Resultados definitivos del proceso, cerrado el <strong><?= htmlspecialchars($evento['fecha_fin']) ?></strong>.
        Esta vista es de <strong>solo lectura</strong>: la información histórica no puede modificarse.
    </div>

    <?php MostrarHistorialEvento($conn, $idEvento); ?>

</main>

<?php CerrarPagina(); ?>
