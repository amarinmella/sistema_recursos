<?php

/**
 * Crear Nuevo Item de Inventario
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

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $precio_unitario = floatval($_POST['precio_unitario'] ?? 0);
    $estado = $_POST['estado'] ?? '';
    $categoria = trim($_POST['categoria'] ?? '');
    $proveedor = trim($_POST['proveedor'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $fecha_adquisicion = $_POST['fecha_adquisicion'] ?? '';

    // Validar datos
    $errores = [];

    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio";
    }

    if ($cantidad < 0) {
        $errores[] = "La cantidad no puede ser negativa";
    }

    if ($precio_unitario < 0) {
        $errores[] = "El precio no puede ser negativo";
    }

    if (empty($estado)) {
        $errores[] = "Debes seleccionar un estado";
    }

    if (empty($fecha_adquisicion)) {
        $errores[] = "La fecha de adquisición es obligatoria";
    }

    // Si hay errores, mostrarlos
    if (!empty($errores)) {
        $_SESSION['error'] = implode('<br>', $errores);
        redirect('crear.php');
        exit;
    }

    // Preparar datos para insertar
    $data = [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio_unitario,
        'estado' => $estado,
        'categoria' => $categoria,
        'proveedor' => $proveedor,
        'ubicacion' => $ubicacion,
        'fecha_adquisicion' => $fecha_adquisicion,
        'fecha_registro' => date('Y-m-d H:i:s')
    ];

    try {
        // Insertar en la base de datos
        $resultado = $db->insert('inventario', $data);

        if ($resultado) {
            // Obtener el ID del nuevo item
            $id_nuevo_item = $resultado;

            // Registrar la acción
            $log_data = [
                'id_usuario' => $_SESSION['usuario_id'],
                'accion' => 'crear',
                'entidad' => 'inventario',
                'id_entidad' => $id_nuevo_item,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'fecha' => date('Y-m-d H:i:s')
            ];
            $db->insert('log_acciones', $log_data);

            // Redireccionar con mensaje de éxito
            $_SESSION['success'] = "Item de inventario creado correctamente";
            redirect('listar.php');
            exit;
        } else {
            // Mostrar error
            $_SESSION['error'] = "Error al crear el item: " . $db->getError();
            redirect('crear.php');
            exit;
        }
    } catch (Exception $e) {
        // Capturar y mostrar cualquier excepción
        error_log("Error al crear item de inventario: " . $e->getMessage());
        $_SESSION['error'] = "Error al crear el item: " . $e->getMessage();
        redirect('crear.php');
        exit;
    }
}

// Estados disponibles
$estados = ['disponible', 'agotado', 'mantenimiento', 'baja'];

// Categorías sugeridas
$categorias_sugeridas = ['Equipos de Cómputo', 'Mobiliario', 'Material de Oficina', 'Equipos Audiovisuales', 'Herramientas', 'Consumibles', 'Otros'];

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Item de Inventario - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            grid-column: 1 / -1;
            margin-top: 20px;
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
                <h1>Crear Nuevo Item de Inventario</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <div class="breadcrumb">
                <a href="listar.php">← Volver al Inventario</a>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <h2 class="form-title">Información del Item</h2>

                <form action="" method="POST" class="user-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">Nombre del Item *</label>
                            <input type="text" id="nombre" name="nombre" required>
                        </div>

                        <div class="form-group">
                            <label for="categoria">Categoría</label>
                            <select id="categoria" name="categoria">
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categorias_sugeridas as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" placeholder="Descripción detallada del item..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="cantidad">Cantidad *</label>
                            <input type="number" id="cantidad" name="cantidad" min="0" value="1" required>
                        </div>

                        <div class="form-group">
                            <label for="precio_unitario">Precio Unitario ($)</label>
                            <input type="number" id="precio_unitario" name="precio_unitario" min="0" step="0.01" placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="estado">Estado *</label>
                            <select id="estado" name="estado" required>
                                <option value="">Seleccione un estado</option>
                                <?php foreach ($estados as $estado): ?>
                                    <option value="<?php echo $estado; ?>"><?php echo ucfirst($estado); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="fecha_adquisicion">Fecha de Adquisición *</label>
                            <input type="date" id="fecha_adquisicion" name="fecha_adquisicion" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="proveedor">Proveedor</label>
                            <input type="text" id="proveedor" name="proveedor" placeholder="Nombre del proveedor">
                        </div>

                        <div class="form-group">
                            <label for="ubicacion">Ubicación</label>
                            <input type="text" id="ubicacion" name="ubicacion" placeholder="Ubicación física del item">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Item</button>
                        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Establecer fecha actual por defecto
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInput = document.getElementById('fecha_adquisicion');
            const hoy = new Date().toISOString().split('T')[0];
            fechaInput.value = hoy;
        });
    </script>
</body>

</html> 