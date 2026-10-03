<?php

require_once('../conn/conn.php');
require_once(__DIR__ . '/lector_archivos.php');

/* ------------------------------------------------------------
   Errores de base de datos
   ------------------------------------------------------------
   Desde PHP 8.1 mysqli lanza excepciones en vez de devolver
   false. Si nadie las captura, el servidor responde con un
   error 500 en blanco y no hay forma de saber qué pasó. Esta
   función traduce la excepción a algo accionable.
   ------------------------------------------------------------ */

function MotivoErrorBaseDatos($e)
{
    $texto = $e->getMessage();

    /* Los saltos de línea romperían el alert() de JavaScript */
    $texto = trim(preg_replace('/\s+/', ' ', $texto));

    if (stripos($texto, 'Unknown column') !== false
        || stripos($texto, "doesn't exist") !== false
        || stripos($texto, 'no existe') !== false) {
        return 'La base de datos del servidor está desactualizada y le falta algo que el sistema necesita. '
             . 'Detalle: ' . $texto . ' | Solución: entra a phpMyAdmin, abre la pestaña SQL y ejecuta el '
             . 'archivo conn/migracion_cpanel.sql.';
    }

    if (stripos($texto, 'Duplicate entry') !== false) {
        return 'Hay un registro duplicado que impide guardar. Detalle: ' . $texto;
    }

    if (stripos($texto, 'foreign key') !== false) {
        return 'Un dato hace referencia a otro que no existe (jornada o formación). Detalle: ' . $texto;
    }

    return 'Error de base de datos: ' . $texto;
}

function Formaciones($conn,$jornada)
{
    $sql = "SELECT * FROM formacion WHERE  id_jornada = ?";

    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"i",$jornada);

    mysqli_stmt_execute($stmt);

    $res = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($res)) {
        echo "<option value=".$row['id_formacion'].">".$row['ficha']."  ----  ".htmlspecialchars($row['formacion'])."</option>";
    }

}

function ObtenerEvento($conn,$idEvento)
{
    $stmt = mysqli_prepare($conn,"SELECT * FROM eventos WHERE id_evento = ?");
    mysqli_stmt_bind_param($stmt,"i",$idEvento);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($res);
}

function EventoActivoId($conn)
{
    $res = mysqli_query($conn,"SELECT id_evento FROM eventos WHERE activo = 1 ORDER BY id_evento DESC LIMIT 1");
    if ($row = mysqli_fetch_assoc($res)) {
        return (int)$row['id_evento'];
    }
    return null;
}

function OpcionesEventos($conn,$selected = null)
{
    $res = mysqli_query($conn,"SELECT id_evento, evento, anio, activo FROM eventos ORDER BY anio DESC, id_evento DESC");
    while ($row = mysqli_fetch_assoc($res)) {
        $sel = ($selected !== null && $selected == $row['id_evento']) ? 'selected' : '';
        $etiqueta = $row['anio'] . ' - ' . htmlspecialchars($row['evento']) . ($row['activo'] ? ' (Activo)' : '');
        echo "<option value='".$row['id_evento']."' $sel>".$etiqueta."</option>";
    }
}

function OpcionesJornadas($conn,$idEvento,$selected = null)
{
    $sql = "SELECT * FROM jornada WHERE id_evento = ? AND jornada <> 'Administrador' ORDER BY id_jornada";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"i",$idEvento);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($res)) {
        $sel = ($selected !== null && $selected == $row['id_jornada']) ? 'selected' : '';
        echo "<option value='".$row['id_jornada']."' $sel>".htmlspecialchars($row['jornada'])."</option>";
    }
}

function RegistrarAprendiz($conn,$nombre,$numDoc,$formacion,$correo)
{
    $validate = "SELECT id_aprendiz FROM aprendiz WHERE documento = ?";
    $validate1 = mysqli_prepare($conn,$validate);
    mysqli_stmt_bind_param($validate1,"s",$numDoc);

    mysqli_stmt_execute($validate1);
    $res = mysqli_stmt_get_result($validate1);

    if (mysqli_num_rows($res) > 0) {
        echo
        '
        <script>
        alert("Ese número de documento ya está registrado en el sistema");
        window.location.href="../vistas/jornada.php";
        </script>
        ';
        exit;
    }

    $idrol = 2;
    $idestado = 1;
    $sql = "INSERT INTO aprendiz (nombre,documento,correo,id_formacion,id_rol,id_estado) VALUES (?,?,?,?,?,?)";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"sssiii",$nombre,$numDoc,$correo,$formacion,$idrol,$idestado);

    if (mysqli_stmt_execute($stmt)) {
        $nombreSeguro = addslashes($nombre);
        echo
        '
        <script>
        alert("'.$nombreSeguro.', tu registro quedó completo. Ya puedes ingresar a votar.");
        window.location.href="../";
        </script>
        ';
    }
}

function ValidateLogin($conn,$docLogin,$correo,$rol,$ficha)
{
    

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if(empty($rol))
        {
        $sql = "SELECT f.*, a.*, j.*
        FROM formacion AS f 
        INNER JOIN aprendiz AS a ON a.id_formacion = f.id_formacion 
        INNER JOIN jornada AS j ON f.id_jornada = j.id_jornada 
        WHERE documento = ? AND correo = ?";
        
        $stmt = mysqli_prepare($conn,$sql);
        mysqli_stmt_bind_param($stmt,"ss",$docLogin,$correo);
        }
        else if($rol == "2")
        {
        // El aprendiz escribe el número de ficha, que ahora vive en f.ficha
        // (id_formacion pasó a ser un id interno).
        $sql = "SELECT f.*, a.*, j.*
        FROM formacion AS f
        INNER JOIN aprendiz AS a ON a.id_formacion = f.id_formacion
        INNER JOIN jornada AS j ON f.id_jornada = j.id_jornada
        WHERE documento = ? AND f.ficha = ?";

        $stmt = mysqli_prepare($conn,$sql);
        mysqli_stmt_bind_param($stmt,"ss",$docLogin,$ficha);
        }

        
        

        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($res)) {
            
            $idestado = $row["id_estado"];
            $iduser = $row["id_aprendiz"];
            $nombre = $row["nombre"];
            $jornadaid = $row["id_jornada"];
            $idrol = $row["id_rol"];

            

            $VotoNoRepeat = "SELECT * FROM votacion WHERE id_aprendiz = ?";
            $stmt1 = mysqli_prepare($conn,$VotoNoRepeat);
            mysqli_stmt_bind_param($stmt1,"i",$iduser);

            mysqli_stmt_execute($stmt1);
            $res = mysqli_stmt_get_result($stmt1);

            if ($idrol == 1) {
                $_SESSION['rol'] = $idrol;
                echo 
                '
                <script>
                alert(" Bienvenido Administrador '.$nombre.'");
                window.location.href="../vistas/administracion.php";
                </script>
                ';
            }elseif ($idrol == 2) {

                if ($idestado != 1) {
                    $estadoNombre = "";
                    $sqlEstado = "SELECT estados FROM estado WHERE id_estado = ?";
                    $stmtEstado = mysqli_prepare($conn,$sqlEstado);
                    mysqli_stmt_bind_param($stmtEstado,"i",$idestado);
                    mysqli_stmt_execute($stmtEstado);
                    $resEstado = mysqli_stmt_get_result($stmtEstado);
                    if ($rowEstado = mysqli_fetch_assoc($resEstado)) {
                        $estadoNombre = $rowEstado['estados'];
                    }

                    echo
                    '
                    <script>
                    alert("No estás habilitado para votar. Tu estado actual es: '.$estadoNombre.'");
                    window.location.href="../";
                    </script>
                    ';
                    exit;
                }

                    if (mysqli_num_rows($res) > 0) {

                    echo
                    '
                    <script>
                    alert("'.$nombre.' ya has votado, gracias por participar :D");
                    window.location.href="../";
                    </script>
                    ';

                }else {

                $_SESSION['rol'] = $idrol;
                $_SESSION['id_aprendiz'] = $iduser;
                $_SESSION['id_jornada'] = $jornadaid;
                echo
                '
                <script>
                alert("Bienvenido '.$nombre.', a continuación podrás votar");
                window.location.href="../vistas/votaciones.php";
                </script>
                ';
                
                
                }

            }
            elseif ($idrol  == 3) {

                $_SESSION['rol'] = $idrol;
                $_SESSION['nombre'] = $nombre;
                echo
                '
                <script>
                alert("Bienvenido/a auditor '.$nombre.'");
                window.location.href="../vistas/administracion.php";
                </script>
                ';
            }
            
            
            

        }
        else {
        echo 
        '
        <script>
        alert("Correo y/o Numero de documento incorrectos");
        window.location.href="../";
        </script>
        ';
        }
}

