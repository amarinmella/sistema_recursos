<?php

/**
 * Dashboard del Estudiante
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado y sea estudiante
require_login();
if ($_SESSION['usuario_rol'] != ROL_ESTUDIANTE) {
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

// Obtener reservas recientes del estudiante
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
// Total de reservas del estudiante
$total_reservas = $db->getRow(
    "SELECT COUNT(*) as total FROM reservas WHERE id_usuario = ?",
    [$_SESSION['usuario_id']]
)['total'] ?? 0;

// Reservas por estado
$reservas_por_estado = $db->getRows(
    "SELECT estado, COUNT(*) as total FROM reservas WHERE id_usuario = ? GROUP BY estado",
    [$_SESSION['usuario_id']]
);

// Inicializar contadores
$reservas_pendientes = 0;
$reservas_confirmadas = 0;
$reservas_canceladas = 0;
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
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Estudiante - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"></div>
                <div>Sistema de Gestión</div>
            </div>
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">Dashboard</a>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Mis Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="perfil.php" class="nav-item">Mi Perfil</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Panel de Estudiante</h1>
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
                    <div class="stat-title">Reservas Completadas</div>
                    <div class="stat-value"><?php echo $reservas_completadas; ?></div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <div class="card-title">Próximas Reservas</div>
                    <?php if (empty($proximas_reservas)): ?>
                        <p>No tienes próximas reservas programadas.</p>
                        <a href="../reservas/crear.php" class="btn btn-primary" style="margin-top: 15px;">Crear nueva reserva</a>
                    <?php else: ?>
                        <table>
                            <tr>
                                <th>Recurso</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                                <th>Estado</th>
                            </tr>
                            <?php foreach ($proximas_reservas as $reserva): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reserva['recurso_nombre']); ?></td>
                                    <td><?php echo format_date($reserva['fecha_inicio'], true); ?></td>
                                    <td><?php echo format_date($reserva['fecha_fin'], true); ?></td>
                                    <td>
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
                        </table>
                        <a href="../reservas/listar.php" class="view-all">Ver todas mis reservas</a>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-title">Actividad Reciente</div>
                    <?php if (empty($reservas_recientes)): ?>
                        <p>No hay actividad reciente.</p>
                    <?php else: ?>
                        <table>
                            <tr>
                                <th>Recurso</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                            </tr>
                            <?php foreach ($reservas_recientes as $reserva): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reserva['recurso_nombre']); ?></td>
                                    <td><?php echo format_date($reserva['fecha_inicio'], true); ?></td>
                                    <td>
                                        <?php
                                        switch ($reserva['estado']) {
                                            case 'pendiente':
                                                echo '<span class="badge badge-warning">Pendiente</span>';
                                                break;
                                            case 'confirmada':
                                                echo '<span class="badge badge-success">Confirmada</span>';
                                                break;
                                            case 'cancelada':
                                                echo '<span class="badge badge-danger">Cancelada</span>';
                                                break;
                                            case 'completada':
                                                echo '<span class="badge badge-info">Completada</span>';
                                                break;
                                            default:
                                                echo $reserva['estado'];
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-title">Recursos Disponibles</div>

                <div class="resources-list">
                    <?php
                    // Obtener recursos disponibles
                    $recursos_disponibles = $db->getRows(
                        "SELECT r.id_recurso, r.nombre, r.ubicacion, tr.nombre as tipo
                         FROM recursos r
                         JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
                         WHERE r.disponible = 1 AND r.estado = 'disponible'
                         ORDER BY tr.nombre, r.nombre
                         LIMIT 6"
                    );

                    if (empty($recursos_disponibles)):
                    ?>
                        <p>No hay recursos disponibles en este momento.</p>
                    <?php else: ?>
                        <div class="resources-grid">
                            <?php foreach ($recursos_disponibles as $recurso): ?>
                                <div class="resource-item">
                                    <div class="resource-name">
                                        <?php echo htmlspecialchars($recurso['nombre']); ?>
                                    </div>
                                    <div class="resource-details">
                                        <div class="resource-type">
                                            <?php echo htmlspecialchars($recurso['tipo']); ?>
                                        </div>
                                        <?php if (!empty($recurso['ubicacion'])): ?>
                                            <div class="resource-location">
                                                <?php echo htmlspecialchars($recurso['ubicacion']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="../reservas/crear.php?recurso=<?php echo $recurso['id_recurso']; ?>" class="btn btn-primary resource-btn">Reservar</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="../recursos/listar.php" class="view-all">Ver todos los recursos</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>