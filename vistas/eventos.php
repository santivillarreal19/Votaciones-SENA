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

/* La sidebar entra aquí con ?nuevo=1 para crear un año electoral */
$abrirNuevo = isset($_GET['nuevo']) && $_GET['nuevo'] == '1';

AbrirPagina('Años Electorales', 1, 'anios');
BarraSuperior('Años Electorales', 'Administración del proceso');
?>

<main class="contenedor">

    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h2>Eventos registrados</h2>
            <button type="button" class="btn btn-lima" onclick="document.getElementById('modalEvento').showModal()">Nuevo año electoral</button>
        </div>

        <p class="nota" style="margin-bottom:18px;">
            Usa <strong>Jornadas</strong> para definir las jornadas de votación de cada año y sus horarios.
            Los años ya finalizados solo permiten consultar su historial.
        </p>

        <?php MostrarEventos($conn); ?>
    </div>

</main>

<dialog id="modalEvento">
    <div class="modal-cabecera">
        <h2>Nuevo año electoral</h2>
        <button type="button" class="modal-cerrar" onclick="document.getElementById('modalEvento').close()">✕</button>
    </div>
    <div class="modal-cuerpo">
        <form action="../back/RegEvento.php" method="post">
            <div class="campo">
                <label for="event_name">Nombre del evento</label>
                <input type="text" id="event_name" name="event_name" placeholder="Ejemplo: Elección de Representantes <?= date("Y") ?>" required>
            </div>

            <div class="campo">
                <label for="anio_visible">Año</label>
                <input type="number" id="anio_visible" value="<?= date("Y") ?>" disabled>
                <p class="ayuda">Solo se pueden crear eventos para el año en curso.</p>
            </div>

            <div class="campo">
                <label for="fechaini">Fecha de inicio</label>
                <input type="date" id="fechaini" name="fechaini" required>
            </div>

            <div class="campo">
                <label for="fechafin">Fecha de cierre</label>
                <input type="date" id="fechafin" name="fechafin" required>
            </div>

            <p class="ayuda" style="margin-bottom:16px;">Al crearlo se generan automáticamente sus jornadas: Mañana, Tarde, Noche, Virtual y A Distancia.</p>

            <button type="submit" class="btn btn-solido btn-ancho">Crear año electoral</button>
        </form>
    </div>
</dialog>

<dialog id="modalFechas">
    <div class="modal-cabecera">
        <h2>Modificar fechas del evento</h2>
        <button type="button" class="modal-cerrar" onclick="document.getElementById('modalFechas').close()">✕</button>
    </div>
    <div class="modal-cuerpo">
        <form action="../back/edit_evento.php" method="post">
            <input type="hidden" name="id_evento" id="id_evento" required>

            <div class="campo">
                <label for="fecha_inicio">Fecha de inicio</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio" required>
            </div>

            <div class="campo">
                <label for="fecha_fin">Fecha de cierre</label>
                <input type="date" name="fecha_fin" id="fecha_fin" required>
            </div>

            <button type="submit" class="btn btn-solido btn-ancho">Actualizar evento</button>
        </form>
    </div>
</dialog>

<?php if ($abrirNuevo): ?>
<script>document.getElementById('modalEvento').showModal();</script>
<?php endif; ?>

<?php CerrarPagina(); ?>