function CandidatosCajas($conn,$aprendiz,$jornada)
{
    $sql = "SELECT * FROM candidatos WHERE id_jornada = ? ORDER BY tarjeton";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"i",$jornada);
    mysqli_stmt_execute($stmt);

    $res = mysqli_stmt_get_result($stmt);

    echo '<div class="rejilla-candidatos">';

    $total = 0;
    $retraso = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $total++;
        $retraso += 0.06;
        $nombre = htmlspecialchars($row['nombre']);
        $lema = htmlspecialchars($row['slogan'] ?? '');

        echo '
        <article class="candidato" style="animation-delay: ' . $retraso . 's;">
            <div class="candidato-retrato">
                <span class="candidato-tarjeton">N.º ' . (int)$row['tarjeton'] . '</span>
                <img src="' . htmlspecialchars($row['foto']) . '" alt="Fotografía de ' . $nombre . '">
            </div>
            <div class="candidato-cuerpo">
                <h3 class="candidato-nombre">' . $nombre . '</h3>'
                . ($lema !== '' ? '<p class="candidato-lema">“' . $lema . '”</p>' : '<p class="candidato-lema"></p>') .
                '<form method="POST" action="../back/RegVoto.php">
                    <input type="hidden" name="id_candidato" value="' . $row['id_candidato'] . '">
                    <input type="hidden" name="id_aprendiz" value="' . $aprendiz . '">
                    <button type="submit" class="btn btn-solido btn-ancho"
                            onclick="return confirm(\'Vas a votar por ' . $nombre . '. Tu voto es definitivo. ¿Confirmas?\')">Votar</button>
                </form>
            </div>
        </article>';
    }

    echo '</div>';

    if ($total === 0) {
        echo "<div class='mensaje mensaje-aviso'>Todavía no hay candidatos inscritos para tu jornada.</div>";
    }
}

function RegistrarVoto($conn,$idaprendiz,$idcandidato)
{
    date_default_timezone_set('America/Bogota');
    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    $sqlVentana = "SELECT e.fecha_inicio AS evento_inicio, e.fecha_fin AS evento_fin,
                          j.fecha_inicio AS jornada_inicio, j.fecha_fin AS jornada_fin
                   FROM candidatos c
                   INNER JOIN jornada j ON c.id_jornada = j.id_jornada
                   INNER JOIN eventos e ON j.id_evento = e.id_evento
                   WHERE c.id_candidato = ?";
    $stmtVentana = mysqli_prepare($conn,$sqlVentana);
    mysqli_stmt_bind_param($stmtVentana,"i",$idcandidato);
    mysqli_stmt_execute($stmtVentana);
    $resVentana = mysqli_stmt_get_result($stmtVentana);
    $ventana = mysqli_fetch_assoc($resVentana);

    if (!$ventana) {
        echo
        '
        <script>
        alert("Candidato no válido");
        window.location.href="../";
        </script>
        ';
        exit();
    }

    if ($fecha < $ventana['evento_inicio'] || $fecha > $ventana['evento_fin']) {
        echo
        '
        <script>
        alert("El periodo de votación de este evento no está activo");
        window.location.href="../";
        </script>
        ';
        exit();
    }

    if ($hora < $ventana['jornada_inicio'] || $hora > $ventana['jornada_fin']) {
        echo
        '
        <script>
        alert("Estás fuera del horario de votación de tu jornada");
        window.location.href="../";
        </script>
        ';
        exit();
    }

    $validate = "SELECT * FROM votacion WHERE id_aprendiz = ?";
    $val = mysqli_prepare($conn,$validate);
    mysqli_stmt_bind_param($val,"i",$idaprendiz);
    mysqli_stmt_execute($val);
    
    $res = mysqli_stmt_get_result($val);
    if (mysqli_num_rows($res) > 0) {
        echo 
            '
            <script>
            alert("Ya Tienes Un Voto Registrado");
            window.location.href="../";
            </script>
            ';
            exit();
    }
    if ($idaprendiz != null && $idcandidato != null) {
        $sql = "INSERT INTO votacion(id_aprendiz,id_candidato) VALUES (?,?)";
        $stmt = mysqli_prepare($conn,$sql);
        mysqli_stmt_bind_param($stmt,"ii",$idaprendiz,$idcandidato);

        if (mysqli_stmt_execute($stmt)) {
            echo 
            '
            <script>
            alert("Muchas Gracias por Registrar tu voto");
            window.location.href="../";
            </script>
            ';
        }
    }
}



function VistaAdmin($conn,$idEvento,$filtro,$rol,$soloLectura = false)
{
    $sql = "SELECT c.id_candidato AS c_idcandidato, c.*, COUNT(v.id_voto) AS total_votos
        FROM candidatos AS c
        LEFT JOIN votacion AS v ON v.id_candidato = c.id_candidato
        WHERE c.id_jornada IN (SELECT id_jornada FROM jornada WHERE id_evento = ?)";
    $types = "i";
    $params = [$idEvento];

    if (!empty($filtro)) {
        $sql .= " AND c.id_jornada = ?";
        $types .= "i";
        $params[] = $filtro;
    }

    $sql .= " GROUP BY c.id_candidato, c.nombre ORDER BY total_votos DESC";

    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,$types,...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $puedeEditar = ($rol === 1 && !$soloLectura);

    if ($soloLectura) {
        echo "<div class='mensaje mensaje-aviso'>Estás consultando un año electoral anterior. La información se muestra en modo solo lectura.</div>";
    }

    echo "<div class='tabla-marco'><table class='tabla'>
    <thead><tr>
        <th>Tarjetón</th>
        <th>Candidato</th>
        <th>Votos</th>";
    if ($puedeEditar) {
        echo "<th>Acciones</th>";
    }
    echo "</tr></thead><tbody>";

    $total = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $total++;
        $idcandidato = $row["c_idcandidato"];
        $nombre = htmlspecialchars($row['nombre']);
        echo "<tr>
                <td><span class='num dato-fuerte'>{$row['tarjeton']}</span></td>
                <td class='dato-fuerte'>$nombre</td>
                <td><span class='insignia insignia-ok'>{$row['total_votos']}</span></td>";
        if ($puedeEditar) {
            echo "<td><div class='acciones-fila'>
                    <a class='btn btn-linea btn-chico' href='../vistas/editar_info.php?idcandidato=$idcandidato'>Editar</a>
                    <a class='btn btn-peligro btn-chico' href='../back/eliminar_candidato.php?idcandidato=$idcandidato' onclick=\"return confirm('¿Eliminar a $nombre?')\">Eliminar</a>
                  </div></td>";
        }
        echo "</tr>";
    }

    if ($total === 0) {
        $colspan = $puedeEditar ? 4 : 3;
        echo "<tr><td colspan='$colspan' class='vacio'>No hay candidatos registrados para este año y jornada.</td></tr>";
    }

    echo "</tbody></table></div>";
}

