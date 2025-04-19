<?php

/**
 * Módulo de Reportes - Estadísticas de Reservas
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
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-90 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$id_tipo = isset($_GET['tipo']) ? intval($_GET['tipo']) : 0;
$id_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$modo_visualizacion = isset($_GET['visualizacion']) ? $_GET['visualizacion'] : 'mensual';

// Validar y formatear fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
    $fecha_inicio = date('Y-m-d', strtotime('-90 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_fin = date('Y-m-d');
}

// Verificar diferencia de fechas para visualización diaria
$diff = strtotime($fecha_fin) - strtotime($fecha_inicio);
$dias_diff = floor($diff / (60 * 60 * 24));
if ($dias_diff > 365 && $modo_visualizacion == 'diario') {
    $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
    $_SESSION['info'] = "El rango de fechas era demasiado amplio para visualización diaria. Se ha ajustado a los últimos 30 días.";
}

// Preparar filtros para consultas
$filtros = [];
$params = [];

// Filtros de fechas
$filtros[] = "r.fecha_inicio >= ?";
$params[] = $fecha_inicio . ' 00:00:00';
$filtros[] = "r.fecha_inicio <= ?";
$params[] = $fecha_fin . ' 23:59:59';

// Filtro de tipo de recurso
if ($id_tipo > 0) {
    $filtros[] = "tr.id_tipo = ?";
    $params[] = $id_tipo;
}
// Filtro de usuario
if ($id_usuario > 0) {
    $filtros[] = "r.id_usuario = ?";
    $params[] = $id_usuario;
}

// Construir cláusula WHERE
$where = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Consulta según el modo de visualización
switch ($modo_visualizacion) {
    case 'diario':
        $sql_tendencia = "
            SELECT 
                DATE(r.fecha_inicio) as periodo,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            {$where}
            GROUP BY DATE(r.fecha_inicio)
            ORDER BY periodo ASC
        ";
        break;
    case 'semanal':
        $sql_tendencia = "
            SELECT 
                CONCAT(YEAR(r.fecha_inicio), '-', WEEK(r.fecha_inicio)) as periodo_id,
                CONCAT('Semana ', WEEK(r.fecha_inicio), ' (', YEAR(r.fecha_inicio), ')') as periodo,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            {$where}
            GROUP BY YEAR(r.fecha_inicio), WEEK(r.fecha_inicio)
            ORDER BY YEAR(r.fecha_inicio) ASC, WEEK(r.fecha_inicio) ASC
        ";
        break;
    case 'mensual':
    default:
        $sql_tendencia = "
            SELECT 
                DATE_FORMAT(r.fecha_inicio, '%Y-%m') as periodo_id,
                DATE_FORMAT(r.fecha_inicio, '%M %Y') as periodo,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            {$where}
            GROUP BY DATE_FORMAT(r.fecha_inicio, '%Y-%m')
            ORDER BY periodo_id ASC
        ";
        break;
}

// Ejecutar consultas
$tendencias   = $db->getRows($sql_tendencia, $params);

$sql_dias_semana = "
    SELECT 
        DAYOFWEEK(r.fecha_inicio) as dia_numero,
        CASE DAYOFWEEK(r.fecha_inicio)
            WHEN 1 THEN 'Domingo'
            WHEN 2 THEN 'Lunes'
            WHEN 3 THEN 'Martes'
            WHEN 4 THEN 'Miércoles'
            WHEN 5 THEN 'Jueves'
            WHEN 6 THEN 'Viernes'
            WHEN 7 THEN 'Sábado'
        END as dia_semana,
        COUNT(*) as total_reservas
    FROM reservas r
    JOIN recursos rc ON r.id_recurso = rc.id_recurso
    JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
    {$where}
    GROUP BY DAYOFWEEK(r.fecha_inicio)
    ORDER BY dia_numero ASC
";
$dias_semana = $db->getRows($sql_dias_semana, $params);

$sql_horas = "
    SELECT 
        HOUR(r.fecha_inicio) as hora,
        COUNT(*) as total_reservas
    FROM reservas r
    JOIN recursos rc ON r.id_recurso = rc.id_recurso
    JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
    {$where}
    GROUP BY HOUR(r.fecha_inicio)
    ORDER BY hora ASC
";
$horas = $db->getRows($sql_horas, $params);

$sql_duracion = "
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin) <= 1 THEN 'Menos de 1 hora'
            WHEN TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin) <= 2 THEN '1-2 horas'
            WHEN TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin) <= 4 THEN '2-4 horas'
            WHEN TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin) <= 8 THEN '4-8 horas'
            ELSE 'Más de 8 horas'
        END as rango_duracion,
        COUNT(*) as total_reservas
    FROM reservas r
    JOIN recursos rc ON r.id_recurso = rc.id_recurso
    JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
    {$where}
    GROUP BY rango_duracion
    ORDER BY 
        CASE rango_duracion
            WHEN 'Menos de 1 hora' THEN 1
            WHEN '1-2 horas' THEN 2
            WHEN '2-4 horas' THEN 3
            WHEN '4-8 horas' THEN 4
            ELSE 5
        END ASC
";
$duraciones = $db->getRows($sql_duracion, $params);

$sql_estados = "
    SELECT 
        r.estado,
        COUNT(*) as total_reservas
    FROM reservas r
    JOIN recursos rc ON r.id_recurso = rc.id_recurso
    JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
    {$where}
    GROUP BY r.estado
";
$estados = $db->getRows($sql_estados, $params);

$sql_tipos = "
    SELECT 
        tr.nombre as tipo_recurso,
        COUNT(*) as total_reservas
    FROM reservas r
    JOIN recursos rc ON r.id_recurso = rc.id_recurso
    JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
    {$where}
    GROUP BY tr.id_tipo
    ORDER BY total_reservas DESC
";
$tipos_recurso = $db->getRows($sql_tipos, $params);

$sql_total = "
    SELECT COUNT(*) as total FROM reservas r
    JOIN recursos rc ON r.id_recurso = rc.id_recurso
    JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
    {$where}
";
$total_reservas = $db->getRow($sql_total, $params)['total'] ?? 0;

$tipos = $db->getRows("SELECT id_tipo, nombre FROM tipos_recursos ORDER BY nombre");
$usuarios = $db->getRows(
    "SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) as nombre_completo, r.nombre as rol
     FROM usuarios u
     JOIN roles r ON u.id_rol = r.id_rol
     WHERE u.activo = 1
     ORDER BY u.nombre, u.apellido"
);

// Manejo de mensajes
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
} elseif (isset($_SESSION['info'])) {
    $mensaje = '<div class="alert alert-info">' . $_SESSION['info'] . '</div>';
    unset($_SESSION['info']);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Reservas - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/reportes.css">
    <link rel="stylesheet" href="../assets/css/pdf-buttons.css">
    <!-- Incluir Chart.js vía CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Se fuerza un tamaño mínimo para visualizar correctamente el canvas */
        .chart-container {
            width: 100%;
            min-height: 300px;
            position: relative;
        }

        canvas {
            width: 100% !important;
            height: 300px !important;
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
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                    <a href="../reportes/reportes_dashboard.php" class="nav-item active">Reportes</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Estadísticas de Reservas</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="breadcrumb">
                <a href="../admin/dashboard.php">Dashboard</a> &gt;
                <a href="reportes_dashboard.php">Reportes</a> &gt;
                <span>Estadísticas de Reservas</span>
            </div>

            <div class="report-filters">
                <h2 class="filter-title">Filtros de Análisis</h2>
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
                            <label class="filter-label" for="usuario">Usuario:</label>
                            <select id="usuario" name="usuario" class="filter-select">
                                <option value="0">Todos los usuarios</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?php echo $usuario['id_usuario']; ?>" <?php echo ($id_usuario == $usuario['id_usuario']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($usuario['nombre_completo'] . ' (' . $usuario['rol'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label" for="visualizacion">Visualización:</label>
                            <select id="visualizacion" name="visualizacion" class="filter-select">
                                <option value="diario" <?php echo ($modo_visualizacion == 'diario') ? 'selected' : ''; ?>>Diaria</option>
                                <option value="semanal" <?php echo ($modo_visualizacion == 'semanal') ? 'selected' : ''; ?>>Semanal</option>
                                <option value="mensual" <?php echo ($modo_visualizacion == 'mensual') ? 'selected' : ''; ?>>Mensual</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="filtro-btn">Filtrar</button>
                        <a href="estadisticas_reservas.php" class="filtro-btn btn-reset">Reiniciar</a>
                        <a href="exportar_csv.php?reporte=estadisticas_reservas&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&usuario=<?php echo $id_usuario; ?>&visualizacion=<?php echo $modo_visualizacion; ?>" class="btn btn-secondary csv-btn">
                            <i class="csv-icon"></i> Exportar a CSV
                        </a>
                        <a href="generar_pdf_estadisticas_reservas.php?fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&usuario=<?php echo $id_usuario; ?>&visualizacion=<?php echo $modo_visualizacion; ?>" class="btn btn-primary" style="margin-left: 10px;">
                            <i class="pdf-icon"></i> Generar PDF
                        </a>
                    </div>
                </form>
            </div>

            <div class="stats-container" style="margin-bottom: 20px;">
                <div class="card">
                    <h2 class="card-title">Resumen del Período</h2>
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px;">
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo $total_reservas; ?></div>
                            <div class="stat-label">Total Reservas</div>
                        </div>
                        <?php
                        $total_estados = [
                            'pendiente' => 0,
                            'confirmada' => 0,
                            'cancelada' => 0,
                            'completada' => 0
                        ];
                        foreach ($estados as $estado) {
                            $total_estados[$estado['estado']] = $estado['total_reservas'];
                        }
                        ?>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo $total_estados['confirmada']; ?></div>
                            <div class="stat-label">Confirmadas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo $total_estados['pendiente']; ?></div>
                            <div class="stat-label">Pendientes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo $total_estados['cancelada']; ?></div>
                            <div class="stat-label">Canceladas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo $total_estados['completada']; ?></div>
                            <div class="stat-label">Completadas</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección de gráficos -->
            <div class="grid-2">
                <!-- Gráfico de Tendencia de Reservas -->
                <div class="card">
                    <h2 class="card-title">Tendencia de Reservas <?php echo ucfirst($modo_visualizacion); ?></h2>
                    <div class="chart-container">
                        <canvas id="tendencias-chart"></canvas>
                    </div>
                    <?php if (!empty($tendencias)): ?>
                        <div class="table-container">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Período</th>
                                        <th class="number-cell">Total</th>
                                        <th class="number-cell">Confirmadas</th>
                                        <th class="number-cell">Canceladas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tendencias as $tendencia): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tendencia['periodo']); ?></td>
                                            <td class="number-cell"><?php echo $tendencia['total_reservas']; ?></td>
                                            <td class="number-cell"><?php echo $tendencia['confirmadas']; ?></td>
                                            <td class="number-cell"><?php echo $tendencia['canceladas']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No hay datos disponibles para el período seleccionado.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Gráfico de Reservas por Día de la Semana -->
                <div class="card">
                    <h2 class="card-title">Reservas por Día de la Semana</h2>
                    <div class="chart-container">
                        <canvas id="dias-semana-chart"></canvas>
                    </div>
                    <?php if (!empty($dias_semana)): ?>
                        <div class="table-container">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Día</th>
                                        <th class="number-cell">Total Reservas</th>
                                        <th class="number-cell">% del Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dias_semana as $dia): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dia['dia_semana']); ?></td>
                                            <td class="number-cell"><?php echo $dia['total_reservas']; ?></td>
                                            <td class="number-cell"><?php echo number_format(($dia['total_reservas'] / $total_reservas) * 100, 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No hay datos disponibles para el período seleccionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid-2" style="margin-top: 20px;">
                <!-- Gráfico de Reservas por Hora del Día -->
                <div class="card">
                    <h2 class="card-title">Reservas por Hora del Día</h2>
                    <div class="chart-container">
                        <canvas id="horas-chart"></canvas>
                    </div>
                    <?php if (!empty($horas)): ?>
                        <div class="table-container">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th class="number-cell">Total Reservas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hora_pico = ['hora' => 0, 'total' => 0];
                                    foreach ($horas as $h):
                                        if ($h['total_reservas'] > $hora_pico['total']) {
                                            $hora_pico = ['hora' => $h['hora'], 'total' => $h['total_reservas']];
                                        }
                                    ?>
                                        <tr <?php echo ($h['total_reservas'] == $hora_pico['total']) ? 'style="background-color: rgba(74, 144, 226, 0.1);"' : ''; ?>>
                                            <td><?php echo str_pad($h['hora'], 2, '0', STR_PAD_LEFT) . ':00'; ?></td>
                                            <td class="number-cell"><?php echo $h['total_reservas']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No hay datos disponibles para el período seleccionado.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Gráfico de Distribución por Duración -->
                <div class="card">
                    <h2 class="card-title">Distribución por Duración</h2>
                    <div class="chart-container">
                        <canvas id="duracion-chart"></canvas>
                    </div>
                    <?php if (!empty($duraciones)): ?>
                        <div class="table-container">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Duración</th>
                                        <th class="number-cell">Total Reservas</th>
                                        <th class="number-cell">% del Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($duraciones as $duracion): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($duracion['rango_duracion']); ?></td>
                                            <td class="number-cell"><?php echo $duracion['total_reservas']; ?></td>
                                            <td class="number-cell"><?php echo number_format(($duracion['total_reservas'] / $total_reservas) * 100, 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No hay datos disponibles para el período seleccionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid-2" style="margin-top: 20px;">
                <!-- Gráfico de Distribución por Tipo de Recurso -->
                <div class="card">
                    <h2 class="card-title">Distribución por Tipo de Recurso</h2>
                    <div class="chart-container">
                        <canvas id="tipos-chart"></canvas>
                    </div>
                    <?php if (!empty($tipos_recurso)): ?>
                        <div class="table-container">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Tipo de Recurso</th>
                                        <th class="number-cell">Total Reservas</th>
                                        <th class="number-cell">% del Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tipos_recurso as $tipo): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tipo['tipo_recurso']); ?></td>
                                            <td class="number-cell"><?php echo $tipo['total_reservas']; ?></td>
                                            <td class="number-cell"><?php echo number_format(($tipo['total_reservas'] / $total_reservas) * 100, 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No hay datos disponibles para el período seleccionado.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Gráfico de Distribución por Estado -->
                <div class="card">
                    <h2 class="card-title">Distribución por Estado</h2>
                    <div class="chart-container">
                        <canvas id="estados-chart"></canvas>
                    </div>
                    <?php if (!empty($estados)): ?>
                        <div class="table-container">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Estado</th>
                                        <th class="number-cell">Total Reservas</th>
                                        <th class="number-cell">% del Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estados as $estado): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-<?php echo getEstadoClass($estado['estado']); ?>">
                                                    <?php echo ucfirst($estado['estado']); ?>
                                                </span>
                                            </td>
                                            <td class="number-cell"><?php echo $estado['total_reservas']; ?></td>
                                            <td class="number-cell"><?php echo number_format(($estado['total_reservas'] / $total_reservas) * 100, 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No hay datos disponibles para el período seleccionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2 class="card-title">Análisis y Recomendaciones</h2>
                <?php if (empty($tendencias)): ?>
                    <div class="empty-state">
                        <p>No hay suficientes datos para generar análisis y recomendaciones.</p>
                    </div>
                <?php else: ?>
                    <div style="padding: 15px;">
                        <h3>Patrones Identificados</h3>
                        <?php
                        $max_dia = ['dia' => '', 'total' => 0];
                        foreach ($dias_semana as $dia) {
                            if ($dia['total_reservas'] > $max_dia['total']) {
                                $max_dia = ['dia' => $dia['dia_semana'], 'total' => $dia['total_reservas']];
                            }
                        }
                        $hora_pico = ['hora' => 0, 'total' => 0];
                        foreach ($horas as $hora) {
                            if ($hora['total_reservas'] > $hora_pico['total']) {
                                $hora_pico = ['hora' => $hora['hora'], 'total' => $hora['total_reservas']];
                            }
                        }
                        $tasa_cancelacion = ($total_estados['cancelada'] / $total_reservas) * 100;
                        $tipo_mas_usado = ['tipo' => '', 'total' => 0];
                        foreach ($tipos_recurso as $tipo) {
                            if ($tipo['total_reservas'] > $tipo_mas_usado['total']) {
                                $tipo_mas_usado = ['tipo' => $tipo['tipo_recurso'], 'total' => $tipo['total_reservas']];
                            }
                        }
                        ?>
                        <ul style="margin-bottom: 20px;">
                            <li><strong>Día de mayor demanda:</strong> <?php echo $max_dia['dia']; ?> (<?php echo $max_dia['total']; ?> reservas)</li>
                            <li><strong>Hora pico:</strong> <?php echo str_pad($hora_pico['hora'], 2, '0', STR_PAD_LEFT) . ':00'; ?> (<?php echo $hora_pico['total']; ?> reservas)</li>
                            <li><strong>Tasa de cancelación:</strong> <?php echo number_format($tasa_cancelacion, 1); ?>%</li>
                            <li><strong>Recursos más utilizados:</strong> <?php echo $tipo_mas_usado['tipo']; ?> (<?php echo $tipo_mas_usado['total']; ?> reservas)</li>
                        </ul>

                        <h3>Recomendaciones</h3>
                        <div style="background-color: rgba(74, 144, 226, 0.1); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <?php if ($tasa_cancelacion > 20): ?>
                                <p><strong>Alta tasa de cancelación detectada (<?php echo number_format($tasa_cancelacion, 1); ?>%):</strong> Considere implementar una política de confirmación anticipada o enviar recordatorios automáticos para reducir cancelaciones de última hora.</p>
                            <?php endif; ?>
                            <p><strong>Optimización de recursos:</strong> Para mejorar la disponibilidad, considere aumentar la cantidad de recursos de tipo "<?php echo $tipo_mas_usado['tipo']; ?>" que han mostrado alta demanda.</p>
                            <p><strong>Gestión de horarios pico:</strong> Hay una mayor demanda los días <?php echo $max_dia['dia']; ?> alrededor de las <?php echo str_pad($hora_pico['hora'], 2, '0', STR_PAD_LEFT) . ':00'; ?>. Considere aumentar el personal de soporte durante estos períodos o implementar un sistema de reservas prioritarias.</p>
                            <?php
                            if (count($tendencias) >= 3) {
                                $primero = reset($tendencias);
                                $ultimo = end($tendencias);
                                if ($ultimo['total_reservas'] > $primero['total_reservas'] * 1.2) {
                                    echo '<p><strong>Tendencia creciente:</strong> Se observa un aumento consistente en el número de reservas. Considere invertir en la ampliación de la infraestructura para satisfacer la creciente demanda.</p>';
                                } elseif ($ultimo['total_reservas'] < $primero['total_reservas'] * 0.8) {
                                    echo '<p><strong>Tendencia decreciente:</strong> Se observa una disminución en el uso del sistema. Recomendamos realizar una encuesta de satisfacción para identificar posibles problemas.</p>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Inicialización de gráficos con Chart.js y console.log para depurar datos -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Datos y gráfico de Tendencia de Reservas (Líneas)
            const ctxTendencias = document.getElementById('tendencias-chart').getContext('2d');
            const tendenciasLabels = <?php echo json_encode(array_column($tendencias, 'periodo')); ?>;
            const tendenciasTotal = <?php echo json_encode(array_column($tendencias, 'total_reservas')); ?>;
            const tendenciasConfirmadas = <?php echo json_encode(array_column($tendencias, 'confirmadas')); ?>;
            const tendenciasCanceladas = <?php echo json_encode(array_column($tendencias, 'canceladas')); ?>;

            console.log("Datos Tendencia Labels:", tendenciasLabels);
            console.log("Datos Tendencia Total:", tendenciasTotal);
            console.log("Datos Tendencia Confirmadas:", tendenciasConfirmadas);
            console.log("Datos Tendencia Canceladas:", tendenciasCanceladas);

            new Chart(ctxTendencias, {
                type: 'line',
                data: {
                    labels: tendenciasLabels,
                    datasets: [{
                            label: 'Total Reservas',
                            data: tendenciasTotal,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            fill: true,
                            tension: 0.2
                        },
                        {
                            label: 'Confirmadas',
                            data: tendenciasConfirmadas,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            fill: true,
                            tension: 0.2
                        },
                        {
                            label: 'Canceladas',
                            data: tendenciasCanceladas,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            fill: true,
                            tension: 0.2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Tendencia de Reservas'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // 2. Datos y gráfico de Reservas por Día de la Semana (Barras)
            const ctxDias = document.getElementById('dias-semana-chart').getContext('2d');
            const diasLabels = <?php echo json_encode(array_column($dias_semana, 'dia_semana')); ?>;
            const diasTotal = <?php echo json_encode(array_column($dias_semana, 'total_reservas')); ?>;

            console.log("Datos Día de la Semana Labels:", diasLabels);
            console.log("Datos Día de la Semana Total:", diasTotal);

            new Chart(ctxDias, {
                type: 'bar',
                data: {
                    labels: diasLabels,
                    datasets: [{
                        label: 'Total Reservas',
                        data: diasTotal,
                        backgroundColor: 'rgba(153, 102, 255, 0.6)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Reservas por Día de la Semana'
                        }
                    }
                }
            });

            // 3. Datos y gráfico de Reservas por Hora del Día (Barras)
            const ctxHoras = document.getElementById('horas-chart').getContext('2d');
            const horasLabels = <?php
                                $labels = [];
                                foreach ($horas as $h) {
                                    $labels[] = str_pad($h['hora'], 2, '0', STR_PAD_LEFT) . ':00';
                                }
                                echo json_encode($labels);
                                ?>;
            const horasTotal = <?php echo json_encode(array_column($horas, 'total_reservas')); ?>;

            console.log("Datos Horas Labels:", horasLabels);
            console.log("Datos Horas Total:", horasTotal);

            new Chart(ctxHoras, {
                type: 'bar',
                data: {
                    labels: horasLabels,
                    datasets: [{
                        label: 'Total Reservas',
                        data: horasTotal,
                        backgroundColor: 'rgba(255, 159, 64, 0.6)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Reservas por Hora del Día'
                        }
                    }
                }
            });

            // 4. Datos y gráfico de Distribución por Duración (Barras)
            const ctxDuracion = document.getElementById('duracion-chart').getContext('2d');
            const duracionLabels = <?php echo json_encode(array_column($duraciones, 'rango_duracion')); ?>;
            const duracionTotal = <?php echo json_encode(array_column($duraciones, 'total_reservas')); ?>;

            console.log("Datos Duración Labels:", duracionLabels);
            console.log("Datos Duración Total:", duracionTotal);

            new Chart(ctxDuracion, {
                type: 'bar',
                data: {
                    labels: duracionLabels,
                    datasets: [{
                        label: 'Total Reservas',
                        data: duracionTotal,
                        backgroundColor: 'rgba(255, 205, 86, 0.6)',
                        borderColor: 'rgba(255, 205, 86, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Distribución por Duración'
                        }
                    }
                }
            });

            // 5. Datos y gráfico de Distribución por Tipo de Recurso (Doughnut)
            const ctxTipos = document.getElementById('tipos-chart').getContext('2d');
            const tiposLabels = <?php echo json_encode(array_column($tipos_recurso, 'tipo_recurso')); ?>;
            const tiposTotal = <?php echo json_encode(array_column($tipos_recurso, 'total_reservas')); ?>;

            console.log("Datos Tipo de Recurso Labels:", tiposLabels);
            console.log("Datos Tipo de Recurso Total:", tiposTotal);

            new Chart(ctxTipos, {
                type: 'doughnut',
                data: {
                    labels: tiposLabels,
                    datasets: [{
                        label: 'Total Reservas',
                        data: tiposTotal,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(255, 205, 86, 0.6)',
                            'rgba(153, 102, 255, 0.6)'
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Distribución por Tipo de Recurso'
                        }
                    }
                }
            });

            // 6. Datos y gráfico de Distribución por Estado (Doughnut)
            const ctxEstados = document.getElementById('estados-chart').getContext('2d');
            const estadosLabels = <?php echo json_encode(array_map('ucfirst', array_column($estados, 'estado'))); ?>;
            const estadosTotal = <?php echo json_encode(array_column($estados, 'total_reservas')); ?>;

            console.log("Datos Estado Labels:", estadosLabels);
            console.log("Datos Estado Total:", estadosTotal);

            new Chart(ctxEstados, {
                type: 'doughnut',
                data: {
                    labels: estadosLabels,
                    datasets: [{
                        label: 'Total Reservas',
                        data: estadosTotal,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(255, 205, 86, 0.6)',
                            'rgba(201, 203, 207, 0.6)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(201, 203, 207, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Distribución por Estado'
                        }
                    }
                }
            });
        });
    </script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/reportes.js"></script>
</body>

</html>