<?php

/**
 * Listado de Recursos
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
$filtro_tipo = isset($_GET['tipo']) ? intval($_GET['tipo']) : 0;
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Construir la consulta SQL base
$sql = "SELECT r.id_recurso, r.nombre, r.estado, r.ubicacion, r.disponible, 
               r.fecha_alta, t.nombre as tipo, t.id_tipo
        FROM recursos r
        JOIN tipos_recursos t ON r.id_tipo = t.id_tipo";

// Añadir condiciones según filtros
$condiciones = [];
$params = [];

if ($filtro_tipo > 0) {
    $condiciones[] = "r.id_tipo = ?";
    $params[] = $filtro_tipo;
}

if (!empty($filtro_estado)) {
    $condiciones[] = "r.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($busqueda)) {
    $condiciones[] = "(r.nombre LIKE ? OR r.ubicacion LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Añadir WHERE si hay condiciones
if (!empty($condiciones)) {
    $sql .= " WHERE " . implode(' AND ', $condiciones);
}

// Ordenar los resultados
$sql .= " ORDER BY r.nombre ASC";

// Ejecutar la consulta
$recursos = $db->getRows($sql, $params);

// Obtener tipos de recursos para el filtro
$tipos = $db->getRows("SELECT id_tipo, nombre FROM tipos_recursos ORDER BY nombre");

// Estados posibles para el filtro
$estados = ['disponible', 'mantenimiento', 'dañado', 'baja'];

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Determinar si el usuario tiene permiso para crear/editar recursos
$puede_modificar = has_role([ROL_ADMIN, ROL_ACADEMICO]);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Recursos - Sistema de Gestión de Recursos</title>
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
                <h1>Listado de Recursos</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <form action="" method="GET" class="filtros">
                    <div class="filtro-grupo">
                        <label class="filtro-label">Tipo:</label>
                        <select name="tipo" class="filtro-select">
                            <option value="0">Todos</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?php echo $tipo['id_tipo']; ?>" <?php echo ($filtro_tipo == $tipo['id_tipo']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

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
                        <label class="filtro-label">Buscar:</label>
                        <input type="text" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" class="filtro-input" placeholder="Nombre o ubicación">
                    </div>

                    <button type="submit" class="filtro-btn">Filtrar</button>
                    <a href="listar.php" class="filtro-btn btn-reset">Resetear</a>

                    <?php if ($puede_modificar): ?>
                        <a href="crear.php" class="btn-agregar">+ Agregar Recurso</a>
                    <?php endif; ?>
                </form>

                <div class="table-container">
                    <?php if (empty($recursos)): ?>
                        <div class="empty-state">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM11 15H13V17H11V15ZM11 7H13V13H11V7Z" fill="#6c757d" />
                            </svg>
                            <p>No se encontraron recursos con los filtros seleccionados.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Ubicación</th>
                                    <th>Estado</th>
                                    <th>Disponible</th>
                                    <th>Fecha Alta</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recursos as $recurso): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($recurso['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($recurso['tipo']); ?></td>
                                        <td><?php echo htmlspecialchars($recurso['ubicacion']); ?></td>
                                        <td>
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
                                        </td>
                                        <td>
                                            <?php echo $recurso['disponible'] ? 'Sí' : 'No'; ?>
                                        </td>
                                        <td><?php echo format_date($recurso['fecha_alta']); ?></td>
                                        <td>
                                            <a href="ver.php?id=<?php echo $recurso['id_recurso']; ?>" class="accion-btn btn-editar">Ver</a>

                                            <?php if ($puede_modificar): ?>
                                                <a href="editar.php?id=<?php echo $recurso['id_recurso']; ?>" class="accion-btn btn-editar">Editar</a>

                                                <?php if (has_role(ROL_ADMIN)): ?>
                                                    <a href="procesar.php?accion=eliminar&id=<?php echo $recurso['id_recurso']; ?>"
                                                        onclick="return confirm('¿Estás seguro de eliminar este recurso?');"
                                                        class="accion-btn btn-eliminar">Eliminar</a>
                                                <?php endif; ?>
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