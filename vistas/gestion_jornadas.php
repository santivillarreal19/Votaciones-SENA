<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');
require_once('_plantilla.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    echo '<script>alert("No puedes acceder como administrador");window.location.href="../";</script>';
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

AbrirPagina('Jornadas ' . $evento['anio'], 1, 'anios');
BarraSuperior('Jornadas de votación', $evento['evento'] . ' · ' . $evento['anio'], 'eventos.php', 'Años electorales');
?>

<main class="contenedor">

    <?php if ($evento['activo']): ?>
        <div class="mensaje">Este es el año electoral <strong>activo</strong>: las jornadas que definas aquí son las que se usan hoy para registrar candidatos, cargar aprendices y votar.</div>
    <?php endif; ?>

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Horarios por jornada</h2>
            <span class="rotulo">Determinan cuándo puede votar cada jornada</span>
        </div>
        <?php MostrarHorariosJornadas($conn, $idEvento); ?>
    </div>

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Agregar jornada</h2>
            <span class="rotulo">Mañana · Tarde · Noche · Virtual · A Distancia</span>
        </div>
        <?php FormularioNuevaJornada($idEvento); ?>
    </div>

</main>

<?php CerrarPagina(); ?>
