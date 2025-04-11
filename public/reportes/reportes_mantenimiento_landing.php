<?php

/**
 * Módulo de Reportes - Landing de Mantenimiento de Recursos
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

// Obtener estadísticas rápidas para mostrar
$estadisticas = [
    'total_mantenimientos' => $db->getValue("SELECT COUNT(*) FROM mantenimiento"),
    'mantenimientos_activos' => $db->getValue("SELECT COUNT(*) FROM mantenimiento WHERE estado IN ('pendiente', 'en_progreso')"),
    'mantenimientos_pendientes' => $db->getValue("SELECT COUNT(*) FROM mantenimiento WHERE estado = 'pendiente'"),
    'mantenimientos_completados' => $db->getValue("SELECT COUNT(*) FROM mantenimiento WHERE estado = 'completado'"),
    'recursos_en_mantenimiento' => $db->getValue("SELECT COUNT(DISTINCT id_recurso) FROM mantenimiento WHERE estado IN ('pendiente', 'en_progreso')")
];

// Obtener mantenimientos recientes
$mantenimientos_recientes = $db->getRows("
    SELECT m.id_mantenimiento, m.descripcion, m.fecha_inicio, m.fecha_fin, m.estado,
           r.nombre as nombre_recurso,
           CONCAT(u.nombre, ' ', u.apellido) as responsable
    FROM mantenimiento m
    JOIN recursos r ON m.id_recurso = r.id_recurso
    JOIN usuarios u ON m.id_usuario = u.id_usuario
    ORDER BY m.fecha_inicio DESC
    LIMIT 5
");

// Obtener recursos con más mantenimientos
$recursos_mantenimiento = $db->getRows("
    SELECT r.id_recurso, r.nombre, COUNT(m.id_mantenimiento) as total_mantenimientos
    FROM recursos r
    JOIN mantenimiento m ON r.id_recurso = m.id_recurso
    GROUP BY r.id_recurso
    ORDER BY total_mantenimientos DESC
    LIMIT 5
");

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
    <title>Reportes de Mantenimiento - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/reportes.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
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
                <h1>Reportes de Mantenimiento</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="breadcrumb">
                <a href="../admin/dashboard.php">Dashboard</a> &gt;
                <a href="reportes_dashboard.php">Reportes</a> &gt;
                <span>Mantenimiento de Recursos</span>
            </div>

            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($estadisticas['total_mantenimientos']); ?></div>
                    <div class="stat-title">Total Mantenimientos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($estadisticas['mantenimientos_activos']); ?></div>
                    <div class="stat-title">Mantenimientos Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($estadisticas['mantenimientos_pendientes']); ?></div>
                    <div class="stat-title">Pendientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($estadisticas['mantenimientos_completados']); ?></div>
                    <div class="stat-title">Completados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($estadisticas['recursos_en_mantenimiento']); ?></div>
                    <div class="stat-title">Recursos en Mantenimiento</div>
                </div>
            </div>

            <div class="reports-selection">
                <h2>Informes Disponibles de Mantenimiento</h2>

                <div class="report-cards">
                    <a href="reportes_mant_estadisticas.php" class="report-card">
                        <div class="report-icon stats-icon"></div>
                        <div class="report-info">
                            <h3>Estadísticas de Mantenimiento</h3>
                            <p>Análisis detallado del estado, duración y frecuencia de los mantenimientos.</p>
                            <div class="report-action">Ver informe</div>
                        </div>
                    </a>

                    <a href="reportes_mant_recursos.php" class="report-card">
                        <div class="report-icon resources-icon"></div>
                        <div class="report-info">
                            <h3>Mantenimiento por Recurso</h3>
                            <p>Análisis de los recursos que requieren más mantenimiento y sus problemas comunes.</p>
                            <div class="report-action">Ver informe</div>
                        </div>
                    </a>

                    <a href="reportes_mant_periodos.php" class="report-card">
                        <div class="report-icon calendar-icon"></div>
                        <div class="report-info">
                            <h3>Mantenimiento por Período</h3>
                            <p>Distribución temporal de mantenimientos para identificar patrones y picos.</p>
                            <div class="report-action">Ver informe</div>
                        </div>
                    </a>

                    <a href="reportes_mant_preventivos.php" class="report-card">
                        <div class="report-icon preventive-icon"></div>
                        <div class="report-info">
                            <h3>Mantenimiento Preventivo</h3>
                            <p>Análisis de efectividad de mantenimientos preventivos y recomendaciones.</p>
                            <div class="report-action">Ver informe</div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h2 class="card-title">Mantenimientos Recientes</h2>
                    <?php if (empty($mantenimientos_recientes)): ?>
                        <div class="empty-state">
                            <p>No hay mantenimientos recientes registrados.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Recurso</th>
                                        <th>Estado</th>
                                        <th>Responsable</th>
                                        <th>Fecha Inicio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mantenimientos_recientes as $mant): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($mant['nombre_recurso']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo get_maintenance_status_class($mant['estado']); ?>">
                                                    <?php
                                                    $texto_estado = $mant['estado'];
                                                    if ($texto_estado == 'en_progreso') {
                                                        $texto_estado = 'En Progreso';
                                                    } else {
                                                        $texto_estado = ucfirst($texto_estado);
                                                    }
                                                    echo $texto_estado;
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($mant['responsable']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($mant['fecha_inicio'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2 class="card-title">Recursos con Más Mantenimientos</h2>
                    <?php if (empty($recursos_mantenimiento)): ?>
                        <div class="empty-state">
                            <p>No hay datos de mantenimiento suficientes para mostrar estadísticas.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container">
                            <canvas id="recursos-chart" height="200"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Opciones de Reporte Completo</h2>
                <p class="report-description">Para un análisis detallado y personalizable de los datos de mantenimiento, utilice el reporte completo.</p>

                <div class="report-options">
                    <a href="reportes_mantenimiento.php" class="btn btn-primary btn-large">
                        <i class="report-icon"></i> Ver Reporte Completo
                    </a>

                    <div class="export-options">
                        <a href="exportar_csv.php?reporte=mantenimiento" class="btn btn-secondary">
                            <i class="csv-icon"></i> Exportar a CSV
                        </a>
                        <a href="generar_pdf_mantenimiento.php" class="btn btn-secondary">
                            <i class="pdf-icon"></i> Generar PDF
                        </a>
                    </div>
                </div>

                <div class="report-filters-preview">
                    <h3>Opciones de filtrado disponibles:</h3>
                    <ul class="filter-list">
                        <li><strong>Período de tiempo</strong> - Seleccione un rango de fechas específico</li>
                        <li><strong>Tipo de recurso</strong> - Filtre por categoría de recursos</li>
                        <li><strong>Estado del mantenimiento</strong> - Pendiente, En Progreso o Completado</li>
                        <li><strong>Recurso específico</strong> - Análisis de un solo recurso</li>
                        <li><strong>Responsable</strong> - Filtrar por persona a cargo del mantenimiento</li>
                        <li><strong>Tipo de agrupación</strong> - Por recurso, período, responsable o tipo</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/chart.min.js"></script>
    <script>
        // Crear gráfico de recursos con más mantenimientos
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($recursos_mantenimiento)): ?>
                const ctx = document.getElementById('recursos-chart').getContext('2d');

                const labels = [
                    <?php foreach ($recursos_mantenimiento as $recurso): ?> '<?php echo addslashes($recurso['nombre']); ?>',
                    <?php endforeach; ?>
                ];

                const data = [
                    <?php foreach ($recursos_mantenimiento as $recurso): ?>
                        <?php echo $recurso['total_mantenimientos']; ?>,
                    <?php endforeach; ?>
                ];

                new Chart(ctx, {
                    type: 'horizontalBar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Total Mantenimientos',
                            data: data,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
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
        });
    </script>
</body>

</html>

<?php
// Función para obtener la clase CSS según el estado del mantenimiento
function get_maintenance_status_class($estado)
{
    switch ($estado) {
        case 'pendiente':
            return 'warning';
        case 'en_progreso':
            return 'primary';
        case 'completado':
            return 'success';
        default:
            return 'secondary';
    }
}
?>