<?php

/**
 * Formulario para crear recursos
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

// Obtener tipos de recursos
$db = Database::getInstance();
$tipos = $db->getRows("SELECT id_tipo, nombre FROM tipos_recursos ORDER BY nombre");

// Estados posibles
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

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos
    $nombre = trim($_POST['nombre'] ?? '');
    $id_tipo = intval($_POST['id_tipo'] ?? 0);
    $estado = $_POST['estado'] ?? 'disponible';
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $disponible = isset($_POST['disponible']) ? 1 : 0;

    // Validar datos
    $errores = [];

    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio";
    }

    if ($id_tipo <= 0) {
        $errores[] = "Debes seleccionar un tipo de recurso";
    }

    if (!in_array($estado, $estados)) {
        $errores[] = "El estado seleccionado no es válido";
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
        'id_tipo' => $id_tipo,
        'estado' => $estado,
        'ubicacion' => $ubicacion,
        'descripcion' => $descripcion,
        'disponible' => $disponible,
        'fecha_alta' => date('Y-m-d H:i:s')
    ];

    // Insertar en la base de datos
    $resultado = $db->insert('recursos', $data);

    if ($resultado) {
        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'crear',
            'entidad' => 'recursos',
            'id_entidad' => $resultado,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s')
        ];
        $db->insert('log_acciones', $log_data);

        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Recurso creado correctamente";
        redirect('listar.php');
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al crear el recurso: " . $db->getError();
        redirect('crear.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Recurso - Sistema de Gestión de Recursos</title>
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
                <h1>Crear Nuevo Recurso</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <h2 class="form-title">Información del Recurso</h2>

                <form action="" method="POST" class="resource-form">
                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>

                    <div class="form-group">
                        <label for="id_tipo">Tipo de Recurso *</label>
                        <select id="id_tipo" name="id_tipo" required>
                            <option value="">Seleccione un tipo</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?php echo $tipo['id_tipo']; ?>">
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <?php foreach ($estados as $estado): ?>
                                <option value="<?php echo $estado; ?>" <?php echo ($estado === 'disponible') ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($estado); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="ubicacion">Ubicación</label>
                        <input type="text" id="ubicacion" name="ubicacion">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion"></textarea>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="disponible" name="disponible" checked>
                            <label for="disponible">Disponible para reservas</label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Recurso</button>
                        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2 class="form-title">Información Adicional</h2>
                <p>
                    <strong>Instrucciones:</strong>
                </p>
                <ul style="margin-bottom: 20px; line-height: 1.5;">
                    <li>Complete todos los campos marcados con asterisco (*).</li>
                    <li>El estado <strong>Disponible</strong> indica que el recurso está en buen estado y puede ser utilizado.</li>
                    <li>El estado <strong>Mantenimiento</strong> indica que el recurso está temporalmente en mantenimiento preventivo o correctivo.</li>
                    <li>El estado <strong>Dañado</strong> indica que el recurso tiene algún problema y no puede ser utilizado hasta su reparación.</li>
                    <li>El estado <strong>Baja</strong> indica que el recurso ya no está en uso o ha sido retirado del inventario.</li>
                    <li>La opción <strong>Disponible para reservas</strong> determina si el recurso puede ser reservado por los usuarios, independientemente de su estado.</li>
                </ul>

                <p>
                    <strong>Tipos de recursos disponibles:</strong>
                </p>
                <ul>
                    <?php foreach ($tipos as $tipo): ?>
                        <li><?php echo htmlspecialchars($tipo['nombre']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.resource-form');

            form.addEventListener('submit', function(event) {
                let hasError = false;

                // Validar nombre
                const nombreField = document.getElementById('nombre');
                if (nombreField.value.trim() === '') {
                    showError(nombreField, 'El nombre es obligatorio');
                    hasError = true;
                } else {
                    removeError(nombreField);
                }

                // Validar tipo
                const tipoField = document.getElementById('id_tipo');
                if (tipoField.value === '') {
                    showError(tipoField, 'Debe seleccionar un tipo de recurso');
                    hasError = true;
                } else {
                    removeError(tipoField);
                }

                // Si hay errores, prevenir envío
                if (hasError) {
                    event.preventDefault();
                }
            });

            // Función para mostrar error
            function showError(field, message) {
                // Remover error previo si existe
                removeError(field);

                // Crear elemento de error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = message;

                // Añadir borde rojo al campo
                field.style.borderColor = '#e74c3c';

                // Insertar mensaje de error después del campo
                field.parentNode.appendChild(errorDiv);
            }

            // Función para remover error
            function removeError(field) {
                field.style.borderColor = '';

                // Buscar y eliminar mensajes de error existentes
                const parent = field.parentNode;
                const errorDiv = parent.querySelector('.error-message');

                if (errorDiv) {
                    parent.removeChild(errorDiv);
                }
            }
        });
    </script>
</body>

</html>