<?php
require_once('_plantilla.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['rol'])) {
    echo '<script>alert("No has escogido un Rol");window.location.href="../";</script>';
    exit();
}
elseif ($_SESSION['rol'] != "2") {
    echo '<script>alert("Escoge el rol de aprendiz para acceder");window.location.href="../";</script>';
    exit();
}

$aprendiz = $_SESSION['rol'];

AbrirPagina('Ingreso Aprendiz');
?>

<header class="barra">
    <div class="barra-interior">
        <div class="barra-marca">
            <div class="barra-sello">SENA</div>
            <div>
                <h1>Elección de Representantes</h1>
                <p>Acceso aprendiz</p>
            </div>
        </div>
        <a href="../" class="btn btn-claro">← Cambiar de perfil</a>
    </div>
</header>

<main class="contenedor-angosto">
    <div class="acceso">
        <div class="acceso-cabecera">
            <h1>Ingreso de Aprendiz</h1>
            <p>Identifícate para emitir tu voto. Solo puedes votar una vez.</p>
        </div>

        <div class="acceso-cuerpo">
            <form action="../back/ingreso.php" method="post">
                <input type="hidden" name="rol" value="<?= htmlspecialchars($aprendiz) ?>">

                <div class="campo">
                    <label for="ficha">Número de ficha</label>
                    <input type="number" id="ficha" name="ficha" placeholder="Ejemplo: 3172078" required autofocus>
                </div>

                <div class="campo">
                    <label for="numdoc">Contraseña</label>
                    <input type="password" id="numdoc" name="numdoc" placeholder="Número de documento"
                           oninput="this.value = this.value.replace(/[^0-9]/g,'')" required>
                    <p class="ayuda">Tu contraseña es tu número de documento, sin puntos ni espacios.</p>
                </div>

                <button type="submit" class="btn btn-solido btn-ancho">Ingresar a votar</button>
            </form>
        </div>

        <div class="acceso-pie">
            Si tus datos no aparecen, comunícate con tu instructor o con Bienestar al Aprendiz.
        </div>
    </div>
</main>

<?php CerrarPagina(); ?>
