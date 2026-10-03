<?php
require_once('_plantilla.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['rol'])) {
    echo '<script>alert("No has escogido un Rol");window.location.href="../";</script>';
    exit();
}
elseif ($_SESSION['rol'] != 1 && $_SESSION['rol'] != 3) {
    echo '<script>alert("No tienes acceso a esta vista");window.location.href="../";</script>';
    exit();
}

$esAuditor = ($_SESSION['rol'] == 3);
$perfil = $esAuditor ? 'Auditor' : 'Administrador';

AbrirPagina('Ingreso ' . $perfil);
?>

<header class="barra">
    <div class="barra-interior">
        <div class="barra-marca">
            <div class="barra-sello">SENA</div>
            <div>
                <h1>Elección de Representantes</h1>
                <p>Acceso <?= strtolower($perfil) ?></p>
            </div>
        </div>
        <a href="../" class="btn btn-claro">← Cambiar de perfil</a>
    </div>
</header>

<main class="contenedor-angosto">
    <div class="acceso">
        <div class="acceso-cabecera">
            <h1>Ingreso de <?= htmlspecialchars($perfil) ?></h1>
            <p>Autentícate con tu correo institucional para continuar.</p>
        </div>

        <div class="acceso-cuerpo">
            <form action="../back/ingreso.php" method="post">
                <div class="campo">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" placeholder="nombre@sena.edu.co" required autofocus>
                </div>

                <div class="campo">
                    <label for="numdoc">Contraseña</label>
                    <input type="password" id="numdoc" name="numdoc" placeholder="Número de documento"
                           oninput="this.value = this.value.replace(/[^0-9]/g,'')" required>
                    <p class="ayuda">Tu contraseña es tu número de documento, sin puntos ni espacios.</p>
                </div>

                <button type="submit" class="btn btn-solido btn-ancho">Ingresar</button>
            </form>
        </div>

        <div class="acceso-pie">
            ¿Problemas para ingresar? Comunícate con Bienestar al Aprendiz.
        </div>
    </div>
</main>

<?php CerrarPagina(); ?>