function RegistrarCandidato($conn,$nombre,$idjornada,$formacion,$correo,$slogan,$tarj,$foto)
{
    // El tarjetón solo debe ser único dentro del mismo año electoral:
    // dos años distintos pueden reutilizar el mismo número.
    $sqlValidar = "SELECT c.nombre, j.jornada
                   FROM candidatos c
                   INNER JOIN jornada j ON c.id_jornada = j.id_jornada
                   WHERE c.tarjeton = ?
                     AND j.id_evento = (SELECT id_evento FROM jornada WHERE id_jornada = ?)";
    $stmtValidar = mysqli_prepare($conn,$sqlValidar);
    mysqli_stmt_bind_param($stmtValidar,"ii",$tarj,$idjornada);
    mysqli_stmt_execute($stmtValidar);
    $resValidar = mysqli_stmt_get_result($stmtValidar);

    if ($ocupado = mysqli_fetch_assoc($resValidar)) {
        $ocupadoPor = addslashes($ocupado['nombre']);
        $jornadaOcupada = addslashes($ocupado['jornada']);
        echo
            '
            <script>
            alert("El tarjetón '.(int)$tarj.' ya está usado en este año electoral por '.$ocupadoPor.' (jornada '.$jornadaOcupada.'). Ingresa otro número.");
            window.location.href="../vistas/administracion.php";
            </script>
            ';
            exit;
    }

    $sql = "INSERT INTO candidatos(tarjeton,slogan,nombre,foto,correo,id_formacion,id_jornada) VALUES (?,?,?,?,?,?,?)";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"issssii",$tarj,$slogan,$nombre,$foto,$correo,$formacion,$idjornada);

    if (mysqli_stmt_execute($stmt)) {
        $nombreSeguro = addslashes($nombre);
        echo
            '
            <script>
            alert("Has Registrado Al Candidato '.$nombreSeguro.' con el tarjetón '.(int)$tarj.'");
            window.location.href="../vistas/administracion.php";
            </script>
            ';
    }
}

function EliminarCandidato($conn,$id)
{
    $sql = "DELETE FROM candidatos WHERE id_candidato = ?";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"i",$id);

    if (mysqli_stmt_execute($stmt)) {
        echo 
            '
            <script>
            alert("El candidtato ha sido eliminado");
            window.location.href="../vistas/administracion.php";
            </script>
            ';
    }
}

function ActualizarCandidato($conn,$idcandidato,$name,$foto)
{
    $sql = "UPDATE candidatos SET nombre = ?, foto = ? WHERE id_candidato = ?";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"ssi",$name,$foto,$idcandidato);

    if (mysqli_stmt_execute($stmt)) {
        echo 
            '
            <script>
            alert("Has Actualizado Los Datos del Candidato");
            window.location.href="../vistas/administracion.php";
            </script>
            ';
    }
}

function AddEvent($conn,$evento,$anio,$fechaini,$fechafin)
{
    $sql = "INSERT INTO eventos (evento,anio,fecha_inicio,fecha_fin) VALUES (?,?,?,?)";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"siss",$evento,$anio,$fechaini,$fechafin);

    if (mysqli_stmt_execute($stmt)) {
        $idEvento = mysqli_insert_id($conn);

        $jornadas = ['Mañana','Tarde','Noche','Virtual','A Distancia'];
        $sqlJornada = "INSERT INTO jornada (id_evento,jornada,fecha_inicio,fecha_fin) VALUES (?,?,?,?)";
        $stmtJornada = mysqli_prepare($conn,$sqlJornada);
        $horaIni = "00:00:00";
        $horaFin = "23:59:59";
        foreach ($jornadas as $nombreJornada) {
            mysqli_stmt_bind_param($stmtJornada,"isss",$idEvento,$nombreJornada,$horaIni,$horaFin);
            mysqli_stmt_execute($stmtJornada);
        }

        echo
        '
        <script>
        alert("Año '.$anio.' registrado con sus jornadas: Mañana, Tarde, Noche, Virtual y A Distancia. Actívalo cuando quieras empezar a usarlo.");
        window.location.href="../vistas/eventos.php";
        </script>
        ';
    }
}

function ActivarEvento($conn,$idEvento)
{
    mysqli_query($conn,"UPDATE eventos SET activo = 0");

    $sql = "UPDATE eventos SET activo = 1 WHERE id_evento = ?";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"i",$idEvento);

    if (mysqli_stmt_execute($stmt)) {
        echo
        '
        <script>
        alert("Año electoral activado. El registro, la creación de candidatos y la votación ahora usan este año.");
        window.location.href="../vistas/eventos.php";
        </script>
        ';
    }
}

function ActualizarHorarioJornada($conn,$idJornada,$idEvento,$horaIni,$horaFin)
{
    $sql = "UPDATE jornada SET fecha_inicio = ?, fecha_fin = ? WHERE id_jornada = ? AND id_evento = ?";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"ssii",$horaIni,$horaFin,$idJornada,$idEvento);

    if (mysqli_stmt_execute($stmt)) {
        echo
        '
        <script>
        alert("Horario de la jornada actualizado");
        window.location.href="../vistas/gestion_jornadas.php?id_evento='.(int)$idEvento.'";
        </script>
        ';
    }
}

function AgregarJornada($conn,$idEvento,$nombreJornada,$horaIni,$horaFin)
{
    $validar = mysqli_prepare($conn,"SELECT id_jornada FROM jornada WHERE id_evento = ? AND jornada = ?");
    mysqli_stmt_bind_param($validar,"is",$idEvento,$nombreJornada);
    mysqli_stmt_execute($validar);
    $res = mysqli_stmt_get_result($validar);

    if (mysqli_num_rows($res) > 0) {
        echo
        '
        <script>
        alert("Ese año ya tiene una jornada con ese nombre");
        window.location.href="../vistas/gestion_jornadas.php?id_evento='.(int)$idEvento.'";
        </script>
        ';
        exit;
    }

    $sql = "INSERT INTO jornada (id_evento,jornada,fecha_inicio,fecha_fin) VALUES (?,?,?,?)";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"isss",$idEvento,$nombreJornada,$horaIni,$horaFin);

    if (mysqli_stmt_execute($stmt)) {
        echo
        '
        <script>
        alert("Jornada agregada");
        window.location.href="../vistas/gestion_jornadas.php?id_evento='.(int)$idEvento.'";
        </script>
        ';
    }
}

function MostrarHorariosJornadas($conn,$idEvento)
{
    $sql = "SELECT * FROM jornada WHERE id_evento = ? AND jornada <> 'Administrador' ORDER BY id_jornada";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"i",$idEvento);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    echo "<div class='tabla-marco'><table class='tabla'>
    <thead><tr>
        <th>Jornada</th><th>Apertura</th><th>Cierre</th><th>Modificar horario</th>
    </tr></thead><tbody>";

    $total = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $total++;
        $nombreJornada = htmlspecialchars($row['jornada']);
        echo "<tr>
            <td class='dato-fuerte'>$nombreJornada</td>
            <td><span class='num'>{$row['fecha_inicio']}</span></td>
            <td><span class='num'>{$row['fecha_fin']}</span></td>
            <td>
                <form action='../back/ActualizarHorario.php' method='post' style='display:flex;gap:8px;align-items:center;flex-wrap:wrap;'>
                    <input type='hidden' name='id_jornada' value='{$row['id_jornada']}'>
                    <input type='hidden' name='id_evento' value='$idEvento'>
                    <input type='time' name='hora_inicio' value='{$row['fecha_inicio']}' required style='width:auto;'>
                    <input type='time' name='hora_fin' value='{$row['fecha_fin']}' required style='width:auto;'>
                    <button type='submit' class='btn btn-linea btn-chico'>Guardar</button>
                </form>
            </td>
        </tr>";
    }

    if ($total === 0) {
        echo "<tr><td colspan='4' class='vacio'>Este año todavía no tiene jornadas registradas.</td></tr>";
    }

    echo "</tbody></table></div>";
}

