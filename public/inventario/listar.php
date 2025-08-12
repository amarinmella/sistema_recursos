<?php

/**
 * Listado de Inventario
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/permissions.php';

// Verificar que el usuario esté logueado y tenga permisos
require_login();
if (!has_role([ROL_ADMIN, ROL_ACADEMICO])) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Obtener notificaciones no leídas
$notificaciones_no_leidas = $db->getRow("
    SELECT COUNT(*) as total
    FROM notificaciones_incidencias
    WHERE id_usuario_destino = ? AND leida = 0
", [$_SESSION['usuario_id']])['total'] ?? 0;

// Definir variables para filtrado
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$filtro_ubicacion = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Construir la consulta SQL base
$sql = "SELECT i.id_item, i.nombre, i.descripcion, i.cantidad, i.precio_unitario, 
               i.estado, i.fecha_adquisicion, i.proveedor, i.ubicacion, i.categoria
        FROM inventario i";

// Añadir condiciones según filtros
$condiciones = [];
$params = [];

if (!empty($filtro_categoria)) {
    $condiciones[] = "i.categoria = ?";
    $params[] = $filtro_categoria;
}

if (!empty($filtro_ubicacion)) {
    $condiciones[] = "i.ubicacion = ?";
    $params[] = $filtro_ubicacion;
}

if (!empty($filtro_estado)) {
    $condiciones[] = "i.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($busqueda)) {
    $condiciones[] = "(i.nombre LIKE ? OR i.descripcion LIKE ? OR i.proveedor LIKE ? OR i.ubicacion LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Añadir WHERE si hay condiciones
if (!empty($condiciones)) {
    $sql .= " WHERE " . implode(' AND ', $condiciones);
}

// Ordenar los resultados
$sql .= " ORDER BY i.nombre ASC";

// Ejecutar la consulta
$inventario = $db->getRows($sql, $params);

// Obtener categorías para el filtro
$categorias = $db->getRows("SELECT DISTINCT categoria FROM inventario WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");

// Obtener ubicaciones para el filtro
$ubicaciones = $db->getRows("SELECT DISTINCT ubicacion FROM inventario WHERE ubicacion IS NOT NULL AND ubicacion != '' ORDER BY ubicacion");

// Estados posibles para el filtro
$estados = ['disponible', 'agotado', 'mantenimiento', 'baja'];

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
    <title>Inventario - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive-tables.css">
    <style>
        .inventario-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            min-width: 600px;
        }
        
        .inventario-table th,
        .inventario-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .inventario-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        .inventario-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-disponible {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-agotado {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-mantenimiento {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-baja {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .accion-btn {
            display: inline-block;
            padding: 6px 12px;
            margin: 2px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-ver {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-editar {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-eliminar {
            background-color: #dc3545;
            color: white;
        }
        
        .filtros {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filtro-grupo {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 150px;
            flex: 1;
        }
        
        .filtro-label {
            font-weight: 500;
            font-size: 14px;
        }
        
        .filtro-select,
        .filtro-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filtro-btn {
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
            white-space: nowrap;
        }
        
        .btn-reset {
            background-color: #6c757d;
        }
        
        .btn-agregar {
            background-color: #28a745;
            margin-left: auto;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state svg {
            margin-bottom: 20px;
        }
        
        /* Estilos responsivos específicos */
        @media (max-width: 1200px) {
            .filtros {
                gap: 12px;
            }
            
            .filtro-grupo {
                min-width: 140px;
            }
        }
        
        @media (max-width: 992px) {
            .filtros {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            
            .filtro-grupo {
                min-width: auto;
            }
            
            .btn-agregar {
                margin-left: 0;
                margin-top: 10px;
            }
            
            .inventario-table {
                font-size: 14px;
            }
            
            .inventario-table th,
            .inventario-table td {
                padding: 10px 8px;
            }
            
            .accion-btn {
                padding: 5px 10px;
                font-size: 11px;
                margin: 1px;
            }
        }
        
        @media (max-width: 768px) {
            .filtros {
                gap: 10px;
            }
            
            .filtro-label {
                font-size: 13px;
            }
            
            .filtro-select,
            .filtro-input {
                padding: 10px 12px;
                font-size: 16px; /* Evita zoom en iOS */
            }
            
            .filtro-btn {
                padding: 10px 16px;
                font-size: 16px;
            }
            
            .inventario-table {
                font-size: 13px;
                min-width: 500px;
            }
            
            .inventario-table th,
            .inventario-table td {
                padding: 8px 6px;
            }
            
            .accion-btn {
                padding: 4px 8px;
                font-size: 10px;
                margin: 1px;
            }
            
            .badge {
                font-size: 11px;
                padding: 3px 6px;
            }
            
            .empty-state {
                padding: 30px 15px;
            }
            
            .empty-state svg {
                width: 48px;
                height: 48px;
            }
        }
        
        @media (max-width: 480px) {
            .filtros {
                gap: 8px;
            }
            
            .filtro-grupo {
                gap: 4px;
            }
            
            .filtro-label {
                font-size: 12px;
            }
            
            .filtro-select,
            .filtro-input {
                padding: 8px 10px;
                font-size: 14px;
            }
            
            .filtro-btn {
                padding: 8px 12px;
                font-size: 14px;
            }
            
            .inventario-table {
                font-size: 12px;
                min-width: 400px;
            }
            
            .inventario-table th,
            .inventario-table td {
                padding: 6px 4px;
            }
            
            .accion-btn {
                padding: 3px 6px;
                font-size: 9px;
                margin: 1px;
            }
            
            .badge {
                font-size: 10px;
                padding: 2px 5px;
            }
            
            .empty-state {
                padding: 20px 10px;
            }
            
            .empty-state svg {
                width: 40px;
                height: 40px;
            }
            
            .empty-state p {
                font-size: 14px;
            }
        }
        
        /* Mejoras para dispositivos táctiles */
        @media (hover: none) and (pointer: coarse) {
            .filtro-select,
            .filtro-input,
            .filtro-btn {
                min-height: 44px;
            }
            
            .accion-btn {
                min-height: 36px;
                min-width: 36px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        }
        
        /* Mejoras de accesibilidad */
        .inventario-table th {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .inventario-table td[data-label]::before {
            content: attr(data-label) ": ";
            font-weight: bold;
            display: none;
        }
        
        @media (max-width: 600px) {
            .inventario-table,
            .inventario-table thead,
            .inventario-table tbody,
            .inventario-table th,
            .inventario-table td,
            .inventario-table tr {
                display: block;
            }
            
            .inventario-table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            .inventario-table tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
                padding: 10px;
                border-radius: 4px;
            }
            
            .inventario-table td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: left;
            }
            
            .inventario-table td::before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
                display: block;
            }
            
            .inventario-table td:last-child {
                border-bottom: 0;
            }
        }
        
        /* Estados de carga */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .loading::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"></div>
                <div>Sistema de Gestión</div>
            </div>
            <div class="sidebar-nav">
                <?php echo generar_menu_navegacion('inventario'); ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Gestión de Inventario</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <form action="" method="GET" class="filtros">
                    <div class="filtro-grupo">
                        <label class="filtro-label">Categoría:</label>
                        <select name="categoria" class="filtro-select">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo htmlspecialchars($categoria['categoria']); ?>" <?php echo ($filtro_categoria == $categoria['categoria']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['categoria']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label class="filtro-label">Ubicación:</label>
                        <select name="ubicacion" class="filtro-select">
                            <option value="">Todas</option>
                            <?php foreach ($ubicaciones as $ubicacion): ?>
                                <option value="<?php echo htmlspecialchars($ubicacion['ubicacion']); ?>" <?php echo ($filtro_ubicacion == $ubicacion['ubicacion']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ubicacion['ubicacion']); ?>
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
                        <input type="text" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" class="filtro-input" placeholder="Nombre, descripción, proveedor o ubicación">
                    </div>

                    <button type="submit" class="filtro-btn">Filtrar</button>
                    <a href="listar.php" class="filtro-btn btn-reset">Resetear</a>

                    <a href="crear.php" class="filtro-btn btn-agregar">+ Agregar Item</a>
                </form>

                <div class="table-container">
                    <?php if (empty($inventario)): ?>
                        <div class="table-empty">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM11 15H13V17H11V15ZM11 7H13V13H11V7Z" fill="#6c757d" />
                            </svg>
                            <h3>No se encontraron items</h3>
                            <p>No se encontraron items en el inventario con los filtros seleccionados.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="responsive-table inventario-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Nombre</th>
                                        <th scope="col">Categoría</th>
                                        <th scope="col">Cantidad</th>
                                        <th scope="col">Precio Unit.</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col">Ubicación</th>
                                        <th scope="col">Proveedor</th>
                                        <th scope="col">Fecha Adquisición</th>
                                        <th scope="col">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventario as $item): ?>
                                        <tr>
                                            <td data-label="Nombre">
                                                <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                                                <?php if (!empty($item['descripcion'])): ?>
                                                    <br><small style="color: #6c757d;"><?php echo htmlspecialchars(substr($item['descripcion'], 0, 50)) . (strlen($item['descripcion']) > 50 ? '...' : ''); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Categoría"><?php echo htmlspecialchars($item['categoria'] ?: 'Sin categoría'); ?></td>
                                            <td data-label="Cantidad">
                                                <strong><?php echo $item['cantidad']; ?></strong>
                                            </td>
                                            <td data-label="Precio Unit.">
                                                $<?php echo number_format($item['precio_unitario'], 2); ?>
                                            </td>
                                            <td data-label="Estado">
                                                <?php
                                                $estado = $item['estado'];
                                                $badgeClass = 'badge-' . $estado;
                                                echo '<span class="badge ' . $badgeClass . '">' . ucfirst($estado) . '</span>';
                                                ?>
                                            </td>
                                            <td data-label="Ubicación"><?php echo htmlspecialchars($item['ubicacion'] ?: 'No especificada'); ?></td>
                                            <td data-label="Proveedor"><?php echo htmlspecialchars($item['proveedor'] ?: 'No especificado'); ?></td>
                                            <td data-label="Fecha Adquisición"><?php echo format_date($item['fecha_adquisicion']); ?></td>
                                            <td data-label="Acciones">
                                                <div class="table-actions">
                                                    <a href="ver.php?id=<?php echo $item['id_item']; ?>" class="table-action-btn primary">Ver</a>
                                                    <a href="editar.php?id=<?php echo $item['id_item']; ?>" class="table-action-btn warning">Editar</a>
                                                    <a href="procesar.php?accion=eliminar&id=<?php echo $item['id_item']; ?>"
                                                        onclick="return confirmAction('¿Estás seguro de eliminar este item del inventario?', function() { window.location.href = this.href; });"
                                                        class="table-action-btn danger">Eliminar</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html> 
            }
            
            .filtro-btn {
                padding: 8px 12px;
                font-size: 14px;
            }
            
            .inventario-table {
                font-size: 12px;
                min-width: 400px;
            }
            
            .inventario-table th,
            .inventario-table td {
                padding: 6px 4px;
            }
            
            .accion-btn {
                padding: 3px 6px;
                font-size: 9px;
                margin: 1px;
            }
            
            .badge {
                font-size: 10px;
                padding: 2px 5px;
            }
            
            .empty-state {
                padding: 20px 10px;
            }
            
            .empty-state svg {
                width: 40px;
                height: 40px;
            }
            
            .empty-state p {
                font-size: 14px;
            }
        }
        
        /* Mejoras para dispositivos táctiles */
        @media (hover: none) and (pointer: coarse) {
            .filtro-select,
            .filtro-input,
            .filtro-btn {
                min-height: 44px;
            }
            
            .accion-btn {
                min-height: 36px;
                min-width: 36px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        }
        
        /* Mejoras de accesibilidad */
        .inventario-table th {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .inventario-table td[data-label]::before {
            content: attr(data-label) ": ";
            font-weight: bold;
            display: none;
        }
        
        @media (max-width: 600px) {
            .inventario-table,
            .inventario-table thead,
            .inventario-table tbody,
            .inventario-table th,
            .inventario-table td,
            .inventario-table tr {
                display: block;
            }
            
            .inventario-table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            .inventario-table tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
                padding: 10px;
                border-radius: 4px;
            }
            
            .inventario-table td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: left;
            }
            
            .inventario-table td::before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
                display: block;
            }
            
            .inventario-table td:last-child {
                border-bottom: 0;
            }
        }
        
        /* Estados de carga */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .loading::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"></div>
                <div>Sistema de Gestión</div>
            </div>
            <div class="sidebar-nav">
                <?php echo generar_menu_navegacion('inventario'); ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Gestión de Inventario</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <form action="" method="GET" class="filtros">
                    <div class="filtro-grupo">
                        <label class="filtro-label">Categoría:</label>
                        <select name="categoria" class="filtro-select">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo htmlspecialchars($categoria['categoria']); ?>" <?php echo ($filtro_categoria == $categoria['categoria']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['categoria']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-grupo">
                        <label class="filtro-label">Ubicación:</label>
                        <select name="ubicacion" class="filtro-select">
                            <option value="">Todas</option>
                            <?php foreach ($ubicaciones as $ubicacion): ?>
                                <option value="<?php echo htmlspecialchars($ubicacion['ubicacion']); ?>" <?php echo ($filtro_ubicacion == $ubicacion['ubicacion']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ubicacion['ubicacion']); ?>
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
                        <input type="text" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" class="filtro-input" placeholder="Nombre, descripción, proveedor o ubicación">
                    </div>

                    <button type="submit" class="filtro-btn">Filtrar</button>
                    <a href="listar.php" class="filtro-btn btn-reset">Resetear</a>

                    <a href="crear.php" class="filtro-btn btn-agregar">+ Agregar Item</a>
                </form>

                <div class="table-container">
                    <?php if (empty($inventario)): ?>
                        <div class="table-empty">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM11 15H13V17H11V15ZM11 7H13V13H11V7Z" fill="#6c757d" />
                            </svg>
                            <h3>No se encontraron items</h3>
                            <p>No se encontraron items en el inventario con los filtros seleccionados.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="responsive-table inventario-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Nombre</th>
                                        <th scope="col">Categoría</th>
                                        <th scope="col">Cantidad</th>
                                        <th scope="col">Precio Unit.</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col">Ubicación</th>
                                        <th scope="col">Proveedor</th>
                                        <th scope="col">Fecha Adquisición</th>
                                        <th scope="col">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventario as $item): ?>
                                        <tr>
                                            <td data-label="Nombre">
                                                <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                                                <?php if (!empty($item['descripcion'])): ?>
                                                    <br><small style="color: #6c757d;"><?php echo htmlspecialchars(substr($item['descripcion'], 0, 50)) . (strlen($item['descripcion']) > 50 ? '...' : ''); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Categoría"><?php echo htmlspecialchars($item['categoria'] ?: 'Sin categoría'); ?></td>
                                            <td data-label="Cantidad">
                                                <strong><?php echo $item['cantidad']; ?></strong>
                                            </td>
                                            <td data-label="Precio Unit.">
                                                $<?php echo number_format($item['precio_unitario'], 2); ?>
                                            </td>
                                            <td data-label="Estado">
                                                <?php
                                                $estado = $item['estado'];
                                                $badgeClass = 'badge-' . $estado;
                                                echo '<span class="badge ' . $badgeClass . '">' . ucfirst($estado) . '</span>';
                                                ?>
                                            </td>
                                            <td data-label="Ubicación"><?php echo htmlspecialchars($item['ubicacion'] ?: 'No especificada'); ?></td>
                                            <td data-label="Proveedor"><?php echo htmlspecialchars($item['proveedor'] ?: 'No especificado'); ?></td>
                                            <td data-label="Fecha Adquisición"><?php echo format_date($item['fecha_adquisicion']); ?></td>
                                            <td data-label="Acciones">
                                                <div class="table-actions">
                                                    <a href="ver.php?id=<?php echo $item['id_item']; ?>" class="table-action-btn primary">Ver</a>
                                                    <a href="editar.php?id=<?php echo $item['id_item']; ?>" class="table-action-btn warning">Editar</a>
                                                    <a href="procesar.php?accion=eliminar&id=<?php echo $item['id_item']; ?>"
                                                        onclick="return confirmAction('¿Estás seguro de eliminar este item del inventario?', function() { window.location.href = this.href; });"
                                                        class="table-action-btn danger">Eliminar</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html> 
                        <table class="inventario-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unit.</th>
                                    <th>Estado</th>
                                    <th>Ubicación</th>
                                    <th>Proveedor</th>
                                    <th>Fecha Adquisición</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventario as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                                            <?php if (!empty($item['descripcion'])): ?>
                                                <br><small style="color: #6c757d;"><?php echo htmlspecialchars(substr($item['descripcion'], 0, 50)) . (strlen($item['descripcion']) > 50 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['categoria'] ?: 'Sin categoría'); ?></td>
                                        <td>
                                            <strong><?php echo $item['cantidad']; ?></strong>
                                        </td>
                                        <td>
                                            $<?php echo number_format($item['precio_unitario'], 2); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $estado = $item['estado'];
                                            $badgeClass = 'badge-' . $estado;
                                            echo '<span class="badge ' . $badgeClass . '">' . ucfirst($estado) . '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['ubicacion'] ?: 'No especificada'); ?></td>
                                        <td><?php echo htmlspecialchars($item['proveedor'] ?: 'No especificado'); ?></td>
                                        <td><?php echo format_date($item['fecha_adquisicion']); ?></td>
                                        <td>
                                            <a href="ver.php?id=<?php echo $item['id_item']; ?>" class="accion-btn btn-ver">Ver</a>
                                            <a href="editar.php?id=<?php echo $item['id_item']; ?>" class="accion-btn btn-editar">Editar</a>
                                            <a href="procesar.php?accion=eliminar&id=<?php echo $item['id_item']; ?>"
                                                onclick="return confirm('¿Estás seguro de eliminar este item del inventario?');"
                                                class="accion-btn btn-eliminar">Eliminar</a>
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