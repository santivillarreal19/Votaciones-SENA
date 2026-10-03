<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['rol'])) {
    echo '<script>alert("Debes Iniciar Sesión como aprendiz");window.location.href="../";</script>';
    exit();
}

$aprendiz = $_SESSION['id_aprendiz'];
$jornada = $_SESSION['id_jornada'] ?? '';

require_once('../conn/conn.php');
require_once('../model/functions.php');
require_once('_plantilla.php');

date_default_timezone_set('America/Bogota');
$fecha = date("Y-m-d");
$stmt1 = mysqli_query($conn, "SELECT * FROM eventos WHERE activo = 1");

if ($row = mysqli_fetch_assoc($stmt1)) {
    $fechaini = $row["fecha_inicio"];
    $fechafin = $row["fecha_fin"];

    if ($fecha < $fechaini) {
        echo '<script>alert("El evento aún no ha empezado");window.location.href="../";</script>';
        exit();
    }
    elseif ($fecha > $fechafin) {
        echo '<script>alert("El evento ya ha terminado");window.location.href="../";</script>';
        exit();
    }
}

$hora = date("H:i:s");

$sql = "SELECT * FROM jornada WHERE id_jornada = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $jornada);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $horaini = $row["fecha_inicio"];
    $horafin = $row["fecha_fin"];
    $nombreJornada = $row["jornada"];

    if ($hora < $horaini || $hora > $horafin) {
        echo '<script>alert("Lo sentimos, estás fuera del horario de votación");window.location.href="../";</script>';
        exit();
    }
} else {
    echo '<script>alert("Lo sentimos, no se encontró tu jornada académica");window.location.href="../";</script>';
    exit();
}

AbrirPagina('Votación');
?>

<header class="barra">
    <div class="barra-interior">
        <div class="barra-marca">
            <div class="barra-sello">SENA</div>
            <div>
                <h1>Elige a tu representante</h1>
                <p>Jornada <?= htmlspecialchars($nombreJornada) ?> · cierra a las <?= htmlspecialchars(substr($horafin, 0, 5)) ?></p>
            </div>
        </div>
    </div>
</header>

<main class="contenedor">

    <div class="mensaje">
        Tu voto es <strong>secreto y definitivo</strong>: solo puedes votar una vez y no podrás cambiarlo.
        Revisa bien antes de confirmar.
    </div>

    <?php CandidatosCajas($conn, $aprendiz, $jornada); ?>

</main>

<?php CerrarPagina(); ?>
