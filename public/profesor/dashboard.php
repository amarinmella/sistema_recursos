<?php

/**
 * Dashboard del Profesor
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/permissions.php';

// Verificar que el usuario esté logueado y sea profesor
require_login();
if ($_SESSION['usuario_rol'] != ROL_PROFESOR) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Obtener datos del usuario actual
$usuario = $db->getRow(
    "SELECT * FROM usuarios WHERE id_usuario = ?",
    [$_SESSION['usuario_id']]
);

// Obtener reservas recientes del profesor
$reservas_recientes = $db->getRows(
    "SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado,
            rc.nombre as recurso_nombre, rc.ubicacion as recurso_ubicacion
     FROM reservas r
     JOIN recursos rc ON r.id_recurso = rc.id_recurso
     WHERE r.id_usuario = ?
     ORDER BY r.fecha_creacion DESC
     LIMIT 5",
    [$_SESSION['usuario_id']]
);

// Obtener próximas reservas
$proximas_reservas = $db->getRows(
    "SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado,
            rc.nombre as recurso_nombre, rc.ubicacion as recurso_ubicacion
     FROM reservas r
     JOIN recursos rc ON r.id_recurso = rc.id_recurso
     WHERE r.id_usuario = ? AND r.fecha_inicio > NOW() AND r.estado IN ('pendiente', 'confirmada')
     ORDER BY r.fecha_inicio ASC
     LIMIT 5",
    [$_SESSION['usuario_id']]
);

// Obtener estadísticas
// Total de reservas del profesor
$total_reservas = $db->getRow(
    "SELECT COUNT(*) as total FROM reservas WHERE id_usuario = ?",
    [$_SESSION['usuario_id']]
)['total'] ?? 0;

// Reservas por estado
$reservas_por_estado = $db->getRows(
    "SELECT estado, COUNT(*) as total FROM reservas WHERE id_usuario = ? GROUP BY estado",
    [$_SESSION['usuario_id']]
);

// Reservas pendientes
$reservas_pendientes = 0;
// Reservas confirmadas
$reservas_confirmadas = 0;
// Reservas canceladas
$reservas_canceladas = 0;
// Reservas completadas
$reservas_completadas = 0;

foreach ($reservas_por_estado as $estado) {
    switch ($estado['estado']) {
        case 'pendiente':
            $reservas_pendientes = $estado['total'];
            break;
        case 'confirmada':
            $reservas_confirmadas = $estado['total'];
            break;
        case 'cancelada':
            $reservas_canceladas = $estado['total'];
            break;
        case 'completada':
            $reservas_completadas = $estado['total'];
            break;
    }
}

// Obtener incidencias recientes del profesor
$incidencias_recientes = $db->getRows(
    "SELECT id_incidencia, titulo, estado, fecha_reporte
     FROM bitacora_incidencias 
     WHERE id_usuario = ?
     ORDER BY fecha_reporte DESC
     LIMIT 5",
    [$_SESSION['usuario_id']]
);

// Contar incidencias por estado
$incidencias_por_estado = $db->getRows(
    "SELECT estado, COUNT(*) as total FROM bitacora_incidencias WHERE id_usuario = ? GROUP BY estado",
    [$_SESSION['usuario_id']]
);

$incidencias_reportadas = 0;
$incidencias_en_revision = 0;
$incidencias_resueltas = 0;

foreach ($incidencias_por_estado as $estado) {
    switch ($estado['estado']) {
        case 'reportada':
            $incidencias_reportadas = $estado['total'];
            break;
        case 'en_revision':
        case 'en_proceso':
            $incidencias_en_revision = $estado['total'];
            break;
        case 'resuelta':
        case 'cerrada':
            $incidencias_resueltas = $estado['total'];
            break;
    }
}

// Obtener notificaciones no leídas
$notificaciones = $db->getRows(
    "SELECT * FROM notificaciones 
     WHERE id_usuario = ? AND leido = 0
     ORDER BY fecha DESC
     LIMIT 5",
    [$_SESSION['usuario_id']]
);

// Contar notificaciones no leídas
$notificaciones_count = count($notificaciones);

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Obtener funcionalidades disponibles
$funcionalidades = obtener_funcionalidades_profesor();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Profesor - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive-tables.css">
</head>

<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"></div>
                <div>Sistema de Gestión</div>
            </div>
            <div class="sidebar-nav">
                <?php echo generar_menu_navegacion('dashboard'); ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Panel de Profesor</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <?php if ($notificaciones_count > 0): ?>
                <div class="card">
                    <h2 class="card-title">Notificaciones</h2>
                    <div class="notification-list">
                        <?php foreach ($notificaciones as $notificacion): ?>
                            <div class="notification-item">
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notificacion['mensaje']); ?>
                                </div>
                                <div class="notification-date">
                                    <?php echo format_date($notificacion['fecha'], true); ?>
                                </div>
                                <a href="marcar_leido.php?id=<?php echo $notificacion['id_notificacion']; ?>" class="mark-read">Marcar como leída</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="notificaciones.php" class="view-all">Ver todas las notificaciones</a>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total de Reservas</div>
                    <div class="stat-value"><?php echo $total_reservas; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Reservas Pendientes</div>
                    <div class="stat-value"><?php echo $reservas_pendientes; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Reservas Confirmadas</div>
                    <div class="stat-value"><?php echo $reservas_confirmadas; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Incidencias Reportadas</div>
                    <div class="stat-value"><?php echo $incidencias_reportadas; ?></div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <div class="card-title">Próximas Reservas</div>
                    <?php if (empty($proximas_reservas)): ?>
                        <p>No tienes próximas reservas programadas.</p>
                        <a href="../reservas/crear.php" class="btn btn-primary" style="margin-top: 15px;">Crear nueva reserva</a>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="responsive-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Recurso</th>
                                        <th scope="col">Fecha Inicio</th>
                                        <th scope="col">Fecha Fin</th>
                                        <th scope="col">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proximas_reservas as $reserva): ?>
                                        <tr>
                                            <td data-label="Recurso"><?php echo htmlspecialchars($reserva['recurso_nombre']); ?></td>
                                            <td data-label="Fecha Inicio"><?php echo format_date($reserva['fecha_inicio'], true); ?></td>
                                            <td data-label="Fecha Fin"><?php echo format_date($reserva['fecha_fin'], true); ?></td>
                                            <td data-label="Estado">
                                                <?php
                                                switch ($reserva['estado']) {
                                                    case 'pendiente':
                                                        echo '<span class="badge badge-warning">Pendiente</span>';
                                                        break;
                                                    case 'confirmada':
                                                        echo '<span class="badge badge-success">Confirmada</span>';
                                                        break;
                                                    default:
                                                        echo ucfirst($reserva['estado']);
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="../reservas/listar.php" class="view-all">Ver todas mis reservas</a>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-title">Incidencias Recientes</div>
                    <?php if (empty($incidencias_recientes)): ?>
                        <p>No hay incidencias reportadas.</p>
                        <a href="../bitacora/gestionar.php" class="btn btn-primary" style="margin-top: 15px;">Reportar incidencia</a>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="responsive-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Título</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col">Fecha Reporte</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incidencias_recientes as $incidencia): ?>
                                        <tr>
                                            <td data-label="Título"><?php echo htmlspecialchars($incidencia['titulo']); ?></td>
                                            <td data-label="Estado">
                                                <?php
                                                switch ($incidencia['estado']) {
                                                    case 'reportada':
                                                        echo '<span class="badge badge-warning">Reportada</span>';
                                                        break;
                                                    case 'en_revision':
                                                        echo '<span class="badge badge-info">En Revisión</span>';
                                                        break;
                                                    case 'en_proceso':
                                                        echo '<span class="badge badge-primary">En Proceso</span>';
                                                        break;
                                                    case 'resuelta':
                                                        echo '<span class="badge badge-success">Resuelta</span>';
                                                        break;
                                                    case 'cerrada':
                                                        echo '<span class="badge badge-secondary">Cerrada</span>';
                                                        break;
                                                    default:
                                                        echo ucfirst($incidencia['estado']);
                                                }
                                                ?>
                                            </td>
                                            <td data-label="Fecha Reporte"><?php echo format_date($incidencia['fecha_reporte'], true); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="../bitacora/gestionar.php" class="view-all">Ver todas las incidencias</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-title">Acciones Rápidas</div>
                <div class="quick-actions">
                    <a href="../reservas/crear.php" class="quick-action">
                        <div class="quick-action-icon">📅</div>
                        <div class="quick-action-label">Nueva Reserva</div>
                    </a>
                    <a href="../reservas/calendario.php" class="quick-action">
                        <div class="quick-action-icon">🗓️</div>
                        <div class="quick-action-label">Ver Calendario</div>
                    </a>
                    <a href="../recursos/listar.php" class="quick-action">
                        <div class="quick-action-icon">📋</div>
                        <div class="quick-action-label">Buscar Recursos</div>
                    </a>
                    <a href="perfil.php" class="quick-action">
                        <div class="quick-action-icon">👤</div>
                        <div class="quick-action-label">Mi Perfil</div>
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-title">Información de Permisos</div>
                <div class="permissions-info">
                    <h3>Funcionalidades Disponibles:</h3>
                    <ul>
                        <li><strong>📋 Recursos:</strong> Solo lectura - Puedes ver los recursos disponibles pero no modificarlos</li>
                        <li><strong>📅 Mis Reservas:</strong> Puedes crear, editar y eliminar tus reservas siempre que el recurso no esté ocupado</li>
                        <li><strong>🗓️ Calendario:</strong> Puedes ver todas las reservas de recursos (tuyas y de otros usuarios)</li>
                        <li><strong>⚠️ Gestionar Incidencias:</strong> Puedes crear incidencias y editarlas solo durante los primeros 5 minutos</li>
                    </ul>
                    
                    <h3>Restricciones:</h3>
                    <ul>
                        <li>No puedes eliminar incidencias</li>
                        <li>No puedes editar reservas que ya han comenzado</li>
                        <li>No puedes crear reservas para recursos ya ocupados</li>
                        <li>No tienes acceso a gestión de usuarios, inventario, mantenimiento o reportes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>