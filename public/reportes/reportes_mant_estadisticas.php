<?php

/**
 * Módulo de Reportes - Estadísticas de Mantenimiento
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
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mensual';

// Validar y formatear fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
    $fecha_inicio = date('Y-m-d', strtotime('-90 days'));
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_fin = date('Y-m-d');
}

// Preparar filtros para la consulta
$filtros = [];
$params = [];

// Añadir filtro de fechas
$filtros[] = "m.fecha_inicio >= ?";
$params[] = $fecha_inicio . ' 00:00:00';

$filtros[] = "m.fecha_inicio <= ?";
$params[] = $fecha_fin . ' 23:59:59';

// Añadir filtro de tipo de recurso
if ($id_tipo > 0) {
    $filtros[] = "tr.id_tipo = ?";
    $params[] = $id_tipo;
}

// Añadir filtro de estado
if (!empty($estado)) {
    $filtros[] = "m.estado = ?";
    $params[] = $estado;
}

// Construir cláusula WHERE
$where = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Consulta base para estadísticas generales
$sql_stats = "
    SELECT 
        COUNT(*) as total_mantenimientos,
        SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
        AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio,
        COUNT(DISTINCT m.id_recurso) as recursos_afectados,
        COUNT(DISTINCT m.id_usuario) as personal_involucrado
    FROM mantenimiento m
    JOIN recursos r ON m.id_recurso = r.id_recurso
    JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
    $where
";

$estadisticas_generales = $db->getRow($sql_stats, $params);

// Consulta para estadísticas por tipo de recurso
$sql_por_tipo = "
    SELECT 
        tr.nombre as tipo_recurso,
        COUNT(*) as total,
        SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
        AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
    FROM mantenimiento m
    JOIN recursos r ON m.id_recurso = r.id_recurso
    JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
    $where
    GROUP BY tr.id_tipo
    ORDER BY total DESC
";

$estadisticas_por_tipo = $db->getRows($sql_por_tipo, $params);

// Consulta para estadísticas por período (mensual, semanal, etc.)
switch ($periodo) {
    case 'semanal':
        $group_by = "YEARWEEK(m.fecha_inicio, 1)";
        $select_date = "CONCAT('Semana ', WEEK(m.fecha_inicio), ' - ', YEAR(m.fecha_inicio)) as periodo";
        break;
    case 'trimestral':
        $group_by = "CONCAT(YEAR(m.fecha_inicio), '-', QUARTER(m.fecha_inicio))";
        $select_date = "CONCAT('T', QUARTER(m.fecha_inicio), ' ', YEAR(m.fecha_inicio)) as periodo";
        break;
    case 'anual':
        $group_by = "YEAR(m.fecha_inicio)";
        $select_date = "YEAR(m.fecha_inicio) as periodo";
        break;
    default: // mensual
        $group_by = "DATE_FORMAT(m.fecha_inicio, '%Y-%m')";
        $select_date = "DATE_FORMAT(m.fecha_inicio, '%m/%Y') as periodo";
        break;
}

$sql_por_periodo = "
    SELECT 
        $select_date,
        COUNT(*) as total,
        SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
        AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
    FROM mantenimiento m
    JOIN recursos r ON m.id_recurso = r.id_recurso
    JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
    $where
    GROUP BY $group_by
    ORDER BY m.fecha_inicio
";

$estadisticas_por_periodo = $db->getRows($sql_por_periodo, $params);

// Consulta para duración promedio por tipo de mantenimiento
$sql_duracion = "
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, m.fecha_inicio, m.fecha_fin) < 24 THEN 'Corto (< 24h)'
            WHEN TIMESTAMPDIFF(HOUR, m.fecha_inicio, m.fecha_fin) < 72 THEN 'Medio (1-3 días)'
            ELSE 'Largo (> 3 días)'
        END as categoria_duracion,
        COUNT(*) as total,
        AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, m.fecha_fin)) as horas_promedio
    FROM mantenimiento m
    JOIN recursos r ON m.id_recurso = r.id_recurso
    JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
    $where
    AND m.estado = 'completado'
    AND m.fecha_fin IS NOT NULL
    GROUP BY categoria_duracion
    ORDER BY horas_promedio
";

$estadisticas_duracion = $db->getRows($sql_duracion, $params);

// Obtener lista de tipos de recursos para filtrar
$tipos = $db->getRows(
    "SELECT id_tipo, nombre FROM tipos_recursos ORDER BY nombre"
);

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Función para formatear horas
function formatear_horas($horas)
{
    if (is_null($horas)) return "N/A";

    if ($horas < 1) {
        return round($horas * 60) . " min";
    } elseif ($horas < 24) {
        return round($horas, 1) . " horas";
    } else {
        $dias = floor($horas / 24);
        $horas_restantes = $horas % 24;
        return $dias . " días " . round($horas_restantes, 1) . " h";
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Mantenimiento - Sistema de Gestión de Recursos</title>
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
                <h1>Estadísticas de Mantenimiento</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="breadcrumb">
                <a href="../admin/dashboard.php">Dashboard</a> &gt;
                <a href="reportes_dashboard.php">Reportes</a> &gt;
                <a href="reportes_mantenimiento_landing.php">Mantenimiento</a> &gt;
                <span>Estadísticas</span>
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
                            <label class="filter-label" for="estado">Estado:</label>
                            <select id="estado" name="estado" class="filter-select">
                                <option value="">Todos los estados</option>
                                <option value="pendiente" <?php echo ($estado == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="en_progreso" <?php echo ($estado == 'en_progreso') ? 'selected' : ''; ?>>En Progreso</option>
                                <option value="completado" <?php echo ($estado == 'completado') ? 'selected' : ''; ?>>Completado</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="periodo">Agrupación por Periodo:</label>
                            <select id="periodo" name="periodo" class="filter-select">
                                <option value="mensual" <?php echo ($periodo == 'mensual') ? 'selected' : ''; ?>>Mensual</option>
                                <option value="semanal" <?php echo ($periodo == 'semanal') ? 'selected' : ''; ?>>Semanal</option>
                                <option value="trimestral" <?php echo ($periodo == 'trimestral') ? 'selected' : ''; ?>>Trimestral</option>
                                <option value="anual" <?php echo ($periodo == 'anual') ? 'selected' : ''; ?>>Anual</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="filtro-btn">Filtrar</button>
                        <a href="reportes_mant_estadisticas.php" class="filtro-btn btn-reset">Reiniciar</a>

                        <!-- Botón para exportar a CSV -->
                        <a href="exportar_csv.php?reporte=mant_estadisticas&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&estado=<?php echo $estado; ?>&periodo=<?php echo $periodo; ?>" class="btn btn-secondary csv-btn">
                            <i class="csv-icon"></i> Exportar a CSV
                        </a>

                        <!-- Botón para generar PDF -->
                        <a href="generar_pdf_mant_estadisticas.php?fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&estado=<?php echo $estado; ?>&periodo=<?php echo $periodo; ?>" class="btn btn-primary" style="margin-left: 10px;">
                            <i class="pdf-icon"></i> Generar PDF
                        </a>
                    </div>
                </form>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h2 class="card-title">Resumen General</h2>
                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($estadisticas_generales['total_mantenimientos']); ?></div>
                            <div class="stat-label">Total Mantenimientos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($estadisticas_generales['pendientes']); ?></div>
                            <div class="stat-label">Pendientes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($estadisticas_generales['en_progreso']); ?></div>
                            <div class="stat-label">En Progreso</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($estadisticas_generales['completados']); ?></div>
                            <div class="stat-label">Completados</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo formatear_horas($estadisticas_generales['duracion_promedio']); ?></div>
                            <div class="stat-label">Duración Promedio</div>
                        </div>
                    </div>
                    <div class="secondary-stats">
                        <div class="stat-row">
                            <div class="stat-label">Recursos Afectados:</div>
                            <div class="stat-value"><?php echo number_format($estadisticas_generales['recursos_afectados']); ?></div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-label">Personal Involucrado:</div>
                            <div class="stat-value"><?php echo number_format($estadisticas_generales['personal_involucrado']); ?></div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-label">Tasa de Finalización:</div>
                            <div class="stat-value">
                                <?php
                                $tasa_finalizacion = $estadisticas_generales['total_mantenimientos'] > 0
                                    ? ($estadisticas_generales['completados'] / $estadisticas_generales['total_mantenimientos']) * 100
                                    : 0;
                                echo number_format($tasa_finalizacion, 1) . '%';
                                ?>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-label">Período Analizado:</div>
                            <div class="stat-value">
                                <?php
                                $diferencia = (strtotime($fecha_fin) - strtotime($fecha_inicio)) / (60 * 60 * 24);
                                echo number_format($diferencia) . ' días';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Distribución por Estado</h2>
                    <div class="mini-chart" id="estados-chart"></div>
                    <div class="table-container">
                        <table style="width: 100%; margin-top: 10px;">
                            <thead>
                                <tr>
                                    <th>Estado</th>
                                    <th class="number-cell">Total</th>
                                    <th class="number-cell">Porcentaje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge badge-warning">Pendientes</span></td>
                                    <td class="number-cell"><?php echo $estadisticas_generales['pendientes']; ?></td>
                                    <td class="number-cell">
                                        <?php
                                        $porcentaje = $estadisticas_generales['total_mantenimientos'] > 0
                                            ? ($estadisticas_generales['pendientes'] / $estadisticas_generales['total_mantenimientos']) * 100
                                            : 0;
                                        echo number_format($porcentaje, 1) . '%';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-primary">En Progreso</span></td>
                                    <td class="number-cell"><?php echo $estadisticas_generales['en_progreso']; ?></td>
                                    <td class="number-cell">
                                        <?php
                                        $porcentaje = $estadisticas_generales['total_mantenimientos'] > 0
                                            ? ($estadisticas_generales['en_progreso'] / $estadisticas_generales['total_mantenimientos']) * 100
                                            : 0;
                                        echo number_format($porcentaje, 1) . '%';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-success">Completados</span></td>
                                    <td class="number-cell"><?php echo $estadisticas_generales['completados']; ?></td>
                                    <td class="number-cell">
                                        <?php
                                        $porcentaje = $estadisticas_generales['total_mantenimientos'] > 0
                                            ? ($estadisticas_generales['completados'] / $estadisticas_generales['total_mantenimientos']) * 100
                                            : 0;
                                        echo number_format($porcentaje, 1) . '%';
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h2 class="card-title">Mantenimientos por Tipo de Recurso</h2>
                    <div class="mini-chart" id="tipo-chart"></div>
                    <div class="table-container" style="margin-top: 15px;">
                        <?php if (empty($estadisticas_por_tipo)): ?>
                            <div class="empty-state">
                                <p>No hay datos disponibles para mostrar.</p>
                            </div>
                        <?php else: ?>
                            <table style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Tipo de Recurso</th>
                                        <th class="number-cell">Total</th>
                                        <th class="number-cell">Pendientes</th>
                                        <th class="number-cell">En Progreso</th>
                                        <th class="number-cell">Completados</th>
                                        <th class="number-cell">Duración Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estadisticas_por_tipo as $tipo): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tipo['tipo_recurso']); ?></td>
                                            <td class="number-cell"><?php echo $tipo['total']; ?></td>
                                            <td class="number-cell"><?php echo $tipo['pendientes']; ?></td>
                                            <td class="number-cell"><?php echo $tipo['en_progreso']; ?></td>
                                            <td class="number-cell"><?php echo $tipo['completados']; ?></td>
                                            <td class="number-cell"><?php echo formatear_horas($tipo['duracion_promedio']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Duración de Mantenimientos</h2>
                    <div class="mini-chart" id="duracion-chart"></div>
                    <div class="table-container" style="margin-top: 15px;">
                        <?php if (empty($estadisticas_duracion)): ?>
                            <div class="empty-state">
                                <p>No hay datos de mantenimientos completados para mostrar la duración.</p>
                            </div>
                        <?php else: ?>
                            <table style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Categoría</th>
                                        <th class="number-cell">Total</th>
                                        <th class="number-cell">Duración Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estadisticas_duracion as $duracion): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($duracion['categoria_duracion']); ?></td>
                                            <td class="number-cell"><?php echo $duracion['total']; ?></td>
                                            <td class="number-cell"><?php echo formatear_horas($duracion['horas_promedio']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Evolución de Mantenimientos por <?php echo ucfirst($periodo); ?></h2>

                <?php if (empty($estadisticas_por_periodo)): ?>
                    <div class="empty-state">
                        <p>No hay datos suficientes para mostrar la evolución temporal.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="evolucion-chart" height="300"></canvas>
                    </div>

                    <div class="table-container" style="margin-top: 20px;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Período</th>
                                    <th class="number-cell">Total</th>
                                    <th class="number-cell">Pendientes</th>
                                    <th class="number-cell">En Progreso</th>
                                    <th class="number-cell">Completados</th>
                                    <th class="number-cell">Duración Promedio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estadisticas_por_periodo as $periodo_data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($periodo_data['periodo']); ?></td>
                                        <td class="number-cell"><?php echo $periodo_data['total']; ?></td>
                                        <td class="number-cell"><?php echo $periodo_data['pendientes']; ?></td>
                                        <td class="number-cell"><?php echo $periodo_data['en_progreso']; ?></td>
                                        <td class="number-cell"><?php echo $periodo_data['completados']; ?></td>
                                        <td class="number-cell"><?php echo formatear_horas($periodo_data['duracion_promedio']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">Análisis y Tendencias</h2>

                <?php if ($estadisticas_generales['total_mantenimientos'] > 0): ?>
                    <div style="margin-top: 20px;">
                        <h3 style="font-size: 16px; margin-bottom: 10px;">Análisis de Datos:</h3>

                        <ul style="padding-left: 20px; margin-bottom: 15px;">
                            <?php
                            // Calcular tendencias
                            $tendencia_creciente = false;
                            $tendencia_decreciente = false;
                            $periodos_totales = count($estadisticas_por_periodo);

                            if ($periodos_totales >= 3) {
                                $primera_mitad = array_slice($estadisticas_por_periodo, 0, floor($periodos_totales / 2));
                                $segunda_mitad = array_slice($estadisticas_por_periodo, floor($periodos_totales / 2));

                                $suma_primera = 0;
                                $suma_segunda = 0;

                                foreach ($primera_mitad as $p) {
                                    $suma_primera += $p['total'];
                                }

                                foreach ($segunda_mitad as $p) {
                                    $suma_segunda += $p['total'];
                                }

                                $promedio_primera = $suma_primera / count($primera_mitad);
                                $promedio_segunda = $suma_segunda / count($segunda_mitad);

                                $tendencia_creciente = $promedio_segunda > $promedio_primera * 1.2;
                                $tendencia_decreciente = $promedio_segunda < $promedio_primera * 0.8;
                            }

                            // Identificar el tipo con más mantenimientos
                            $tipo_max_mantenimientos = !empty($estadisticas_por_tipo) ? $estadisticas_por_tipo[0] : null;

                            // Verificar la tasa de conclusión
                            $tasa_conclusion = ($estadisticas_generales['total_mantenimientos'] > 0)
                                ? ($estadisticas_generales['completados'] / $estadisticas_generales['total_mantenimientos']) * 100
                                : 0;
                            ?>

                            <li>
                                <strong>Volumen de mantenimientos:</strong> Durante el período analizado se han registrado
                                <?php echo number_format($estadisticas_generales['total_mantenimientos']); ?> mantenimientos,
                                con una tasa de conclusión del <?php echo number_format($tasa_conclusion, 1); ?>%.
                                <?php if ($tendencia_creciente): ?>
                                    Se observa una tendencia creciente en el número de mantenimientos registrados.
                                <?php elseif ($tendencia_decreciente): ?>
                                    Se observa una tendencia decreciente en el número de mantenimientos registrados.
                                <?php endif; ?>
                            </li>

                            <?php if ($tipo_max_mantenimientos): ?>
                                <li>
                                    <strong>Tipo de recurso más afectado:</strong> Los recursos de tipo
                                    "<?php echo htmlspecialchars($tipo_max_mantenimientos['tipo_recurso']); ?>"
                                    han requerido el mayor número de mantenimientos (<?php echo $tipo_max_mantenimientos['total']; ?>),
                                    lo que representa el <?php echo number_format(($tipo_max_mantenimientos['total'] / $estadisticas_generales['total_mantenimientos']) * 100, 1); ?>% del total.
                                </li>
                            <?php endif; ?>

                            <li>
                                <strong>Duración de mantenimientos:</strong> La duración promedio de los mantenimientos es de
                                <?php echo formatear_horas($estadisticas_generales['duracion_promedio']); ?>.
                                <?php
                                // Analizar si la duración es razonable
                                if ($estadisticas_generales['duracion_promedio'] > 72): // Más de 3 días
                                ?>
                                    Esta duración es relativamente alta, lo que podría indicar la complejidad de los problemas
                                    o posibles ineficiencias en el proceso de mantenimiento.
                                <?php elseif ($estadisticas_generales['duracion_promedio'] < 8): // Menos de 8 horas
                                ?>
                                    Esta duración es bastante baja, lo que sugiere mantenimientos preventivos eficientes
                                    o problemas de fácil solución.
                                <?php endif; ?>
                            </li>

                            <li>
                                <strong>Estado actual:</strong>
                                <?php
                                $mantenimientos_activos = $estadisticas_generales['pendientes'] + $estadisticas_generales['en_progreso'];
                                $porcentaje_activos = $estadisticas_generales['total_mantenimientos'] > 0
                                    ? ($mantenimientos_activos / $estadisticas_generales['total_mantenimientos']) * 100
                                    : 0;
                                ?>
                                Actualmente hay <?php echo $mantenimientos_activos; ?> mantenimientos activos
                                (<?php echo number_format($porcentaje_activos, 1); ?>% del total).
                                <?php if ($porcentaje_activos > 50): ?>
                                    Esta proporción es alta, lo que podría indicar un retraso en la resolución de mantenimientos.
                                <?php elseif ($porcentaje_activos < 20): ?>
                                    Esta proporción es baja, lo que indica una buena gestión de los mantenimientos.
                                <?php endif; ?>
                            </li>
                        </ul>

                        <div style="margin-top: 20px; padding: 15px; background-color: rgba(74, 144, 226, 0.1); border-radius: 4px;">
                            <h4 style="margin-bottom: 10px; color: #4A90E2;">Recomendaciones:</h4>

                            <?php
                            // Generar recomendaciones basadas en el análisis
                            if ($porcentaje_activos > 50) {
                                echo "<p>Dada la alta proporción de mantenimientos activos, se recomienda revisar los procesos actuales y considerar la asignación de más recursos para agilizar la resolución de casos pendientes.</p>";
                            }

                            if ($tendencia_creciente) {
                                echo "<p>La tendencia creciente en el número de mantenimientos sugiere posibles problemas sistemáticos. Se recomienda analizar en detalle las causas más comunes de mantenimiento para implementar medidas preventivas.</p>";
                            }

                            if ($tipo_max_mantenimientos && ($tipo_max_mantenimientos['total'] / $estadisticas_generales['total_mantenimientos']) > 0.4) {
                                echo "<p>Los recursos de tipo \"" . htmlspecialchars($tipo_max_mantenimientos['tipo_recurso']) . "\" requieren mantenimiento desproporcionadamente a menudo. Se recomienda revisar la calidad de estos recursos o mejorar los procedimientos de mantenimiento preventivo para este tipo específico.</p>";
                            }

                            if ($estadisticas_generales['duracion_promedio'] > 72) {
                                echo "<p>La duración promedio de los mantenimientos es alta. Se sugiere analizar los casos más prolongados para identificar cuellos de botella en el proceso y optimizar los tiempos de resolución.</p>";
                            }

                            if ($tasa_conclusion < 60) {
                                echo "<p>La tasa de conclusión de mantenimientos es relativamente baja. Se recomienda implementar un sistema de seguimiento más riguroso y establecer plazos objetivo para mejorar la eficiencia.</p>";
                            }
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No hay suficientes datos para generar análisis y tendencias.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/chart.min.js"></script>
    <script>
        // Datos para los gráficos
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico de estados
            const ctxEstados = document.getElementById('estados-chart').getContext('2d');
            new Chart(ctxEstados, {
                type: 'doughnut',
                data: {
                    labels: ['Pendientes', 'En Progreso', 'Completados'],
                    datasets: [{
                        data: [
                            <?php echo $estadisticas_generales['pendientes']; ?>,
                            <?php echo $estadisticas_generales['en_progreso']; ?>,
                            <?php echo $estadisticas_generales['completados']; ?>
                        ],
                        backgroundColor: [
                            '#FFC107', // Amarillo para pendientes
                            '#007BFF', // Azul para en progreso
                            '#28A745' // Verde para completados
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                }
            });

            // Gráfico por tipo de recurso
            <?php if (!empty($estadisticas_por_tipo)): ?>
                const ctxTipo = document.getElementById('tipo-chart').getContext('2d');
                new Chart(ctxTipo, {
                    type: 'bar',
                    data: {
                        labels: [
                            <?php foreach ($estadisticas_por_tipo as $tipo): ?> '<?php echo addslashes($tipo['tipo_recurso']); ?>',
                            <?php endforeach; ?>
                        ],
                        datasets: [{
                            label: 'Total Mantenimientos',
                            data: [
                                <?php foreach ($estadisticas_por_tipo as $tipo): ?>
                                    <?php echo $tipo['total']; ?>,
                                <?php endforeach; ?>
                            ],
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        legend: {
                            display: false
                        }
                    }
                });
            <?php endif; ?>

            // Gráfico de duración
            <?php if (!empty($estadisticas_duracion)): ?>
                const ctxDuracion = document.getElementById('duracion-chart').getContext('2d');
                new Chart(ctxDuracion, {
                    type: 'pie',
                    data: {
                        labels: [
                            <?php foreach ($estadisticas_duracion as $duracion): ?> '<?php echo addslashes($duracion['categoria_duracion']); ?>',
                            <?php endforeach; ?>
                        ],
                        datasets: [{
                            data: [
                                <?php foreach ($estadisticas_duracion as $duracion): ?>
                                    <?php echo $duracion['total']; ?>,
                                <?php endforeach; ?>
                            ],
                            backgroundColor: [
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(255, 159, 64, 0.6)',
                                'rgba(255, 99, 132, 0.6)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            <?php endif; ?>

            // Gráfico de evolución temporal
            <?php if (!empty($estadisticas_por_periodo)): ?>
                const ctxEvolucion = document.getElementById('evolucion-chart').getContext('2d');
                new Chart(ctxEvolucion, {
                    type: 'line',
                    data: {
                        labels: [
                            <?php foreach ($estadisticas_por_periodo as $periodo_data): ?> '<?php echo addslashes($periodo_data['periodo']); ?>',
                            <?php endforeach; ?>
                        ],
                        datasets: [{
                                label: 'Total',
                                data: [
                                    <?php foreach ($estadisticas_por_periodo as $periodo_data): ?>
                                        <?php echo $periodo_data['total']; ?>,
                                    <?php endforeach; ?>
                                ],
                                borderColor: 'rgba(75, 192, 192, 1)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                borderWidth: 2,
                                fill: true
                            },
                            {
                                label: 'Pendientes',
                                data: [
                                    <?php foreach ($estadisticas_por_periodo as $periodo_data): ?>
                                        <?php echo $periodo_data['pendientes']; ?>,
                                    <?php endforeach; ?>
                                ],
                                borderColor: 'rgba(255, 193, 7, 1)',
                                backgroundColor: 'rgba(255, 193, 7, 0.2)',
                                borderWidth: 2,
                                fill: true
                            },
                            {
                                label: 'En Progreso',
                                data: [
                                    <?php foreach ($estadisticas_por_periodo as $periodo_data): ?>
                                        <?php echo $periodo_data['en_progreso']; ?>,
                                    <?php endforeach; ?>
                                ],
                                borderColor: 'rgba(0, 123, 255, 1)',
                                backgroundColor: 'rgba(0, 123, 255, 0.2)',
                                borderWidth: 2,
                                fill: true
                            },
                            {
                                label: 'Completados',
                                data: [
                                    <?php foreach ($estadisticas_por_periodo as $periodo_data): ?>
                                        <?php echo $periodo_data['completados']; ?>,
                                    <?php endforeach; ?>
                                ],
                                borderColor: 'rgba(40, 167, 69, 1)',
                                backgroundColor: 'rgba(40, 167, 69, 0.2)',
                                borderWidth: 2,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>