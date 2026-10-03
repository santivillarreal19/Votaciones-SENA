<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');
require_once('_plantilla.php');

AbrirPagina('Registro de Aprendiz');
BarraSuperior('Registro de Aprendiz', 'Paso 1 de 2 · Jornada', '../', 'Inicio');
?>

<main class="contenedor-angosto">
    <div class="acceso">
        <div class="acceso-cabecera">
            <h1>Selecciona tu jornada</h1>
            <p>Con ella cargaremos los programas de formación disponibles.</p>
        </div>

        <div class="acceso-cuerpo">
            <form action="registro.php" method="post">
                <div class="campo">
                    <label for="jornada">Jornada académica</label>
                    <select name="jornada" id="jornada" required autofocus>
                        <option value="">Seleccionar jornada</option>
                        <?php OpcionesJornadas($conn, EventoActivoId($conn)); ?>
                    </select>
                    <p class="ayuda">Es la jornada en la que cursas tu programa actualmente.</p>
                </div>

                <button type="submit" class="btn btn-solido btn-ancho">Continuar</button>
            </form>
        </div>

        <div class="acceso-pie">
            ¿Tienes dudas? Comunícate con Bienestar al Aprendiz.
        </div>
    </div>
</main>

<?php CerrarPagina(); ?>
