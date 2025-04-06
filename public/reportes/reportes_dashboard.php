<?php

/**
 * Módulo de Reportes - Dashboard principal
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

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Obtener algunas estadísticas generales para mostrar
$estadisticas = [];

// Total de reservas
$sql = "SELECT COUNT(*) as total FROM reservas";
$resultado = $db->getRow($sql);
$estadisticas['total_reservas'] = $resultado ? $resultado['total'] : 0;

// Reservas por estado
$sql = "SELECT estado, COUNT(*) as total FROM reservas GROUP BY estado";
$reservas_por_estado = $db->getRows($sql);

// Recursos más reservados
$sql = "SELECT r.id_recurso, r.nombre, COUNT(re.id_reserva) as total 
        FROM recursos r
        JOIN reservas re ON r.id_recurso = re.id_recurso
        GROUP BY r.id_recurso
        ORDER BY total DESC
        LIMIT 5";
$recursos_populares = $db->getRows($sql);

// Total de recursos
$sql = "SELECT COUNT(*) as total FROM recursos";
$resultado = $db->getRow($sql);
$estadisticas['total_recursos'] = $resultado ? $resultado['total'] : 0;

// Recursos por tipo
$sql = "SELECT t.nombre, COUNT(r.id_recurso) as total 
        FROM recursos r
        JOIN tipos_recursos t ON r.id_tipo = t.id_tipo
        GROUP BY t.id_tipo
        ORDER BY total DESC";
$recursos_por_tipo = $db->getRows($sql);

// Total de usuarios
$sql = "SELECT COUNT(*) as total FROM usuarios WHERE activo = 1";
$resultado = $db->getRow($sql);
$estadisticas['total_usuarios'] = $resultado ? $resultado['total'] : 0;

// Total de mantenimientos
$sql = "SELECT COUNT(*) as total FROM mantenimiento";
$resultado = $db->getRow($sql);
$estadisticas['total_mantenimientos'] = $resultado ? $resultado['total'] : 0;

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/reportes.css">
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
                <h1>Reportes</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <h2 class="card-title">Resumen General</h2>
                <div class="stats-container">
                    <div class="stat-item">
                        <div class="stat-highlight"><?php echo number_format($estadisticas['total_reservas']); ?></div>
                        <div class="stat-label">Reservas Totales</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-highlight"><?php echo number_format($estadisticas['total_recursos']); ?></div>
                        <div class="stat-label">Recursos Disponibles</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-highlight"><?php echo number_format($estadisticas['total_usuarios']); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-highlight"><?php echo number_format($estadisticas['total_mantenimientos']); ?></div>
                        <div class="stat-label">Mantenimientos</div>
                    </div>
                </div>
            </div>

            <h2>Reportes Disponibles</h2>

            <div class="report-grid">
                <a href="uso_recursos.php" class="report-card">
                    <div class="report-icon">📊</div>
                    <div class="report-content">
                        <div class="report-title">Uso de Recursos</div>
                        <div class="report-description">Analiza el uso de los recursos por período, tipo y ubicación.</div>
                        <div class="btn btn-primary">Ver Reporte</div>
                    </div>
                </a>

                <a href="estadisticas_reservas.php" class="report-card">
                    <div class="report-icon">📅</div>
                    <div class="report-content">
                        <div class="report-title">Estadísticas de Reservas</div>
                        <div class="report-description">Visualiza tendencias de reservas por mes, día de la semana y hora.</div>
                        <div class="btn btn-primary">Ver Reporte</div>
                    </div>
                </a>

                <a href="mantenimiento_recursos.php" class="report-card">
                    <div class="report-icon">🔧</div>
                    <div class="report-content">
                        <div class="report-title">Mantenimiento de Recursos</div>
                        <div class="report-description">Reportes sobre mantenimientos programados, completados y pendientes.</div>
                        <div class="btn btn-primary">Ver Reporte</div>
                    </div>
                </a>

                <a href="actividad_usuarios.php" class="report-card">
                    <div class="report-icon">👥</div>
                    <div class="report-content">
                        <div class="report-title">Actividad de Usuarios</div>
                        <div class="report-description">Analiza los patrones de uso de los usuarios y sus reservas.</div>
                        <div class="btn btn-primary">Ver Reporte</div>
                    </div>
                </a>

                <a href="disponibilidad.php" class="report-card">
                    <div class="report-icon">⏰</div>
                    <div class="report-content">
                        <div class="report-title">Disponibilidad de Recursos</div>
                        <div class="report-description">Visualiza los horarios más ocupados y la disponibilidad actual.</div>
                        <div class="btn btn-primary">Ver Reporte</div>
                    </div>
                </a>

                <a href="exportar.php" class="report-card">
                    <div class="report-icon">📄</div>
                    <div class="report-content">
                        <div class="report-title">Exportar Datos</div>
                        <div class="report-description">Exporta datos en formato CSV o PDF para análisis externos.</div>
                        <div class="btn btn-primary">Ir a Exportación</div>
                    </div>
                </a>
            </div>

            <div class="grid-2" style="margin-top: 30px;">
                <div class="card">
                    <h2 class="card-title">Recursos Más Utilizados</h2>
                    <?php if (empty($recursos_populares)): ?>
                        <div class="empty-state">
                            <p>No hay datos suficientes para mostrar este reporte.</p>
                        </div>
                    <?php else: ?>
                        <div class="mini-chart" id="recursos-chart"></div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Recurso</th>
                                    <th>Reservas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recursos_populares as $recurso): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($recurso['nombre']); ?></td>
                                        <td><?php echo $recurso['total']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2 class="card-title">Estado de Reservas</h2>
                    <?php if (empty($reservas_por_estado)): ?>
                        <div class="empty-state">
                            <p>No hay datos suficientes para mostrar este reporte.</p>
                        </div>
                    <?php else: ?>
                        <div class="mini-chart" id="estado-chart"></div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Estado</th>
                                    <th>Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservas_por_estado as $estado): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php echo 'badge-' . getEstadoClass($estado['estado']); ?>">
                                                <?php echo ucfirst($estado['estado']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $estado['total']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/reportes.js"></script>
</body>

</html>

<?php
// Función auxiliar para formatear colores según el estado
function getEstadoClass($estado)
{
    switch ($estado) {
        case 'pendiente':
            return 'warning';
        case 'confirmada':
            return 'success';
        case 'cancelada':
            return 'danger';
        case 'completada':
            return 'info';
        default:
            return 'secondary';
    }
}
?>