<?php
require_once('conn/conn.php');

$mensaje = strtolower(trim($_POST['mensaje'] ?? ''));
$respuetaVillaBot = '';

if (strpos($mensaje, 'candidatos hay') !== false) {
    $sql = "SELECT COUNT(*) AS total_candidatos FROM candidatos";
    $stmt = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($stmt);
    $respuetaVillaBot = 'Actualmente contamos con ' . $data["total_candidatos"] . ' candidatos registrados';
}

/* Cada rol de la base se presenta como un botón propio */
$presentacion = [
    2 => ['icono' => '🎓', 'detalle' => 'Ingresa con tu número de ficha y documento para votar por tu representante.'],
    1 => ['icono' => '⚙️', 'detalle' => 'Gestiona el año electoral, los candidatos, las fichas y los aprendices habilitados.'],
    3 => ['icono' => '🔎', 'detalle' => 'Consulta los resultados y la participación por ficha, en modo solo lectura.'],
];

$roles = [];
$res = mysqli_query($conn, "SELECT id_rol, roles FROM roles ORDER BY FIELD(id_rol,2,1,3), id_rol");
while ($fila = mysqli_fetch_assoc($res)) {
    $roles[] = $fila;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elección de Representantes · SENA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500;600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/sena.css?v=<?= @filemtime(__DIR__ . '/css/sena.css') ?: date('YmdH') ?>">
    <style>
        .consulta{
            max-width:640px;
            margin:44px auto 0;
            border-top:var(--borde);
            padding-top:26px;
        }
        .consulta-fila{display:flex;gap:10px;flex-wrap:wrap;}
        .consulta-fila input{flex:1 1 260px;}
        .consulta-respuesta{
            background:var(--blanco);
            border:var(--borde);
            border-left:3px solid var(--brote);
            border-radius:var(--r-s);
            padding:13px 16px;
            margin-bottom:14px;
            font-size:.9rem;
            animation:emerger .4s ease both;
        }
        @media (max-width:720px){ .consulta-fila .btn{width:100%;} }
    </style>
</head>
<body class="portada">

<header class="barra">
    <div class="barra-interior">
        <div class="barra-marca">
            <div class="barra-sello">SENA</div>
            <div>
                <h1>Elección de Representantes</h1>
                <p>Servicio Nacional de Aprendizaje</p>
            </div>
        </div>
    </div>
</header>

<main class="portada-cuerpo">
    <div class="portada-caja">

        <div class="portada-encabezado">
            <h1>Bienvenido al sistema de votación</h1>
            <p>Selecciona el perfil con el que vas a ingresar para continuar con el proceso electoral.</p>
        </div>

        <div class="roles">
            <?php foreach ($roles as $rol):
                $id = (int)$rol['id_rol'];
                $datos = $presentacion[$id] ?? ['icono' => '👤', 'detalle' => 'Ingresar al sistema.'];
            ?>
                <form action="back/ValidateRol.php" method="post">
                    <input type="hidden" name="rolEscogido" value="<?= $id ?>">
                    <button type="submit" class="rol">
                        <span class="rol-icono"><?= $datos['icono'] ?></span>
                        <span class="rol-nombre"><?= htmlspecialchars($rol['roles']) ?></span>
                        <span class="rol-detalle"><?= htmlspecialchars($datos['detalle']) ?></span>
                        <span class="rol-flecha">Ingresar →</span>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>

        <section class="consulta">
            <p class="rotulo" style="margin-bottom:12px;">Consulta rápida</p>
            <?php if (!empty($respuetaVillaBot)): ?>
                <div class="consulta-respuesta"><?= htmlspecialchars($respuetaVillaBot) ?></div>
            <?php endif; ?>
            <form action="" method="post" class="consulta-fila">
                <input type="text" name="mensaje" placeholder="Ejemplo: ¿cuántos candidatos hay?" required>
                <button type="submit" class="btn btn-linea">Consultar</button>
            </form>
        </section>

    </div>
</main>

<footer class="pie">
    <?= date("Y") ?> · Servicio Nacional de Aprendizaje SENA · Elección de Representantes
</footer>

</body>
</html>
