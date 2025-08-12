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

// Obtener notificaciones no leídas
$notificaciones_no_leidas = $db->getRow("
    SELECT COUNT(*) as total
    FROM notificaciones_incidencias
    WHERE id_usuario_destino = ? AND leida = 0
", [$_SESSION['usuario_id']])['total'] ?? 0;

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $precio_unitario = floatval($_POST['precio_unitario'] ?? 0);
    $estado = $_POST['estado'] ?? '';
    $categoria = trim($_POST['categoria'] ?? '');
    $nueva_categoria = trim($_POST['nueva_categoria'] ?? '');
    $proveedor = trim($_POST['proveedor'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $nueva_ubicacion = trim($_POST['nueva_ubicacion'] ?? '');
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

    // Validar categoría
    if (empty($categoria) && empty($nueva_categoria)) {
        $errores[] = "Debes seleccionar una categoría existente o crear una nueva";
    }

    if (!empty($nueva_categoria) && !empty($categoria)) {
        $errores[] = "No puedes seleccionar una categoría existente y crear una nueva al mismo tiempo";
    }

    // Validar ubicación
    if (empty($ubicacion) && empty($nueva_ubicacion)) {
        $errores[] = "Debes seleccionar una ubicación existente o crear una nueva";
    }

    if (!empty($nueva_ubicacion) && !empty($ubicacion)) {
        $errores[] = "No puedes seleccionar una ubicación existente y crear una nueva al mismo tiempo";
    }

    // Si hay errores, mostrarlos
    if (!empty($errores)) {
        $_SESSION['error'] = implode('<br>', $errores);
        redirect('crear.php');
        exit;
    }

    // Determinar la categoría final
    $categoria_final = !empty($nueva_categoria) ? $nueva_categoria : $categoria;

    // Determinar la ubicación final
    $ubicacion_final = !empty($nueva_ubicacion) ? $nueva_ubicacion : $ubicacion;

    // Preparar datos para insertar
    $data = [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio_unitario,
        'estado' => $estado,
        'categoria' => $categoria_final,
        'proveedor' => $proveedor,
        'ubicacion' => $ubicacion_final,
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
            $_SESSION['success'] = "Item de inventario creado correctamente" . 
                (!empty($nueva_categoria) ? " con la nueva categoría '$nueva_categoria'" : "") .
                (!empty($nueva_ubicacion) ? " en la nueva ubicación '$nueva_ubicacion'" : "");
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

// Ubicaciones sugeridas
$ubicaciones_sugeridas = ['Sala de Computación', 'Oficina Administrativa', 'Aula 101', 'Aula 102', 'Laboratorio de Ciencias', 'Biblioteca', 'Almacén', 'Taller', 'Auditorio', 'Sala de Profesores'];

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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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
            display: flex;
            gap: 10px;
            justify-content: flex-start;
            flex-wrap: wrap;
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
        
        .categoria-section {
            position: relative;
        }
        
        .categoria-checkbox {
            margin-top: 10px;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .categoria-checkbox label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
            color: #495057;
        }
        
        .categoria-checkbox input[type="checkbox"] {
            margin: 0;
        }
        
        .nueva-categoria-container {
            margin-top: 10px;
            padding: 10px;
            background-color: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 4px;
            animation: fadeIn 0.3s ease-in;
        }
        
        .nueva-categoria-container input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #2196f3;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .nueva-categoria-container input:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .select-disabled {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .ubicacion-checkbox {
            margin-top: 10px;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .ubicacion-checkbox label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
            color: #495057;
        }
        
        .ubicacion-checkbox input[type="checkbox"] {
            margin: 0;
        }
        
        .nueva-ubicacion-container {
            margin-top: 10px;
            padding: 10px;
            background-color: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 4px;
            animation: fadeIn 0.3s ease-in;
        }
        
        .nueva-ubicacion-container input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #4caf50;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .nueva-ubicacion-container input:focus {
            outline: none;
            border-color: #388e3c;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
        }
        
        /* Estilos responsivos específicos */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-actions .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .categoria-checkbox,
            .ubicacion-checkbox {
                padding: 10px;
            }
            
            .categoria-checkbox label,
            .ubicacion-checkbox label {
                font-size: 14px;
            }
            
            .nueva-categoria-container,
            .nueva-ubicacion-container {
                padding: 12px;
            }
            
            .card {
                padding: 15px;
            }
            
            .form-title {
                font-size: 1.2rem;
            }
            
            .alert {
                font-size: 14px;
                padding: 10px 12px;
            }
        }
        
        @media (max-width: 480px) {
            .form-row {
                gap: 12px;
            }
            
            .card {
                padding: 12px;
            }
            
            .form-title {
                font-size: 1.1rem;
            }
            
            .breadcrumb {
                font-size: 13px;
            }
            
            .categoria-checkbox,
            .ubicacion-checkbox {
                padding: 8px;
            }
            
            .nueva-categoria-container,
            .nueva-ubicacion-container {
                padding: 10px;
            }
            
            .form-actions .btn {
                padding: 10px 16px;
                font-size: 14px;
            }
        }
        
        /* Mejoras de accesibilidad */
        .form-group label {
            font-weight: 500;
            margin-bottom: 6px;
            display: block;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
        }
        
        /* Indicadores de campos requeridos */
        .form-group label[for*="nombre"]::after,
        .form-group label[for*="cantidad"]::after,
        .form-group label[for*="estado"]::after,
        .form-group label[for*="fecha_adquisicion"]::after {
            content: " *";
            color: #dc3545;
        }
        
        /* Estados de carga */
        .btn.loading {
            position: relative;
            pointer-events: none;
        }
        
        .btn.loading .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Mejoras para dispositivos táctiles */
        @media (hover: none) and (pointer: coarse) {
            .btn,
            .filtro-btn,
            input[type="checkbox"],
            select {
                min-height: 44px;
            }
            
            .categoria-checkbox label,
            .ubicacion-checkbox label {
                min-height: 44px;
                align-items: center;
            }
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
                <a href="../bitacora/gestionar.php" class="nav-item">Gestionar Incidencias</a>
                <a href="../admin/notificaciones_incidencias.php" class="nav-item">Notificaciones (<?php echo $notificaciones_no_leidas; ?>)</a>
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

                <div class="alert alert-info" style="margin-bottom: 20px;">
                    <strong>💡 Tip:</strong> Puedes seleccionar una categoría y ubicación existentes del listado desplegable o crear nuevas marcando las casillas correspondientes. Las nuevas categorías y ubicaciones estarán disponibles para futuros items.
                </div>

                <form action="" method="POST" class="user-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">Nombre del Item *</label>
                            <input type="text" id="nombre" name="nombre" required>
                        </div>

                        <div class="form-group">
                            <label for="categoria">Categoría</label>
                            <select id="categoria" name="categoria" onchange="limpiarNuevaCategoria()">
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categorias_sugeridas as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="categoria-checkbox">
                                <label>
                                    <input type="checkbox" id="crear_nueva_categoria" onchange="toggleNuevaCategoria()">
                                    <span>Crear nueva categoría</span>
                                </label>
                            </div>
                            <div id="nueva_categoria_container" class="nueva-categoria-container" style="display: none;">
                                <label for="nueva_categoria" style="display: block; margin-bottom: 5px; font-weight: 500; color: #1976d2;">
                                    Nombre de la nueva categoría:
                                </label>
                                <input type="text" id="nueva_categoria" name="nueva_categoria" placeholder="Ej: Equipos de Laboratorio, Material Didáctico, etc.">
                            </div>
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
                            <select id="ubicacion" name="ubicacion" onchange="limpiarNuevaUbicacion()">
                                <option value="">Seleccione una ubicación</option>
                                <?php foreach ($ubicaciones_sugeridas as $ubic): ?>
                                    <option value="<?php echo htmlspecialchars($ubic); ?>"><?php echo htmlspecialchars($ubic); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="ubicacion-checkbox">
                                <label>
                                    <input type="checkbox" id="crear_nueva_ubicacion" onchange="toggleNuevaUbicacion()">
                                    <span>Crear nueva ubicación</span>
                                </label>
                            </div>
                            <div id="nueva_ubicacion_container" class="nueva-ubicacion-container" style="display: none;">
                                <label for="nueva_ubicacion" style="display: block; margin-bottom: 5px; font-weight: 500; color: #1976d2;">
                                    Nombre de la nueva ubicación:
                                </label>
                                <input type="text" id="nueva_ubicacion" name="nueva_ubicacion" placeholder="Ej: Aula 203, Laboratorio de Física, etc.">
                            </div>
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

        // Función para alternar entre categoría existente y nueva categoría
        function toggleNuevaCategoria() {
            const checkbox = document.getElementById('crear_nueva_categoria');
            const categoriaSelect = document.getElementById('categoria');
            const nuevaCategoriaContainer = document.getElementById('nueva_categoria_container');
            const nuevaCategoriaInput = document.getElementById('nueva_categoria');

            if (checkbox.checked) {
                // Si se marca crear nueva categoría
                categoriaSelect.classList.add('select-disabled');
                categoriaSelect.value = '';
                nuevaCategoriaContainer.style.display = 'block';
                nuevaCategoriaInput.required = true;
                nuevaCategoriaInput.focus();
            } else {
                // Si se desmarca crear nueva categoría
                categoriaSelect.classList.remove('select-disabled');
                nuevaCategoriaContainer.style.display = 'none';
                nuevaCategoriaInput.required = false;
                nuevaCategoriaInput.value = '';
            }
        }

        // Función para limpiar nueva categoría cuando se selecciona una existente
        function limpiarNuevaCategoria() {
            const checkbox = document.getElementById('crear_nueva_categoria');
            const nuevaCategoriaInput = document.getElementById('nueva_categoria');
            
            if (checkbox.checked) {
                checkbox.checked = false;
                toggleNuevaCategoria();
            }
        }

        // Función para alternar entre ubicación existente y nueva ubicación
        function toggleNuevaUbicacion() {
            const checkbox = document.getElementById('crear_nueva_ubicacion');
            const ubicacionSelect = document.getElementById('ubicacion');
            const nuevaUbicacionContainer = document.getElementById('nueva_ubicacion_container');
            const nuevaUbicacionInput = document.getElementById('nueva_ubicacion');

            if (checkbox.checked) {
                // Si se marca crear nueva ubicación
                ubicacionSelect.classList.add('select-disabled');
                ubicacionSelect.value = '';
                nuevaUbicacionContainer.style.display = 'block';
                nuevaUbicacionInput.required = true;
                nuevaUbicacionInput.focus();
            } else {
                // Si se desmarca crear nueva ubicación
                ubicacionSelect.classList.remove('select-disabled');
                nuevaUbicacionContainer.style.display = 'none';
                nuevaUbicacionInput.required = false;
                nuevaUbicacionInput.value = '';
            }
        }

        // Función para limpiar nueva ubicación cuando se selecciona una existente
        function limpiarNuevaUbicacion() {
            const checkbox = document.getElementById('crear_nueva_ubicacion');
            const nuevaUbicacionInput = document.getElementById('nueva_ubicacion');
            
            if (checkbox.checked) {
                checkbox.checked = false;
                toggleNuevaUbicacion();
            }
        }

        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.user-form');
            
            form.addEventListener('submit', function(event) {
                const checkboxCategoria = document.getElementById('crear_nueva_categoria');
                const categoriaSelect = document.getElementById('categoria');
                const nuevaCategoriaInput = document.getElementById('nueva_categoria');

                const checkboxUbicacion = document.getElementById('crear_nueva_ubicacion');
                const ubicacionSelect = document.getElementById('ubicacion');
                const nuevaUbicacionInput = document.getElementById('nueva_ubicacion');
                
                // Validar categoría
                if (checkboxCategoria.checked) {
                    // Si está marcado crear nueva categoría
                    if (nuevaCategoriaInput.value.trim() === '') {
                        event.preventDefault();
                        alert('Debes ingresar el nombre de la nueva categoría');
                        nuevaCategoriaInput.focus();
                        return;
                    }
                    
                    // Verificar si la nueva categoría ya existe en el dropdown
                    const nuevaCategoria = nuevaCategoriaInput.value.trim();
                    const opcionesCategoria = Array.from(categoriaSelect.options).map(option => option.value.toLowerCase());
                    
                    if (opcionesCategoria.includes(nuevaCategoria.toLowerCase())) {
                        event.preventDefault();
                        alert('La categoría "' + nuevaCategoria + '" ya existe. Por favor selecciónala del listado o elige otro nombre.');
                        nuevaCategoriaInput.focus();
                        return;
                    }
                } else {
                    // Si no está marcado, debe seleccionar una categoría existente
                    if (categoriaSelect.value === '') {
                        event.preventDefault();
                        alert('Debes seleccionar una categoría existente o marcar la opción para crear una nueva');
                        categoriaSelect.focus();
                        return;
                    }
                }

                // Validar ubicación
                if (checkboxUbicacion.checked) {
                    // Si está marcado crear nueva ubicación
                    if (nuevaUbicacionInput.value.trim() === '') {
                        event.preventDefault();
                        alert('Debes ingresar el nombre de la nueva ubicación');
                        nuevaUbicacionInput.focus();
                        return;
                    }
                    
                    // Verificar si la nueva ubicación ya existe en el dropdown
                    const nuevaUbicacion = nuevaUbicacionInput.value.trim();
                    const opcionesUbicacion = Array.from(ubicacionSelect.options).map(option => option.value.toLowerCase());
                    
                    if (opcionesUbicacion.includes(nuevaUbicacion.toLowerCase())) {
                        event.preventDefault();
                        alert('La ubicación "' + nuevaUbicacion + '" ya existe. Por favor selecciónala del listado o elige otro nombre.');
                        nuevaUbicacionInput.focus();
                        return;
                    }
                } else {
                    // Si no está marcado, debe seleccionar una ubicación existente
                    if (ubicacionSelect.value === '') {
                        event.preventDefault();
                        alert('Debes seleccionar una ubicación existente o marcar la opción para crear una nueva');
                        ubicacionSelect.focus();
                        return;
                    }
                }
            });
        });
    </script>
</body>

</html> 