<?php

/**
 * Ver detalles de un usuario
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado y tenga permisos
require_login();
if (!has_role([ROL_ADMIN, ROL_ACADEMICO])) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de usuario no especificado";
    redirect('listar.php');
    exit;
}

$id_usuario = intval($_GET['id']);

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Obtener datos del usuario
$sql = "SELECT u.*, r.nombre as rol_nombre 
        FROM usuarios u
        JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.id_usuario = ?";
$usuario = $db->getRow($sql, [$id_usuario]);

if (!$usuario) {
    $_SESSION['error'] = "El usuario no existe";
    redirect('listar.php');
    exit;
}

// Obtener estadísticas del usuario
$estadisticas = [];

// Total de reservas realizadas
$sql_reservas = "SELECT COUNT(*) as total FROM reservas WHERE id_usuario = ?";
$resultado = $db->getRow($sql_reservas, [$id_usuario]);
$estadisticas['total_reservas'] = $resultado ? $resultado['total'] : 0;

// Reservas por estado
$sql_estados = "SELECT estado, COUNT(*) as total FROM reservas WHERE id_usuario = ? GROUP BY estado";
$reservas_por_estado = $db->getRows($sql_estados, [$id_usuario]);

// Inicializar contadores
$estadisticas['reservas_pendientes'] = 0;
$estadisticas['reservas_confirmadas'] = 0;
$estadisticas['reservas_canceladas'] = 0;
$estadisticas['reservas_completadas'] = 0;

foreach ($reservas_por_estado as $estado) {
    switch ($estado['estado']) {
        case 'pendiente':
            $estadisticas['reservas_pendientes'] = $estado['total'];
            break;
        case 'confirmada':
            $estadisticas['reservas_confirmadas'] = $estado['total'];
            break;
        case 'cancelada':
            $estadisticas['reservas_canceladas'] = $estado['total'];
            break;
        case 'completada':
            $estadisticas['reservas_completadas'] = $estado['total'];
            break;
    }
}

// Obtener últimas reservas
$sql_ultimas_reservas = "SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado, 
                                 rc.nombre as recurso_nombre
                          FROM reservas r
                          JOIN recursos rc ON r.id_recurso = rc.id_recurso
                          WHERE r.id_usuario = ?
                          ORDER BY r.fecha_creacion DESC
                          LIMIT 5";
$ultimas_reservas = $db->getRows($sql_ultimas_reservas, [$id_usuario]);

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Determinar si el usuario tiene permiso para editar usuarios
$puede_editar = has_role(ROL_ADMIN);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Usuario - Sistema de Gestión de Recursos</title>
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
                <a href="../usuarios/listar.php" class="nav-item active">Usuarios</a>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                <a href="../reportes/index.php" class="nav-item">Reportes</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Detalles de Usuario</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="form-title"><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></h2>

                    <div>
                        <?php if ($puede_editar): ?>
                            <a href="editar.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-primary">Editar Usuario</a>

                            <?php if ($usuario['id_usuario'] != $_SESSION['usuario_id']): ?>
                                <?php if ($usuario['activo']): ?>
                                    <a href="procesar.php?accion=desactivar&id=<?php echo $usuario['id_usuario']; ?>"
                                        onclick="return confirm('¿Estás seguro de desactivar este usuario?');"
                                        class="btn btn-secondary">Desactivar</a>
                                <?php else: ?>
                                    <a href="procesar.php?accion=activar&id=<?php echo $usuario['id_usuario']; ?>"
                                        class="btn btn-primary">Activar</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                    <div>
                        <h3>Información Personal</h3>
                        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre']); ?></p>
                        <p><strong>Apellido:</strong> <?php echo htmlspecialchars($usuario['apellido']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
                        <p><strong>Rol:</strong> <?php echo htmlspecialchars($usuario['rol_nombre']); ?></p>
                        <p>
                            <strong>Estado:</strong>
                            <?php if ($usuario['activo']): ?>
                                <span class="badge badge-success">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactivo</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div>
                        <h3>Información de Cuenta</h3>
                        <p><strong>Fecha de registro:</strong> <?php echo format_date($usuario['fecha_registro']); ?></p>
                        <p><strong>Último acceso:</strong> <?php echo $usuario['ultimo_login'] ? format_date($usuario['ultimo_login'], true) : 'Nunca'; ?></p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Estadísticas de Uso</h2>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-title">Total de Reservas</div>
                        <div class="stat-value"><?php echo $estadisticas['total_reservas']; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Pendientes</div>
                        <div class="stat-value"><?php echo $estadisticas['reservas_pendientes']; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Confirmadas</div>
                        <div class="stat-value"><?php echo $estadisticas['reservas_confirmadas']; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Completadas</div>
                        <div class="stat-value"><?php echo $estadisticas['reservas_completadas']; ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($ultimas_reservas)): ?>
                <div class="card">
                    <h2 class="card-title">Últimas Reservas</h2>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Recurso</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Fin</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimas_reservas as $reserva): ?>
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
                                        <td>
                                            <a href="../reservas/ver.php?id=<?php echo $reserva['id_reserva']; ?>" class="accion-btn btn-editar">Ver</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div style="margin-top: 20px;">
                <a href="listar.php" class="btn btn-secondary">Volver al listado</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>