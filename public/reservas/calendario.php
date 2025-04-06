<?php

/**
 * Calendario visual de reservas
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado
require_login();

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Definir variables para filtros
$mes_actual = isset($_GET['mes']) ? intval($_GET['mes']) : intval(date('m'));
$anio_actual = isset($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));
$id_recurso = isset($_GET['recurso']) ? intval($_GET['recurso']) : 0;
$id_tipo = isset($_GET['tipo']) ? intval($_GET['tipo']) : 0;

// Validar rango de fechas
if ($mes_actual < 1 || $mes_actual > 12) {
    $mes_actual = date('m');
}

// Obtener primer y último día del mes
$primer_dia_mes = "$anio_actual-$mes_actual-01";
$ultimo_dia_mes = date('Y-m-t', strtotime($primer_dia_mes));

// Preparar filtros para la consulta
$filtros = [];
$params = [];

// Añadir filtro de recurso si está seleccionado
if ($id_recurso > 0) {
    $filtros[] = "r.id_recurso = ?";
    $params[] = $id_recurso;
}

// Añadir filtro de tipo de recurso si está seleccionado
if ($id_tipo > 0) {
    $filtros[] = "rc.id_tipo = ?";
    $params[] = $id_tipo;
}

// Añadir filtro de fechas (para el mes seleccionado)
$filtros[] = "(
    (r.fecha_inicio BETWEEN ? AND ?) OR 
    (r.fecha_fin BETWEEN ? AND ?) OR 
    (r.fecha_inicio <= ? AND r.fecha_fin >= ?)
)";
$params[] = $primer_dia_mes . ' 00:00:00';
$params[] = $ultimo_dia_mes . ' 23:59:59';
$params[] = $primer_dia_mes . ' 00:00:00';
$params[] = $ultimo_dia_mes . ' 23:59:59';
$params[] = $primer_dia_mes . ' 00:00:00';
$params[] = $ultimo_dia_mes . ' 23:59:59';

// Construir cláusula WHERE
$where = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Consultar reservas para el mes seleccionado
$sql = "SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado, 
               r.descripcion, rc.nombre as recurso_nombre, 
               rc.ubicacion as recurso_ubicacion,
               u.nombre as usuario_nombre, u.apellido as usuario_apellido,
               tr.nombre as tipo_recurso
        FROM reservas r
        JOIN recursos rc ON r.id_recurso = rc.id_recurso
        JOIN usuarios u ON r.id_usuario = u.id_usuario
        JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
        $where
        ORDER BY r.fecha_inicio";

$reservas = $db->getRows($sql, $params);

// Obtener lista de recursos para filtrar
$recursos = $db->getRows(
    "SELECT id_recurso, nombre, ubicacion FROM recursos WHERE disponible = 1 ORDER BY nombre"
);

// Obtener lista de tipos de recursos para filtrar
$tipos = $db->getRows(
    "SELECT id_tipo, nombre FROM tipos_recursos ORDER BY nombre"
);

// Preparar datos para el calendario
$dias_mes = date('t', strtotime($primer_dia_mes));
$dia_semana_inicio = date('N', strtotime($primer_dia_mes));
$nombre_mes = date('F', strtotime($primer_dia_mes));
$nombre_mes_espanol = traducirMes($nombre_mes);

// Función para traducir nombre de mes al español
function traducirMes($mes)
{
    $meses = [
        'January' => 'Enero',
        'February' => 'Febrero',
        'March' => 'Marzo',
        'April' => 'Abril',
        'May' => 'Mayo',
        'June' => 'Junio',
        'July' => 'Julio',
        'August' => 'Agosto',
        'September' => 'Septiembre',
        'October' => 'Octubre',
        'November' => 'Noviembre',
        'December' => 'Diciembre'
    ];

    return $meses[$mes] ?? $mes;
}

// Organizar las reservas por día
$eventos_por_dia = [];
foreach ($reservas as $reserva) {
    // Convertir fechas a objetos DateTime
    $fecha_inicio = new DateTime($reserva['fecha_inicio']);
    $fecha_fin = new DateTime($reserva['fecha_fin']);

    // Si la reserva abarca varios días, añadirla a cada día dentro del mes actual
    $fecha_actual = clone $fecha_inicio;

    // Ajustar la fecha actual al inicio del mes si es anterior
    if ($fecha_actual < new DateTime($primer_dia_mes)) {
        $fecha_actual = new DateTime($primer_dia_mes);
    }

    // Iterar hasta el final de la reserva o el final del mes
    $fecha_fin_mes = new DateTime($ultimo_dia_mes . ' 23:59:59');
    $fecha_fin_reserva = ($fecha_fin > $fecha_fin_mes) ? $fecha_fin_mes : $fecha_fin;

    while ($fecha_actual <= $fecha_fin_reserva) {
        $dia = $fecha_actual->format('j');

        if (!isset($eventos_por_dia[$dia])) {
            $eventos_por_dia[$dia] = [];
        }

        $eventos_por_dia[$dia][] = $reserva;

        // Avanzar al siguiente día
        $fecha_actual->modify('+1 day');
    }
}

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Calcular el mes y año anterior
$mes_anterior = $mes_actual - 1;
$anio_anterior = $anio_actual;
if ($mes_anterior < 1) {
    $mes_anterior = 12;
    $anio_anterior--;
}

// Calcular el mes y año siguiente
$mes_siguiente = $mes_actual + 1;
$anio_siguiente = $anio_actual;
if ($mes_siguiente > 12) {
    $mes_siguiente = 1;
    $anio_siguiente++;
}

// Función para obtener el color según el estado de la reserva
function colorEstado($estado)
{
    $colores = [
        'pendiente' => 'badge-warning',
        'confirmada' => 'badge-success',
        'cancelada' => 'badge-danger',
        'completada' => 'badge-info'
    ];

    return $colores[$estado] ?? 'badge-secondary';
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Reservas - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        /* Estilos específicos para el calendario */
        .calendar {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }

        .calendar th {
            text-align: center;
            padding: 12px 0;
            background-color: var(--light-color);
            border: 1px solid #ddd;
        }

        .calendar td {
            height: 120px;
            border: 1px solid #ddd;
            vertical-align: top;
            padding: 5px;
            background-color: white;
            position: relative;
        }

        .calendar td.empty {
            background-color: #f9f9f9;
        }

        .day-number {
            font-weight: 600;
            font-size: 14px;
            position: absolute;
            top: 5px;
            right: 5px;
            color: var(--dark-color);
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            border-radius: 50%;
        }

        .is-today .day-number {
            background-color: var(--primary-color);
            color: white;
        }

        .events-container {
            margin-top: 30px;
            max-height: 95px;
            overflow-y: auto;
        }

        .event {
            font-size: 12px;
            margin-bottom: 4px;
            padding: 4px;
            border-radius: 4px;
            background-color: rgba(74, 144, 226, 0.1);
            border-left: 3px solid var(--primary-color);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .event:hover {
            background-color: rgba(74, 144, 226, 0.2);
        }

        .event.pendiente {
            border-left-color: var(--warning-color);
        }

        .event.confirmada {
            border-left-color: var(--success-color);
        }

        .event.cancelada {
            border-left-color: var(--danger-color);
        }

        .event.completada {
            border-left-color: #17a2b8;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
        }

        .month-selector {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
            color: var(--dark-color);
            transition: all 0.2s;
        }

        .month-selector:hover {
            background-color: var(--light-color);
        }

        .today-btn {
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .today-btn:hover {
            background-color: #3a80d2;
        }

        /* Tooltip para eventos */
        .event-tooltip {
            position: absolute;
            z-index: 10;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 10px;
            width: 250px;
            display: none;
            font-size: 12px;
        }

        .event-tooltip h4 {
            font-size: 14px;
            margin-bottom: 5px;
            color: var(--dark-color);
        }

        .event-tooltip p {
            margin: 5px 0;
        }

        .event:hover+.event-tooltip {
            display: block;
        }
    </style>
</head>

<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"></div>
                <div>Sistema de Gestión</div>
            </div>
            <div class="sidebar-nav">
                <a href="../admin/dashboard.php" class="nav-item">Dashboard</a>
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="../usuarios/listar.php" class="nav-item">Usuarios</a>
                <?php endif; ?>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item active">Calendario</a>
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                    <a href="../reportes/index.php" class="nav-item">Reportes</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Calendario de Reservas</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <!-- Filtros de Calendario -->
            <div class="card">
                <form action="" method="GET" class="filtros">
                    <!-- Recurso -->
                    <div class="filtro-grupo">
                        <label class="filtro-label" for="recurso">Recurso:</label>
                        <select name="recurso" id="recurso" class="filtro-select">
                            <option value="0">Todos los recursos</option>
                            <?php foreach ($recursos as $recurso): ?>
                                <option value="<?php echo $recurso['id_recurso']; ?>" <?php echo ($id_recurso == $recurso['id_recurso']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($recurso['nombre']); ?>
                                    <?php echo !empty($recurso['ubicacion']) ? '(' . htmlspecialchars($recurso['ubicacion']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tipo de Recurso -->
                    <div class="filtro-grupo">
                        <label class="filtro-label" for="tipo">Tipo:</label>
                        <select name="tipo" id="tipo" class="filtro-select">
                            <option value="0">Todos los tipos</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?php echo $tipo['id_tipo']; ?>" <?php echo ($id_tipo == $tipo['id_tipo']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Inputs ocultos para mantener mes y año -->
                    <input type="hidden" name="mes" value="<?php echo $mes_actual; ?>">
                    <input type="hidden" name="anio" value="<?php echo $anio_actual; ?>">

                    <!-- Botones -->
                    <button type="submit" class="filtro-btn">Filtrar</button>
                    <a href="calendario.php" class="filtro-btn btn-reset">Reiniciar</a>
                    <a href="crear.php" class="btn-agregar">+ Nueva Reserva</a>
                </form>
            </div>

            <!-- Calendario -->
            <div class="card">
                <div class="calendar-header">
                    <div class="calendar-title">
                        <?php echo $nombre_mes_espanol; ?> <?php echo $anio_actual; ?>
                    </div>
                    <div class="calendar-nav">
                        <a href="?mes=<?php echo $mes_anterior; ?>&anio=<?php echo $anio_anterior; ?>&recurso=<?php echo $id_recurso; ?>&tipo=<?php echo $id_tipo; ?>" class="month-selector">
                            &laquo; Mes Anterior
                        </a>
                        <button type="button" onclick="window.location='?mes=<?php echo date('m'); ?>&anio=<?php echo date('Y'); ?>&recurso=<?php echo $id_recurso; ?>&tipo=<?php echo $id_tipo; ?>'" class="today-btn">
                            Hoy
                        </button>
                        <a href="?mes=<?php echo $mes_siguiente; ?>&anio=<?php echo $anio_siguiente; ?>&recurso=<?php echo $id_recurso; ?>&tipo=<?php echo $id_tipo; ?>" class="month-selector">
                            Mes Siguiente &raquo;
                        </a>
                    </div>
                </div>

                <table class="calendar">
                    <thead>
                        <tr>
                            <th>Lunes</th>
                            <th>Martes</th>
                            <th>Miércoles</th>
                            <th>Jueves</th>
                            <th>Viernes</th>
                            <th>Sábado</th>
                            <th>Domingo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Iniciar la primera semana
                        echo "<tr>";

                        // Espacios vacíos antes del primer día del mes
                        for ($i = 1; $i < $dia_semana_inicio; $i++) {
                            echo "<td class='empty'></td>";
                        }

                        // Días del mes
                        for ($dia = 1; $dia <= $dias_mes; $dia++) {
                            $fecha_completa = "$anio_actual-$mes_actual-" . str_pad($dia, 2, '0', STR_PAD_LEFT);
                            $es_hoy = ($fecha_completa == date('Y-m-d'));
                            $clase_hoy = $es_hoy ? 'is-today' : '';

                            // Día actual de la semana (1-7)
                            $dia_semana = date('N', strtotime($fecha_completa));

                            // Si es el primer día de la semana y no es el primer día, cerrar la fila anterior y abrir una nueva
                            if ($dia_semana == 1 && $dia != 1) {
                                echo "</tr><tr>";
                            }

                            // Mostrar celda del día
                            echo "<td class='$clase_hoy'>";
                            echo "<div class='day-number'>$dia</div>";

                            // Mostrar eventos para este día
                            if (isset($eventos_por_dia[$dia]) && !empty($eventos_por_dia[$dia])) {
                                echo "<div class='events-container'>";

                                foreach ($eventos_por_dia[$dia] as $evento) {
                                    $hora_inicio = date('H:i', strtotime($evento['fecha_inicio']));
                                    $hora_fin = date('H:i', strtotime($evento['fecha_fin']));

                                    echo "<div class='event {$evento['estado']}' data-id='{$evento['id_reserva']}'>";
                                    echo substr($evento['recurso_nombre'], 0, 15) . (strlen($evento['recurso_nombre']) > 15 ? '...' : '');
                                    echo "</div>";

                                    // Tooltip con información detallada
                                    echo "<div class='event-tooltip'>";
                                    echo "<h4>" . htmlspecialchars($evento['recurso_nombre']) . "</h4>";
                                    echo "<p><strong>Tipo:</strong> " . htmlspecialchars($evento['tipo_recurso']) . "</p>";
                                    echo "<p><strong>Inicio:</strong> " . date('d/m/Y H:i', strtotime($evento['fecha_inicio'])) . "</p>";
                                    echo "<p><strong>Fin:</strong> " . date('d/m/Y H:i', strtotime($evento['fecha_fin'])) . "</p>";
                                    echo "<p><strong>Usuario:</strong> " . htmlspecialchars($evento['usuario_nombre'] . ' ' . $evento['usuario_apellido']) . "</p>";
                                    echo "<p><strong>Estado:</strong> <span class='badge " . colorEstado($evento['estado']) . "'>" . ucfirst($evento['estado']) . "</span></p>";
                                    if (!empty($evento['descripcion'])) {
                                        echo "<p><strong>Descripción:</strong> " . htmlspecialchars(substr($evento['descripcion'], 0, 100)) . (strlen($evento['descripcion']) > 100 ? '...' : '') . "</p>";
                                    }
                                    echo "<p><a href='ver.php?id={$evento['id_reserva']}'>Ver detalles</a></p>";
                                    echo "</div>";
                                }

                                echo "</div>";
                            }

                            echo "</td>";
                        }

                        // Espacios vacíos después del último día del mes
                        $ultimo_dia_semana = date('N', strtotime($ultimo_dia_mes));
                        for ($i = $ultimo_dia_semana + 1; $i <= 7; $i++) {
                            echo "<td class='empty'></td>";
                        }

                        echo "</tr>";
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2 class="card-title">Leyenda</h2>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div>
                        <span class="badge badge-warning">Pendiente</span>
                        <span> - Reserva pendiente de aprobación</span>
                    </div>
                    <div>
                        <span class="badge badge-success">Confirmada</span>
                        <span> - Reserva confirmada</span>
                    </div>
                    <div>
                        <span class="badge badge-danger">Cancelada</span>
                        <span> - Reserva cancelada</span>
                    </div>
                    <div>
                        <span class="badge badge-info">Completada</span>
                        <span> - Reserva finalizada</span>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <p>
                        <strong>Nota:</strong> Pase el cursor sobre un evento para ver más detalles
                        o haga clic para ir a la página de detalles de la reserva.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hacer clic en un evento para ir a la página de detalles
            const eventos = document.querySelectorAll('.event');
            eventos.forEach(function(evento) {
                evento.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    window.location.href = `ver.php?id=${id}`;
                });
            });

            // Cambiar mes y año cuando cambian los selectores
            const recursoSelect = document.getElementById('recurso');
            const tipoSelect = document.getElementById('tipo');

            // Actualizar los inputs ocultos al cambiar el mes o año
            recursoSelect.addEventListener('change', actualizarFiltros);
            tipoSelect.addEventListener('change', actualizarFiltros);

            function actualizarFiltros() {
                const form = document.querySelector('.filtros');
                form.submit();
            }
        });
    </script>
</body>

</html>