function FormularioNuevaJornada($idEvento)
{
    echo "<form action='../back/AgregarJornada.php' method='post' class='filtros'>
        <input type='hidden' name='id_evento' value='" . (int)$idEvento . "'>
        <div class='campo'>
            <label>Nombre de la jornada</label>
            <input type='text' name='nombre_jornada' placeholder='Ejemplo: Mañana' required>
        </div>
        <div class='campo'>
            <label>Apertura</label>
            <input type='time' name='hora_inicio' value='06:00' required>
        </div>
        <div class='campo'>
            <label>Cierre</label>
            <input type='time' name='hora_fin' value='12:00' required>
        </div>
        <button type='submit' class='btn btn-solido'>Agregar jornada</button>
    </form>";
}

function MostrarEventos($conn)
{
    $sql = "SELECT * FROM eventos ORDER BY anio DESC, id_evento DESC";
    $stmt = mysqli_query($conn,$sql);

    echo "<div class='tabla-marco'><table class='tabla'>
    <thead><tr>
        <th>Año</th>
        <th>Evento</th>
        <th>Inicio</th>
        <th>Cierre</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr></thead><tbody>";

    $hoy = date("Y-m-d");
    while ($row = mysqli_fetch_assoc($stmt))
    {
        $idevento = $row["id_evento"];
        $anio = $row["anio"];
        $evento = htmlspecialchars($row["evento"]);
        $inicio = $row["fecha_inicio"];
        $fin = $row["fecha_fin"];
        $activo = $row["activo"];
        $terminoPorFecha = ($fin < $hoy);
        $bloqueado = (!$activo && $terminoPorFecha);

        if ($activo) {
            $badge = "<span class='insignia insignia-ok'>Activo</span>";
        } elseif ($bloqueado) {
            $badge = "<span class='insignia insignia-neutra'>Finalizado</span>";
        } else {
            $badge = "<span class='insignia insignia-espera'>Inactivo</span>";
        }

        echo "<tr>";
        echo "<td><span class='num dato-fuerte'>".$anio."</span></td>";
        echo "<td class='dato-fuerte'>".$evento."</td>";
        echo "<td><span class='num'>".$inicio."</span></td>";
        echo "<td><span class='num'>".$fin."</span></td>";
        echo "<td>".$badge."</td>";
        echo "<td><div class='acciones-fila'>";

        if (!$bloqueado) {
            if (!$activo) {
                echo "<a class='btn btn-lima btn-chico' href='../back/ActivarEvento.php?id_evento=$idevento' onclick=\"return confirm('¿Activar este año como el año electoral en uso?')\">Activar</a>";
            }
            echo "<a class='btn btn-linea btn-chico' href='#' onclick=\"
                    id_evento.value='$idevento';
                    fecha_inicio.value='$inicio';
                    fecha_fin.value='$fin';
                    document.getElementById('modalFechas').showModal();
                    return false;
                \">Fechas</a>";
            echo "<a class='btn btn-linea btn-chico' href='gestion_jornadas.php?id_evento=$idevento'>Jornadas</a>";
        }

        if ($terminoPorFecha) {
            echo "<a class='btn btn-solido btn-chico' href='historial_evento.php?id_evento=$idevento'>Historial</a>";
        }

        if (!$activo) {
            $conteo = ConteoRegistrosEvento($conn,$idevento);
            $mensajeConfirm = "¿ELIMINAR PERMANENTEMENTE el año $anio ($evento)? Se borrarán también sus {$conteo['candidatos']} candidatos, {$conteo['aprendices']} aprendices y {$conteo['votos']} votos registrados. Esta acción NO se puede deshacer.";
            echo "<a class='btn btn-peligro btn-chico' href='../back/EliminarEvento.php?id_evento=$idevento' onclick=\"return confirm('$mensajeConfirm')\">Eliminar</a>";
        }

        echo "</div></td>";
    echo "</tr>";

    }
    echo "</tbody></table></div>";
}

function ConteoRegistrosEvento($conn,$idEvento)
{
    $sql = "SELECT
                COUNT(DISTINCT c.id_candidato) AS candidatos,
                COUNT(DISTINCT a.id_aprendiz) AS aprendices,
                COUNT(DISTINCT v.id_voto) AS votos
            FROM jornada j
            LEFT JOIN candidatos c ON c.id_jornada = j.id_jornada
            LEFT JOIN formacion f ON f.id_jornada = j.id_jornada
            LEFT JOIN aprendiz a ON a.id_formacion = f.id_formacion
            LEFT JOIN votacion v ON v.id_candidato = c.id_candidato
            WHERE j.id_evento = ?";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"i",$idEvento);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res);
}

function EliminarEvento($conn,$idEvento)
{
    $res = mysqli_query($conn,"SELECT activo FROM eventos WHERE id_evento = ".(int)$idEvento);
    $row = mysqli_fetch_assoc($res);

    if (!$row) {
        echo
        '
        <script>
        alert("Ese año electoral no existe");
        window.location.href="../vistas/eventos.php";
        </script>
        ';
        exit;
    }

    if ($row['activo']) {
        echo
        '
        <script>
        alert("No puedes eliminar el año electoral activo. Activa otro año primero.");
        window.location.href="../vistas/eventos.php";
        </script>
        ';
        exit;
    }

    $sql = "DELETE FROM eventos WHERE id_evento = ?";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"i",$idEvento);

    if (mysqli_stmt_execute($stmt)) {
        echo
        '
        <script>
        alert("Año electoral eliminado junto con sus jornadas, candidatos, aprendices y votos");
        window.location.href="../vistas/eventos.php";
        </script>
        ';
    }
}

function EditCandidato($conn,$idevento,$inicio,$fin)
{
    $sql = "UPDATE eventos SET fecha_inicio = ?, fecha_fin = ?
     WHERE id_evento = ?";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"ssi",$inicio,$fin,$idevento);

    if (mysqli_stmt_execute($stmt)) {
        echo
        '
        <script>
        alert("Evento Actualizado");
        window.location.href="../vistas/eventos.php";
        </script>
        ';
    }
}

