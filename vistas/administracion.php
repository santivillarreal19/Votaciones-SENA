<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');
require_once('_plantilla.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['rol'])) {
    echo '<script>alert("No puedes acceder como administrador");window.location.href="../";</script>';
    exit();
}
elseif ($_SESSION['rol'] === 3) {
    $rol = 3;
}
else {
    $rol = 1;
}

$filtro = $_POST['filtro'] ?? '';
$idEventoActivo = EventoActivoId($conn);
$eventoSeleccionado = $_POST['evento'] ?? $idEventoActivo;
$soloLectura = ($eventoSeleccionado != $idEventoActivo);

AbrirPagina('Panel de Administración', $rol, 'panel');
BarraSuperior('Panel de Administración', $rol === 3 ? 'Perfil auditor' : 'Perfil administrador');
?>

<main class="contenedor">

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Resultados de la votación</h2>
            <span class="rotulo">Consulta por año y jornada</span>
        </div>

        <form action="" method="post" class="filtros" data-filtro-vivo>
            <div class="campo">
                <label for="evento">Año electoral</label>
                <select name="evento" id="evento">
                    <?php OpcionesEventos($conn, $eventoSeleccionado); ?>
                </select>
            </div>
            <div class="campo">
                <label for="filtro">Jornada</label>
                <select name="filtro" id="filtro">
                    <option value="">Todas las jornadas</option>
                    <?php OpcionesJornadas($conn, $eventoSeleccionado, $filtro); ?>
                </select>
            </div>
            <button type="submit" class="btn btn-solido" data-solo-sin-js>Consultar</button>
            <a href="?" class="btn btn-linea">Limpiar</a>
        </form>
    </div>

    <div class="tarjeta">
        <?php VistaAdmin($conn, $eventoSeleccionado, $filtro, $rol, $soloLectura); ?>
    </div>

</main>

<?php CerrarPagina(); ?>
