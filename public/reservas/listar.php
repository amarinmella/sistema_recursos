<?php

/**
 * Listado de Reservas
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado
require_login();

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Definir variables para filtrado
$filtro_recurso = isset($_GET['recurso']) ? intval($_GET['recurso']) : 0;
$filtro_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

// Construir la consulta SQL base
$sql = "SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado, r.descripcion, 
               r.fecha_creacion, u.nombre, u.apellido, rc.nombre as recurso_nombre,
               rc.id_recurso, u.id_usuario
        FROM reservas r
        JOIN usuarios u ON r.id_usuario = u.id_usuario
        JOIN recursos rc ON r.id_recurso = rc.id_recurso";

// Añadir condiciones según filtros y permisos
$condiciones = [];
$params = [];

// Si no es administrador o académico, solo mostrar las reservas propias
if (!has_role([ROL_ADMIN, ROL_ACADEMICO])) {
    $condiciones[] = "r.id_usuario = ?";
    $params[] = $_SESSION['usuario_id'];
} else if ($filtro_usuario > 0) {
    $condiciones[] = "r.id_usuario = ?";
    $params[] = $filtro_usuario;
}

if ($filtro_recurso > 0) {
    $condiciones[] = "r.id_recurso = ?";
    $params[] = $filtro_recurso;
}

if (!empty($filtro_estado)) {
    $condiciones[] = "r.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($fecha_inicio)) {
    $condiciones[] = "r.fecha_inicio >= ?";
    $params[] = $fecha_inicio . ' 00:00:00';
}

if (!empty($fecha_fin)) {
    $condiciones[] = "r.fecha_fin <= ?";
    $params[] = $fecha_fin . ' 23:59:59';
}

// Añadir WHERE si hay condiciones
if (!empty($condiciones)) {
    $sql .= " WHERE " . implode(' AND ', $condiciones);
}

// Ordenar los resultados
$sql .= " ORDER BY r.fecha_inicio DESC";

// Ejecutar la consulta
$reservas = $db->getRows($sql, $params);

// Obtener lista de recursos para el filtro
$recursos = $db->getRows("SELECT id_recurso, nombre FROM recursos WHERE disponible = 1 ORDER BY nombre");

// Obtener usuarios para el filtro (solo admin y académico)
$usuarios = [];
if (has_role([ROL_ADMIN, ROL_ACADEMICO])) {
    $usuarios = $db->getRows("SELECT id_usuario, nombre, apellido FROM usuarios ORDER BY apellido, nombre");
}

// Estados posibles para el filtro
$estados = ['pendiente', 'confirmada', 'cancelada', 'completada'];

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Determinar si el usuario puede crear reservas
$puede_crear = true; // Todos los usuarios pueden crear reservas

// Determinar si el usuario puede gestionar reservas de otros
$puede_gestionar = has_role([ROL_ADMIN, ROL_ACADEMICO]);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Reservas - Sistema de Gestión de Recursos</title>
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
                <h1>Listado de Reservas</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <form action="" method="GET" class="filtros">
                    <div class="filtro-grupo">
                        <label class="filtro-label">Recurso:</label>
                        <select name="recurso" class="filtro-select">
                            <option value="0">Todos</option>
                            <?php foreach ($recursos as $recurso): ?>
                                <option value="<?php echo $recurso['id_recurso']; ?>" <?php echo ($filtro_recurso == $recurso['id_recurso']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($recurso['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                        <div class="filtro-grupo">
                            <label class="filtro-label">Usuario:</label>
                            <select name="usuario" class="filtro-select">
                                <option value="0">Todos</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?php echo $usuario['id_usuario']; ?>" <?php echo ($filtro_usuario == $usuario['id_usuario']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="filtro-grupo">
                        <label class="filtro-label">Estado:</label>
                        <select name="estado" class="filtro-select">
                            <option value="">Todos</option>
                            <?php foreach ($estados as $estado): ?>
                                <option value="<?php echo $estado; ?>" <?php echo ($filtro_estado == $estado) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($estado); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label class="filtro-label">Desde:</label>
                        <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" class="filtro-input">
                    </div>

                    <div class="filtro-grupo">
                        <label class="filtro-label">Hasta:</label>
                        <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" class="filtro-input">
                    </div>

                    <button type="submit" class="filtro-btn">Filtrar</button>
                    <a href="listar.php" class="filtro-btn btn-reset">Resetear</a>

                    <?php if ($puede_crear): ?>
                        <a href="crear.php" class="btn-agregar">+ Nueva Reserva</a>
                    <?php endif; ?>
                </form>

                <div class="table-container">
                    <?php if (empty($reservas)): ?>
                        <div class="empty-state">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM11 15H13V17H11V15ZM11 7H13V13H11V7Z" fill="#6c757d" />
                            </svg>
                            <p>No se encontraron reservas con los filtros seleccionados.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Recurso</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Fin</th>
                                    <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                                        <th>Usuario</th>
                                    <?php endif; ?>
                                    <th>Estado</th>
                                    <th>Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservas as $reserva): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reserva['recurso_nombre']); ?></td>
                                        <td><?php echo format_date($reserva['fecha_inicio'], true); ?></td>
                                        <td><?php echo format_date($reserva['fecha_fin'], true); ?></td>
                                        <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                                            <td><?php echo htmlspecialchars($reserva['nombre'] . ' ' . $reserva['apellido']); ?></td>
                                        <?php endif; ?>
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
                                        <td><?php echo format_date($reserva['fecha_creacion']); ?></td>
                                        <td>
                                            <a href="ver.php?id=<?php echo $reserva['id_reserva']; ?>" class="accion-btn btn-editar">Ver</a>

                                            <?php
                                            // Mostrar opciones de gestión según permisos y estado de la reserva
                                            $es_propietario = $reserva['id_usuario'] == $_SESSION['usuario_id'];
                                            $puede_editar = ($es_propietario || $puede_gestionar) && in_array($reserva['estado'], ['pendiente', 'confirmada']);
                                            $puede_cancelar = ($es_propietario || $puede_gestionar) && in_array($reserva['estado'], ['pendiente', 'confirmada']);
                                            $puede_confirmar = $puede_gestionar && $reserva['estado'] === 'pendiente';
                                            $puede_completar = $puede_gestionar && $reserva['estado'] === 'confirmada';

                                            if ($puede_editar && strtotime($reserva['fecha_inicio']) > time()):
                                            ?>
                                                <a href="editar.php?id=<?php echo $reserva['id_reserva']; ?>" class="accion-btn btn-editar">Editar</a>
                                            <?php endif; ?>

                                            <?php if ($puede_cancelar && strtotime($reserva['fecha_fin']) > time()): ?>
                                                <a href="procesar.php?accion=cancelar&id=<?php echo $reserva['id_reserva']; ?>"
                                                    onclick="return confirm('¿Estás seguro de cancelar esta reserva?');"
                                                    class="accion-btn btn-eliminar">Cancelar</a>
                                            <?php endif; ?>

                                            <?php if ($puede_confirmar): ?>
                                                <a href="procesar.php?accion=confirmar&id=<?php echo $reserva['id_reserva']; ?>"
                                                    class="accion-btn btn-editar">Confirmar</a>
                                            <?php endif; ?>

                                            <?php if ($puede_completar && strtotime($reserva['fecha_fin']) <= time()): ?>
                                                <a href="procesar.php?accion=completar&id=<?php echo $reserva['id_reserva']; ?>"
                                                    class="accion-btn btn-editar">Completar</a>
                                            <?php endif; ?>
                                        </td>
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
</body>

</html>