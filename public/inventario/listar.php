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

// Ejecutar la consulta y asegurar que sea un array
$inventario = $db->getRows($sql, $params);
if ($inventario === false) {
    $inventario = [];
}

// Obtener categorías para el filtro
$categorias = $db->getRows("SELECT DISTINCT categoria FROM inventario WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
if ($categorias === false) {
    $categorias = [];
}

// Obtener ubicaciones para el filtro
$ubicaciones = $db->getRows("SELECT DISTINCT ubicacion FROM inventario WHERE ubicacion IS NOT NULL AND ubicacion != '' ORDER BY ubicacion");
if ($ubicaciones === false) {
    $ubicaciones = [];
}

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
        .filtros {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filtro-grupo {
            display: flex;
            flex-direction: column;
        }
        
        .filtro-grupo label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .filtro-grupo select,
        .filtro-grupo input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filtros-acciones {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-filtrar {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-limpiar {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .inventario-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .inventario-table th,
        .inventario-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .inventario-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .accion-btn {
            display: inline-block;
            padding: 4px 8px;
            margin: 2px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 12px;
            color: white;
        }
        
        .btn-ver {
            background-color: #17a2b8;
        }
        
        .btn-editar {
            background-color: #ffc107;
        }
        
        .btn-eliminar {
            background-color: #dc3545;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-disponible {
            background-color: #28a745;
            color: white;
        }
        
        .badge-agotado {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-mantenimiento {
            background-color: #ffc107;
            color: black;
        }
        
        .badge-baja {
            background-color: #6c757d;
            color: white;
        }
        
        .estadisticas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .estadistica-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .estadistica-valor {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }
        
        .estadistica-label {
            color: #6c757d;
            margin-top: 5px;
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

            <!-- Estadísticas -->
            <div class="estadisticas">
                <div class="estadistica-card">
                    <div class="estadistica-valor"><?php echo is_array($inventario) ? count($inventario) : 0; ?></div>
                    <div class="estadistica-label">Total de Items</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-valor">
                        <?php 
                        if (is_array($inventario)) {
                            $disponibles = array_filter($inventario, function($item) {
                                return $item['estado'] == 'disponible';
                            });
                            echo count($disponibles);
                        } else {
                            echo 0;
                        }
                        ?>
                    </div>
                    <div class="estadistica-label">Disponibles</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-valor">
                        <?php 
                        if (is_array($inventario)) {
                            $agotados = array_filter($inventario, function($item) {
                                return $item['estado'] == 'agotado';
                            });
                            echo count($agotados);
                        } else {
                            echo 0;
                        }
                        ?>
                    </div>
                    <div class="estadistica-label">Agotados</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-valor">
                        <?php 
                        if (is_array($inventario)) {
                            $mantenimiento = array_filter($inventario, function($item) {
                                return $item['estado'] == 'mantenimiento';
                            });
                            echo count($mantenimiento);
                        } else {
                            echo 0;
                        }
                        ?>
                    </div>
                    <div class="estadistica-label">En Mantenimiento</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filtros">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" action="">
                    <div class="filtros-grid">
                        <div class="filtro-grupo">
                            <label for="busqueda">Buscar:</label>
                            <input type="text" id="busqueda" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Nombre, descripción, proveedor...">
                        </div>
                        
                        <div class="filtro-grupo">
                            <label for="categoria">Categoría:</label>
                            <select id="categoria" name="categoria">
                                <option value="">Todas las categorías</option>
                                <?php if (is_array($categorias)): ?>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['categoria']); ?>" <?php echo $filtro_categoria == $cat['categoria'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['categoria']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="filtro-grupo">
                            <label for="ubicacion">Ubicación:</label>
                            <select id="ubicacion" name="ubicacion">
                                <option value="">Todas las ubicaciones</option>
                                <?php if (is_array($ubicaciones)): ?>
                                    <?php foreach ($ubicaciones as $ubic): ?>
                                        <option value="<?php echo htmlspecialchars($ubic['ubicacion']); ?>" <?php echo $filtro_ubicacion == $ubic['ubicacion'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ubic['ubicacion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="filtro-grupo">
                            <label for="estado">Estado:</label>
                            <select id="estado" name="estado">
                                <option value="">Todos los estados</option>
                                <?php foreach ($estados as $est): ?>
                                    <option value="<?php echo $est; ?>" <?php echo $filtro_estado == $est ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($est); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filtros-acciones">
                        <button type="submit" class="btn-filtrar">Filtrar</button>
                        <a href="listar.php" class="btn-limpiar">Limpiar Filtros</a>
                        <?php if (has_role(ROL_ADMIN)): ?>
                            <a href="crear.php" class="btn-filtrar">Agregar Item</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabla de Inventario -->
            <div class="card">
                <div class="card-title">Listado de Inventario</div>
                <?php if (!is_array($inventario) || empty($inventario)): ?>
                    <p>No se encontraron items en el inventario con los filtros aplicados.</p>
                <?php else: ?>
                    <div class="table-responsive">
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
                                            <?php if (has_role(ROL_ADMIN)): ?>
                                                <a href="editar.php?id=<?php echo $item['id_item']; ?>" class="accion-btn btn-editar">Editar</a>
                                                <a href="procesar.php?accion=eliminar&id=<?php echo $item['id_item']; ?>"
                                                    onclick="return confirm('¿Estás seguro de eliminar este item del inventario?');"
                                                    class="accion-btn btn-eliminar">Eliminar</a>
                                            <?php endif; ?>
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

    <script src="../assets/js/main.js"></script>
</body>

</html> 