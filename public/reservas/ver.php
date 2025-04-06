<?php

/**
 * Ver detalles de una reserva
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
    $_SESSION['error'] = "ID de reserva no especificado";
    redirect('listar.php');
    exit;
}

$id_reserva = intval($_GET['id']);

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Obtener datos de la reserva
$sql = "SELECT r.*, u.nombre as usuario_nombre, u.apellido as usuario_apellido,
               u.email as usuario_email, rc.nombre as recurso_nombre, 
               rc.ubicacion as recurso_ubicacion, rc.estado as recurso_estado,
               t.nombre as tipo_recurso
        FROM reservas r
        JOIN usuarios u ON r.id_usuario = u.id_usuario
        JOIN recursos rc ON r.id_recurso = rc.id_recurso
        JOIN tipos_recursos t ON rc.id_tipo = t.id_tipo
        WHERE r.id_reserva = ?";
$reserva = $db->getRow($sql, [$id_reserva]);

if (!$reserva) {
    $_SESSION['error'] = "La reserva no existe";
    redirect('listar.php');
    exit;
}

// Verificar permisos (solo propietario o admin/académico pueden ver detalles)
$es_propietario = $reserva['id_usuario'] == $_SESSION['usuario_id'];
$es_admin = has_role([ROL_ADMIN, ROL_ACADEMICO]);

if (!$es_propietario && !$es_admin) {
    $_SESSION['error'] = "No tienes permisos para ver esta reserva";
    redirect('listar.php');
    exit;
}

// Obtener notificaciones relacionadas con esta reserva
$notificaciones = $db->getRows(
    "SELECT n.*, u.nombre, u.apellido
     FROM notificaciones n
     JOIN usuarios u ON n.id_usuario = u.id_usuario
     WHERE n.id_reserva = ?
     ORDER BY n.fecha DESC",
    [$id_reserva]
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

// Determinar acciones disponibles según estado y permisos
$puede_editar = ($es_propietario || $es_admin) && in_array($reserva['estado'], ['pendiente', 'confirmada']) && strtotime($reserva['fecha_inicio']) > time();
$puede_cancelar = ($es_propietario || $es_admin) && in_array($reserva['estado'], ['pendiente', 'confirmada']) && strtotime($reserva['fecha_fin']) > time();
$puede_confirmar = $es_admin && $reserva['estado'] === 'pendiente';
$puede_completar = $es_admin && $reserva['estado'] === 'confirmada' && strtotime($reserva['fecha_fin']) <= time();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Reserva - Sistema de Gestión de Recursos</title>
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
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="../usuarios/listar.php" class="nav-item">Usuarios</a>
                <?php endif; ?>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item active">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                    <a href="../reportes/index.php" class="nav-item">Reportes</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Detalles de Reserva</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="form-title">Reserva #<?php echo $reserva['id_reserva']; ?></h2>

                    <div>
                        <?php if ($puede_editar): ?>
                            <a href="editar.php?id=<?php echo $reserva['id_reserva']; ?>" class="btn btn-primary">Editar</a>
                        <?php endif; ?>

                        <?php if ($puede_cancelar): ?>
                            <a href="procesar.php?accion=cancelar&id=<?php echo $reserva['id_reserva']; ?>"
                                onclick="return confirm('¿Estás seguro de cancelar esta reserva?');"
                                class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>

                        <?php if ($puede_confirmar): ?>
                            <a href="procesar.php?accion=confirmar&id=<?php echo $reserva['id_reserva']; ?>"
                                class="btn btn-primary">Confirmar</a>
                        <?php endif; ?>

                        <?php if ($puede_completar): ?>
                            <a href="procesar.php?accion=completar&id=<?php echo $reserva['id_reserva']; ?>"
                                class="btn btn-primary">Marcar como Completada</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <div class="estado-badge" style="margin-bottom: 15px;">
                        <strong>Estado:</strong>
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
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div>
                            <h3>Información de la Reserva</h3>
                            <p><strong>Fecha de inicio:</strong> <?php echo format_date($reserva['fecha_inicio'], true); ?></p>
                            <p><strong>Fecha de fin:</strong> <?php echo format_date($reserva['fecha_fin'], true); ?></p>
                            <p><strong>Fecha de creación:</strong> <?php echo format_date($reserva['fecha_creacion'], true); ?></p>
                            <?php if (!empty($reserva['descripcion'])): ?>
                                <p><strong>Descripción:</strong> <?php echo nl2br(htmlspecialchars($reserva['descripcion'])); ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <h3>Recurso Reservado</h3>
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($reserva['recurso_nombre']); ?></p>
                            <p><strong>Tipo:</strong> <?php echo htmlspecialchars($reserva['tipo_recurso']); ?></p>
                            <p><strong>Ubicación:</strong> <?php echo empty($reserva['recurso_ubicacion']) ? 'No especificada' : htmlspecialchars($reserva['recurso_ubicacion']); ?></p>
                            <p><strong>Estado actual:</strong>
                                <?php
                                switch ($reserva['recurso_estado']) {
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
                                        echo $reserva['recurso_estado'];
                                }
                                ?>
                            </p>
                            <p><a href="../recursos/ver.php?id=<?php echo $reserva['id_recurso']; ?>" class="btn-agregar" style="display: inline-block;">Ver detalles del recurso</a></p>
                        </div>

                        <div>
                            <h3>Usuario</h3>
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($reserva['usuario_nombre'] . ' ' . $reserva['usuario_apellido']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($reserva['usuario_email']); ?></p>
                            <?php if ($es_admin): ?>
                                <p><a href="../usuarios/ver.php?id=<?php echo $reserva['id_usuario']; ?>" class="btn-agregar" style="display: inline-block;">Ver detalles del usuario</a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($notificaciones)): ?>
                <div class="card">
                    <h2 class="card-title">Notificaciones</h2>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Destinatario</th>
                                    <th>Mensaje</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notificaciones as $notificacion): ?>
                                    <tr>
                                        <td><?php echo format_date($notificacion['fecha'], true); ?></td>
                                        <td><?php echo htmlspecialchars($notificacion['nombre'] . ' ' . $notificacion['apellido']); ?></td>
                                        <td><?php echo htmlspecialchars($notificacion['mensaje']); ?></td>
                                        <td><?php echo $notificacion['leido'] ? 'Leída' : 'No leída'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2 class="card-title">Información Adicional</h2>

                <div class="alert alert-info">
                    <strong>Nota:</strong> Las reservas pueden estar en los siguientes estados:
                    <ul style="margin-top: 10px; margin-bottom: 0;">
                        <li><strong>Pendiente:</strong> La reserva ha sido creada pero requiere confirmación.</li>
                        <li><strong>Confirmada:</strong> La reserva ha sido aprobada y el recurso está reservado para el período indicado.</li>
                        <li><strong>Cancelada:</strong> La reserva ha sido cancelada y el recurso está disponible para otras reservas.</li>
                        <li><strong>Completada:</strong> La reserva ha finalizado y el recurso ha sido utilizado según lo previsto.</li>
                    </ul>
                </div>

                <div style="margin-top: 20px;">
                    <a href="listar.php" class="btn btn-secondary">Volver al listado</a>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>