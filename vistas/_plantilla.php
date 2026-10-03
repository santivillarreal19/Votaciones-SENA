<?php
/* ============================================================
   Plantilla compartida de todas las vistas.
   Centraliza <head>, la navegación lateral, la barra superior
   y el pie, para que el sistema se vea igual en todas las
   pantallas.

   Uso típico en una vista administrativa:
       AbrirPagina('Panel', $rol, 'panel');
       BarraSuperior('Panel', 'Perfil administrador');
       ... contenido ...
       CerrarPagina();
   ============================================================ */

/* Devuelve la fecha de modificación del archivo para usarla como versión
   en la URL del CSS/JS. Así, cada vez que se sube una versión nueva al
   servidor, el navegador la descarga sola en lugar de usar la cacheada. */
function VersionArchivo($rutaDesdeRaiz)
{
    $ruta = dirname(__DIR__) . $rutaDesdeRaiz;
    $marca = @filemtime($ruta);

    return $marca !== false ? $marca : date('YmdH');
}

/* Secciones del menú lateral: clave => [ícono, texto, destino, roles] */
function SeccionesNavegacion()
{
    return [
        'panel'      => ['◧', 'Panel de resultados',   'administracion.php',      [1, 3]],
        'candidatos' => ['◆', 'Inscribir candidato',   'AddCandidatoJornada.php', [1]],
        'programas'  => ['▤', 'Programas de formación','AddJornada.php',          [1]],
        'aprendices' => ['◉', 'Gestión de aprendices', 'gestion_aprendices.php',  [1]],
        'anios'      => ['▣', 'Años electorales',      'eventos.php',             [1]],
        'fichas'     => ['◈', 'Participación por ficha','fichasVotaciones.php',   [1, 3]],
    ];
}

function AbrirPagina($titulo, $rolNavegacion = null, $seccionActiva = '')
{
    $GLOBALS['navLateral'] = $rolNavegacion;
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> · Votaciones SENA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500;600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/sena.css?v=<?= VersionArchivo('/css/sena.css') ?>">
    <script src="../js/sena.js?v=<?= VersionArchivo('/js/sena.js') ?>" defer></script>
</head>
<body>
    <?php
    if ($rolNavegacion !== null) {
        NavegacionLateral((int)$rolNavegacion, $seccionActiva);
    }
    ?>
    <div class="lienzo">
        <?php if ($rolNavegacion !== null): ?>
            <div class="velo" id="veloNav" aria-hidden="true"></div>
        <?php endif; ?>
    <?php
}

function NavegacionLateral($rol, $seccionActiva)
{
    $perfil = ($rol === 3) ? 'Auditor' : 'Administrador';
    ?>
    <nav class="nav-lateral" id="navLateral" aria-label="Menú principal">
        <div class="nav-marca">
            <div class="nav-marca-sello">SENA</div>
            <div>
                <strong>Votaciones</strong>
                <span><?= htmlspecialchars($perfil) ?></span>
            </div>
        </div>

        <p class="nav-grupo">Secciones</p>

        <?php foreach (SeccionesNavegacion() as $clave => $seccion):
            [$icono, $texto, $destino, $roles] = $seccion;
            if (!in_array($rol, $roles, true)) { continue; }
            $activo = ($clave === $seccionActiva) ? ' nav-enlace-activo' : '';
        ?>
            <a class="nav-enlace<?= $activo ?>" href="<?= $destino ?>"<?= $activo !== '' ? ' aria-current="page"' : '' ?>>
                <span class="nav-icono" aria-hidden="true"><?= $icono ?></span>
                <?= htmlspecialchars($texto) ?>
            </a>
        <?php endforeach; ?>

        <?php if ($rol === 1): ?>
            <p class="nav-grupo">Acciones</p>
            <a class="nav-enlace" href="eventos.php?nuevo=1">
                <span class="nav-icono" aria-hidden="true">＋</span>
                Nuevo año electoral
            </a>
        <?php endif; ?>

        <div class="nav-salir">
            <a class="nav-enlace" href="../">
                <span class="nav-icono" aria-hidden="true">⤺</span>
                Cerrar sesión
            </a>
        </div>
    </nav>
    <?php
}

function BarraSuperior($titulo, $subtitulo = '', $enlaceVolver = null, $textoVolver = 'Volver')
{
    $conMenu = isset($GLOBALS['navLateral']) && $GLOBALS['navLateral'] !== null;
    ?>
    <header class="barra">
        <div class="barra-interior">
            <div class="barra-marca">
                <?php if ($conMenu): ?>
                    <button type="button" class="boton-menu" id="botonMenu"
                            aria-controls="navLateral" aria-expanded="false" aria-label="Abrir menú">
                        <span></span><span></span><span></span>
                    </button>
                <?php endif; ?>
                <div class="barra-sello">SENA</div>
                <div>
                    <h1><?= htmlspecialchars($titulo) ?></h1>
                    <?php if ($subtitulo !== ''): ?>
                        <p><?= htmlspecialchars($subtitulo) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($enlaceVolver !== null): ?>
                <a href="<?= htmlspecialchars($enlaceVolver) ?>" class="btn btn-claro">← <?= htmlspecialchars($textoVolver) ?></a>
            <?php endif; ?>
        </div>
    </header>
    <?php
}

function CerrarPagina()
{
    $conMenu = isset($GLOBALS['navLateral']) && $GLOBALS['navLateral'] !== null;
    ?>
    <footer class="pie">
        <?= date("Y") ?> · Servicio Nacional de Aprendizaje SENA · Elección de Representantes
    </footer>
    </div>

    <?php if ($conMenu): ?>
    <script>
    (function () {
        var cuerpo = document.body;
        var boton  = document.getElementById('botonMenu');
        var velo   = document.getElementById('veloNav');
        var CLAVE  = 'senaNavAbierta';

        function aplicar(abierta, recordar) {
            cuerpo.classList.toggle('nav-abierta', abierta);
            if (boton) {
                boton.setAttribute('aria-expanded', abierta ? 'true' : 'false');
                boton.setAttribute('aria-label', abierta ? 'Cerrar menú' : 'Abrir menú');
            }
            if (recordar) {
                try { localStorage.setItem(CLAVE, abierta ? '1' : '0'); } catch (e) {}
            }
        }

        /* Recuerda el estado entre pantallas, pero nunca lo abre solo en móvil */
        try {
            if (localStorage.getItem(CLAVE) === '1' && window.innerWidth > 860) {
                var lienzo = document.querySelector('.lienzo');
                if (lienzo) { lienzo.style.transition = 'none'; }
                aplicar(true, false);
                requestAnimationFrame(function () {
                    if (lienzo) { lienzo.style.transition = ''; }
                });
            }
        } catch (e) {}

        if (boton) {
            boton.addEventListener('click', function () {
                aplicar(!cuerpo.classList.contains('nav-abierta'), true);
            });
        }

        if (velo) {
            velo.addEventListener('click', function () { aplicar(false, true); });
        }

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && cuerpo.classList.contains('nav-abierta')) {
                aplicar(false, true);
            }
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
    <?php
}
