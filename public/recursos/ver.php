<?php

/**
 * Ver detalles de un recurso
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado
require_login();

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de recurso no especificado";
    redirect('listar.php');
    exit;
}

$id_recurso = intval($_GET['id']);

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Obtener datos del recurso
$sql = "SELECT r.*, t.nombre as tipo_nombre 
        FROM recursos r
        JOIN tipos_recursos t ON r.id_tipo = t.id_tipo
        WHERE r.id_recurso = ?";
$recurso = $db->getRow($sql, [$id_recurso]);

if (!$recurso) {
    $_SESSION['error'] = "El recurso no existe";
    redirect('listar.php');
    exit;
}

// Verificar si hay reservas actuales para este recurso
$reservas_actuales = $db->getRows(
    "SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado, r.descripcion,
            u.nombre as usuario_nombre, u.apellido as usuario_apellido
     FROM reservas r
     JOIN usuarios u ON r.id_usuario = u.id_usuario
     WHERE r.id_recurso = ? AND r.estado IN ('pendiente', 'confirmada') AND r.fecha_fin >= NOW()
     ORDER BY r.fecha_inicio ASC",
    [$id_recurso]
);

// Verificar si hay mantenimientos registrados para este recurso
$mantenimientos = $db->getRows(
    "SELECT m.*, u.nombre as usuario_nombre, u.apellido as usuario_apellido
     FROM mantenimiento m
     JOIN usuarios u ON m.id_usuario = u.id_usuario
     WHERE m.id_recurso = ?
     ORDER BY m.fecha_inicio DESC
     LIMIT 5",
    [$id_recurso]
);

// Determinar si el usuario tiene permiso para editar el recurso
$puede_editar = has_role([ROL_ADMIN, ROL_ACADEMICO]);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Recurso - Sistema de Gestión de Recursos</title>
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
                <a href="../admin/dashboard.php" class="nav-item">Dashboard</a>
                <a href="../usuarios/listar.php" class="nav-item">Usuarios</a>
                <a href="../recursos/listar.php" class="nav-item active">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                <a href="../reportes/index.php" class="nav-item">Reportes</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Detalles del Recurso</h1>
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

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="form-title"><?php echo htmlspecialchars($recurso['nombre']); ?></h2>

                    <?php if ($puede_editar): ?>
                        <div>
                            <a href="editar.php?id=<?php echo $recurso['id_recurso']; ?>" class="btn btn-primary">Editar Recurso</a>

                            <?php if (has_role(ROL_ADMIN)): ?>
                                <a href="procesar.php?accion=eliminar&id=<?php echo $recurso['id_recurso']; ?>"
                                    onclick="return confirm('¿Estás seguro de eliminar este recurso?');"
                                    class="btn btn-secondary">Eliminar</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                    <div>
                        <p><strong>Tipo:</strong> <?php echo htmlspecialchars($recurso['tipo_nombre']); ?></p>
                        <p><strong>Estado:</strong>
                            <?php
                            switch ($recurso['estado']) {
                                case 'disponible':
                                    echo '<span class="badge badge-success">Disponible</span>';
                                    break;
                                case 'mantenimiento':
                                    echo '<span class="badge badge-warning">Mantenimiento</span>';
                                    break;
                                case 'dañado':
                                    echo '<span class="badge badge-danger">Dañado</span>';
                                    break;
                                case 'baja':
                                    echo '<span class="badge badge-secondary">Baja</span>';
                                    break;
                                default:
                                    echo $recurso['estado'];
                            }
                            ?>
                        </p>
                        <p><strong>Disponible para reservas:</strong> <?php echo $recurso['disponible'] ? 'Sí' : 'No'; ?></p>
                    </div>

                    <div>
                        <p><strong>Ubicación:</strong> <?php echo empty($recurso['ubicacion']) ? 'No especificada' : htmlspecialchars($recurso['ubicacion']); ?></p>
                        <p><strong>Fecha de alta:</strong> <?php echo format_date($recurso['fecha_alta']); ?></p>
                    </div>
                </div>

                <?php if (!empty($recurso['descripcion'])): ?>
                    <div style="margin-top: 20px;">
                        <h3>Descripción</h3>
                        <p><?php echo nl2br(htmlspecialchars($recurso['descripcion'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">Reservas Actuales</h2>

                <?php if (empty($reservas_actuales)): ?>
                    <p>No hay reservas actuales para este recurso.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Fin</th>
                                    <th>Estado</th>
                                    <th>Descripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservas_actuales as $reserva): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reserva['usuario_nombre'] . ' ' . $reserva['usuario_apellido']); ?></td>
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
                                                    echo $reserva['estado'];
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo empty($reserva['descripcion']) ? '-' : htmlspecialchars($reserva['descripcion']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 20px;">
                    <a href="../reservas/crear.php?recurso=<?php echo $recurso['id_recurso']; ?>" class="btn btn-primary">Reservar este recurso</a>
                    <a href="../reservas/listar.php?recurso=<?php echo $recurso['id_recurso']; ?>" class="btn btn-secondary">Ver todas las reservas</a>
                </div>
            </div>

            <?php if (!empty($mantenimientos)): ?>
                <div class="card">
                    <h2 class="card-title">Historial de Mantenimiento</h2>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Fin</th>
                                    <th>Responsable</th>
                                    <th>Estado</th>
                                    <th>Descripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mantenimientos as $mantenimiento): ?>
                                    <tr>
                                        <td><?php echo format_date($mantenimiento['fecha_inicio'], true); ?></td>
                                        <td><?php echo $mantenimiento['fecha_fin'] ? format_date($mantenimiento['fecha_fin'], true) : 'En progreso'; ?></td>
                                        <td><?php echo htmlspecialchars($mantenimiento['usuario_nombre'] . ' ' . $mantenimiento['usuario_apellido']); ?></td>
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
                                        <td><?php echo nl2br(htmlspecialchars($mantenimiento['descripcion'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 20px;">
                        <a href="../mantenimiento/registrar.php?recurso=<?php echo $recurso['id_recurso']; ?>" class="btn btn-primary">Registrar mantenimiento</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>