function VotosPorFichas($conn,$filtro,$filtro2,$idEvento)
{
    if ($idEvento === null) {
        echo "<div class='mensaje mensaje-aviso'>No hay ningún año electoral activo.</div>";
        return;
    }

    // Solo las fichas del año activo: las de años anteriores cuelgan de
    // jornadas de otro evento y no deben aparecer aquí.
    $sql = "SELECT
                f.id_formacion,
                f.ficha AS numero_ficha,
                f.formacion AS nombre_formacion,
                COUNT(a.id_aprendiz) AS total_aprendices,
                COUNT(DISTINCT v.id_aprendiz) AS aprendices_que_votaron,
                (COUNT(a.id_aprendiz) - COUNT(DISTINCT v.id_aprendiz)) AS aprendices_faltantes
            FROM formacion f
            INNER JOIN jornada j ON f.id_jornada = j.id_jornada
            INNER JOIN aprendiz a ON a.id_formacion = f.id_formacion AND a.id_rol = 2
            LEFT JOIN votacion v ON v.id_aprendiz = a.id_aprendiz
            WHERE j.id_evento = ? AND j.jornada <> 'Administrador'";
    $tipos = "i";
    $parametros = [$idEvento];

    if (!empty($filtro2)) {
        $sql .= " AND f.id_jornada = ?";
        $tipos .= "i";
        $parametros[] = $filtro2;
    }
    if (!empty($filtro)) {
        $sql .= " AND f.id_formacion = ?";
        $tipos .= "i";
        $parametros[] = $filtro;
    }

    $sql .= " GROUP BY f.id_formacion, f.ficha, f.formacion ORDER BY f.formacion";

    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,$tipos,...$parametros);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    echo "<div class='tabla-marco'><table class='tabla'>
        <thead><tr>
            <th>Ficha</th>
            <th>Programa de formación</th>
            <th>Aprendices</th>
            <th>Votaron</th>
            <th>Faltantes</th>
        </tr></thead><tbody>";

        if(mysqli_num_rows($result) > 0){
                while ($row = mysqli_fetch_assoc($result)) {
                    $faltantes = (int)$row['aprendices_faltantes'];
                    $insigniaFaltantes = $faltantes === 0 ? 'insignia-ok' : 'insignia-espera';
                    echo "<tr>
                        <td><span class='num dato-fuerte'>{$row['numero_ficha']}</span></td>
                        <td>" . htmlspecialchars($row['nombre_formacion']) . "</td>
                        <td><span class='num'>{$row['total_aprendices']}</span></td>
                        <td><span class='insignia insignia-lima'>{$row['aprendices_que_votaron']}</span></td>
                        <td><span class='insignia $insigniaFaltantes'>{$faltantes}</span></td>
                    </tr>";
            }
        }
        else
        {
            echo "<tr><td colspan='5' class='vacio'>No se encontraron datos con esos filtros.</td></tr>";
        }


        echo "</tbody></table></div>";
}

/* Deja un texto comparable: sin espacios de sobra, sin tildes y
   en minúsculas. Se usa para emparejar jornadas y estados escritos
   de cualquier forma ("MAÑANA", "Mañana", "mañana").

   No usa mbstring a propósito: muchos hostings compartidos no la
   traen activada, y una llamada a mb_strtolower() inexistente
   tumba la petición entera con un error 500.

   El truco es quitar las tildes PRIMERO, cubriendo mayúsculas y
   minúsculas. Lo que queda es ASCII, así que basta con cambiar
   A-Z por a-z byte a byte: strtr() no toca los bytes altos de
   UTF-8, y a diferencia de strtolower() no depende del locale. */
function NormalizarTexto($txt)
{
    $txt = trim((string)$txt);

    $txt = strtr($txt, [
        'Á'=>'a','À'=>'a','Ä'=>'a','Â'=>'a','Ã'=>'a','Å'=>'a',
        'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a',
        'É'=>'e','È'=>'e','Ë'=>'e','Ê'=>'e','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
        'Í'=>'i','Ì'=>'i','Ï'=>'i','Î'=>'i','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
        'Ó'=>'o','Ò'=>'o','Ö'=>'o','Ô'=>'o','Õ'=>'o',
        'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
        'Ú'=>'u','Ù'=>'u','Ü'=>'u','Û'=>'u','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
        'Ñ'=>'n','ñ'=>'n','Ç'=>'c','ç'=>'c',
    ]);

    return strtr($txt, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
}

function ObtenerOCrearFormacion($conn,$nombreFormacion,$idJornada)
{
    $stmt = mysqli_prepare($conn,"SELECT id_formacion FROM formacion WHERE formacion = ? AND id_jornada = ?");
    mysqli_stmt_bind_param($stmt,"si",$nombreFormacion,$idJornada);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        return (int)$row['id_formacion'];
    }

    // Si la ficha no venía en el archivo de aprendices se asigna un número
    // provisional; conviene registrarla antes en "Programas de Formación".
    $nuevaFicha = 1;
    $r = mysqli_query($conn,"SELECT MAX(ficha) AS m FROM formacion");
    if ($m = mysqli_fetch_assoc($r)) {
        $nuevaFicha = (int)$m['m'] + 1;
    }

    $ins = mysqli_prepare($conn,"INSERT INTO formacion (ficha,formacion,id_jornada) VALUES (?,?,?)");
    mysqli_stmt_bind_param($ins,"isi",$nuevaFicha,$nombreFormacion,$idJornada);
    mysqli_stmt_execute($ins);

    return mysqli_insert_id($conn);
}

function EliminarAprendiz($conn,$id)
{
    $sql = "DELETE FROM aprendiz WHERE id_aprendiz = ? AND id_rol = 2";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"i",$id);

    if (mysqli_stmt_execute($stmt)) {
        echo
        '
        <script>
        alert("Aprendiz eliminado del sistema");
        window.location.href="../vistas/gestion_aprendices.php";
        </script>
        ';
    }
}

function OpcionesFormaciones($conn,$idEvento,$idJornada = null,$selected = null)
{
    $sql = "SELECT f.id_formacion, f.ficha, f.formacion, j.jornada
            FROM formacion f
            INNER JOIN jornada j ON f.id_jornada = j.id_jornada
            WHERE j.id_evento = ? AND j.jornada <> 'Administrador'";
    $types = "i";
    $params = [$idEvento];

    if (!empty($idJornada)) {
        $sql .= " AND j.id_jornada = ?";
        $types .= "i";
        $params[] = $idJornada;
    }
    $sql .= " ORDER BY j.jornada, f.formacion";

    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,$types,...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($res)) {
        $sel = ($selected !== null && $selected == $row['id_formacion']) ? 'selected' : '';
        $etiqueta = $row['ficha'] . " - " . htmlspecialchars($row['formacion']) . " (" . htmlspecialchars($row['jornada']) . ")";
        echo "<option value='".$row['id_formacion']."' $sel>".$etiqueta."</option>";
    }
}

function ListarAprendices($conn,$idEvento,$idJornada,$idEstado,$busqueda,$idFormacion = null)
{
    $sql = "SELECT a.id_aprendiz, a.nombre, a.documento, a.correo, f.ficha, f.formacion, j.jornada, e.estados, e.id_estado
            FROM aprendiz a
            INNER JOIN formacion f ON a.id_formacion = f.id_formacion
            INNER JOIN jornada j ON f.id_jornada = j.id_jornada
            INNER JOIN estado e ON a.id_estado = e.id_estado
            WHERE a.id_rol = 2 AND j.id_evento = ?";
    $types = "i";
    $params = [$idEvento];

    if (!empty($idJornada)) {
        $sql .= " AND j.id_jornada = ?";
        $types .= "i";
        $params[] = $idJornada;
    }
    if (!empty($idFormacion)) {
        $sql .= " AND f.id_formacion = ?";
        $types .= "i";
        $params[] = $idFormacion;
    }
    if (!empty($idEstado)) {
        $sql .= " AND a.id_estado = ?";
        $types .= "i";
        $params[] = $idEstado;
    }
    if (!empty($busqueda)) {
        $sql .= " AND (a.nombre LIKE ? OR a.documento LIKE ?)";
        $types .= "ss";
        $like = "%".$busqueda."%";
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY j.jornada, f.formacion, a.nombre";

    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,$types,...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    echo "<div class='table-wrap'><table class='data-table'>
        <thead><tr>
            <th>Nombre</th>
            <th>Documento</th>
            <th>Jornada</th>
            <th>Ficha/Formación</th>
            <th>Estado</th>
            <th>Acción</th>
        </tr></thead><tbody>";

    $total = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $total++;
        $claseInsignia = ((int)$row['id_estado'] === 1) ? 'insignia-ok' : 'insignia-alerta';
        $nombre = htmlspecialchars($row['nombre']);
        $documento = htmlspecialchars($row['documento']);
        $jornada = htmlspecialchars($row['jornada']);
        $formacion = "<span class='num'>{$row['ficha']}</span> · " . htmlspecialchars($row['formacion']);
        $estado = htmlspecialchars($row['estados']);
        $idAprendiz = $row['id_aprendiz'];

        echo "<tr>
            <td class='dato-fuerte'>$nombre</td>
            <td><span class='num'>$documento</span></td>
            <td>$jornada</td>
            <td>$formacion</td>
            <td><span class='insignia $claseInsignia'>$estado</span></td>
            <td>
                <a class='btn btn-peligro btn-chico' href='../back/EliminarAprendiz.php?id=$idAprendiz' onclick=\"return confirm('¿Eliminar a $nombre? Esta acción no se puede deshacer.')\">Eliminar</a>
            </td>
        </tr>";
    }

    if ($total === 0) {
        echo "<tr><td colspan='6' class='vacio'>No se encontraron aprendices con esos filtros.</td></tr>";
    }

    echo "</tbody></table></div>";
}

