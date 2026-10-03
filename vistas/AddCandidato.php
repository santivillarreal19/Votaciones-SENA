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

$jornada = $_POST['jornada'] ?? '';

if ($jornada === '') {
    echo '<script>alert("Primero debes escoger la jornada del candidato");window.location.href="AddCandidatoJornada.php";</script>';
    exit();
}

$stmtJornada = mysqli_prepare($conn, "SELECT jornada FROM jornada WHERE id_jornada = ?");
mysqli_stmt_bind_param($stmtJornada, "i", $jornada);
mysqli_stmt_execute($stmtJornada);
$filaJornada = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtJornada));
$nombreJornada = $filaJornada['jornada'] ?? '';

AbrirPagina('Datos del Candidato', 1, 'candidatos');
BarraSuperior('Inscribir candidato', 'Paso 2 de 2 · Datos', 'AddCandidatoJornada.php', 'Atrás');
?>

<main class="contenedor-angosto" style="max-width:620px;">
    <div class="acceso">
        <div class="acceso-cabecera">
            <h1>Datos del candidato</h1>
            <p>Jornada <?= htmlspecialchars($nombreJornada) ?></p>
        </div>

        <div class="acceso-cuerpo">
            <form action="../back/RegCandidato.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="idjornada" value="<?= htmlspecialchars($jornada) ?>">

                <div class="campo">
                    <label for="candidatoName">Nombre completo</label>
                    <input type="text" id="candidatoName" name="candidatoName" placeholder="Nombres y apellidos" required autofocus>
                </div>

                <div class="campo">
                    <label for="candidatoTarj">Número de tarjetón</label>
                    <input type="number" id="candidatoTarj" name="candidatoTarj" placeholder="Ejemplo: 1" required>
                    <p class="ayuda">Debe ser único dentro de este año electoral. Puede repetirse respecto a años anteriores.</p>
                </div>

                <div class="campo">
                    <label for="formacion">Programa de formación</label>
                    <select name="formacion" id="formacion" required>
                        <option value="">Seleccionar programa</option>
                        <?php Formaciones($conn, $jornada); ?>
                    </select>
                    <p class="ayuda">Solo aparecen los programas registrados en esta jornada.</p>
                </div>

                <div class="campo">
                    <label for="candidatoCorreo">Correo electrónico</label>
                    <input type="email" id="candidatoCorreo" name="candidatoCorreo" placeholder="nombre@sena.edu.co">
                </div>

                <div class="campo">
                    <label for="Slogan">Lema de campaña</label>
                    <input type="text" id="Slogan" name="Slogan" placeholder="Frase con la que se presenta">
                </div>

                <div class="campo">
                    <label for="foto">Fotografía</label>
                    <input type="file" id="foto" name="foto" accept="image/*" required>
                    <p class="ayuda">Se recomienda una imagen cuadrada, de rostro y fondo claro.</p>
                </div>

                <button type="submit" class="btn btn-solido btn-ancho">Inscribir candidato</button>
            </form>
        </div>

        <div class="acceso-pie">
            Paso 2 de 2 · Podrás editar sus datos después desde el panel.
        </div>
    </div>
</main>

<?php CerrarPagina(); ?>
