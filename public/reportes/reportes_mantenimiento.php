<?php

/**
 * Módulo de Reportes - Mantenimiento de Recursos
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
$id_recurso = isset($_GET['recurso']) ? intval($_GET['recurso']) : 0;
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$agrupacion = isset($_GET['agrupacion']) ? $_GET['agrupacion'] : 'recurso';

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

// Añadir filtro de recurso específico
if ($id_recurso > 0) {
    $filtros[] = "r.id_recurso = ?";
    $params[] = $id_recurso;
}

// Añadir filtro de estado
if (!empty($estado)) {
    $filtros[] = "m.estado = ?";
    $params[] = $estado;
}

// Construir cláusula WHERE
$where = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Generar consulta según agrupación seleccionada
switch ($agrupacion) {
    case 'recurso':
        $sql = "
            SELECT 
                r.id_recurso,
                r.nombre as nombre_recurso,
                tr.nombre as tipo_recurso,
                COUNT(*) as total_mantenimientos,
                SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
            FROM mantenimiento m
            JOIN recursos r ON m.id_recurso = r.id_recurso
            JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
            $where
            GROUP BY r.id_recurso
            ORDER BY total_mantenimientos DESC
        ";
        break;

    case 'mensual':
        $sql = "
            SELECT 
                DATE_FORMAT(m.fecha_inicio, '%Y-%m') as mes,
                DATE_FORMAT(m.fecha_inicio, '%m/%Y') as mes_formateado,
                COUNT(*) as total_mantenimientos,
                SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
            FROM mantenimiento m
            JOIN recursos r ON m.id_recurso = r.id_recurso
            JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
            $where
            GROUP BY DATE_FORMAT(m.fecha_inicio, '%Y-%m')
            ORDER BY DATE_FORMAT(m.fecha_inicio, '%Y-%m')
        ";
        break;

    case 'tipo':
        $sql = "
            SELECT 
                tr.id_tipo,
                tr.nombre as tipo_recurso,
                COUNT(*) as total_mantenimientos,
                SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
            FROM mantenimiento m
            JOIN recursos r ON m.id_recurso = r.id_recurso
            JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
            $where
            GROUP BY tr.id_tipo
            ORDER BY total_mantenimientos DESC
        ";
        break;

    case 'responsable':
        $sql = "
            SELECT 
                u.id_usuario,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_responsable,
                ro.nombre as rol,
                COUNT(*) as total_mantenimientos,
                SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
            FROM mantenimiento m
            JOIN recursos r ON m.id_recurso = r.id_recurso
            JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
            JOIN usuarios u ON m.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            $where
            GROUP BY u.id_usuario
            ORDER BY total_mantenimientos DESC
        ";
        break;

    default:
        $sql = "
            SELECT 
                r.id_recurso,
                r.nombre as nombre_recurso,
                tr.nombre as tipo_recurso,
                COUNT(*) as total_mantenimientos,
                SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
            FROM mantenimiento m
            JOIN recursos r ON m.id_recurso = r.id_recurso
            JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
            $where
            GROUP BY r.id_recurso
            ORDER BY total_mantenimientos DESC
        ";
}

// Obtener datos para el reporte
$estadisticas = $db->getRows($sql, $params);

// Obtener lista de tipos de recursos para filtrar
$tipos = $db->getRows(
    "SELECT id_tipo, nombre FROM tipos_recursos ORDER BY nombre"
);

// Obtener lista de recursos para filtrar
$recursos = $db->getRows(
    "SELECT r.id_recurso, r.nombre, tr.nombre as tipo 
     FROM recursos r
     JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
     ORDER BY r.nombre"
);

// Calcular totales para el resumen
$total_mantenimientos = 0;
$total_pendientes = 0;
$total_en_progreso = 0;
$total_completados = 0;
$suma_duracion = 0;
$count_duracion = 0;

foreach ($estadisticas as $item) {
    $total_mantenimientos += $item['total_mantenimientos'];
    $total_pendientes += $item['pendientes'];
    $total_en_progreso += $item['en_progreso'];
    $total_completados += $item['completados'];

    if (!is_null($item['duracion_promedio'])) {
        $suma_duracion += $item['duracion_promedio'] * $item['total_mantenimientos'];
        $count_duracion += $item['total_mantenimientos'];
    }
}

$duracion_promedio_global = $count_duracion > 0 ? $suma_duracion / $count_duracion : 0;

// Consulta para obtener los recursos con más mantenimientos
$sql_recursos_mas_mantenimientos = "
    SELECT 
        r.id_recurso,
        r.nombre as nombre_recurso,
        COUNT(*) as total_mantenimientos
    FROM mantenimiento m
    JOIN recursos r ON m.id_recurso = r.id_recurso
    WHERE m.fecha_inicio >= ? AND m.fecha_inicio <= ?
    GROUP BY r.id_recurso
    ORDER BY total_mantenimientos DESC
    LIMIT 5
";

$recursos_mas_mantenimientos = $db->getRows($sql_recursos_mas_mantenimientos, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

// Consulta para obtener la duración promedio por tipo de recurso
$sql_duracion_por_tipo = "
    SELECT 
        tr.id_tipo,
        tr.nombre as tipo_recurso,
        AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
    FROM mantenimiento m
    JOIN recursos r ON m.id_recurso = r.id_recurso
    JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
    WHERE m.estado = 'completado' 
    AND m.fecha_inicio >= ? 
    AND m.fecha_inicio <= ?
    GROUP BY tr.id_tipo
    ORDER BY duracion_promedio DESC
";

$duracion_por_tipo = $db->getRows($sql_duracion_por_tipo, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

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
    <title>Mantenimiento de Recursos - Sistema de Gestión de Recursos</title>
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
                <h1>Reporte de Mantenimiento de Recursos</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

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
                                <?php foreach ($recursos as $recurso): ?>
                                    <option value="<?php echo $recurso['id_recurso']; ?>" <?php echo ($id_recurso == $recurso['id_recurso']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($recurso['nombre'] . ' (' . $recurso['tipo'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

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
                            <label class="filter-label" for="agrupacion">Agrupar por:</label>
                            <select id="agrupacion" name="agrupacion" class="filter-select">
                                <option value="recurso" <?php echo ($agrupacion == 'recurso') ? 'selected' : ''; ?>>Por Recurso</option>
                                <option value="mensual" <?php echo ($agrupacion == 'mensual') ? 'selected' : ''; ?>>Mensual</option>
                                <option value="tipo" <?php echo ($agrupacion == 'tipo') ? 'selected' : ''; ?>>Por Tipo de Recurso</option>
                                <option value="responsable" <?php echo ($agrupacion == 'responsable') ? 'selected' : ''; ?>>Por Responsable</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="filtro-btn">Filtrar</button>
                        <a href="reportes_mantenimiento.php" class="filtro-btn btn-reset">Reiniciar</a>

                        <!-- Botón para exportar a CSV -->
                        <a href="exportar_csv.php?reporte=mantenimiento&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&recurso=<?php echo $id_recurso; ?>&estado=<?php echo $estado; ?>&agrupacion=<?php echo $agrupacion; ?>" class="btn btn-secondary csv-btn">
                            <i class="csv-icon"></i> Exportar a CSV
                        </a>

                        <!-- Botón para generar PDF -->
                        <a href="generar_pdf_mantenimiento.php?fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&recurso=<?php echo $id_recurso; ?>&estado=<?php echo $estado; ?>&agrupacion=<?php echo $agrupacion; ?>" class="btn btn-primary" style="margin-left: 10px;">
                            <i class="pdf-icon"></i> Generar PDF
                        </a>
                    </div>
                </form>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h2 class="card-title">Resumen del Período</h2>
                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_mantenimientos); ?></div>
                            <div class="stat-label">Total Mantenimientos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_pendientes); ?></div>
                            <div class="stat-label">Pendientes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_en_progreso); ?></div>
                            <div class="stat-label">En Progreso</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_completados); ?></div>
                            <div class="stat-label">Completados</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo formatear_horas($duracion_promedio_global); ?></div>
                            <div class="stat-label">Duración Promedio</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Estado de Mantenimientos</h2>
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
                                    <td class="number-cell"><?php echo $total_pendientes; ?></td>
                                    <td class="number-cell"><?php echo ($total_mantenimientos > 0) ? number_format(($total_pendientes / $total_mantenimientos) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-primary">En Progreso</span></td>
                                    <td class="number-cell"><?php echo $total_en_progreso; ?></td>
                                    <td class="number-cell"><?php echo ($total_mantenimientos > 0) ? number_format(($total_en_progreso / $total_mantenimientos) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-success">Completados</span></td>
                                    <td class="number-cell"><?php echo $total_completados; ?></td>
                                    <td class="number-cell"><?php echo ($total_mantenimientos > 0) ? number_format(($total_completados / $total_mantenimientos) * 100, 1) : 0; ?>%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h2 class="card-title">Recursos con más mantenimientos</h2>
                    <div class="mini-chart" id="recursos-chart"></div>
                    <div class="table-container" style="margin-top: 15px;">
                        <?php if (empty($recursos_mas_mantenimientos)): ?>
                            <div class="empty-state">
                                <p>No hay datos disponibles.</p>
                            </div>
                        <?php else: ?>
                            <table style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Recurso</th>
                                        <th class="number-cell">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recursos_mas_mantenimientos as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['nombre_recurso']); ?></td>
                                            <td class="number-cell"><?php echo $item['total_mantenimientos']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Duración promedio por tipo de recurso</h2>
                    <div class="mini-chart" id="duracion-chart"></div>
                    <div class="table-container" style="margin-top: 15px;">
                        <?php if (empty($duracion_por_tipo)): ?>
                            <div class="empty-state">
                                <p>No hay datos disponibles.</p>
                            </div>
                        <?php else: ?>
                            <table style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Tipo de Recurso</th>
                                        <th class="number-cell">Duración Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($duracion_por_tipo as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['tipo_recurso']); ?></td>
                                            <td class="number-cell"><?php echo formatear_horas($item['duracion_promedio']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Estadísticas Detalladas</h2>

                <?php if (empty($estadisticas)): ?>
                    <div class="empty-state">
                        <p>No hay datos disponibles para los filtros seleccionados.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <?php switch ($agrupacion):
                                        case 'recurso': ?>
                                            <th>Recurso</th>
                                            <th>Tipo</th>
                                        <?php break;
                                        case 'mensual': ?>
                                            <th>Mes</th>
                                        <?php break;
                                        case 'tipo': ?>
                                            <th>Tipo de Recurso</th>
                                        <?php break;
                                        case 'responsable': ?>
                                            <th>Responsable</th>
                                            <th>Rol</th>
                                    <?php break;
                                    endswitch; ?>
                                    <th class="number-cell">Total Mantenimientos</th>
                                    <th class="number-cell">Pendientes</th>
                                    <th class="number-cell">En Progreso</th>
                                    <th class="number-cell">Completados</th>
                                    <th class="number-cell">Duración Promedio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estadisticas as $item): ?>
                                    <tr>
                                        <?php switch ($agrupacion):
                                            case 'recurso': ?>
                                                <td><?php echo htmlspecialchars($item['nombre_recurso']); ?></td>
                                                <td><?php echo htmlspecialchars($item['tipo_recurso']); ?></td>
                                            <?php break;
                                            case 'mensual': ?>
                                                <td><?php echo $item['mes_formateado']; ?></td>
                                            <?php break;
                                            case 'tipo': ?>
                                                <td><?php echo htmlspecialchars($item['tipo_recurso']); ?></td>
                                            <?php break;
                                            case 'responsable': ?>
                                                <td><?php echo htmlspecialchars($item['nombre_responsable']); ?></td>
                                                <td><?php echo htmlspecialchars($item['rol']); ?></td>
                                        <?php break;
                                        endswitch; ?>
                                        <td class="number-cell"><?php echo $item['total_mantenimientos']; ?></td>
                                        <td class="number-cell"><?php echo $item['pendientes']; ?></td>
                                        <td class="number-cell"><?php echo $item['en_progreso']; ?></td>
                                        <td class="number-cell"><?php echo $item['completados']; ?></td>
                                        <td class="number-cell"><?php echo formatear_horas($item['duracion_promedio']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">Análisis y Recomendaciones</h2>

                <?php if (!empty($estadisticas)): ?>
                    <div class="mini-chart" id="tendencias-chart"></div>

                    <div style="margin-top: 20px;">
                        <h3 style="font-size: 16px; margin-bottom: 10px;">Análisis de Datos:</h3>

                        <ul style="padding-left: 20px; margin-bottom: 15px;">
                            <?php
                            // Calcular estadísticas interesantes
                            $tasa_completados = $total_mantenimientos > 0 ? ($total_completados / $total_mantenimientos) * 100 : 0;
                            $tasa_pendientes = $total_mantenimientos > 0 ? ($total_pendientes / $total_mantenimientos) * 100 : 0;

                            // Encontrar recurso con más mantenimientos pendientes
                            $max_pendientes = 0;
                            $recurso_max_pendientes = "";

                            // Identificar el recurso o tipo con más mantenimientos pendientes
                            if ($agrupacion == 'recurso' || $agrupacion == 'tipo') {
                                foreach ($estadisticas as $item) {
                                    if ($item['pendientes'] > $max_pendientes) {
                                        $max_pendientes = $item['pendientes'];
                                        if ($agrupacion == 'recurso') {
                                            $recurso_max_pendientes = $item['nombre_recurso'];
                                        } else {
                                            $recurso_max_pendientes = "Tipo: " . $item['tipo_recurso'];
                                        }
                                    }
                                }
                            }
                            ?>

                            <li>
                                <strong>Volumen de mantenimientos:</strong> En el período analizado se registraron un total de <?php echo number_format($total_mantenimientos); ?> mantenimientos,
                                con un índice de finalización del <?php echo number_format($tasa_completados, 1); ?>%.
                            </li>

                            <?php if (!empty($recurso_max_pendientes)): ?>
                                <li>
                                    <strong>Recursos pendientes:</strong>
                                    <?php echo $recurso_max_pendientes; ?> tiene el mayor número de mantenimientos pendientes (<?php echo $max_pendientes; ?>).
                                    <?php if ($max_pendientes > 3): ?>
                                        Esto podría indicar un problema recurrente con este recurso o tipo que merece atención especial.
                                    <?php endif; ?>
                                </li>
                            <?php endif; ?>

                            <li>
                                <strong>Duración promedio:</strong> El tiempo promedio de mantenimiento es de <?php echo formatear_horas($duracion_promedio_global); ?>.
                                <?php if ($duracion_promedio_global > 48): ?>
                                    Este tiempo es relativamente alto, lo que podría indicar problemas complejos o falta de personal.
                                <?php elseif ($duracion_promedio_global < 2): ?>
                                    Este tiempo es muy bajo, lo que sugiere mantenimientos preventivos o reparaciones menores.
                                <?php endif; ?>
                            </li>

                            <li>
                                <strong>Estado actual:</strong>
                                <?php
                                $mantenimientos_activos = $total_pendientes + $total_en_progreso;
                                $porcentaje_activos = $total_mantenimientos > 0 ? ($mantenimientos_activos / $total_mantenimientos) * 100 : 0;
                                ?>
                                Actualmente hay <?php echo $mantenimientos_activos; ?> mantenimientos activos (<?php echo number_format($porcentaje_activos, 1); ?>% del total).
                                <?php if ($porcentaje_activos > 50): ?>
                                    Esta proporción es alta, lo que podría indicar una acumulación de trabajo o falta de recursos.
                                <?php elseif ($porcentaje_activos < 10 && $total_mantenimientos > 10): ?>
                                    Esta proporción es muy baja, lo que sugiere un buen manejo de los mantenimientos.
                                <?php endif; ?>
                            </li>
                        </ul>

                        <?php if ($total_mantenimientos > 0): ?>
                            <div style="margin-top: 20px; padding: 15px; background-color: rgba(74, 144, 226, 0.1); border-radius: 4px;">
                                <h4 style="margin-bottom: 10px; color: #4A90E2;">Recomendaciones basadas en los datos:</h4>

                                <?php
                                // Generar recomendaciones basadas en los datos analizados
                                if ($tasa_pendientes > 30) {
                                    echo "<p>Se recomienda asignar más recursos para atender el alto número de mantenimientos pendientes, especialmente para " .
                                        ($recurso_max_pendientes ? $recurso_max_pendientes : "los recursos con más incidencias") . ".</p>";
                                }

                                // Identificar tipos de recursos con mantenimientos frecuentes
                                if ($agrupacion == 'tipo' && count($estadisticas) > 1) {
                                    $tipo_mas_mantenimientos = $estadisticas[0]; // Ya están ordenados por total
                                    $porcentaje_del_total = ($tipo_mas_mantenimientos['total_mantenimientos'] / $total_mantenimientos) * 100;

                                    if ($porcentaje_del_total > 40) {
                                        echo "<p>El tipo de recurso '" . htmlspecialchars($tipo_mas_mantenimientos['tipo_recurso']) .
                                            "' representa un " . number_format($porcentaje_del_total, 1) . "% del total de mantenimientos. " .
                                            "Se recomienda evaluar la calidad o proveedor de estos recursos, o mejorar su mantenimiento preventivo.</p>";
                                    }
                                }

                                // Sugerir si hay tiempos de mantenimiento muy largos
                                if ($duracion_promedio_global > 72) { // Más de 3 días
                                    echo "<p>El tiempo promedio de mantenimiento es muy alto (" . formatear_horas($duracion_promedio_global) .
                                        "). Se recomienda revisar los procesos de mantenimiento o considerar la contratación de personal adicional.</p>";
                                }

                                // Si hay pocos mantenimientos en el período
                                if ($total_mantenimientos < 5 && (strtotime($fecha_fin) - strtotime($fecha_inicio)) / (60 * 60 * 24) > 30) {
                                    echo "<p>Se registraron pocos mantenimientos en un período extenso. Considere implementar un programa de mantenimiento preventivo regular para evitar problemas futuros.</p>";
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No hay suficientes datos para generar análisis y recomendaciones.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/chart.min.js"></script>
    <script>
        // Datos para los gráficos
        const datosEstados = {
            labels: ['Pendientes', 'En Progreso', 'Completados'],
            datasets: [{
                data: [
                    <?php echo $total_pendientes; ?>,
                    <?php echo $total_en_progreso; ?>,
                    <?php echo $total_completados; ?>
                ],
                backgroundColor: [
                    '#FFC107', // Amarillo para pendientes
                    '#007BFF', // Azul para en progreso
                    '#28A745' // Verde para completados
                ],
                borderWidth: 1
            }]
        };

        // Configuración para el gráfico de estados
        const configEstados = {
            type: 'doughnut',
            data: datosEstados,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        padding: 10
                    }
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
        };

        // Datos para recursos con más mantenimientos
        const etiquetasRecursos = [];
        const valoresRecursos = [];

        <?php foreach ($recursos_mas_mantenimientos as $item): ?>
            etiquetasRecursos.push('<?php echo addslashes($item['nombre_recurso']); ?>');
            valoresRecursos.push(<?php echo $item['total_mantenimientos']; ?>);
        <?php endforeach; ?>

        const datosRecursos = {
            labels: etiquetasRecursos,
            datasets: [{
                label: 'Mantenimientos',
                data: valoresRecursos,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        };

        const configRecursos = {
            type: 'horizontalBar',
            data: datosRecursos,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false
                },
                scales: {
                    xAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        };

        // Datos para duración por tipo
        const etiquetasDuracion = [];
        const valoresDuracion = [];

        <?php foreach ($duracion_por_tipo as $item): ?>
            etiquetasDuracion.push('<?php echo addslashes($item['tipo_recurso']); ?>');
            valoresDuracion.push(<?php echo $item['duracion_promedio']; ?>);
        <?php endforeach; ?>

        const datosDuracion = {
            labels: etiquetasDuracion,
            datasets: [{
                label: 'Horas',
                data: valoresDuracion,
                backgroundColor: 'rgba(255, 159, 64, 0.5)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        };

        const configDuracion = {
            type: 'horizontalBar',
            data: datosDuracion,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false
                },
                scales: {
                    xAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                if (value < 1) {
                                    return Math.round(value * 60) + " min";
                                } else if (value < 24) {
                                    return Math.round(value) + " h";
                                } else {
                                    return Math.floor(value / 24) + " d " + Math.round(value % 24) + " h";
                                }
                            }
                        }
                    }]
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            const value = tooltipItem.xLabel;
                            if (value < 1) {
                                return Math.round(value * 60) + " minutos";
                            } else if (value < 24) {
                                return Math.round(value) + " horas";
                            } else {
                                return Math.floor(value / 24) + " días " + Math.round(value % 24) + " horas";
                            }
                        }
                    }
                }
            }
        };

        // Preparar datos para el gráfico de tendencias
        const labelsTendencias = [];
        const datosPendientes = [];
        const datosEnProgreso = [];
        const datosCompletados = [];

        <?php foreach ($estadisticas as $item): ?>
            <?php switch ($agrupacion):
                case 'recurso': ?>
                    labelsTendencias.push('<?php echo addslashes($item['nombre_recurso']); ?>');
                <?php break;
                case 'mensual': ?>
                    labelsTendencias.push('<?php echo $item['mes_formateado']; ?>');
                <?php break;
                case 'tipo': ?>
                    labelsTendencias.push('<?php echo addslashes($item['tipo_recurso']); ?>');
                <?php break;
                case 'responsable': ?>
                    labelsTendencias.push('<?php echo addslashes($item['nombre_responsable']); ?>');
            <?php break;
            endswitch; ?>

            datosPendientes.push(<?php echo $item['pendientes']; ?>);
            datosEnProgreso.push(<?php echo $item['en_progreso']; ?>);
            datosCompletados.push(<?php echo $item['completados']; ?>);
        <?php endforeach; ?>

        const datosTendencias = {
            labels: labelsTendencias,
            datasets: [{
                    label: 'Pendientes',
                    data: datosPendientes,
                    backgroundColor: 'rgba(255, 193, 7, 0.2)',
                    borderColor: '#FFC107',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#FFC107'
                },
                {
                    label: 'En Progreso',
                    data: datosEnProgreso,
                    backgroundColor: 'rgba(0, 123, 255, 0.2)',
                    borderColor: '#007BFF',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#007BFF'
                },
                {
                    label: 'Completados',
                    data: datosCompletados,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: '#28A745',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#28A745'
                }
            ]
        };

        // Configuración para el gráfico de tendencias
        const configTendencias = {
            type: '<?php echo ($agrupacion == 'mensual') ? 'line' : 'bar'; ?>',
            data: datosTendencias,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }],
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                },
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 10
                    }
                }
            }
        };

        // Crear los gráficos cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico de estados
            const ctxEstados = document.getElementById('estados-chart').getContext('2d');
            new Chart(ctxEstados, configEstados);

            // Gráfico de recursos con más mantenimientos
            if (document.getElementById('recursos-chart')) {
                const ctxRecursos = document.getElementById('recursos-chart').getContext('2d');
                new Chart(ctxRecursos, configRecursos);
            }

            // Gráfico de duración por tipo
            if (document.getElementById('duracion-chart')) {
                const ctxDuracion = document.getElementById('duracion-chart').getContext('2d');
                new Chart(ctxDuracion, configDuracion);
            }

            // Gráfico de tendencias
            const ctxTendencias = document.getElementById('tendencias-chart').getContext('2d');
            new Chart(ctxTendencias, configTendencias);
        });
    </script>
</body>

</html>