function CargarAprendicesCSV($conn,$idEvento,$rutaArchivo,$nombreOriginal = '')
{
    $insertados = 0;
    $actualizados = 0;
    $errores = [];

    $jornadas = [];
    $res = mysqli_query($conn,"SELECT id_jornada, jornada FROM jornada WHERE id_evento = ".(int)$idEvento);
    while ($r = mysqli_fetch_assoc($res)) {
        $jornadas[NormalizarTexto($r['jornada'])] = (int)$r['id_jornada'];
    }

    $estados = [];
    $res = mysqli_query($conn,"SELECT id_estado, estados FROM estado");
    while ($r = mysqli_fetch_assoc($res)) {
        $estados[NormalizarTexto($r['estados'])] = (int)$r['id_estado'];
    }
    $aliasEstado = [
        'retirado' => 'desercion',
        'retiro' => 'desercion',
        'desertor' => 'desercion',
    ];

    $motivo = null;
    $filas = LeerFilasArchivo($rutaArchivo,$nombreOriginal,$motivo);
    if ($filas === null) {
        $detalle = $motivo ?: 'Verifica que sea un .csv, .xls o .xlsx válido.';
        return ['insertados' => 0, 'actualizados' => 0, 'errores' => ['No se pudo leer el archivo. ' . $detalle]];
    }

    $fila = 0;
    $primeraFila = true;
    foreach ($filas as $datos) {
        $fila++;

        if (count($datos) < 6) {
            if ($primeraFila) { $primeraFila = false; continue; }
            $errores[] = "Fila $fila: se esperaban 6 columnas (nombre,documento,correo,jornada,formacion,estado)";
            continue;
        }

        [$nombre,$documento,$correo,$jornadaTxt,$formacionTxt,$estadoTxt] = array_map('trim',array_slice($datos,0,6));

        if ($primeraFila) {
            $primeraFila = false;
            if (NormalizarTexto($documento) === 'documento') {
                continue;
            }
        }

        if ($nombre === '' || $documento === '') {
            $errores[] = "Fila $fila: nombre o documento vacío";
            continue;
        }

        $keyJornada = NormalizarTexto($jornadaTxt);
        if (!isset($jornadas[$keyJornada])) {
            $errores[] = "Fila $fila: la jornada '$jornadaTxt' no existe en el año activo";
            continue;
        }
        $idJornada = $jornadas[$keyJornada];

        $keyEstado = NormalizarTexto($estadoTxt);
        $keyEstado = $aliasEstado[$keyEstado] ?? $keyEstado;
        if (!isset($estados[$keyEstado])) {
            $errores[] = "Fila $fila: el estado '$estadoTxt' no es válido (usa Habilitado, Inducción o Deserción)";
            continue;
        }
        $idEstado = $estados[$keyEstado];

        $idFormacion = ObtenerOCrearFormacion($conn,$formacionTxt,$idJornada);

        $stmtBuscar = mysqli_prepare($conn,"SELECT id_aprendiz FROM aprendiz WHERE documento = ?");
        mysqli_stmt_bind_param($stmtBuscar,"s",$documento);
        mysqli_stmt_execute($stmtBuscar);
        $resBuscar = mysqli_stmt_get_result($stmtBuscar);

        if ($existente = mysqli_fetch_assoc($resBuscar)) {
            $stmtUpd = mysqli_prepare($conn,"UPDATE aprendiz SET nombre = ?, correo = ?, id_formacion = ?, id_estado = ? WHERE id_aprendiz = ?");
            mysqli_stmt_bind_param($stmtUpd,"ssiii",$nombre,$correo,$idFormacion,$idEstado,$existente['id_aprendiz']);
            mysqli_stmt_execute($stmtUpd);
            $actualizados++;
        } else {
            $idRol = 2;
            $stmtIns = mysqli_prepare($conn,"INSERT INTO aprendiz (nombre,documento,correo,id_formacion,id_rol,id_estado) VALUES (?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmtIns,"sssiii",$nombre,$documento,$correo,$idFormacion,$idRol,$idEstado);
            mysqli_stmt_execute($stmtIns);
            $insertados++;
        }
    }

    return ['insertados' => $insertados, 'actualizados' => $actualizados, 'errores' => $errores];
}

function MostrarHistorialEvento($conn,$idEvento)
{
    $sql = "SELECT j.id_jornada, j.jornada,
                c.id_candidato, c.tarjeton, c.nombre, c.foto, c.slogan,
                COUNT(v.id_voto) AS total_votos
            FROM jornada j
            LEFT JOIN candidatos c ON c.id_jornada = j.id_jornada
            LEFT JOIN votacion v ON v.id_candidato = c.id_candidato
            WHERE j.id_evento = ? AND j.jornada <> 'Administrador'
            GROUP BY j.id_jornada, j.jornada, c.id_candidato, c.tarjeton, c.nombre, c.foto, c.slogan
            ORDER BY j.jornada, total_votos DESC";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"i",$idEvento);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $porJornada = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $porJornada[$row['jornada']][] = $row;
    }

    if (empty($porJornada)) {
        echo "<div class='mensaje'>Este año no tiene jornadas registradas.</div>";
        return;
    }

    foreach ($porJornada as $nombreJornada => $candidatos) {
        echo "<div class='tarjeta'>";
        echo "<div class='tarjeta-cabecera'>
                <h2>Jornada " . htmlspecialchars($nombreJornada) . "</h2>
                <span class='rotulo'>" . count(array_filter($candidatos, function($c){ return !empty($c['id_candidato']); })) . " candidatos</span>
              </div>";

        if (empty($candidatos[0]['id_candidato'])) {
            echo "<p class='nota'>No hubo candidatos registrados en esta jornada.</p></div>";
            continue;
        }

        $maxVotos = (int)$candidatos[0]['total_votos'];

        echo "<div class='rejilla-candidatos'>";
        $retraso = 0;
        foreach ($candidatos as $c) {
            $retraso += 0.06;
            $esGanador = ($maxVotos > 0 && (int)$c['total_votos'] === $maxVotos);
            $nombre = htmlspecialchars($c['nombre']);
            $foto = htmlspecialchars($c['foto']);
            $lema = htmlspecialchars($c['slogan'] ?? '');

            echo "<article class='candidato" . ($esGanador ? " candidato-ganador" : "") . "' style='animation-delay:{$retraso}s;'>";
            if ($esGanador) {
                echo "<div class='cinta-ganador'>Ganador</div>";
            }
            echo "<div class='candidato-retrato'>
                    <span class='candidato-tarjeton'>N.º " . (int)$c['tarjeton'] . "</span>
                    <img src='$foto' alt='Fotografía de $nombre'>
                  </div>";
            echo "<div class='candidato-cuerpo'>";
            echo "<h3 class='candidato-nombre'>$nombre</h3>";
            if ($lema !== '') {
                echo "<p class='candidato-lema'>“" . $lema . "”</p>";
            }
            echo "<span class='conteo-votos'>{$c['total_votos']} votos</span>";
            echo "</div></article>";
        }
        echo "</div>";

        if ($maxVotos === 0) {
            echo "<p class='nota'>Esta jornada no registró votos.</p>";
        }

        echo "</div>";
    }
}

