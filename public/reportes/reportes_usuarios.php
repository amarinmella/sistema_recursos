<?php

/**
 * Módulo de Reportes - Actividad de Usuarios
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
$id_rol = isset($_GET['rol']) ? intval($_GET['rol']) : 0;
$id_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$agrupacion = isset($_GET['agrupacion']) ? $_GET['agrupacion'] : 'usuario';

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
$filtros[] = "r.fecha_inicio >= ?";
$params[] = $fecha_inicio . ' 00:00:00';

$filtros[] = "r.fecha_inicio <= ?";
$params[] = $fecha_fin . ' 23:59:59';

// Añadir filtro de rol
if ($id_rol > 0) {
    $filtros[] = "ro.id_rol = ?";
    $params[] = $id_rol;
}

// Añadir filtro de usuario específico
if ($id_usuario > 0) {
    $filtros[] = "u.id_usuario = ?";
    $params[] = $id_usuario;
}

// Construir cláusula WHERE
$where = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Generar consulta según agrupación seleccionada
switch ($agrupacion) {
    case 'usuario':
        $sql = "
            SELECT 
                u.id_usuario,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
                ro.nombre as rol,
                COUNT(r.id_reserva) as total_reservas,
                COUNT(DISTINCT r.id_recurso) as recursos_distintos,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                AVG(TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin)) as duracion_promedio,
                MIN(r.fecha_inicio) as primera_reserva,
                MAX(r.fecha_inicio) as ultima_reserva,
                DATEDIFF(NOW(), MAX(r.fecha_inicio)) as dias_ultima_actividad
            FROM reservas r
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            $where
            GROUP BY u.id_usuario
            ORDER BY total_reservas DESC
        ";
        break;

    case 'rol':
        $sql = "
            SELECT 
                ro.id_rol,
                ro.nombre as rol,
                COUNT(DISTINCT u.id_usuario) as total_usuarios,
                COUNT(r.id_reserva) as total_reservas,
                COUNT(DISTINCT r.id_recurso) as recursos_distintos,
                ROUND(COUNT(r.id_reserva) / COUNT(DISTINCT u.id_usuario), 2) as reservas_por_usuario,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                AVG(TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin)) as duracion_promedio
            FROM reservas r
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            $where
            GROUP BY ro.id_rol
            ORDER BY total_reservas DESC
        ";
        break;

    case 'mensual':
        $sql = "
            SELECT 
                DATE_FORMAT(r.fecha_inicio, '%Y-%m') as mes,
                DATE_FORMAT(r.fecha_inicio, '%m/%Y') as mes_formateado,
                COUNT(DISTINCT u.id_usuario) as usuarios_activos,
                COUNT(r.id_reserva) as total_reservas,
                COUNT(DISTINCT r.id_recurso) as recursos_utilizados,
                ROUND(COUNT(r.id_reserva) / COUNT(DISTINCT u.id_usuario), 2) as reservas_por_usuario,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                AVG(TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin)) as duracion_promedio
            FROM reservas r
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            $where
            GROUP BY DATE_FORMAT(r.fecha_inicio, '%Y-%m')
            ORDER BY mes
        ";
        break;

    case 'frecuencia':
        $sql = "
            SELECT 
                CASE 
                    WHEN num_reservas >= 20 THEN 'Muy frecuente (20+ reservas)'
                    WHEN num_reservas >= 10 THEN 'Frecuente (10-19 reservas)'
                    WHEN num_reservas >= 5 THEN 'Regular (5-9 reservas)'
                    WHEN num_reservas >= 2 THEN 'Ocasional (2-4 reservas)'
                    ELSE 'Único (1 reserva)'
                END as categoria_frecuencia,
                COUNT(DISTINCT u.id_usuario) as total_usuarios,
                SUM(num_reservas) as total_reservas,
                ROUND(AVG(num_reservas), 2) as promedio_reservas_por_usuario
            FROM (
                SELECT 
                    id_usuario, 
                    COUNT(*) as num_reservas
                FROM reservas
                WHERE fecha_inicio >= ? AND fecha_inicio <= ?
                GROUP BY id_usuario
            ) reservas_por_usuario
            JOIN usuarios u ON reservas_por_usuario.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            " . (($id_rol > 0) ? " WHERE ro.id_rol = " . intval($id_rol) : "") . "
            GROUP BY categoria_frecuencia
            ORDER BY MIN(num_reservas) DESC
        ";

        // Ajustar parámetros solo para esta consulta específica
        $params_frecuencia = [
            $fecha_inicio . ' 00:00:00',
            $fecha_fin . ' 23:59:59'
        ];

        $estadisticas = $db->getRows($sql, $params_frecuencia);
        break;

    default:
        $sql = "
            SELECT 
                u.id_usuario,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
                ro.nombre as rol,
                COUNT(r.id_reserva) as total_reservas,
                COUNT(DISTINCT r.id_recurso) as recursos_distintos,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                AVG(TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin)) as duracion_promedio
            FROM reservas r
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            $where
            GROUP BY u.id_usuario
            ORDER BY total_reservas DESC
        ";
}

// Obtener datos para el reporte, si no se hizo ya en el caso de 'frecuencia'
if ($agrupacion != 'frecuencia') {
    $estadisticas = $db->getRows($sql, $params);
}

// Consultas adicionales para estadísticas generales

// Obtener los usuarios más activos
$sql_usuarios_activos = "
    SELECT 
        u.id_usuario,
        CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
        ro.nombre as rol,
        COUNT(r.id_reserva) as total_reservas
    FROM reservas r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    JOIN roles ro ON u.id_rol = ro.id_rol
    WHERE r.fecha_inicio >= ? AND r.fecha_inicio <= ?
    GROUP BY u.id_usuario
    ORDER BY total_reservas DESC
    LIMIT 5
";

$usuarios_activos = $db->getRows($sql_usuarios_activos, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

// Obtener los tipos de recursos más solicitados por los usuarios
$sql_recursos_populares = "
    SELECT 
        tr.nombre as tipo_recurso,
        COUNT(r.id_reserva) as total_reservas,
        COUNT(DISTINCT r.id_usuario) as usuarios_distintos
    FROM reservas r
    JOIN recursos rec ON r.id_recurso = rec.id_recurso
    JOIN tipos_recursos tr ON rec.id_tipo = tr.id_tipo
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.fecha_inicio >= ? AND r.fecha_inicio <= ?
    " . (($id_rol > 0) ? " AND u.id_rol = " . intval($id_rol) : "") . "
    " . (($id_usuario > 0) ? " AND u.id_usuario = " . intval($id_usuario) : "") . "
    GROUP BY tr.id_tipo
    ORDER BY total_reservas DESC
    LIMIT 5
";

$recursos_populares = $db->getRows($sql_recursos_populares, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

// Obtener estadísticas de horarios preferidos
$sql_horarios = "
    SELECT 
        CASE 
            WHEN HOUR(fecha_inicio) BETWEEN 7 AND 11 THEN 'Mañana (7-11)'
            WHEN HOUR(fecha_inicio) BETWEEN 12 AND 16 THEN 'Tarde (12-16)'
            WHEN HOUR(fecha_inicio) BETWEEN 17 AND 21 THEN 'Noche (17-21)'
            ELSE 'Madrugada (22-6)'
        END as franja_horaria,
        COUNT(*) as total_reservas,
        COUNT(DISTINCT id_usuario) as usuarios_distintos
    FROM reservas r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.fecha_inicio >= ? AND r.fecha_inicio <= ?
    " . (($id_rol > 0) ? " AND u.id_rol = " . intval($id_rol) : "") . "
    " . (($id_usuario > 0) ? " AND u.id_usuario = " . intval($id_usuario) : "") . "
    GROUP BY franja_horaria
    ORDER BY total_reservas DESC
";

$horarios_preferidos = $db->getRows($sql_horarios, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

// Obtener estadísticas de días de la semana preferidos
$sql_dias = "
    SELECT 
        DAYOFWEEK(fecha_inicio) as dia_numero,
        CASE DAYOFWEEK(fecha_inicio)
            WHEN 1 THEN 'Domingo'
            WHEN 2 THEN 'Lunes'
            WHEN 3 THEN 'Martes'
            WHEN 4 THEN 'Miércoles'
            WHEN 5 THEN 'Jueves'
            WHEN 6 THEN 'Viernes'
            WHEN 7 THEN 'Sábado'
        END as dia_semana,
        COUNT(*) as total_reservas,
        COUNT(DISTINCT id_usuario) as usuarios_distintos
    FROM reservas r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.fecha_inicio >= ? AND r.fecha_inicio <= ?
    " . (($id_rol > 0) ? " AND u.id_rol = " . intval($id_rol) : "") . "
    " . (($id_usuario > 0) ? " AND u.id_usuario = " . intval($id_usuario) : "") . "
    GROUP BY dia_numero
    ORDER BY dia_numero
";

$dias_preferidos = $db->getRows($sql_dias, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

// Consulta resumen general
$sql_resumen = "
    SELECT 
        COUNT(DISTINCT u.id_usuario) as total_usuarios_activos,
        COUNT(r.id_reserva) as total_reservas,
        COUNT(DISTINCT r.id_recurso) as total_recursos_utilizados,
        ROUND(COUNT(r.id_reserva) / COUNT(DISTINCT u.id_usuario), 2) as promedio_reservas_por_usuario,
        SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) / COUNT(r.id_reserva) * 100 as tasa_cancelacion,
        AVG(TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin)) as duracion_promedio_horas
    FROM reservas r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    JOIN roles ro ON u.id_rol = ro.id_rol
    WHERE r.fecha_inicio >= ? AND r.fecha_inicio <= ?
    " . (($id_rol > 0) ? " AND ro.id_rol = " . intval($id_rol) : "") . "
    " . (($id_usuario > 0) ? " AND u.id_usuario = " . intval($id_usuario) : "") . "
";

$resumen = $db->getRow($sql_resumen, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

// Consulta para usuarios inactivos (sin reservas en el período)
$sql_inactivos = "
    SELECT 
        COUNT(*) as total_inactivos,
        COUNT(*) / (SELECT COUNT(*) FROM usuarios WHERE activo = 1) * 100 as porcentaje_inactivos
    FROM usuarios u
    WHERE u.activo = 1
    AND u.id_usuario NOT IN (
        SELECT DISTINCT id_usuario 
        FROM reservas 
        WHERE fecha_inicio >= ? AND fecha_inicio <= ?
    )
    " . (($id_rol > 0) ? " AND u.id_rol = " . intval($id_rol) : "") . "
";

$usuarios_inactivos = $db->getRow($sql_inactivos, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

// Obtener lista de roles para filtrar
$roles = $db->getRows(
    "SELECT id_rol, nombre FROM roles ORDER BY nombre"
);

// Obtener lista de usuarios activos para filtrar
$usuarios = $db->getRows(
    "SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) as nombre_completo, r.nombre as rol
     FROM usuarios u
     JOIN roles r ON u.id_rol = r.id_rol
     WHERE u.activo = 1
     ORDER BY u.nombre, u.apellido"
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

// Función segura para number_format que evita errores con valores nulos
function number_format_safe($number, $decimals = 0, $dec_point = '.', $thousands_sep = ',')
{
    return is_null($number) ? 'N/A' : number_format((float)$number, $decimals, $dec_point, $thousands_sep);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actividad de Usuarios - Sistema de Gestión de Recursos</title>
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
                <h1>Actividad de Usuarios</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="breadcrumb">
                <a href="../admin/dashboard.php">Dashboard</a> &gt;
                <a href="reportes_dashboard.php">Reportes</a> &gt;
                <span>Actividad de Usuarios</span>
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
                            <label class="filter-label" for="rol">Rol:</label>
                            <select id="rol" name="rol" class="filter-select">
                                <option value="0">Todos los roles</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?php echo $rol['id_rol']; ?>" <?php echo ($id_rol == $rol['id_rol']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rol['nombre']); ?>
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
                            <label class="filter-label" for="agrupacion">Agrupar por:</label>
                            <select id="agrupacion" name="agrupacion" class="filter-select">
                                <option value="usuario" <?php echo ($agrupacion == 'usuario') ? 'selected' : ''; ?>>Por Usuario</option>
                                <option value="rol" <?php echo ($agrupacion == 'rol') ? 'selected' : ''; ?>>Por Rol</option>
                                <option value="mensual" <?php echo ($agrupacion == 'mensual') ? 'selected' : ''; ?>>Mensual</option>
                                <option value="frecuencia" <?php echo ($agrupacion == 'frecuencia') ? 'selected' : ''; ?>>Por Frecuencia de Uso</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="filtro-btn">Filtrar</button>
                        <a href="reportes_usuarios.php" class="filtro-btn btn-reset">Reiniciar</a>

                        <!-- Botón para exportar a CSV -->
                        <a href="exportar_csv.php?reporte=usuarios&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&rol=<?php echo $id_rol; ?>&usuario=<?php echo $id_usuario; ?>&agrupacion=<?php echo $agrupacion; ?>" class="btn btn-secondary csv-btn">
                            <i class="csv-icon"></i> Exportar a CSV
                        </a>

                        <!-- Botón para generar PDF -->
                        <a href="generar_pdf_usuarios.php?fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&rol=<?php echo $id_rol; ?>&usuario=<?php echo $id_usuario; ?>&agrupacion=<?php echo $agrupacion; ?>" class="btn btn-primary" style="margin-left: 10px;">
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
                            <div class="stat-highlight"><?php echo number_format_safe($resumen['total_usuarios_activos']); ?></div>
                            <div class="stat-label">Usuarios Activos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format_safe($resumen['total_reservas']); ?></div>
                            <div class="stat-label">Reservas Realizadas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format_safe($resumen['promedio_reservas_por_usuario'], 1); ?></div>
                            <div class="stat-label">Reservas/Usuario</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format_safe($resumen['tasa_cancelacion'], 1); ?>%</div>
                            <div class="stat-label">Tasa Cancelación</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo formatear_horas($resumen['duracion_promedio_horas']); ?></div>
                            <div class="stat-label">Duración Promedio</div>
                        </div>
                    </div>
                    <div class="secondary-stats">
                        <div class="stat-row">
                            <div class="stat-label">Recursos Utilizados:</div>
                            <div class="stat-value"><?php echo number_format_safe($resumen['total_recursos_utilizados']); ?></div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-label">Usuarios Inactivos:</div>
                            <div class="stat-value">
                                <?php echo number_format_safe($usuarios_inactivos['total_inactivos']); ?>
                                (<?php echo number_format_safe($usuarios_inactivos['porcentaje_inactivos'], 1); ?>%)
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Usuarios Más Activos</h2>
                    <?php if (empty($usuarios_activos)): ?>
                        <div class="empty-state">
                            <p>No hay datos de actividad de usuarios para mostrar.</p>
                        </div>
                    <?php else: ?>
                        <div class="mini-chart" id="usuarios-activos-chart"></div>
                        <div class="table-container">
                            <table style="width: 100%; margin-top: 10px;">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Rol</th>
                                        <th class="number-cell">Reservas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios_activos as $usuario): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($usuario['nombre_usuario']); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['rol']); ?></td>
                                            <td class="number-cell"><?php echo $usuario['total_reservas']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h2 class="card-title">Recursos Más Solicitados</h2>
                    <?php if (empty($recursos_populares)): ?>
                        <div class="empty-state">
                            <p>No hay datos disponibles sobre recursos utilizados.</p>
                        </div>
                    <?php else: ?>
                        <div class="mini-chart" id="recursos-chart"></div>
                        <div class="table-container">
                            <table style="width: 100%; margin-top: 10px;">
                                <thead>
                                    <tr>
                                        <th>Tipo de Recurso</th>
                                        <th class="number-cell">Reservas</th>
                                        <th class="number-cell">Usuarios</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recursos_populares as $recurso): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($recurso['tipo_recurso']); ?></td>
                                            <td class="number-cell"><?php echo $recurso['total_reservas']; ?></td>
                                            <td class="number-cell"><?php echo $recurso['usuarios_distintos']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2 class="card-title">Horarios Preferidos</h2>
                    <?php if (empty($horarios_preferidos)): ?>
                        <div class="empty-state">
                            <p>No hay datos disponibles sobre horarios de uso.</p>
                        </div>
                    <?php else: ?>
                        <div class="mini-chart" id="horarios-chart"></div>
                        <div class="table-container">
                            <table style="width: 100%; margin-top: 10px;">
                                <thead>
                                    <tr>
                                        <th>Franja Horaria</th>
                                        <th class="number-cell">Reservas</th>
                                        <th class="number-cell">% del Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_reservas_horarios = array_sum(array_column($horarios_preferidos, 'total_reservas'));
                                    foreach ($horarios_preferidos as $horario):
                                        $porcentaje = ($total_reservas_horarios > 0) ? ($horario['total_reservas'] / $total_reservas_horarios) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($horario['franja_horaria']); ?></td>
                                            <td class="number-cell"><?php echo $horario['total_reservas']; ?></td>
                                            <td class="number-cell"><?php echo number_format_safe($porcentaje, 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Días de Uso Preferidos</h2>
                <?php if (empty($dias_preferidos)): ?>
                    <div class="empty-state">
                        <p>No hay datos disponibles sobre días de uso.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="dias-chart" height="250"></canvas>
                    </div>
                    <div class="table-container" style="margin-top: 20px;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Día</th>
                                    <th class="number-cell">Reservas</th>
                                    <th class="number-cell">Usuarios Distintos</th>
                                    <th class="number-cell">% del Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_reservas_dias = array_sum(array_column($dias_preferidos, 'total_reservas'));

                                // Reordenar para que comience en lunes
                                $dias_ordenados = [];
                                foreach ($dias_preferidos as $dia) {
                                    $dias_ordenados[$dia['dia_numero']] = $dia;
                                }

                                for ($i = 2; $i <= 7; $i++) { // Lunes a sábado
                                    if (isset($dias_ordenados[$i])) {
                                        $dia = $dias_ordenados[$i];
                                        $porcentaje = ($total_reservas_dias > 0) ? ($dia['total_reservas'] / $total_reservas_dias) * 100 : 0;
                                        echo "<tr>
                                            <td>{$dia['dia_semana']}</td>
                                            <td class=\"number-cell\">{$dia['total_reservas']}</td>
                                            <td class=\"number-cell\">{$dia['usuarios_distintos']}</td>
                                            <td class=\"number-cell\">" . number_format_safe($porcentaje, 1) . "%</td>
                                        </tr>";
                                    }
                                }

                                // Domingo al final
                                if (isset($dias_ordenados[1])) {
                                    $dia = $dias_ordenados[1];
                                    $porcentaje = ($total_reservas_dias > 0) ? ($dia['total_reservas'] / $total_reservas_dias) * 100 : 0;
                                    echo "<tr>
                                        <td>{$dia['dia_semana']}</td>
                                        <td class=\"number-cell\">{$dia['total_reservas']}</td>
                                        <td class=\"number-cell\">{$dia['usuarios_distintos']}</td>
                                        <td class=\"number-cell\">" . number_format_safe($porcentaje, 1) . "%</td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">Estadísticas Detalladas - <?php echo ucfirst($agrupacion); ?></h2>

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
                                        case 'usuario': ?>
                                            <th>Usuario</th>
                                            <th>Rol</th>
                                        <?php break;
                                        case 'rol': ?>
                                            <th>Rol</th>
                                            <th class="number-cell">Usuarios</th>
                                        <?php break;
                                        case 'mensual': ?>
                                            <th>Mes</th>
                                            <th class="number-cell">Usuarios Activos</th>
                                        <?php break;
                                        case 'frecuencia': ?>
                                            <th>Categoría de Frecuencia</th>
                                            <th class="number-cell">Usuarios</th>
                                    <?php break;
                                    endswitch; ?>
                                    <th class="number-cell">Total Reservas</th>
                                    <?php if ($agrupacion != 'frecuencia'): ?>
                                        <th class="number-cell">Recursos</th>
                                        <th class="number-cell">Pendientes</th>
                                        <th class="number-cell">Confirmadas</th>
                                        <th class="number-cell">Canceladas</th>
                                        <th class="number-cell">Completadas</th>
                                        <th class="number-cell">Duración Promedio</th>
                                    <?php else: ?>
                                        <th class="number-cell">Promedio por Usuario</th>
                                    <?php endif; ?>
                                    <?php if ($agrupacion == 'usuario'): ?>
                                        <th>Última Actividad</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estadisticas as $item): ?>
                                    <tr>
                                        <?php switch ($agrupacion):
                                            case 'usuario': ?>
                                                <td><?php echo htmlspecialchars($item['nombre_usuario']); ?></td>
                                                <td><?php echo htmlspecialchars($item['rol']); ?></td>
                                            <?php break;
                                            case 'rol': ?>
                                                <td><?php echo htmlspecialchars($item['rol']); ?></td>
                                                <td class="number-cell"><?php echo $item['total_usuarios']; ?></td>
                                            <?php break;
                                            case 'mensual': ?>
                                                <td><?php echo htmlspecialchars($item['mes_formateado']); ?></td>
                                                <td class="number-cell"><?php echo $item['usuarios_activos']; ?></td>
                                            <?php break;
                                            case 'frecuencia': ?>
                                                <td><?php echo htmlspecialchars($item['categoria_frecuencia']); ?></td>
                                                <td class="number-cell"><?php echo $item['total_usuarios']; ?></td>
                                        <?php break;
                                        endswitch; ?>
                                        <td class="number-cell"><?php echo $item['total_reservas']; ?></td>
                                        <?php if ($agrupacion != 'frecuencia'): ?>
                                            <td class="number-cell"><?php echo $item['recursos_distintos']; ?></td>
                                            <td class="number-cell"><?php echo $item['pendientes']; ?></td>
                                            <td class="number-cell"><?php echo $item['confirmadas']; ?></td>
                                            <td class="number-cell"><?php echo $item['canceladas']; ?></td>
                                            <td class="number-cell"><?php echo $item['completadas']; ?></td>
                                            <td class="number-cell"><?php echo formatear_horas($item['duracion_promedio']); ?></td>
                                        <?php else: ?>
                                            <td class="number-cell"><?php echo number_format_safe($item['promedio_reservas_por_usuario'], 1); ?></td>
                                        <?php endif; ?>
                                        <?php if ($agrupacion == 'usuario'): ?>
                                            <td>
                                                <?php if (isset($item['dias_ultima_actividad'])): ?>
                                                    <?php
                                                    if ($item['dias_ultima_actividad'] == 0) {
                                                        echo "Hoy";
                                                    } elseif ($item['dias_ultima_actividad'] == 1) {
                                                        echo "Ayer";
                                                    } else {
                                                        echo "Hace " . $item['dias_ultima_actividad'] . " días";
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
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
                    <div style="margin-top: 20px;">
                        <h3 style="font-size: 16px; margin-bottom: 10px;">Análisis de Patrones de Uso:</h3>

                        <ul style="padding-left: 20px; margin-bottom: 15px;">
                            <?php
                            // Preparar algunas variables para el análisis
                            $usuarios_regulares = 0;
                            $usuarios_ocasionales = 0;

                            if ($agrupacion == 'frecuencia') {
                                foreach ($estadisticas as $item) {
                                    if (
                                        strpos($item['categoria_frecuencia'], 'Muy frecuente') !== false ||
                                        strpos($item['categoria_frecuencia'], 'Frecuente') !== false ||
                                        strpos($item['categoria_frecuencia'], 'Regular') !== false
                                    ) {
                                        $usuarios_regulares += $item['total_usuarios'];
                                    } else {
                                        $usuarios_ocasionales += $item['total_usuarios'];
                                    }
                                }
                            }

                            // Identificar horario preferido
                            $horario_preferido = '';
                            $max_reservas_horario = 0;
                            foreach ($horarios_preferidos as $horario) {
                                if ($horario['total_reservas'] > $max_reservas_horario) {
                                    $max_reservas_horario = $horario['total_reservas'];
                                    $horario_preferido = $horario['franja_horaria'];
                                }
                            }

                            // Identificar día preferido
                            $dia_preferido = '';
                            $max_reservas_dia = 0;
                            foreach ($dias_preferidos as $dia) {
                                if ($dia['total_reservas'] > $max_reservas_dia) {
                                    $max_reservas_dia = $dia['total_reservas'];
                                    $dia_preferido = $dia['dia_semana'];
                                }
                            }

                            // Calcular tasa de cancelación
                            $tasa_cancelacion = ($resumen['total_reservas'] > 0) ?
                                (array_sum(array_column($estadisticas, 'canceladas')) / $resumen['total_reservas']) * 100 : 0;
                            ?>

                            <li>
                                <strong>Participación de usuarios:</strong>
                                En el período analizado, <?php echo number_format_safe($resumen['total_usuarios_activos']); ?> usuarios realizaron reservas,
                                mientras que <?php echo number_format_safe($usuarios_inactivos['total_inactivos']); ?> usuarios (<?php echo number_format_safe($usuarios_inactivos['porcentaje_inactivos'], 1); ?>%)
                                no registraron actividad.
                                <?php if ($agrupacion == 'frecuencia' && ($usuarios_regulares + $usuarios_ocasionales > 0)): ?>
                                    El <?php echo number_format(($usuarios_regulares / ($usuarios_regulares + $usuarios_ocasionales)) * 100, 1); ?>%
                                    de los usuarios son usuarios regulares (5+ reservas).
                                <?php endif; ?>
                            </li>

                            <li>
                                <strong>Patrones temporales:</strong>
                                <?php if (!empty($horario_preferido) && !empty($dia_preferido)): ?>
                                    Los usuarios prefieren realizar reservas en horario de <?php echo $horario_preferido; ?>,
                                    principalmente los días <?php echo $dia_preferido; ?>.
                                <?php else: ?>
                                    No se identificaron patrones temporales claros en las reservas.
                                <?php endif; ?>
                            </li>

                            <li>
                                <strong>Comportamiento de reservas:</strong>
                                <?php if ($resumen['total_reservas'] > 0): ?>
                                    En promedio, cada usuario activo realiza <?php echo number_format_safe($resumen['promedio_reservas_por_usuario'], 1); ?> reservas.
                                    La tasa de cancelación es del <?php echo number_format_safe($resumen['tasa_cancelacion'], 1); ?>%.
                                    <?php if ($resumen['tasa_cancelacion'] > 20): ?>
                                        Esta tasa es relativamente alta, lo que sugiere posibles problemas en el proceso de reserva
                                        o cambios frecuentes en los planes de los usuarios.
                                    <?php elseif ($resumen['tasa_cancelacion'] < 5): ?>
                                        Esta tasa es muy baja, lo que indica un buen compromiso de los usuarios con sus reservas.
                                    <?php endif; ?>
                                <?php endif; ?>
                            </li>

                            <li>
                                <strong>Preferencias de recursos:</strong>
                                <?php if (!empty($recursos_populares)): ?>
                                    Los recursos más solicitados son del tipo "<?php echo htmlspecialchars($recursos_populares[0]['tipo_recurso']); ?>"
                                    con <?php echo $recursos_populares[0]['total_reservas']; ?> reservas realizadas por
                                    <?php echo $recursos_populares[0]['usuarios_distintos']; ?> usuarios distintos.
                                <?php else: ?>
                                    No hay datos suficientes para analizar preferencias de recursos.
                                <?php endif; ?>
                            </li>
                        </ul>

                        <div style="margin-top: 20px; padding: 15px; background-color: rgba(74, 144, 226, 0.1); border-radius: 4px;">
                            <h4 style="margin-bottom: 10px; color: #4A90E2;">Recomendaciones:</h4>

                            <ul style="padding-left: 20px; margin-bottom: 0;">
                                <?php
                                // Generar recomendaciones basadas en el análisis
                                if ($usuarios_inactivos['porcentaje_inactivos'] > 30) {
                                    echo "<li>Implementar una campaña de reactivación para el " . number_format_safe($usuarios_inactivos['porcentaje_inactivos'], 1) . "% de usuarios inactivos mediante comunicaciones personalizadas o incentivos.</li>";
                                }

                                if ($resumen['tasa_cancelacion'] > 20) {
                                    echo "<li>Revisar el proceso de reservas para reducir la alta tasa de cancelaciones. Considerar enviar recordatorios o implementar una política de cancelación más efectiva.</li>";
                                }

                                if (!empty($horario_preferido) && !empty($dia_preferido)) {
                                    echo "<li>Optimizar la disponibilidad de recursos durante " . $horario_preferido . " y los días " . $dia_preferido . ", que son los períodos de mayor demanda.</li>";
                                }

                                if (!empty($recursos_populares)) {
                                    echo "<li>Evaluar la posibilidad de ampliar la cantidad de recursos del tipo \"" . htmlspecialchars($recursos_populares[0]['tipo_recurso']) . "\" debido a su alta demanda.</li>";
                                }

                                if ($resumen['promedio_reservas_por_usuario'] < 2 && $resumen['total_usuarios_activos'] > 10) {
                                    echo "<li>Promover un mayor uso del sistema entre los usuarios actuales, ya que la media de " . number_format_safe($resumen['promedio_reservas_por_usuario'], 1) . " reservas por usuario es relativamente baja.</li>";
                                }
                                ?>
                                <li>Realizar un seguimiento periódico de la actividad de usuarios para detectar tendencias y ajustar estrategias según sea necesario.</li>
                            </ul>
                        </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico de usuarios más activos
            <?php if (!empty($usuarios_activos)): ?>
                const ctxUsuarios = document.getElementById('usuarios-activos-chart').getContext('2d');
                new Chart(ctxUsuarios, {
                    type: 'horizontalBar',
                    data: {
                        labels: [
                            <?php foreach ($usuarios_activos as $usuario): ?> '<?php echo addslashes($usuario['nombre_usuario']); ?>',
                            <?php endforeach; ?>
                        ],
                        datasets: [{
                            label: 'Reservas',
                            data: [
                                <?php foreach ($usuarios_activos as $usuario): ?>
                                    <?php echo $usuario['total_reservas']; ?>,
                                <?php endforeach; ?>
                            ],
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            xAxes: [{
                                ticks: {
                                    beginAtZero: true
                                }
                            }]
                        },
                        legend: {
                            display: false
                        }
                    }
                });
            <?php endif; ?>

            // Gráfico de recursos populares
            <?php if (!empty($recursos_populares)): ?>
                const ctxRecursos = document.getElementById('recursos-chart').getContext('2d');
                new Chart(ctxRecursos, {
                    type: 'bar',
                    data: {
                        labels: [
                            <?php foreach ($recursos_populares as $recurso): ?> '<?php echo addslashes($recurso['tipo_recurso']); ?>',
                            <?php endforeach; ?>
                        ],
                        datasets: [{
                            label: 'Reservas',
                            data: [
                                <?php foreach ($recursos_populares as $recurso): ?>
                                    <?php echo $recurso['total_reservas']; ?>,
                                <?php endforeach; ?>
                            ],
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgba(75, 192, 192, 1)',
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
                        }
                    }
                });
            <?php endif; ?>

            // Gráfico de horarios preferidos
            <?php if (!empty($horarios_preferidos)): ?>
                const ctxHorarios = document.getElementById('horarios-chart').getContext('2d');
                new Chart(ctxHorarios, {
                    type: 'doughnut',
                    data: {
                        labels: [
                            <?php foreach ($horarios_preferidos as $horario): ?> '<?php echo addslashes($horario['franja_horaria']); ?>',
                            <?php endforeach; ?>
                        ],
                        datasets: [{
                            data: [
                                <?php foreach ($horarios_preferidos as $horario): ?>
                                    <?php echo $horario['total_reservas']; ?>,
                                <?php endforeach; ?>
                            ],
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.6)',
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 206, 86, 0.6)',
                                'rgba(75, 192, 192, 0.6)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)'
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
            <?php endif; ?>

            // Gráfico de días de la semana
            <?php if (!empty($dias_preferidos)): ?>
                const ctxDias = document.getElementById('dias-chart').getContext('2d');

                // Reordenar para que comience en lunes
                const etiquetasDias = [];
                const datosDias = [];
                const coloresDias = [];
                const borde = [];

                // Ordenar días de lunes a domingo
                const diasSemana = {
                    2: 'Lunes',
                    3: 'Martes',
                    4: 'Miércoles',
                    5: 'Jueves',
                    6: 'Viernes',
                    7: 'Sábado',
                    1: 'Domingo'
                };

                const colorBase = 'rgba(54, 162, 235, 0.6)';
                const colorBorde = 'rgba(54, 162, 235, 1)';
                const colorDestacado = 'rgba(255, 99, 132, 0.6)';
                const colorBordeDestacado = 'rgba(255, 99, 132, 1)';

                // Encontrar el día con más reservas
                let maxReservas = 0;
                let diaMasReservas = 0;

                <?php foreach ($dias_preferidos as $dia): ?>
                    if (<?php echo $dia['total_reservas']; ?> > maxReservas) {
                        maxReservas = <?php echo $dia['total_reservas']; ?>;
                        diaMasReservas = <?php echo $dia['dia_numero']; ?>;
                    }
                <?php endforeach; ?>

                // Agregar días en orden (lunes a domingo)
                for (let numDia = 2; numDia <= 7; numDia++) {
                    etiquetasDias.push(diasSemana[numDia]);

                    // Buscar si hay datos para este día
                    <?php
                    $datos_dias_js = "const datosPorDia = {";
                    foreach ($dias_preferidos as $dia) {
                        $datos_dias_js .= "{$dia['dia_numero']}: {$dia['total_reservas']},";
                    }
                    $datos_dias_js .= "};";
                    echo $datos_dias_js;
                    ?>

                    const valorDia = datosPorDia[numDia] || 0;
                    datosDias.push(valorDia);

                    // Destacar el día con más reservas
                    if (numDia === diaMasReservas) {
                        coloresDias.push(colorDestacado);
                        borde.push(colorBordeDestacado);
                    } else {
                        coloresDias.push(colorBase);
                        borde.push(colorBorde);
                    }
                }

                // Agregar domingo al final
                etiquetasDias.push(diasSemana[1]);
                const valorDomingo = datosPorDia[1] || 0;
                datosDias.push(valorDomingo);

                if (1 === diaMasReservas) {
                    coloresDias.push(colorDestacado);
                    borde.push(colorBordeDestacado);
                } else {
                    coloresDias.push(colorBase);
                    borde.push(colorBorde);
                }

                new Chart(ctxDias, {
                    type: 'bar',
                    data: {
                        labels: etiquetasDias,
                        datasets: [{
                            label: 'Reservas',
                            data: datosDias,
                            backgroundColor: coloresDias,
                            borderColor: borde,
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
                            text: 'Reservas por día de la semana'
                        }
                    }
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>