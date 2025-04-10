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
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$id_tipo = isset($_GET['tipo']) ? intval($_GET['tipo']) : 0;
$id_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$agrupacion = isset($_GET['agrupacion']) ? $_GET['agrupacion'] : 'diaria';

// Validar y formatear fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
    $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_fin = date('Y-m-d');
}

// Preparar filtros para la consulta
$filtros = [];
$params = [];

// Añadir filtro de fechas
$filtros[] = "r.fecha_inicio >= ?";
$params[] = $fecha_inicio . ' 00:00:00';

$filtros[] = "r.fecha_inicio <= ?";
$params[] = $fecha_fin . ' 23:59:59';

// Añadir filtro de tipo de recurso
if ($id_tipo > 0) {
    $filtros[] = "tr.id_tipo = ?";
    $params[] = $id_tipo;
}

// Añadir filtro de usuario
if ($id_usuario > 0) {
    $filtros[] = "r.id_usuario = ?";
    $params[] = $id_usuario;
}

// Añadir filtro de estado
if (!empty($estado)) {
    $filtros[] = "r.estado = ?";
    $params[] = $estado;
}

// Construir cláusula WHERE
$where = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Generar consulta según agrupación seleccionada
switch ($agrupacion) {
    case 'diaria':
        $sql = "
            SELECT 
                DATE(r.fecha_inicio) as fecha,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            $where
            GROUP BY DATE(r.fecha_inicio)
            ORDER BY DATE(r.fecha_inicio)
        ";
        break;

    case 'semanal':
        $sql = "
            SELECT 
                YEARWEEK(r.fecha_inicio, 1) as semana,
                MIN(DATE(r.fecha_inicio)) as fecha_inicio_semana,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            $where
            GROUP BY YEARWEEK(r.fecha_inicio, 1)
            ORDER BY YEARWEEK(r.fecha_inicio, 1)
        ";
        break;

    case 'mensual':
        $sql = "
            SELECT 
                DATE_FORMAT(r.fecha_inicio, '%Y-%m') as mes,
                DATE_FORMAT(r.fecha_inicio, '%m/%Y') as mes_formateado,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            $where
            GROUP BY DATE_FORMAT(r.fecha_inicio, '%Y-%m')
            ORDER BY DATE_FORMAT(r.fecha_inicio, '%Y-%m')
        ";
        break;

    case 'usuario':
        $sql = "
            SELECT 
                u.id_usuario,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            $where
            GROUP BY u.id_usuario
            ORDER BY total_reservas DESC
        ";
        break;

    case 'recurso':
        $sql = "
            SELECT 
                rc.id_recurso,
                rc.nombre as nombre_recurso,
                tr.nombre as tipo_recurso,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            $where
            GROUP BY rc.id_recurso
            ORDER BY total_reservas DESC
        ";
        break;

    default:
        $sql = "
            SELECT 
                DATE(r.fecha_inicio) as fecha,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            $where
            GROUP BY DATE(r.fecha_inicio)
            ORDER BY DATE(r.fecha_inicio)
        ";
}

// Obtener datos para el reporte
$estadisticas = $db->getRows($sql, $params);

// Obtener lista de tipos de recursos para filtrar
$tipos = $db->getRows(
    "SELECT id_tipo, nombre FROM tipos_recursos ORDER BY nombre"
);

// Obtener lista de usuarios activos para filtrar
$usuarios = $db->getRows(
    "SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) as nombre_completo, r.nombre as rol
     FROM usuarios u
     JOIN roles r ON u.id_rol = r.id_rol
     WHERE u.activo = 1
     ORDER BY u.nombre, u.apellido"
);

// Calcular totales para el resumen
$total_reservas = 0;
$total_pendientes = 0;
$total_confirmadas = 0;
$total_canceladas = 0;
$total_completadas = 0;