/* ============================================================
   Programas de formación (fichas)
   ============================================================ */

function AgregarFormacion($conn,$idFicha,$nombreFormacion,$idJornada)
{
    // Una ficha solo se bloquea si ya está registrada en ESA MISMA jornada.
    // La misma ficha puede repetirse en jornadas de otros años electorales,
    // porque cada año necesita su propia copia de la ficha.
    $stmt = mysqli_prepare($conn,"SELECT formacion FROM formacion WHERE ficha = ? AND id_jornada = ?");
    mysqli_stmt_bind_param($stmt,"ii",$idFicha,$idJornada);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($existente = mysqli_fetch_assoc($res)) {
        $yaExiste = addslashes($existente['formacion']);
        echo
        '
        <script>
        alert("La ficha '.(int)$idFicha.' ya está registrada en esta jornada como: '.$yaExiste.'");
        window.location.href="../vistas/AddJornada.php";
        </script>
        ';
        exit;
    }

    $sql = "INSERT INTO formacion (ficha,formacion,id_jornada) VALUES (?,?,?)";
    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,"isi",$idFicha,$nombreFormacion,$idJornada);

    if (mysqli_stmt_execute($stmt)) {
        $nombreSeguro = addslashes($nombreFormacion);
        echo
        '
        <script>
        alert("Programa de formación agregado: '.$nombreSeguro.' (ficha '.(int)$idFicha.')");
        window.location.href="../vistas/AddJornada.php";
        </script>
        ';
    }
}

function CargarFormacionesArchivo($conn,$idEvento,$rutaArchivo,$nombreOriginal)
{
    $insertados = 0;
    $actualizados = 0;
    $errores = [];

    $jornadas = [];
    $res = mysqli_query($conn,"SELECT id_jornada, jornada FROM jornada WHERE id_evento = ".(int)$idEvento);
    while ($r = mysqli_fetch_assoc($res)) {
        $jornadas[NormalizarTexto($r['jornada'])] = (int)$r['id_jornada'];
    }

    $motivo = null;
    $filas = LeerFilasArchivo($rutaArchivo,$nombreOriginal,$motivo);
    if ($filas === null) {
        $detalle = $motivo ?: 'Verifica que sea un .csv, .xls o .xlsx válido.';
        return ['insertados' => 0, 'actualizados' => 0, 'errores' => ['No se pudo leer el archivo. ' . $detalle]];
    }

    $numeroFila = 0;
    $primeraFila = true;
    foreach ($filas as $datos) {
        $numeroFila++;

        if (count($datos) < 3) {
            if ($primeraFila) { $primeraFila = false; continue; }
            $errores[] = "Fila $numeroFila: se esperaban 3 columnas (ficha,formacion,jornada)";
            continue;
        }

        [$fichaTxt,$formacionTxt,$jornadaTxt] = array_map('trim',array_slice($datos,0,3));

        if ($primeraFila) {
            $primeraFila = false;
            if (NormalizarTexto($fichaTxt) === 'ficha' || NormalizarTexto($formacionTxt) === 'formacion') {
                continue;
            }
        }

        if ($fichaTxt === '' || $formacionTxt === '') {
            $errores[] = "Fila $numeroFila: ficha o nombre de formación vacío";
            continue;
        }

        if (!ctype_digit(ltrim($fichaTxt,'0')) && !ctype_digit($fichaTxt)) {
            $errores[] = "Fila $numeroFila: la ficha '$fichaTxt' debe ser un número";
            continue;
        }
        $idFicha = (int)$fichaTxt;

        $claveJornada = NormalizarTexto($jornadaTxt);
        if (!isset($jornadas[$claveJornada])) {
            $errores[] = "Fila $numeroFila: la jornada '$jornadaTxt' no existe en el año activo";
            continue;
        }
        $idJornada = $jornadas[$claveJornada];

        // Se busca por ficha + jornada: la misma ficha en otra jornada
        // (otro año) es un registro distinto, no un duplicado.
        $stmtBuscar = mysqli_prepare($conn,"SELECT id_formacion FROM formacion WHERE ficha = ? AND id_jornada = ?");
        mysqli_stmt_bind_param($stmtBuscar,"ii",$idFicha,$idJornada);
        mysqli_stmt_execute($stmtBuscar);
        $resBuscar = mysqli_stmt_get_result($stmtBuscar);

        if ($existente = mysqli_fetch_assoc($resBuscar)) {
            $stmtUpd = mysqli_prepare($conn,"UPDATE formacion SET formacion = ? WHERE id_formacion = ?");
            mysqli_stmt_bind_param($stmtUpd,"si",$formacionTxt,$existente['id_formacion']);
            mysqli_stmt_execute($stmtUpd);
            $actualizados++;
        } else {
            $stmtIns = mysqli_prepare($conn,"INSERT INTO formacion (ficha,formacion,id_jornada) VALUES (?,?,?)");
            mysqli_stmt_bind_param($stmtIns,"isi",$idFicha,$formacionTxt,$idJornada);
            mysqli_stmt_execute($stmtIns);
            $insertados++;
        }
    }

    return ['insertados' => $insertados, 'actualizados' => $actualizados, 'errores' => $errores];
}

/* ============================================================
   Importar el "Reporte de Aprendices" de SofiaPlus
   ------------------------------------------------------------
   El archivo trae la ficha y el programa en su encabezado, y
   los aprendices más abajo. NO trae la jornada, por eso hay
   que indicarla al subirlo.
   ============================================================ */

/* Traduce el estado de SofiaPlus al estado del sistema */
function EstadoDesdeSofia($textoEstado)
{
    $clave = NormalizarTexto($textoEstado);

    if (strpos($clave, 'induccion') !== false)  { return [2, true]; }
    if (strpos($clave, 'formacion') !== false)  { return [1, true]; }
    if (strpos($clave, 'certificar') !== false) { return [1, true]; }

    foreach (['retiro', 'cancel', 'desercion', 'desert', 'aplazado', 'trasladado'] as $palabra) {
        if (strpos($clave, $palabra) !== false) { return [3, true]; }
    }

    return [1, false];   /* desconocido: se habilita, pero se avisa */
}

/* Busca la fila de encabezados y devuelve qué columna es cada dato */
function ColumnasDelReporte($fila)
{
    $mapa = [];

    foreach ($fila as $indice => $celda) {
        $clave = NormalizarTexto($celda);
        if ($clave === '') { continue; }

        if (strpos($clave, 'numero de documento') !== false || $clave === 'documento') { $mapa['documento'] = $indice; }
        elseif (strpos($clave, 'apellido') !== false)                                  { $mapa['apellidos'] = $indice; }
        elseif (strpos($clave, 'nombre') !== false)                                    { $mapa['nombre'] = $indice; }
        elseif (strpos($clave, 'correo') !== false || strpos($clave, 'email') !== false){ $mapa['correo'] = $indice; }
        elseif (strpos($clave, 'estado') !== false)                                    { $mapa['estado'] = $indice; }
    }

    return $mapa;
}

