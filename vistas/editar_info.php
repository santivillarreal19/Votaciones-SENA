<?php
require_once('../conn/conn.php');
require_once('_plantilla.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    echo '<script>alert("No puedes acceder como administrador");window.location.href="../";</script>';
    exit();
}

$id_edit = $_GET['idcandidato'] ?? '';

if ($id_edit == null) {
    echo '<script>alert("No se indicó el candidato a editar");window.location.href="administracion.php";</script>';
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT tarjeton, nombre, foto FROM candidatos WHERE id_candidato = ?");
mysqli_stmt_bind_param($stmt, "i", $id_edit);
mysqli_stmt_execute($stmt);
$candidato = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$candidato) {
    echo '<script>alert("Ese candidato no existe");window.location.href="administracion.php";</script>';
    exit();
}

AbrirPagina('Editar Candidato', 1, 'panel');
BarraSuperior('Editar candidato', 'Tarjetón N.º ' . (int)$candidato['tarjeton'], 'administracion.php', 'Panel');
?>

<main class="contenedor-angosto">
    <div class="acceso">
        <div class="acceso-cabecera">
            <h1><?= htmlspecialchars($candidato['nombre']) ?></h1>
            <p>Actualiza el nombre o reemplaza la fotografía.</p>
        </div>

        <div class="acceso-cuerpo">
            <?php if (!empty($candidato['foto'])): ?>
                <div class="campo">
                    <span class="etiqueta">Fotografía actual</span>
                    <img src="<?= htmlspecialchars($candidato['foto']) ?>" alt="Fotografía actual"
                         style="width:130px;height:130px;object-fit:cover;border-radius:var(--r-m);border:var(--borde);">
                </div>
            <?php endif; ?>

            <form action="../back/edit_info.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="idcandidato" value="<?= htmlspecialchars($id_edit) ?>">

                <div class="campo">
                    <label for="name">Nombre completo</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($candidato['nombre']) ?>" required>
                </div>

                <div class="campo">
                    <label for="foto">Nueva fotografía</label>
                    <input type="file" id="foto" name="foto" accept="image/*">
                </div>

                <button type="submit" class="btn btn-solido btn-ancho">Guardar cambios</button>
            </form>
        </div>
    </div>
</main>

<?php CerrarPagina(); ?>
