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

$idEvento = EventoActivoId($conn);

$idJornada = $_POST['jornada'] ?? '';
$idFormacion = $_POST['formacion'] ?? '';
$idEstado = $_POST['estado'] ?? '';
$busqueda = $_POST['busqueda'] ?? '';

$eventoActivo = null;
if ($idEvento !== null) {
    $eventoActivo = ObtenerEvento($conn, $idEvento);
}

AbrirPagina('Gestión de Aprendices', 1, 'aprendices');
BarraSuperior('Gestión de Aprendices', 'Censo electoral del año activo');
?>

<main class="contenedor">

<?php if ($eventoActivo === null): ?>
    <div class="mensaje mensaje-aviso">
        No hay ningún año electoral activo. Ve a <a href="eventos.php">Años electorales</a> y activa uno antes de gestionar aprendices.
    </div>
<?php else: ?>

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Importar reporte de SofiaPlus</h2>
            <span class="rotulo">Crea la ficha y carga sus aprendices</span>
        </div>

        <p class="nota" style="margin-bottom:16px;">
            Sube el <strong>Reporte de Aprendices</strong> tal como lo descargas de SofiaPlus, sin editarlo.
            El sistema toma la ficha y el programa del propio archivo, y los crea si aún no existen en
            <?= htmlspecialchars($eventoActivo['anio']) ?>.
        </p>

        <form action="../back/CargaReporteSofia.php" method="post" enctype="multipart/form-data" class="filtros">
            <div class="campo">
                <label for="jornada_sofia">Jornada de la ficha</label>
                <select name="jornada" id="jornada_sofia" required>
                    <option value="">Seleccionar</option>
                    <?php OpcionesJornadas($conn, $idEvento); ?>
                </select>
            </div>
            <div class="campo" style="flex:2 1 260px;">
                <label for="archivo_sofia">Archivo del reporte</label>
                <input type="file" id="archivo_sofia" name="archivo" accept=".xls,.xlsx,.csv" required>
            </div>
            <button type="submit" class="btn btn-solido">Importar reporte</button>
        </form>

        <p class="ayuda">
            Admite <strong>.xls</strong> (el formato que entrega SofiaPlus), <strong>.xlsx</strong> y <strong>.csv</strong>.
            La jornada es el único dato que el reporte no incluye, por eso se elige aquí.
            Los estados se traducen solos: <em>EN FORMACION</em> → Habilitado, <em>EN INDUCCION</em> → Inducción,
            <em>RETIRO / CANCELADO / APLAZADO</em> → Deserción.
        </p>
    </div>

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Cargar aprendices desde una planilla propia</h2>
            <span class="rotulo">Formato manual</span>
        </div>

        <form action="../back/CargaAprendices.php" method="post" enctype="multipart/form-data" class="filtros">
            <div class="campo">
                <label for="archivo">Archivo CSV o Excel</label>
                <input type="file" id="archivo" name="archivo" accept=".csv,.xls,.xlsx" required>
            </div>
            <button type="submit" class="btn btn-solido">Cargar aprendices</button>
        </form>

        <p class="ayuda">
            Columnas requeridas, en este orden:
            <strong>nombre, documento, correo, jornada, formacion, estado</strong>.<br>
            La jornada se escribe por nombre y debe existir en el año activo (no distingue mayúsculas ni tildes).
            El estado debe ser <strong>Habilitado</strong> (puede votar), <strong>Inducción</strong> o <strong>Deserción</strong>.<br>
            Si el documento ya existe se actualizan sus datos en lugar de duplicarlo.
        </p>
    </div>

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Aprendices registrados</h2>
            <span class="rotulo">Solo los habilitados pueden votar</span>
        </div>

        <form class="filtros" action="" method="post" style="margin-bottom:22px;" data-filtro-vivo>
            <div class="campo">
                <label for="jornada">Jornada</label>
                <select name="jornada" id="jornada">
                    <option value="">Todas</option>
                    <?php OpcionesJornadas($conn, $idEvento, $idJornada); ?>
                </select>
            </div>
            <div class="campo">
                <label for="formacion">Programa de formación</label>
                <select name="formacion" id="formacion">
                    <option value="">Todos</option>
                    <?php OpcionesFormaciones($conn, $idEvento, $idJornada, $idFormacion); ?>
                </select>
            </div>
            <div class="campo">
                <label for="estado">Estado</label>
                <select name="estado" id="estado">
                    <option value="">Todos</option>
                    <?php
                    $resEstados = mysqli_query($conn, "SELECT * FROM estado ORDER BY id_estado");
                    while ($e = mysqli_fetch_assoc($resEstados)) {
                        $sel = ($idEstado == $e['id_estado']) ? 'selected' : '';
                        echo "<option value='" . $e['id_estado'] . "' $sel>" . htmlspecialchars($e['estados']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="campo">
                <label for="busqueda">Buscar</label>
                <input type="text" id="busqueda" name="busqueda" placeholder="Nombre o documento"
                       value="<?= htmlspecialchars($busqueda) ?>" data-filtra-tabla autocomplete="off">
            </div>
            <button type="submit" class="btn btn-solido" data-solo-sin-js>Filtrar</button>
            <a href="gestion_aprendices.php" class="btn btn-linea">Limpiar</a>
        </form>

        <?php ListarAprendices($conn, $idEvento, $idJornada, $idEstado, $busqueda, $idFormacion); ?>
    </div>

<?php endif; ?>

</main>

<?php CerrarPagina(); ?>
