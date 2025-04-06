<?php

/**
 * Listado de Usuarios
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

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Definir variables para filtrado
$filtro_rol = isset($_GET['rol']) ? intval($_GET['rol']) : 0;
$filtro_activo = isset($_GET['activo']) ? $_GET['activo'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Construir la consulta SQL base
$sql = "SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.activo, 
               u.fecha_registro, u.ultimo_login, r.nombre as rol, r.id_rol
        FROM usuarios u
        JOIN roles r ON u.id_rol = r.id_rol";

// Añadir condiciones según filtros
$condiciones = [];
$params = [];

if ($filtro_rol > 0) {
    $condiciones[] = "u.id_rol = ?";
    $params[] = $filtro_rol;
}

if ($filtro_activo !== '') {
    $condiciones[] = "u.activo = ?";
    $params[] = $filtro_activo;
}

if (!empty($busqueda)) {
    $condiciones[] = "(u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Añadir WHERE si hay condiciones
if (!empty($condiciones)) {
    $sql .= " WHERE " . implode(' AND ', $condiciones);
}

// Ordenar los resultados
$sql .= " ORDER BY u.apellido, u.nombre";

// Ejecutar la consulta
$usuarios = $db->getRows($sql, $params);

// Obtener roles para el filtro
$roles = $db->getRows("SELECT id_rol, nombre FROM roles ORDER BY nombre");

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Determinar si el usuario tiene permiso para crear/editar usuarios
$puede_modificar = has_role(ROL_ADMIN);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Usuarios - Sistema de Gestión de Recursos</title>
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
                <h1>Listado de Usuarios</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <form action="" method="GET" class="filtros">
                    <div class="filtro-grupo">
                        <label class="filtro-label">Rol:</label>
                        <select name="rol" class="filtro-select">
                            <option value="0">Todos</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id_rol']; ?>" <?php echo ($filtro_rol == $rol['id_rol']) ? 'selected' : ''; ?>>
                                    <?php echo $rol['nombre']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label class="filtro-label">Estado:</label>
                        <select name="activo" class="filtro-select">
                            <option value="">Todos</option>
                            <option value="1" <?php echo ($filtro_activo === '1') ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo ($filtro_activo === '0') ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label class="filtro-label">Buscar:</label>
                        <input type="text" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" class="filtro-input" placeholder="Nombre, apellido o email">
                    </div>

                    <button type="submit" class="filtro-btn">Filtrar</button>
                    <a href="listar.php" class="filtro-btn btn-reset">Resetear</a>

                    <?php if ($puede_modificar): ?>
                        <a href="crear.php" class="btn-agregar">+ Agregar Usuario</a>
                    <?php endif; ?>
                </form>

                <div class="table-container">
                    <?php if (empty($usuarios)): ?>
                        <div class="empty-state">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM11 15H13V17H11V15ZM11 7H13V13H11V7Z" fill="#6c757d" />
                            </svg>
                            <p>No se encontraron usuarios con los filtros seleccionados.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Fecha Registro</th>
                                    <th>Último Login</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['rol']); ?></td>
                                        <td>
                                            <?php if ($usuario['activo']): ?>
                                                <span class="badge badge-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo format_date($usuario['fecha_registro']); ?></td>
                                        <td><?php echo $usuario['ultimo_login'] ? format_date($usuario['ultimo_login'], true) : 'Nunca'; ?></td>
                                        <td>
                                            <a href="ver.php?id=<?php echo $usuario['id_usuario']; ?>" class="accion-btn btn-editar">Ver</a>

                                            <?php if ($puede_modificar): ?>
                                                <a href="editar.php?id=<?php echo $usuario['id_usuario']; ?>" class="accion-btn btn-editar">Editar</a>

                                                <?php if (has_role(ROL_ADMIN) && $usuario['id_usuario'] != $_SESSION['usuario_id']): ?>
                                                    <?php if ($usuario['activo']): ?>
                                                        <a href="procesar.php?accion=desactivar&id=<?php echo $usuario['id_usuario']; ?>"
                                                            onclick="return confirm('¿Estás seguro de desactivar este usuario?');"
                                                            class="accion-btn btn-eliminar">Desactivar</a>
                                                    <?php else: ?>
                                                        <a href="procesar.php?accion=activar&id=<?php echo $usuario['id_usuario']; ?>"
                                                            class="accion-btn btn-editar">Activar</a>
                                                    <?php endif; ?>
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