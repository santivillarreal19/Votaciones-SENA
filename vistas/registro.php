<?php
require_once('../conn/conn.php');
require_once('../model/functions.php');
require_once('_plantilla.php');

$jornada = $_POST['jornada'] ?? '';

if ($jornada === '') {
    echo '<script>alert("Primero debes escoger tu jornada");window.location.href="jornada.php";</script>';
    exit();
}

AbrirPagina('Registro de Aprendiz');
BarraSuperior('Registro de Aprendiz', 'Paso 2 de 2 · Tus datos', 'jornada.php', 'Atrás');
?>

<main class="contenedor-angosto">
    <div class="acceso">
        <div class="acceso-cabecera">
            <h1>Completa tu registro</h1>
            <p>Estos datos se usan únicamente para habilitar tu voto.</p>
        </div>

        <div class="acceso-cuerpo">
            <form action="../back/send_register.php" method="post">
                <input type="hidden" name="jornada" value="<?= htmlspecialchars($jornada) ?>">

                <div class="campo">
                    <label for="nombreReg">Nombre completo</label>
                    <input type="text" id="nombreReg" name="nombreReg"
                           oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g,'')"
                           placeholder="Nombres y apellidos" required autofocus>
                    <p class="ayuda">Solo letras y espacios.</p>
                </div>

                <div class="campo">
                    <label for="docRegister">Número de documento</label>
                    <input type="number" id="docRegister" name="docRegister" max="2000000000"
                           placeholder="Sin puntos ni comas" required>
                </div>

                <div class="campo">
                    <label for="correoRegister">Correo electrónico</label>
                    <input type="email" id="correoRegister" name="correoRegister"
                           placeholder="nombre@sena.edu.co" required>
                </div>

                <div class="campo">
                    <label for="formacion">Programa de formación</label>
                    <select name="formacion" id="formacion" required>
                        <option value="">Seleccionar programa</option>
                        <?php Formaciones($conn, $jornada); ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-solido btn-ancho">Completar registro</button>
            </form>
        </div>

        <div class="acceso-pie">
            🔒 Tus datos están protegidos y solo se usan con fines académicos.
        </div>
    </div>
</main>

<?php CerrarPagina(); ?>
