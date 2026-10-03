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
$filtro2 = $_POST['filtro2'] ?? '';
$idEventoActivo = EventoActivoId($conn);

AbrirPagina('Participación por Ficha', $rol, 'fichas');
BarraSuperior('Participación por Ficha', $rol === 3 ? 'Perfil auditor' : 'Perfil administrador');
?>

<main class="contenedor">

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Avance de la votación</h2>
            <span class="rotulo">Aprendices que ya votaron por ficha</span>
        </div>

        <form action="" method="post" class="filtros" style="margin-bottom:22px;" data-filtro-vivo>
            <div class="campo" style="flex:2 1 280px;">
                <label for="filtro">Ficha</label>
                <select name="filtro" id="filtro">
                    <option value="">Todas las fichas</option>
                    <?php
                    $stmtFichas = mysqli_prepare($conn, "SELECT f.id_formacion, f.ficha, f.formacion
                                                         FROM formacion f
                                                         INNER JOIN jornada j ON f.id_jornada = j.id_jornada
                                                         WHERE j.id_evento = ? AND j.jornada <> 'Administrador'
                                                         ORDER BY f.formacion");
                    mysqli_stmt_bind_param($stmtFichas, "i", $idEventoActivo);
                    mysqli_stmt_execute($stmtFichas);
                    $stmt = mysqli_stmt_get_result($stmtFichas);
                    while ($row = mysqli_fetch_assoc($stmt)) {
                        $selected = ($filtro == $row['id_formacion']) ? 'selected' : '';
                        echo "<option value='" . $row['id_formacion'] . "' $selected>" . $row["ficha"] . " · " . htmlspecialchars($row['formacion']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="campo">
                <label for="filtro2">Jornada</label>
                <select name="filtro2" id="filtro2">
                    <option value="">Todas las jornadas</option>
                    <?php OpcionesJornadas($conn, $idEventoActivo, $filtro2); ?>
                </select>
            </div>
            <button type="submit" class="btn btn-solido" data-solo-sin-js>Consultar</button>
            <a href="?" class="btn btn-linea">Limpiar</a>
        </form>

        <?php VotosPorFichas($conn, $filtro, $filtro2, $idEventoActivo); ?>
    </div>

</main>

<?php CerrarPagina(); ?>
