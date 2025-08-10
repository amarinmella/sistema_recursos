<?php

/**
 * Ver Detalles de Item de Inventario
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado y tenga permisos de administrador
require_login();
if (!has_role(ROL_ADMIN)) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de item no especificado";
    redirect('listar.php');
    exit;
}

$id_item = intval($_GET['id']);

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Obtener datos del item
$item = $db->getRow("SELECT * FROM inventario WHERE id_item = ?", [$id_item]);

if (!$item) {
    $_SESSION['error'] = "El item no existe";
    redirect('listar.php');
    exit;
}

// Calcular valor total del inventario
$valor_total = $item['cantidad'] * $item['precio_unitario'];

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Item de Inventario - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .item-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .item-title {
            font-size: 28px;
            margin: 0 0 10px 0;
            font-weight: 600;
        }
        
        .item-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin: 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-section {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
        }
        
        .info-section h3 {
            margin-top: 0;
            color: #495057;
            font-size: 18px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #6c757d;
            min-width: 120px;
        }
        
        .info-value {
            color: #495057;
            text-align: right;
            flex: 1;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        .valor-total {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .valor-total h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
            opacity: 0.9;
        }
        
        .valor-amount {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .description-box {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .description-text {
            margin: 0;
            line-height: 1.6;
            color: #495057;
        }
        
        .no-description {
            font-style: italic;
            color: #6c757d;
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
                <a href="../admin/dashboard.php" class="nav-item">Dashboard</a>
                <a href="../usuarios/listar.php" class="nav-item">Usuarios</a>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                <a href="../inventario/listar.php" class="nav-item active">Inventario</a>
                <a href="../reportes/index.php" class="nav-item">Reportes</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Detalles del Item</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <div class="breadcrumb">
                <a href="listar.php">← Volver al Inventario</a>
            </div>

            <div class="item-header">
                <h1 class="item-title"><?php echo htmlspecialchars($item['nombre']); ?></h1>
                <p class="item-subtitle">
                    <?php echo htmlspecialchars($item['categoria'] ?: 'Sin categoría'); ?> • 
                    ID: #<?php echo $item['id_item']; ?>
                </p>
            </div>

            <div class="valor-total">
                <h3>Valor Total del Inventario</h3>
                <p class="valor-amount">$<?php echo number_format($valor_total, 2); ?></p>
                <small>Basado en <?php echo $item['cantidad']; ?> unidades × $<?php echo number_format($item['precio_unitario'], 2); ?> c/u</small>
            </div>

            <div class="info-grid">
                <div class="info-section">
                    <h3>Información Básica</h3>
                    <div class="info-item">
                        <span class="info-label">ID del Item:</span>
                        <span class="info-value">#<?php echo $item['id_item']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Categoría:</span>
                        <span class="info-value"><?php echo htmlspecialchars($item['categoria'] ?: 'No especificada'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estado:</span>
                        <span class="info-value">
                            <?php
                            $estado = $item['estado'];
                            $badgeClass = 'badge-' . $estado;
                            echo '<span class="badge ' . $badgeClass . '">' . ucfirst($estado) . '</span>';
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Registro:</span>
                        <span class="info-value"><?php echo format_date($item['fecha_registro']); ?></span>
                    </div>
                </div>

                <div class="info-section">
                    <h3>Stock y Precios</h3>
                    <div class="info-item">
                        <span class="info-label">Cantidad:</span>
                        <span class="info-value"><strong><?php echo $item['cantidad']; ?></strong> unidades</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Precio Unitario:</span>
                        <span class="info-value">$<?php echo number_format($item['precio_unitario'], 2); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Valor Total:</span>
                        <span class="info-value"><strong>$<?php echo number_format($valor_total, 2); ?></strong></span>
                    </div>
                </div>

                <div class="info-section">
                    <h3>Ubicación y Proveedor</h3>
                    <div class="info-item">
                        <span class="info-label">Ubicación:</span>
                        <span class="info-value"><?php echo htmlspecialchars($item['ubicacion'] ?: 'No especificada'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Proveedor:</span>
                        <span class="info-value"><?php echo htmlspecialchars($item['proveedor'] ?: 'No especificado'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Adquisición:</span>
                        <span class="info-value"><?php echo format_date($item['fecha_adquisicion']); ?></span>
                    </div>
                </div>
            </div>

            <div class="info-section">
                <h3>Descripción</h3>
                <?php if (!empty($item['descripcion'])): ?>
                    <div class="description-box">
                        <p class="description-text"><?php echo nl2br(htmlspecialchars($item['descripcion'])); ?></p>
                    </div>
                <?php else: ?>
                    <div class="description-box">
                        <p class="description-text no-description">No hay descripción disponible para este item.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="actions">
                <a href="editar.php?id=<?php echo $item['id_item']; ?>" class="btn btn-primary">Editar Item</a>
                <a href="listar.php" class="btn btn-secondary">Volver al Listado</a>
                <a href="procesar.php?accion=eliminar&id=<?php echo $item['id_item']; ?>"
                    onclick="return confirm('¿Estás seguro de eliminar este item del inventario?');"
                    class="btn btn-danger">Eliminar Item</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html> 