function CargarReporteSofia($conn,$idEvento,$idJornada,$rutaArchivo,$nombreOriginal)
{
    $resumen = [
        'ficha' => null, 'programa' => null, 'ficha_creada' => false,
        'insertados' => 0, 'actualizados' => 0, 'errores' => [], 'avisos' => [],
    ];

    $motivo = null;
    $filas = LeerFilasArchivo($rutaArchivo,$nombreOriginal,$motivo);
    if (empty($filas)) {
        $detalle = $motivo ?: 'Verifica que sea el reporte de SofiaPlus (.xls, .xlsx o .csv).';
        $resumen['errores'][] = 'No se pudo leer el archivo. ' . $detalle;
        return $resumen;
    }

    /* --- 1. Ficha y programa: vienen en el encabezado del reporte --- */
    foreach ($filas as $fila) {
        foreach ($fila as $celda) {
            if (preg_match('/(\d{5,10})\s*-\s*(.+)/u', trim($celda), $coincidencias)) {
                $resumen['ficha'] = (int)$coincidencias[1];
                $resumen['programa'] = trim($coincidencias[2]);
                break 2;
            }
        }
    }

    /* Si no estaba en el contenido, se intenta con el nombre del archivo */
    if ($resumen['ficha'] === null && preg_match('/(\d{5,10})/', $nombreOriginal, $coincidencias)) {
        $resumen['ficha'] = (int)$coincidencias[1];
        $resumen['programa'] = 'Ficha ' . $resumen['ficha'];
    }

    if ($resumen['ficha'] === null) {
        $resumen['errores'][] = 'No se encontró el número de ficha en el archivo.';
        return $resumen;
    }

    /* --- 2. Localizar la fila de encabezados --- */
    $mapa = [];
    $filaEncabezado = -1;
    foreach ($filas as $indice => $fila) {
        $posible = ColumnasDelReporte($fila);
        if (isset($posible['documento']) && isset($posible['nombre'])) {
            $mapa = $posible;
            $filaEncabezado = $indice;
            break;
        }
    }

    if ($filaEncabezado === -1) {
        $resumen['errores'][] = 'No se encontraron las columnas del reporte (se esperaba al menos "Número de Documento" y "Nombre").';
        return $resumen;
    }

    /* --- 3. Crear la ficha si aún no existe en esta jornada --- */
    $stmt = mysqli_prepare($conn,"SELECT id_formacion FROM formacion WHERE ficha = ? AND id_jornada = ?");
    mysqli_stmt_bind_param($stmt,"ii",$resumen['ficha'],$idJornada);
    mysqli_stmt_execute($stmt);
    $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($existente) {
        $idFormacion = (int)$existente['id_formacion'];
    } else {
        $ins = mysqli_prepare($conn,"INSERT INTO formacion (ficha,formacion,id_jornada) VALUES (?,?,?)");
        mysqli_stmt_bind_param($ins,"isi",$resumen['ficha'],$resumen['programa'],$idJornada);
        mysqli_stmt_execute($ins);
        $idFormacion = mysqli_insert_id($conn);
        $resumen['ficha_creada'] = true;
    }

    /* --- 4. Cargar los aprendices --- */
    $desconocidos = [];
    $total = count($filas);

    for ($i = $filaEncabezado + 1; $i < $total; $i++) {
        $fila = $filas[$i];
        $numeroFila = $i + 1;

        $documento = trim($fila[$mapa['documento']] ?? '');
        $nombre    = trim($fila[$mapa['nombre']] ?? '');
        $apellidos = isset($mapa['apellidos']) ? trim($fila[$mapa['apellidos']] ?? '') : '';
        $correo    = isset($mapa['correo']) ? trim($fila[$mapa['correo']] ?? '') : '';
        $estadoTxt = isset($mapa['estado']) ? trim($fila[$mapa['estado']] ?? '') : '';

        if ($documento === '' && $nombre === '') { continue; }   /* fila vacía o pie de página */

        $documento = preg_replace('/[^0-9]/', '', $documento);
        if ($documento === '') {
            $resumen['errores'][] = "Fila $numeroFila: documento vacío o no numérico.";
            continue;
        }

        $nombreCompleto = trim($nombre . ' ' . $apellidos);
        if ($nombreCompleto === '') {
            $resumen['errores'][] = "Fila $numeroFila: sin nombre.";
            continue;
        }

        [$idEstado, $reconocido] = EstadoDesdeSofia($estadoTxt);
        if (!$reconocido && $estadoTxt !== '') { $desconocidos[$estadoTxt] = true; }

        $buscar = mysqli_prepare($conn,"SELECT id_aprendiz FROM aprendiz WHERE documento = ?");
        mysqli_stmt_bind_param($buscar,"s",$documento);
        mysqli_stmt_execute($buscar);
        $yaEsta = mysqli_fetch_assoc(mysqli_stmt_get_result($buscar));

        if ($yaEsta) {
            $upd = mysqli_prepare($conn,"UPDATE aprendiz SET nombre = ?, correo = ?, id_formacion = ?, id_estado = ? WHERE id_aprendiz = ?");
            mysqli_stmt_bind_param($upd,"ssiii",$nombreCompleto,$correo,$idFormacion,$idEstado,$yaEsta['id_aprendiz']);
            mysqli_stmt_execute($upd);
            $resumen['actualizados']++;
        } else {
            $idRol = 2;
            $insA = mysqli_prepare($conn,"INSERT INTO aprendiz (nombre,documento,correo,id_formacion,id_rol,id_estado) VALUES (?,?,?,?,?,?)");
            mysqli_stmt_bind_param($insA,"sssiii",$nombreCompleto,$documento,$correo,$idFormacion,$idRol,$idEstado);
            mysqli_stmt_execute($insA);
            $resumen['insertados']++;
        }
    }

    if (!empty($desconocidos)) {
        $resumen['avisos'][] = 'Estados no reconocidos, quedaron como Habilitado: ' . implode(', ', array_keys($desconocidos));
    }

    return $resumen;
}

function ListarFormaciones($conn,$idEvento,$idJornada = null)
{
    $sql = "SELECT f.id_formacion, f.ficha, f.formacion, j.jornada,
                   (SELECT COUNT(*) FROM aprendiz a WHERE a.id_formacion = f.id_formacion AND a.id_rol = 2) AS aprendices
            FROM formacion f
            INNER JOIN jornada j ON f.id_jornada = j.id_jornada
            WHERE j.id_evento = ? AND j.jornada <> 'Administrador'";
    $tipos = "i";
    $parametros = [$idEvento];

    if (!empty($idJornada)) {
        $sql .= " AND j.id_jornada = ?";
        $tipos .= "i";
        $parametros[] = $idJornada;
    }
    $sql .= " ORDER BY j.jornada, f.formacion";

    $stmt = mysqli_prepare($conn,$sql);
    mysqli_stmt_bind_param($stmt,$tipos,...$parametros);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    echo "<div class='tabla-marco'><table class='tabla'>
        <thead><tr>
            <th>Ficha</th>
            <th>Programa de formación</th>
            <th>Jornada</th>
            <th>Aprendices</th>
        </tr></thead><tbody>";

    $total = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $total++;
        echo "<tr>
            <td><span class='num dato-fuerte'>{$row['ficha']}</span></td>
            <td class='dato-fuerte'>".htmlspecialchars($row['formacion'])."</td>
            <td>".htmlspecialchars($row['jornada'])."</td>
            <td><span class='num'>{$row['aprendices']}</span></td>
        </tr>";
    }

    if ($total === 0) {
        echo "<tr><td colspan='4' class='vacio'>Este año todavía no tiene programas de formación registrados.</td></tr>";
    }

    echo "</tbody></table></div>";
}
?>