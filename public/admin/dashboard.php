<?php

/**
 * Dashboard del Administrador
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario sea administrador
require_login();
if ($_SESSION['usuario_rol'] != ROL_ADMIN) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Obtener estadísticas
$db = Database::getInstance();

// Total de usuarios
$total_usuarios = $db->getRow("SELECT COUNT(*) as total FROM usuarios")['total'] ?? 0;

// Total de recursos
$total_recursos = $db->getRow("SELECT COUNT(*) as total FROM recursos")['total'] ?? 0;

// Recursos por estado
$recursos_estado = $db->getRows("
    SELECT estado, COUNT(*) as total 
    FROM recursos 
    GROUP BY estado
");

// Reservas recientes
$reservas_recientes = $db->getRows("
    SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado,
           u.nombre, u.apellido, rc.nombre as recurso
    FROM reservas r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    JOIN recursos rc ON r.id_recurso = rc.id_recurso
    ORDER BY r.fecha_creacion DESC
    LIMIT 5
");

// Mantenimientos activos
$mantenimientos_activos = $db->getRows("
    SELECT m.id_mantenimiento, m.fecha_inicio, m.estado,
           u.nombre, u.apellido, r.nombre as recurso
    FROM mantenimiento m
    JOIN usuarios u ON m.id_usuario = u.id_usuario
    JOIN recursos r ON m.id_recurso = r.id_recurso
    WHERE m.estado IN ('pendiente', 'en progreso')
    ORDER BY m.fecha_inicio ASC
    LIMIT 5
");

// Usuarios recientes
$usuarios_recientes = $db->getRows("
    SELECT id_usuario, nombre, apellido, email, id_rol, fecha_registro
    FROM usuarios
    ORDER BY fecha_registro DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Sistema de Gestión de Recursos</title>
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
                <a href="../usuarios/listar.php" class="nav-item">Usuarios</a>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                <a href="../reportes/reportes_dashboard.php" class="nav-item">Reportes</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Panel de Administración</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total de Usuarios</div>
                    <div class="stat-value"><?php echo $total_usuarios; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Total de Recursos</div>
                    <div class="stat-value"><?php echo $total_recursos; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Recursos Disponibles</div>
                    <div class="stat-value">
                        <?php
                        $disponibles = 0;
                        foreach ($recursos_estado as $recurso) {
                            if ($recurso['estado'] == 'disponible') {
                                $disponibles = $recurso['total'];
                                break;
                            }
                        }
                        echo $disponibles;
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">En Mantenimiento</div>
                    <div class="stat-value">
                        <?php
                        $en_mantenimiento = 0;
                        foreach ($recursos_estado as $recurso) {
                            if ($recurso['estado'] == 'mantenimiento') {
                                $en_mantenimiento = $recurso['total'];
                                break;
                            }
                        }
                        echo $en_mantenimiento;
                        ?>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <div class="card-title">Reservas Recientes</div>
                    <?php if (empty($reservas_recientes)): ?>
                        <p>No hay reservas recientes.</p>
                    <?php else: ?>
                        <table>
                            <tr>
                                <th>Usuario</th>
                                <th>Recurso</th>
                                <th>Fecha Inicio</th>
                                <th>Estado</th>
                            </tr>
                            <?php foreach ($reservas_recientes as $reserva): ?>
                                <tr>
                                    <td><?php echo $reserva['nombre'] . ' ' . $reserva['apellido']; ?></td>
                                    <td><?php echo $reserva['recurso']; ?></td>
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
                        <a href="../reservas/listar.php" class="view-all">Ver todas las reservas</a>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-title">Mantenimientos Activos</div>
                    <?php if (empty($mantenimientos_activos)): ?>
                        <p>No hay mantenimientos activos.</p>
                    <?php else: ?>
                        <table>
                            <tr>
                                <th>Recurso</th>
                                <th>Responsable</th>
                                <th>Fecha Inicio</th>
                                <th>Estado</th>
                            </tr>
                            <?php foreach ($mantenimientos_activos as $mantenimiento): ?>
                                <tr>
                                    <td><?php echo $mantenimiento['recurso']; ?></td>
                                    <td><?php echo $mantenimiento['nombre'] . ' ' . $mantenimiento['apellido']; ?></td>
                                    <td><?php echo format_date($mantenimiento['fecha_inicio'], true); ?></td>
                                    <td>
                                        <?php
                                        switch ($mantenimiento['estado']) {
                                            case 'pendiente':
                                                echo '<span class="badge badge-warning">Pendiente</span>';
                                                break;
                                            case 'en progreso':
                                                echo '<span class="badge badge-info">En Progreso</span>';
                                                break;
                                            case 'completado':
                                                echo '<span class="badge badge-success">Completado</span>';
                                                break;
                                            default:
                                                echo $mantenimiento['estado'];
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        <a href="../mantenimiento/listar.php" class="view-all">Ver todos los mantenimientos</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-title">Usuarios Recientes</div>
                <?php if (empty($usuarios_recientes)): ?>
                    <p>No hay usuarios recientes.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Registro</th>
                        </tr>
                        <?php foreach ($usuarios_recientes as $usuario): ?>
                            <tr>
                                <td><?php echo $usuario['nombre'] . ' ' . $usuario['apellido']; ?></td>
                                <td><?php echo $usuario['email']; ?></td>
                                <td><?php echo nombre_rol($usuario['id_rol']); ?></td>
                                <td><?php echo format_date($usuario['fecha_registro']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <a href="../usuarios/listar.php" class="view-all">Ver todos los usuarios</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>