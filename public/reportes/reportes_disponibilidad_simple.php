<?php

/**
 * Módulo de Reportes - Disponibilidad de Recursos
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado y tenga permisos
require_login();

// Solo administradores y académicos pueden acceder a reportes
if (!has_role([ROL_ADMIN, ROL_ACADEMICO])) {
    $_SESSION['error'] = "No tienes permisos para acceder al módulo de reportes";
    redirect('../admin/dashboard.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Definir variables para filtros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d', strtotime('+7 days'));
$id_tipo = isset($_GET['tipo']) ? intval($_GET['tipo']) : 0;
$id_recurso = isset($_GET['recurso']) ? intval($_GET['recurso']) : 0;
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'diaria';

// Validar y formatear fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
    $fecha_inicio = date('Y-m-d');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_fin = date('Y-m-d', strtotime('+7 days'));
}

// Preparar filtros para la consulta
$filtros = [];
$params = [];

// Añadir filtro de tipo de recurso
if ($id_tipo > 0) {
    $filtros[] = "r.id_tipo = ?";
    $params[] = $id_tipo;
}

// Añadir filtro de recurso específico
if ($id_recurso > 0) {
    $filtros[] = "r.id_recurso = ?";
    $params[] = $id_recurso;
}

// Construir cláusula WHERE para recursos
$where_recursos = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Consulta para obtener recursos según filtros
$sql_recursos = "
    SELECT 
        r.id_recurso, 
        r.nombre AS nombre_recurso,
        r.disponible,
        r.estado,
        t.nombre AS tipo_recurso,
        r.ubicacion
    FROM recursos r
    JOIN tipos_recursos t ON r.id_tipo = t.id_tipo
    $where_recursos
    ORDER BY t.nombre, r.nombre
";

$recursos = $db->getRows($sql_recursos, $params);

// Consulta para obtener las reservas en el período seleccionado
$sql_reservas = "
    SELECT 
        res.id_reserva,
        res.id_recurso,
        res.fecha_inicio,
        res.fecha_fin,
        res.estado,
        CONCAT(u.nombre, ' ', u.apellido) AS nombre_usuario
    FROM reservas res
    JOIN usuarios u ON res.id_usuario = u.id_usuario
    WHERE res.fecha_inicio <= ? 
    AND res.fecha_fin >= ?
    AND res.estado IN ('confirmada', 'pendiente')
";

$params_reservas = [$fecha_fin . ' 23:59:59', $fecha_inicio . ' 00:00:00'];

// Añadir filtros adicionales si es necesario
if ($id_tipo > 0) {
    $sql_reservas .= " AND res.id_recurso IN (SELECT id_recurso FROM recursos WHERE id_tipo = ?)";
    $params_reservas[] = $id_tipo;
}

if ($id_recurso > 0) {
    $sql_reservas .= " AND res.id_recurso = ?";
    $params_reservas[] = $id_recurso;
}

$reservas = $db->getRows($sql_reservas, $params_reservas);

// Consulta para obtener los mantenimientos en el período seleccionado
$sql_mantenimientos = "
    SELECT 
        m.id_mantenimiento,
        m.id_recurso,
        m.fecha_inicio,
        m.fecha_fin,
        m.estado
    FROM mantenimiento m
    WHERE m.fecha_inicio <= ? 
    AND (m.fecha_fin >= ? OR m.fecha_fin IS NULL)
    AND m.estado IN ('pendiente', 'en_progreso')
";

$params_mantenimientos = [$fecha_fin . ' 23:59:59', $fecha_inicio . ' 00:00:00'];

// Añadir filtros adicionales si es necesario
if ($id_tipo > 0) {
    $sql_mantenimientos .= " AND m.id_recurso IN (SELECT id_recurso FROM recursos WHERE id_tipo = ?)";
    $params_mantenimientos[] = $id_tipo;
}

if ($id_recurso > 0) {
    $sql_mantenimientos .= " AND m.id_recurso = ?";
    $params_mantenimientos[] = $id_recurso;
}

$mantenimientos = $db->getRows($sql_mantenimientos, $params_mantenimientos);

// Obtener lista de tipos de recursos para filtrar
$tipos = $db->getRows(
    "SELECT id_tipo, nombre FROM tipos_recursos ORDER BY nombre"
);

// Obtener lista de recursos para filtrar
$recursos_lista = $db->getRows(
    "SELECT r.id_recurso, r.nombre, tr.nombre as tipo 
     FROM recursos r
     JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
     ORDER BY r.nombre"
);

// Calcular la disponibilidad actual
$total_recursos = count($recursos);
$recursos_disponibles = 0;
$recursos_reservados = 0;
$recursos_mantenimiento = 0;

foreach ($recursos as $recurso) {
    $id_recurso = $recurso['id_recurso'];
    $en_mantenimiento = false;
    $reservado = false;

    // Verificar si está en mantenimiento ahora
    foreach ($mantenimientos as $mantenimiento) {
        if ($mantenimiento['id_recurso'] == $id_recurso) {
            $en_mantenimiento = true;
            break;
        }
    }

    // Verificar si está reservado ahora
    $ahora = date('Y-m-d H:i:s');
    foreach ($reservas as $reserva) {
        if (
            $reserva['id_recurso'] == $id_recurso &&
            $reserva['fecha_inicio'] <= $ahora &&
            $reserva['fecha_fin'] >= $ahora
        ) {
            $reservado = true;
            break;
        }
    }

    if ($en_mantenimiento) {
        $recursos_mantenimiento++;
    } elseif ($reservado) {
        $recursos_reservados++;
    } elseif ($recurso['disponible'] == 1 && $recurso['estado'] == 'disponible') {
        $recursos_disponibles++;
    }
}

// Calcular porcentajes
$porcentaje_disponible = $total_recursos > 0 ? ($recursos_disponibles / $total_recursos) * 100 : 0;
$porcentaje_reservado = $total_recursos > 0 ? ($recursos_reservados / $total_recursos) * 100 : 0;
$porcentaje_mantenimiento = $total_recursos > 0 ? ($recursos_mantenimiento / $total_recursos) * 100 : 0;

// Calcular ocupación por horas del día
$horas_ocupadas = [];
for ($hora = 0; $hora < 24; $hora++) {
    $horas_ocupadas[$hora] = [
        'total' => 0,
        'recursos' => []
    ];
}

foreach ($reservas as $reserva) {
    $inicio_hora = (int)date('G', strtotime($reserva['fecha_inicio']));
    $fin_hora = (int)date('G', strtotime($reserva['fecha_fin']));

    // Si la reserva finaliza a las 00:00, considerarla como si terminara a las 23:59
    if ($fin_hora === 0 && date('i', strtotime($reserva['fecha_fin'])) === '00') {
        $fin_hora = 23;
    }

    // Contar cada hora ocupada por la reserva
    for ($hora = $inicio_hora; $hora <= $fin_hora; $hora++) {
        $hora_actual = $hora % 24; // Para manejar reservas que cruzan la medianoche
        $horas_ocupadas[$hora_actual]['total']++;
        $horas_ocupadas[$hora_actual]['recursos'][] = $reserva['id_recurso'];
    }
}

// Encontrar las horas pico (más ocupadas)
$horas_pico = [];
$max_ocupacion = 0;

foreach ($horas_ocupadas as $hora => $datos) {
    if ($datos['total'] > $max_ocupacion) {
        $max_ocupacion = $datos['total'];
        $horas_pico = [$hora];
    } elseif ($datos['total'] == $max_ocupacion && $max_ocupacion > 0) {
        $horas_pico[] = $hora;
    }
}

// Calcular ocupación por día de la semana
$dias_ocupados = [
    0 => ['total' => 0, 'nombre' => 'Domingo'],
    1 => ['total' => 0, 'nombre' => 'Lunes'],
    2 => ['total' => 0, 'nombre' => 'Martes'],
    3 => ['total' => 0, 'nombre' => 'Miércoles'],
    4 => ['total' => 0, 'nombre' => 'Jueves'],
    5 => ['total' => 0, 'nombre' => 'Viernes'],
    6 => ['total' => 0, 'nombre' => 'Sábado']
];

foreach ($reservas as $reserva) {
    $fecha_inicio = new DateTime($reserva['fecha_inicio']);
    $fecha_fin = new DateTime($reserva['fecha_fin']);
    $intervalo = new DateInterval('P1D'); // Intervalo de 1 día
    $periodo = new DatePeriod($fecha_inicio, $intervalo, $fecha_fin);

    // Contar cada día ocupado por la reserva
    foreach ($periodo as $fecha) {
        $dia_semana = (int)$fecha->format('w'); // 0 (domingo) a 6 (sábado)
        $dias_ocupados[$dia_semana]['total']++;
    }

    // Contar también el último día (no incluido en el período)
    $dia_fin = (int)$fecha_fin->format('w');
    $dias_ocupados[$dia_fin]['total']++;
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

// Preparar datos para el gráfico de ocupación por hora
$labels_horas = [];
$datos_ocupacion = [];

for ($hora = 7; $hora <= 22; $hora++) { // Mostrar solo de 7am a 10pm
    $label_hora = $hora . ':00';
    $labels_horas[] = $label_hora;
    $datos_ocupacion[] = $horas_ocupadas[$hora]['total'];
}

// Función para formatear fecha a un formato legible
function formatear_fecha($fecha)
{
    return date('d/m/Y', strtotime($fecha));
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disponibilidad de Recursos - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/reportes.css">
    <link rel="stylesheet" href="../assets/css/pdf-buttons.css">
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
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                    <a href="../reportes/reportes_dashboard.php" class="nav-item active">Reportes</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Disponibilidad de Recursos</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="breadcrumb">
                <a href="../admin/dashboard.php">Dashboard</a> &gt;
                <a href="reportes_dashboard.php">Reportes</a> &gt;
                <span>Disponibilidad de Recursos</span>
            </div>

            <div class="report-filters">
                <h2 class="filter-title">Filtros de Búsqueda</h2>
                <form action="" method="GET">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-label" for="fecha_inicio">Fecha Inicio:</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" class="filter-input" value="<?php echo $fecha_inicio; ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="fecha_fin">Fecha Fin:</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" class="filter-input" value="<?php echo $fecha_fin; ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="tipo">Tipo de Recurso:</label>
                            <select id="tipo" name="tipo" class="filter-select">
                                <option value="0">Todos los tipos</option>
                                <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?php echo $tipo['id_tipo']; ?>" <?php echo ($id_tipo == $tipo['id_tipo']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-label" for="recurso">Recurso:</label>
                            <select id="recurso" name="recurso" class="filter-select">
                                <option value="0">Todos los recursos</option>
                                <?php foreach ($recursos_lista as $recurso): ?>
                                    <option value="<?php echo $recurso['id_recurso']; ?>" <?php echo ($id_recurso == $recurso['id_recurso']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($recurso['nombre'] . ' (' . $recurso['tipo'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="vista">Vista:</label>
                            <select id="vista" name="vista" class="filter-select">
                                <option value="diaria" <?php echo ($vista == 'diaria') ? 'selected' : ''; ?>>Diaria</option>
                                <option value="semanal" <?php echo ($vista == 'semanal') ? 'selected' : ''; ?>>Semanal</option>
                                <option value="mensual" <?php echo ($vista == 'mensual') ? 'selected' : ''; ?>>Mensual</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="filtro-btn">Filtrar</button>
                        <a href="reportes_disponibilidad_simple.php" class="filtro-btn btn-reset">Reiniciar</a>

                        <!-- Botón para exportar a CSV -->
                        <a href="exportar_csv.php?reporte=disponibilidad&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&recurso=<?php echo $id_recurso; ?>&vista=<?php echo $vista; ?>" class="btn btn-secondary csv-btn">
                            <i class="csv-icon"></i> Exportar a CSV
                        </a>

                        <!-- Botón para generar PDF -->
                        <a href="generar_pdf_disponibilidad.php?fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&recurso=<?php echo $id_recurso; ?>&vista=<?php echo $vista; ?>" class="btn btn-primary" style="margin-left: 10px;">
                            <i class="pdf-icon"></i> Generar PDF
                        </a>
                    </div>
                </form>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h2 class="card-title">Disponibilidad Actual</h2>
                    <div class="availability-chart">
                        <canvas id="disponibilidad-chart" height="200"></canvas>
                    </div>
                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($recursos_disponibles); ?></div>
                            <div class="stat-label">Disponibles</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($recursos_reservados); ?></div>
                            <div class="stat-label">Reservados</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($recursos_mantenimiento); ?></div>
                            <div class="stat-label">En Mantenimiento</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_recursos); ?></div>
                            <div class="stat-label">Total Recursos</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Horarios Más Ocupados</h2>
                    <div class="mini-chart" id="horas-chart"></div>
                    <div class="peak-times">
                        <div class="peak-title">Horarios Pico:</div>
                        <div class="peak-values">
                            <?php
                            if (!empty($horas_pico)) {
                                foreach ($horas_pico as $hora) {
                                    echo '<span class="peak-badge">' . $hora . ':00 - ' . ($hora + 1) . ':00</span>';
                                }
                            } else {
                                echo '<span>No se identificaron horarios pico</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="busy-days">
                        <div class="busy-title">Días más ocupados:</div>
                        <div class="busy-values">
                            <?php
                            // Encontrar el día más ocupado
                            $max_dia = 0;
                            $dia_mas_ocupado = '';

                            foreach ($dias_ocupados as $dia => $datos) {
                                if ($datos['total'] > $max_dia) {
                                    $max_dia = $datos['total'];
                                    $dia_mas_ocupado = $datos['nombre'];
                                }
                            }

                            if (!empty($dia_mas_ocupado)) {
                                echo '<span class="busy-badge">' . $dia_mas_ocupado . '</span>';
                            } else {
                                echo '<span>No hay datos suficientes</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Recursos Disponibles Ahora</h2>

                <?php if (empty($recursos)): ?>
                    <div class="empty-state">
                        <p>No hay recursos disponibles para mostrar.</p>
                    </div>
                <?php else: ?>
                    <div class="recursos-grid">
                        <?php
                        $ahora = date('Y-m-d H:i:s');
                        foreach ($recursos as $recurso):
                            $id_recurso = $recurso['id_recurso'];
                            $en_mantenimiento = false;
                            $reservado = false;
                            $reservado_por = '';

                            // Verificar si está en mantenimiento ahora
                            foreach ($mantenimientos as $mantenimiento) {
                                if ($mantenimiento['id_recurso'] == $id_recurso) {
                                    $en_mantenimiento = true;
                                    break;
                                }
                            }

                            // Verificar si está reservado ahora
                            foreach ($reservas as $reserva) {
                                if (
                                    $reserva['id_recurso'] == $id_recurso &&
                                    $reserva['fecha_inicio'] <= $ahora &&
                                    $reserva['fecha_fin'] >= $ahora
                                ) {
                                    $reservado = true;
                                    $reservado_por = $reserva['nombre_usuario'];
                                    break;
                                }
                            }

                            // Determinar estado y clase CSS
                            if ($en_mantenimiento) {
                                $estado = 'En Mantenimiento';
                                $clase = 'mantenimiento';
                            } elseif ($reservado) {
                                $estado = 'Reservado';
                                $clase = 'reservado';
                            } elseif ($recurso['disponible'] == 1 && $recurso['estado'] == 'disponible') {
                                $estado = 'Disponible';
                                $clase = 'disponible';
                            } else {
                                $estado = 'No Disponible';
                                $clase = 'no-disponible';
                            }
                        ?>
                            <div class="recurso-card <?php echo $clase; ?>">
                                <div class="recurso-nombre"><?php echo htmlspecialchars($recurso['nombre_recurso']); ?></div>
                                <div class="recurso-tipo"><?php echo htmlspecialchars($recurso['tipo_recurso']); ?></div>
                                <div class="recurso-ubicacion"><?php echo htmlspecialchars($recurso['ubicacion'] ?: 'Sin ubicación'); ?></div>
                                <div class="recurso-estado"><?php echo $estado; ?></div>
                                <?php if ($reservado && !empty($reservado_por)): ?>
                                    <div class="recurso-reservado-por">Reservado por: <?php echo htmlspecialchars($reservado_por); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($vista == 'diaria'): ?>
                <div class="card">
                    <h2 class="card-title">Disponibilidad por Hora (Hoy)</h2>

                    <?php
                    $fecha_hoy = date('Y-m-d');
                    $horas_dia = [];

                    // Inicializar array para cada hora del día
                    for ($hora = 7; $hora <= 22; $hora++) { // De 7am a 10pm
                        $horas_dia[$hora] = [
                            'disponibles' => $total_recursos,
                            'ocupados' => 0,
                            'recursos_ocupados' => []
                        ];
                    }

                    // Calcular ocupación para hoy
                    foreach ($reservas as $reserva) {
                        $fecha_inicio_reserva = date('Y-m-d', strtotime($reserva['fecha_inicio']));
                        $fecha_fin_reserva = date('Y-m-d', strtotime($reserva['fecha_fin']));

                        if ($fecha_inicio_reserva <= $fecha_hoy && $fecha_fin_reserva >= $fecha_hoy) {
                            $hora_inicio = max(7, (int)date('G', strtotime($reserva['fecha_inicio'])));
                            $hora_fin = min(22, (int)date('G', strtotime($reserva['fecha_fin'])));

                            for ($hora = $hora_inicio; $hora <= $hora_fin; $hora++) {
                                if (isset($horas_dia[$hora])) {
                                    $horas_dia[$hora]['ocupados']++;
                                    $horas_dia[$hora]['recursos_ocupados'][] = $reserva['id_recurso'];
                                    $horas_dia[$hora]['disponibles'] = $total_recursos - count(array_unique($horas_dia[$hora]['recursos_ocupados']));
                                }
                            }
                        }
                    }
                    ?>

                    <div class="disponibilidad-horaria">
                        <div class="horas-header">
                            <?php for ($hora = 7; $hora <= 22; $hora++): ?>
                                <div class="hora-celda"><?php echo $hora; ?>:00</div>
                            <?php endfor; ?>
                        </div>
                        <div class="disponibilidad-barra">
                            <?php for ($hora = 7; $hora <= 22; $hora++):
                                $porcentaje_ocupacion = $total_recursos > 0 ?
                                    ((($total_recursos - $horas_dia[$hora]['disponibles']) / $total_recursos) * 100) : 0;
                                $clase_ocupacion = $porcentaje_ocupacion > 80 ? 'muy-ocupado' : ($porcentaje_ocupacion > 50 ? 'ocupado' : ($porcentaje_ocupacion > 30 ? 'medio-ocupado' : 'poco-ocupado'));
                            ?>
                                <div class="hora-ocupacion <?php echo $clase_ocupacion; ?>" style="height: <?php echo $porcentaje_ocupacion; ?>%">
                                    <div class="ocupacion-info">
                                        <div class="ocupacion-porcentaje"><?php echo number_format($porcentaje_ocupacion, 0); ?>%</div>
                                        <div class="ocupacion-detalle">
                                            <?php echo ($total_recursos - $horas_dia[$hora]['disponibles']); ?> de <?php echo $total_recursos; ?> ocupados
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2 class="card-title">Próximas Reservas</h2>

                <?php
                // Obtener próximas reservas
                $sql_proximas = "
                    SELECT 
                        res.fecha_inicio,
                        res.fecha_fin,
                        r.nombre AS nombre_recurso,
                        CONCAT(u.nombre, ' ', u.apellido) AS nombre_usuario
                    FROM reservas res
                    JOIN recursos r ON res.id_recurso = r.id_recurso
                    JOIN usuarios u ON res.id_usuario = u.id_usuario
                    WHERE res.fecha_inicio > ? 
                    AND res.fecha_inicio <= ?
                    AND res.estado IN ('confirmada', 'pendiente')
                    " . ($id_tipo > 0 ? " AND r.id_tipo = " . intval($id_tipo) : "") . "
                    " . ($id_recurso > 0 ? " AND res.id_recurso = " . intval($id_recurso) : "") . "
                    ORDER BY res.fecha_inicio
                    LIMIT 10
                ";

                $proximas_reservas = $db->getRows($sql_proximas, [date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+7 days'))]);
                ?>

                <?php if (empty($proximas_reservas)): ?>
                    <div class="empty-state">
                        <p>No hay próximas reservas para mostrar.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Recurso</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Fin</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proximas_reservas as $reserva): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reserva['nombre_recurso']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_inicio'])); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_fin'])); ?></td>
                                        <td><?php echo htmlspecialchars($reserva['nombre_usuario']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">Análisis y Recomendaciones</h2>

                <div style="margin-top: 20px;">
                    <h3 style="font-size: 16px; margin-bottom: 10px;">Análisis de Disponibilidad:</h3>

                    <ul style="padding-left: 20px; margin-bottom: 15px;">
                        <?php
                        // Calcular porcentaje de disponibilidad actual
                        $porcentaje_disponible = $total_recursos > 0 ? ($recursos_disponibles / $total_recursos) * 100 : 0;

                        // Encontrar la hora más ocupada
                        $hora_mas_ocupada = 0;
                        $ocupacion_maxima = 0;
                        foreach ($horas_ocupadas as $hora => $datos) {
                            if ($datos['total'] > $ocupacion_maxima) {
                                $ocupacion_maxima = $datos['total'];
                                $hora_mas_ocupada = $hora;
                            }
                        }

                        // Calcular el día más ocupado
                        $dia_mas_ocupado = "";
                        $max_ocupacion_dia = 0;
                        foreach ($dias_ocupados as $dia => $datos) {
                            if ($datos['total'] > $max_ocupacion_dia) {
                                $max_ocupacion_dia = $datos['total'];
                                $dia_mas_ocupado = $datos['nombre'];
                            }
                        }
                        ?>

                        <li>
                            <strong>Disponibilidad actual:</strong>
                            <?php echo number_format($porcentaje_disponible, 1); ?>% de los recursos están disponibles ahora mismo
                            (<?php echo $recursos_disponibles; ?> de <?php echo $total_recursos; ?>).
                            <?php if ($porcentaje_disponible < 30): ?>
                                Esta disponibilidad es baja, lo que indica una alta demanda de recursos en este momento.
                            <?php elseif ($porcentaje_disponible > 70): ?>
                                Esta disponibilidad es alta, lo que sugiere que hay muchos recursos sin utilizar actualmente.
                            <?php endif; ?>
                        </li>

                        <?php if (!empty($hora_mas_ocupada)): ?>
                            <li>
                                <strong>Horario más ocupado:</strong>
                                La franja horaria de <?php echo $hora_mas_ocupada; ?>:00 a <?php echo ($hora_mas_ocupada + 1); ?>:00
                                es la que registra mayor número de reservas (<?php echo $ocupacion_maxima; ?>).
                            </li>
                        <?php endif; ?>

                        <?php if (!empty($dia_mas_ocupado)): ?>
                            <li>
                                <strong>Día con mayor ocupación:</strong>
                                <?php echo $dia_mas_ocupado; ?> es el día de la semana con mayor número de reservas
                                (<?php echo $max_ocupacion_dia; ?> reservas).
                            </li>
                        <?php endif; ?>

                        <li>
                            <strong>Recursos en mantenimiento:</strong>
                            Actualmente hay <?php echo $recursos_mantenimiento; ?> recursos en mantenimiento
                            (<?php echo number_format(($recursos_mantenimiento / $total_recursos) * 100, 1); ?>% del total).
                        </li>
                    </ul>

                    <div style="margin-top: 20px; padding: 15px; background-color: rgba(74, 144, 226, 0.1); border-radius: 4px;">
                        <h4 style="margin-bottom: 10px; color: #4A90E2;">Recomendaciones:</h4>

                        <ul style="padding-left: 20px; margin-bottom: 0;">
                            <?php if ($porcentaje_disponible < 30): ?>
                                <li>Considere aumentar la cantidad de recursos disponibles dado el alto nivel de ocupación actual.</li>
                            <?php endif; ?>

                            <?php if (!empty($hora_mas_ocupada)): ?>
                                <li>Programar mantenimientos fuera de la franja horaria de <?php echo $hora_mas_ocupada; ?>:00 a <?php echo ($hora_mas_ocupada + 1); ?>:00 para minimizar el impacto en los usuarios.</li>
                            <?php endif; ?>

                            <?php if (!empty($dia_mas_ocupado)): ?>
                                <li>Aumentar la disponibilidad de recursos los días <?php echo $dia_mas_ocupado; ?> debido a la alta demanda registrada.</li>
                            <?php endif; ?>

                            <?php if ($recursos_mantenimiento > $total_recursos * 0.2): // Si más del 20% está en mantenimiento 
                            ?>
                                <li>Revisar la planificación de mantenimientos. Actualmente hay un porcentaje elevado de recursos no disponibles por este motivo.</li>
                            <?php endif; ?>

                            <li>Monitorear regularmente los patrones de uso para optimizar la disponibilidad de recursos en los momentos de mayor demanda.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico de disponibilidad actual (doughnut chart)
            const ctxDisponibilidad = document.getElementById('disponibilidad-chart').getContext('2d');
            new Chart(ctxDisponibilidad, {
                type: 'doughnut',
                data: {
                    labels: ['Disponibles', 'Reservados', 'En Mantenimiento'],
                    datasets: [{
                        data: [
                            <?php echo $recursos_disponibles; ?>,
                            <?php echo $recursos_reservados; ?>,
                            <?php echo $recursos_mantenimiento; ?>
                        ],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.7)', // Verde para disponibles
                            'rgba(0, 123, 255, 0.7)', // Azul para reservados
                            'rgba(255, 193, 7, 0.7)' // Amarillo para mantenimiento
                        ],
                        borderColor: [
                            'rgba(40, 167, 69, 1)',
                            'rgba(0, 123, 255, 1)',
                            'rgba(255, 193, 7, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'right'
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                const dataset = data.datasets[tooltipItem.datasetIndex];
                                const total = dataset.data.reduce((previousValue, currentValue) => previousValue + currentValue);
                                const currentValue = dataset.data[tooltipItem.index];
                                const percentage = Math.round((currentValue / total) * 100);
                                return `${data.labels[tooltipItem.index]}: ${currentValue} (${percentage}%)`;
                            }
                        }
                    }
                }
            });

            // Gráfico de ocupación por hora (bar chart)
            const ctxHoras = document.getElementById('horas-chart').getContext('2d');
            new Chart(ctxHoras, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($labels_horas); ?>,
                    datasets: [{
                        label: 'Reservas',
                        data: <?php echo json_encode($datos_ocupacion); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true
                            }
                        }]
                    },
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Ocupación por hora'
                    }
                }
            });
        });
    </script>
</body>

</html>