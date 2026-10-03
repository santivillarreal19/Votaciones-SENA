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

AbrirPagina('Agregar Candidato', 1, 'candidatos');
BarraSuperior('Inscribir candidato', 'Paso 1 de 2 · Jornada');
?>

<main class="contenedor-angosto">
    <div class="acceso">
        <div class="acceso-cabecera">
            <h1>¿A qué jornada pertenece?</h1>
            <p>El candidato solo aparecerá ante los aprendices de la jornada que elijas.</p>
        </div>

        <div class="acceso-cuerpo">
            <form action="AddCandidato.php" method="post">
                <div class="campo">
                    <label for="jornada">Jornada del candidato</label>
                    <select name="jornada" id="jornada" required autofocus>
                        <option value="">Seleccionar jornada</option>
                        <?php OpcionesJornadas($conn, EventoActivoId($conn)); ?>
                    </select>
                    <p class="ayuda">Solo se muestran las jornadas del año electoral activo.</p>
                </div>

                <button type="submit" class="btn btn-solido btn-ancho">Continuar</button>
            </form>
        </div>

        <div class="acceso-pie">
            Paso 1 de 2 · A continuación cargarás sus datos y su fotografía.
        </div>
    </div>
</main>

<?php CerrarPagina(); ?>
