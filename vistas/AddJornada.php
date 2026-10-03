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
$filtroJornada = $_POST['filtro_jornada'] ?? '';

$eventoActivo = null;
if ($idEvento !== null) {
    $eventoActivo = ObtenerEvento($conn, $idEvento);
}

AbrirPagina('Programas de Formación', 1, 'programas');
BarraSuperior('Programas de Formación', 'Fichas habilitadas para el año activo');
?>

<main class="contenedor">

<?php if ($eventoActivo === null): ?>
    <div class="mensaje mensaje-aviso">
        No hay ningún año electoral activo. Ve a <a href="eventos.php">Años electorales</a> y activa uno antes de registrar programas.
    </div>
<?php else: ?>

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Registrar un programa</h2>
            <span class="rotulo"><?= htmlspecialchars($eventoActivo['evento']) ?> · <?= htmlspecialchars($eventoActivo['anio']) ?></span>
        </div>

        <form action="../back/AgregarFormacion.php" method="post" class="filtros">
            <div class="campo">
                <label for="ficha">Número de ficha</label>
                <input type="number" id="ficha" name="ficha" placeholder="Ejemplo: 3172078" required>
            </div>
            <div class="campo" style="flex:2 1 280px;">
                <label for="formacion">Nombre del programa</label>
                <input type="text" id="formacion" name="formacion" placeholder="Ejemplo: Análisis y Desarrollo de Software" required>
            </div>
            <div class="campo">
                <label for="jornada">Jornada</label>
                <select name="jornada" id="jornada" required>
                    <option value="">Seleccionar</option>
                    <?php OpcionesJornadas($conn, $idEvento); ?>
                </select>
            </div>
            <button type="submit" class="btn btn-solido">Registrar</button>
        </form>

        <p class="ayuda">
            El número de ficha es el mismo con el que los aprendices inician sesión, así que debe ser el real.
            Una ficha que ya participó en años anteriores <strong>sí</strong> puede registrarse de nuevo para este año.
        </p>
    </div>

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Importar desde archivo</h2>
            <span class="rotulo">CSV o Excel</span>
        </div>

        <form action="../back/CargaFormaciones.php" method="post" enctype="multipart/form-data" class="filtros">
            <div class="campo">
                <label for="archivo">Archivo</label>
                <input type="file" id="archivo" name="archivo" accept=".csv,.xls,.xlsx" required>
            </div>
            <button type="submit" class="btn btn-solido">Importar</button>
        </form>

        <p class="ayuda">
            Se admiten archivos <strong>.csv</strong>, <strong>.xls</strong> y <strong>.xlsx</strong> con tres columnas en este orden:
            <strong>ficha, formacion, jornada</strong>. La jornada se escribe por nombre y debe existir en el año activo.
            Si la ficha ya está registrada en esa misma jornada, se actualiza su nombre en lugar de duplicarla.
        </p>

        <p class="ayuda">
            ¿La importación falla y no sabes por qué?
            <a href="diagnostico.php">Revisa el diagnóstico del servidor</a>
            para probar el archivo y ver qué le falta al hosting.
        </p>

        <div class="tabla-marco" style="margin-top:16px;">
            <table class="tabla" style="min-width:auto;">
                <thead><tr><th>ficha</th><th>formacion</th><th>jornada</th></tr></thead>
                <tbody>
                    <tr><td><span class="num">3172078</span></td><td>COCINA</td><td>Mañana</td></tr>
                    <tr><td><span class="num">3144508</span></td><td>ANALISIS Y DESARROLLO DE SOFTWARE</td><td>Noche</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Programas registrados en <?= htmlspecialchars($eventoActivo['anio']) ?></h2>
            <span class="rotulo">Con su número de aprendices</span>
        </div>

        <form action="" method="post" class="filtros" style="margin-bottom:22px;" data-filtro-vivo>
            <div class="campo">
                <label for="filtro_jornada">Jornada</label>
                <select name="filtro_jornada" id="filtro_jornada">
                    <option value="">Todas</option>
                    <?php OpcionesJornadas($conn, $idEvento, $filtroJornada); ?>
                </select>
            </div>
            <div class="campo">
                <label for="buscar_programa">Buscar</label>
                <input type="text" id="buscar_programa" placeholder="Ficha o nombre del programa" data-filtra-tabla>
            </div>
            <button type="submit" class="btn btn-solido" data-solo-sin-js>Filtrar</button>
            <a href="AddJornada.php" class="btn btn-linea">Limpiar</a>
        </form>

        <?php ListarFormaciones($conn, $idEvento, $filtroJornada); ?>
    </div>

<?php endif; ?>

</main>

<?php CerrarPagina(); ?>