foreach ($estadisticas as $item) {
    $total_reservas += $item['total_reservas'];
    $total_pendientes += $item['pendientes'];
    $total_confirmadas += $item['confirmadas'];
    $total_canceladas += $item['canceladas'];
    $total_completadas += $item['completadas'];
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
                            <label class="filter-label" for="estado">Estado:</label>
                            <select id="estado" name="estado" class="filter-select">
                                <option value="">Todos los estados</option>
                                <option value="pendiente" <?php echo ($estado == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="confirmada" <?php echo ($estado == 'confirmada') ? 'selected' : ''; ?>>Confirmada</option>
                                <option value="cancelada" <?php echo ($estado == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                                <option value="completada" <?php echo ($estado == 'completada') ? 'selected' : ''; ?>>Completada</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="agrupacion">Agrupar por:</label>
                            <select id="agrupacion" name="agrupacion" class="filter-select">
                                <option value="diaria" <?php echo ($agrupacion == 'diaria') ? 'selected' : ''; ?>>Diaria</option>
                                <option value="semanal" <?php echo ($agrupacion == 'semanal') ? 'selected' : ''; ?>>Semanal</option>
                                <option value="mensual" <?php echo ($agrupacion == 'mensual') ? 'selected' : ''; ?>>Mensual</option>
                                <option value="usuario" <?php echo ($agrupacion == 'usuario') ? 'selected' : ''; ?>>Por Usuario</option>
                                <option value="recurso" <?php echo ($agrupacion == 'recurso') ? 'selected' : ''; ?>>Por Recurso</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="filtro-btn">Filtrar</button>
                        <a href="estadisticas_reservas.php" class="filtro-btn btn-reset">Reiniciar</a>

                        <!-- Botón para exportar a CSV -->
                        <a href="exportar_csv.php?reporte=estadisticas_reservas&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&usuario=<?php echo $id_usuario; ?>&estado=<?php echo $estado; ?>&agrupacion=<?php echo $agrupacion; ?>" class="btn btn-secondary csv-btn">
                            <i class="csv-icon"></i> Exportar a CSV
                        </a>

                        <!-- Botón para generar PDF -->
                        <a href="generar_pdf_estadisticas_reservas.php?fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&usuario=<?php echo $id_usuario; ?>&estado=<?php echo $estado; ?>&agrupacion=<?php echo $agrupacion; ?>" class="btn btn-primary" style="margin-left: 10px;">
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
                            <div class="stat-highlight"><?php echo number_format($total_reservas); ?></div>
                            <div class="stat-label">Reservas Totales</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_pendientes); ?></div>
                            <div class="stat-label">Pendientes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_confirmadas); ?></div>
                            <div class="stat-label">Confirmadas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_canceladas); ?></div>
                            <div class="stat-label">Canceladas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_completadas); ?></div>
                            <div class="stat-label">Completadas</div>
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
                                    <td class="number-cell"><?php echo $total_pendientes; ?></td>
                                    <td class="number-cell"><?php echo ($total_reservas > 0) ? number_format(($total_pendientes / $total_reservas) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-success">Confirmadas</span></td>
                                    <td class="number-cell"><?php echo $total_confirmadas; ?></td>
                                    <td class="number-cell"><?php echo ($total_reservas > 0) ? number_format(($total_confirmadas / $total_reservas) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-danger">Canceladas</span></td>
                                    <td class="number-cell"><?php echo $total_canceladas; ?></td>
                                    <td class="number-cell"><?php echo ($total_reservas > 0) ? number_format(($total_canceladas / $total_reservas) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-info">Completadas</span></td>
                                    <td class="number-cell"><?php echo $total_completadas; ?></td>
                                    <td class="number-cell"><?php echo ($total_reservas > 0) ? number_format(($total_completadas / $total_reservas) * 100, 1) : 0; ?>%</td>
                                </tr>
                            </tbody>
                        </table>
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
                                        case 'diaria': ?>
                                            <th>Fecha</th>
                                        <?php break;
                                        case 'semanal': ?>
                                            <th>Semana</th>
                                        <?php break;
                                        case 'mensual': ?>
                                            <th>Mes</th>
                                        <?php break;
                                        case 'usuario': ?>
                                            <th>Usuario</th>
                                        <?php break;
                                        case 'recurso': ?>
                                            <th>Recurso</th>
                                            <th>Tipo</th>
                                    <?php break;
                                    endswitch; ?>
                                    <th class="number-cell">Total Reservas</th>
                                    <th class="number-cell">Pendientes</th>
                                    <th class="number-cell">Confirmadas</th>
                                    <th class="number-cell">Canceladas</th>
                                    <th class="number-cell">Completadas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estadisticas as $item): ?>
                                    <tr>
                                        <?php switch ($agrupacion):
                                            case 'diaria': ?>
                                                <td><?php echo date('d/m/Y', strtotime($item['fecha'])); ?></td>
                                            <?php break;
                                            case 'semanal': ?>
                                                <td>Semana del <?php echo date('d/m/Y', strtotime($item['fecha_inicio_semana'])); ?></td>
                                            <?php break;
                                            case 'mensual': ?>
                                                <td><?php echo $item['mes_formateado']; ?></td>
                                            <?php break;
                                            case 'usuario': ?>
                                                <td><?php echo htmlspecialchars($item['nombre_usuario']); ?></td>
                                            <?php break;
                                            case 'recurso': ?>
                                                <td><?php echo htmlspecialchars($item['nombre_recurso']); ?></td>
                                                <td><?php echo htmlspecialchars($item['tipo_recurso']); ?></td>
                                        <?php break;
                                        endswitch; ?>
                                        <td class="number-cell"><?php echo $item['total_reservas']; ?></td>
                                        <td class="number-cell"><?php echo $item['pendientes']; ?></td>
                                        <td class="number-cell"><?php echo $item['confirmadas']; ?></td>
                                        <td class="number-cell"><?php echo $item['canceladas']; ?></td>
                                        <td class="number-cell"><?php echo $item['completadas']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">Tendencias y Observaciones</h2>

                <?php if (!empty($estadisticas)): ?>
                    <div class="mini-chart" id="tendencias-chart"></div>

                    <div style="margin-top: 20px;">
                        <h3 style="font-size: 16px; margin-bottom: 10px;">Análisis de Datos:</h3>

                        <ul style="padding-left: 20px; margin-bottom: 15px;">
                            <?php
                            // Calcular estadísticas interesantes
                            $total_periodos = count($estadisticas);
                            $promedio_reservas = $total_periodos > 0 ? $total_reservas / $total_periodos : 0;

                            // Encontrar período con más reservas
                            $max_reservas = 0;
                            $periodo_max = "";

                            foreach ($estadisticas as $item) {
                                if ($item['total_reservas'] > $max_reservas) {
                                    $max_reservas = $item['total_reservas'];

                                    switch ($agrupacion) {
                                        case 'diaria':
                                            $periodo_max = date('d/m/Y', strtotime($item['fecha']));
                                            break;
                                        case 'semanal':
                                            $periodo_max = "Semana del " . date('d/m/Y', strtotime($item['fecha_inicio_semana']));
                                            break;
                                        case 'mensual':
                                            $periodo_max = $item['mes_formateado'];
                                            break;
                                        case 'usuario':
                                            $periodo_max = $item['nombre_usuario'];
                                            break;
                                        case 'recurso':
                                            $periodo_max = $item['nombre_recurso'];
                                            break;
                                    }
                                }
                            }

                            // Calcular tasa de cancelación
                            $tasa_cancelacion = $total_reservas > 0 ? ($total_canceladas / $total_reservas) * 100 : 0;
                            ?>

                            <li>
                                <strong>Volumen de reservas:</strong> En el período analizado se registraron un total de <?php echo number_format($total_reservas); ?> reservas,
                                con un promedio de <?php echo number_format($promedio_reservas, 1); ?> reservas por período.
                            </li>

                            <li>
                                <strong>Período de mayor actividad:</strong>
                                <?php if (!empty($periodo_max)): ?>
                                    El período con mayor número de reservas fue <?php echo $periodo_max; ?> con <?php echo $max_reservas; ?> reservas.
                                <?php else: ?>
                                    No se identificó un período con actividad destacada.
                                <?php endif; ?>
                            </li>

                            <li>
                                <strong>Tasa de cancelación:</strong> La tasa de cancelación de reservas fue del <?php echo number_format($tasa_cancelacion, 1); ?>%.
                                <?php if ($tasa_cancelacion > 20): ?>
                                    Esta tasa es relativamente alta y podría indicar problemas en el proceso de reserva.
                                <?php elseif ($tasa_cancelacion < 5): ?>
                                    Esta tasa es baja, lo que indica un buen funcionamiento del sistema de reservas.
                                <?php endif; ?>
                            </li>

                            <li>
                                <strong>Eficiencia del sistema:</strong>
                                <?php
                                $tasa_completadas = $total_reservas > 0 ? ($total_completadas / $total_reservas) * 100 : 0;
                                ?>
                                El <?php echo number_format($tasa_completadas, 1); ?>% de las reservas fueron completadas satisfactoriamente.
                            </li>
                        </ul>

                        <?php if ($total_reservas > 0): ?>
                            <div style="margin-top: 20px; padding: 15px; background-color: rgba(74, 144, 226, 0.1); border-radius: 4px;">
                                <h4 style="margin-bottom: 10px; color: #4A90E2;">Recomendaciones basadas en los datos:</h4>

                                <?php
                                // Generar recomendaciones basadas en los datos analizados
                                if ($tasa_cancelacion > 20) {
                                    echo "<p>Se recomienda revisar el proceso de reservas para entender por qué hay una alta tasa de cancelaciones. Podría ser útil implementar recordatorios o confirmaciones previas.</p>";
                                }

                                if ($total_pendientes > $total_reservas * 0.3) {
                                    echo "<p>Hay un alto número de reservas pendientes. Se sugiere revisar el proceso de aprobación para hacerlo más eficiente.</p>";
                                }

                                // Sugerencias basadas en uso de recursos (solo aplica si la agrupación es por recurso)
                                if ($agrupacion == 'recurso' && count($estadisticas) > 0) {
                                    // Identificar recursos poco utilizados
                                    $recursos_poco_uso = array_filter($estadisticas, function ($item) {
                                        return $item['total_reservas'] < 5; // Ejemplo: menos de 5 reservas
                                    });

                                    if (count($recursos_poco_uso) > 0) {
                                        echo "<p>Se han identificado " . count($recursos_poco_uso) . " recursos con bajo nivel de utilización. Considere revisar su disponibilidad o promoción.</p>";
                                    }
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No hay suficientes datos para generar tendencias y observaciones.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/chart.min.js"></script>
    <script>
        // Datos para los gráficos
        const datosEstados = {
            labels: ['Pendientes', 'Confirmadas', 'Canceladas', 'Completadas'],
            datasets: [{
                data: [
                    <?php echo $total_pendientes; ?>,
                    <?php echo $total_confirmadas; ?>,
                    <?php echo $total_canceladas; ?>,
                    <?php echo $total_completadas; ?>
                ],
                backgroundColor: [
                    '#FFC107', // Amarillo para pendientes
                    '#28A745', // Verde para confirmadas
                    '#DC3545', // Rojo para canceladas
                    '#17A2B8' // Azul para completadas
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

        // Preparar datos para el gráfico de tendencias
        const labelsTendencias = [];
        const datosPendientes = [];
        const datosConfirmadas = [];
        const datosCanceladas = [];
        const datosCompletadas = [];

        <?php foreach ($estadisticas as $item): ?>
            <?php switch ($agrupacion):
                case 'diaria': ?>
                    labelsTendencias.push('<?php echo date('d/m/Y', strtotime($item['fecha'])); ?>');
                <?php break;
                case 'semanal': ?>
                    labelsTendencias.push('Semana <?php echo date('d/m', strtotime($item['fecha_inicio_semana'])); ?>');
                <?php break;
                case 'mensual': ?>
                    labelsTendencias.push('<?php echo $item['mes_formateado']; ?>');
                <?php break;
                case 'usuario': ?>
                    labelsTendencias.push('<?php echo addslashes($item['nombre_usuario']); ?>');
                <?php break;
                case 'recurso': ?>
                    labelsTendencias.push('<?php echo addslashes($item['nombre_recurso']); ?>');
            <?php break;
            endswitch; ?>

            datosPendientes.push(<?php echo $item['pendientes']; ?>);
            datosConfirmadas.push(<?php echo $item['confirmadas']; ?>);
            datosCanceladas.push(<?php echo $item['canceladas']; ?>);
            datosCompletadas.push(<?php echo $item['completadas']; ?>);
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
                    label: 'Confirmadas',
                    data: datosConfirmadas,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: '#28A745',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#28A745'
                },
                {
                    label: 'Canceladas',
                    data: datosCanceladas,
                    backgroundColor: 'rgba(220, 53, 69, 0.2)',
                    borderColor: '#DC3545',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#DC3545'
                },
                {
                    label: 'Completadas',
                    data: datosCompletadas,
                    backgroundColor: 'rgba(23, 162, 184, 0.2)',
                    borderColor: '#17A2B8',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#17A2B8'
                }
            ]
        };

        // Configuración para el gráfico de tendencias
        const configTendencias = {
            type: '<?php echo ($agrupacion == 'usuario' || $agrupacion == 'recurso') ? 'bar' : 'line'; ?>',
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

            // Gráfico de tendencias
            const ctxTendencias = document.getElementById('tendencias-chart').getContext('2d');
            new Chart(ctxTendencias, configTendencias);
        });
    </script>
</body>

</html>