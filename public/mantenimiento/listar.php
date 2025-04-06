<?php

/**
 * Listado de Mantenimientos
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado
require_login();

// Solo administradores y académicos pueden acceder a mantenimiento
if (!has_role([ROL_ADMIN, ROL_ACADEMICO])) {
    $_SESSION['error'] = "No tienes permisos para acceder al módulo de mantenimiento";
    redirect('../admin/dashboard.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Definir variables para paginación y filtros
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$id_recurso = isset($_GET['recurso']) ? intval($_GET['recurso']) : 0;
$id_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

// Preparar filtros para la consulta
$filtros = [];
$params = [];

// Añadir filtro de recurso
if ($id_recurso > 0) {
    $filtros[] = "m.id_recurso = ?";
    $params[] = $id_recurso;
}

// Añadir filtro de usuario
if ($id_usuario > 0) {
    $filtros[] = "m.id_usuario = ?";
    $params[] = $id_usuario;
}

// Añadir filtro de estado
if (!empty($estado)) {
    $filtros[] = "m.estado = ?";
    $params[] = $estado;
}

// Añadir filtro de fecha inicio (mantenimiento posterior a esta fecha)
if (!empty($fecha_inicio)) {
    $filtros[] = "m.fecha_inicio >= ?";
    $params[] = $fecha_inicio . ' 00:00:00';
}

// Añadir filtro de fecha fin (mantenimiento anterior a esta fecha)
if (!empty($fecha_fin)) {
    $filtros[] = "m.fecha_inicio <= ?";
    $params[] = $fecha_fin . ' 23:59:59';
}

// Construir cláusula WHERE
$where = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Consulta para obtener total de registros
$sql_total = "SELECT COUNT(*) as total FROM mantenimiento m $where";
$resultado_total = $db->getRow($sql_total, $params);
$total_registros = $resultado_total ? $resultado_total['total'] : 0;

// Calcular total de páginas
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Asegurar que la página actual sea válida
if ($pagina_actual < 1) {
    $pagina_actual = 1;
} elseif ($pagina_actual > $total_paginas && $total_paginas > 0) {
    $pagina_actual = $total_paginas;
}

// Consulta para obtener mantenimientos paginados
$sql = "SELECT m.*, 
               r.nombre as recurso_nombre, 
               r.ubicacion as recurso_ubicacion, 
               tr.nombre as tipo_recurso,
               u.nombre as usuario_nombre, 
               u.apellido as usuario_apellido
        FROM mantenimiento m
        JOIN recursos r ON m.id_recurso = r.id_recurso
        JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
        JOIN usuarios u ON m.id_usuario = u.id_usuario
        $where
        ORDER BY m.fecha_registro DESC
        LIMIT ?, ?";

// Añadir parámetros de paginación
$params_paginados = array_merge($params, [$offset, $registros_por_pagina]);

// Ejecutar consulta
$mantenimientos = $db->getRows($sql, $params_paginados);

// Obtener lista de recursos para filtrar
$recursos = $db->getRows(
    "SELECT id_recurso, nombre, ubicacion FROM recursos ORDER BY nombre"
);

// Obtener lista de usuarios para filtrar
$usuarios = $db->getRows(
    "SELECT id_usuario, nombre, apellido FROM usuarios WHERE activo = 1 ORDER BY nombre, apellido"
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

// Función para obtener clase CSS según el estado
function getEstadoClass($estado)
{
    switch ($estado) {
        case 'pendiente':
            return 'badge-warning';
        case 'en progreso':
            return 'badge-primary';
        case 'completado':
            return 'badge-success';
        default:
            return 'badge-secondary';
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimientos - Sistema de Gestión de Recursos</title>
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
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="../mantenimiento/listar.php" class="nav-item active">Mantenimiento</a>
                    <a href="../reportes/reportes_dashboard.php" class="nav-item">Reportes</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Mantenimientos</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 class="card-title">Listado de Mantenimientos</h2>
                    <a href="crear.php" class="btn-agregar">+ Nuevo Mantenimiento</a>
                </div>

                <form action="" method="GET" class="filtros">
                    <div class="filtro-grupo">
                        <label class="filtro-label" for="recurso">Recurso:</label>
                        <select name="recurso" id="recurso" class="filtro-select">
                            <option value="0">Todos los recursos</option>
                            <?php foreach ($recursos as $recurso): ?>
                                <option value="<?php echo $recurso['id_recurso']; ?>" <?php echo ($id_recurso == $recurso['id_recurso']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($recurso['nombre']); ?>
                                    <?php echo !empty($recurso['ubicacion']) ? '(' . htmlspecialchars($recurso['ubicacion']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label class="filtro-label" for="usuario">Responsable:</label>
                        <select name="usuario" id="usuario" class="filtro-select">
                            <option value="0">Todos los responsables</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo $usuario['id_usuario']; ?>" <?php echo ($id_usuario == $usuario['id_usuario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label class="filtro-label" for="estado">Estado:</label>
                        <select name="estado" id="estado" class="filtro-select">
                            <option value="">Todos los estados</option>
                            <option value="pendiente" <?php echo ($estado == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="en progreso" <?php echo ($estado == 'en progreso') ? 'selected' : ''; ?>>En Progreso</option>
                            <option value="completado" <?php echo ($estado == 'completado') ? 'selected' : ''; ?>>Completado</option>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label class="filtro-label" for="fecha_inicio">Desde:</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" class="filtro-input" value="<?php echo $fecha_inicio; ?>">
                    </div>

                    <div class="filtro-grupo">
                        <label class="filtro-label" for="fecha_fin">Hasta:</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" class="filtro-input" value="<?php echo $fecha_fin; ?>">
                    </div>

                    <button type="submit" class="filtro-btn">Filtrar</button>
                    <a href="listar.php" class="filtro-btn btn-reset">Resetear</a>
                </form>

                <?php if (empty($mantenimientos)): ?>
                    <div class="empty-state">
                        <p>No se encontraron registros de mantenimiento.</p>
                        <a href="crear.php" class="btn btn-primary">Crear Nuevo Mantenimiento</a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Recurso</th>
                                    <th>Tipo</th>
                                    <th>Responsable</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Fin</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mantenimientos as $mantenimiento): ?>
                                    <tr>
                                        <td><?php echo $mantenimiento['id_mantenimiento']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($mantenimiento['recurso_nombre']); ?>
                                            <?php if (!empty($mantenimiento['recurso_ubicacion'])): ?>
                                                <br><small>(<?php echo htmlspecialchars($mantenimiento['recurso_ubicacion']); ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($mantenimiento['tipo_recurso']); ?></td>
                                        <td><?php echo htmlspecialchars($mantenimiento['usuario_nombre'] . ' ' . $mantenimiento['usuario_apellido']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($mantenimiento['fecha_inicio'])); ?></td>
                                        <td>
                                            <?php if ($mantenimiento['fecha_fin']): ?>
                                                <?php echo date('d/m/Y H:i', strtotime($mantenimiento['fecha_fin'])); ?>
                                            <?php else: ?>
                                                <span class="badge badge-warning">No finalizado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getEstadoClass($mantenimiento['estado']); ?>">
                                                <?php echo ucfirst($mantenimiento['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="ver.php?id=<?php echo $mantenimiento['id_mantenimiento']; ?>" class="accion-btn btn-editar" title="Ver detalles">
                                                Ver
                                            </a>
                                            <a href="editar.php?id=<?php echo $mantenimiento['id_mantenimiento']; ?>" class="accion-btn btn-editar" title="Editar mantenimiento">
                                                Editar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_paginas > 1): ?>
                        <div class="pagination" style="margin-top: 20px; text-align: center;">
                            <?php if ($pagina_actual > 1): ?>
                                <a href="?pagina=<?php echo $pagina_actual - 1; ?>&recurso=<?php echo $id_recurso; ?>&usuario=<?php echo $id_usuario; ?>&estado=<?php echo $estado; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>" class="btn btn-secondary" style="margin-right: 10px;">&laquo; Anterior</a>
                            <?php endif; ?>

                            <span style="margin: 0 10px;">
                                Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                            </span>

                            <?php if ($pagina_actual < $total_paginas): ?>
                                <a href="?pagina=<?php echo $pagina_actual + 1; ?>&recurso=<?php echo $id_recurso; ?>&usuario=<?php echo $id_usuario; ?>&estado=<?php echo $estado; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>" class="btn btn-secondary" style="margin-left: 10px;">Siguiente &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">Resumen de Mantenimientos</h2>
                <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 15px;">
                    <?php
                    // Obtener conteo de mantenimientos por estado
                    $sql_estados = "SELECT estado, COUNT(*) as total FROM mantenimiento GROUP BY estado";
                    $estados = $db->getRows($sql_estados);

                    $total_pendientes = 0;
                    $total_en_progreso = 0;
                    $total_completados = 0;

                    foreach ($estados as $estado_item) {
                        if ($estado_item['estado'] == 'pendiente') {
                            $total_pendientes = $estado_item['total'];
                        } elseif ($estado_item['estado'] == 'en progreso') {
                            $total_en_progreso = $estado_item['total'];
                        } elseif ($estado_item['estado'] == 'completado') {
                            $total_completados = $estado_item['total'];
                        }
                    }
                    ?>

                    <div style="flex: 1; min-width: 200px; background-color: rgba(255, 193, 7, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 600; color: #ffc107;"><?php echo $total_pendientes; ?></div>
                        <div style="font-size: 14px; color: var(--dark-color);">Pendientes</div>
                    </div>

                    <div style="flex: 1; min-width: 200px; background-color: rgba(74, 144, 226, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 600; color: #4a90e2;"><?php echo $total_en_progreso; ?></div>
                        <div style="font-size: 14px; color: var(--dark-color);">En Progreso</div>
                    </div>

                    <div style="flex: 1; min-width: 200px; background-color: rgba(40, 167, 69, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 600; color: #28a745;"><?php echo $total_completados; ?></div>
                        <div style="font-size: 14px; color: var(--dark-color);">Completados</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>