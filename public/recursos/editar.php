<?php

/**
 * Formulario para editar recursos
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

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de recurso no especificado";
    redirect('listar.php');
    exit;
}

$id_recurso = intval($_GET['id']);

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Obtener datos del recurso
$recurso = $db->getRow("SELECT * FROM recursos WHERE id_recurso = ?", [$id_recurso]);

if (!$recurso) {
    $_SESSION['error'] = "El recurso no existe";
    redirect('listar.php');
    exit;
}

// Obtener tipos de recursos
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
        redirect('editar.php?id=' . $id_recurso);
        exit;
    }

    // Preparar datos para actualizar
    $data = [
        'nombre' => $nombre,
        'id_tipo' => $id_tipo,
        'estado' => $estado,
        'ubicacion' => $ubicacion,
        'descripcion' => $descripcion,
        'disponible' => $disponible
    ];

    // Actualizar en la base de datos
    $resultado = $db->update('recursos', $data, 'id_recurso = ?', [$id_recurso]);

    if ($resultado) {
        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'actualizar',
            'entidad' => 'recursos',
            'id_entidad' => $id_recurso,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s')
        ];
        $db->insert('log_acciones', $log_data);

        // Si el recurso está en mantenimiento, verificar si hay reservas activas y cambiar su estado
        if ($estado === 'mantenimiento' || $estado === 'dañado' || $estado === 'baja' || $disponible === 0) {
            // Obtener reservas pendientes o confirmadas para este recurso
            $reservas = $db->getRows(
                "SELECT id_reserva FROM reservas WHERE id_recurso = ? AND estado IN ('pendiente', 'confirmada') AND fecha_inicio > NOW()",
                [$id_recurso]
            );

            // Cancelar reservas futuras
            if ($reservas) {
                foreach ($reservas as $reserva) {
                    $db->update(
                        'reservas',
                        ['estado' => 'cancelada'],
                        'id_reserva = ?',
                        [$reserva['id_reserva']]
                    );

                    // Crear notificación para el usuario que hizo la reserva
                    $info_reserva = $db->getRow(
                        "SELECT id_usuario, fecha_inicio FROM reservas WHERE id_reserva = ?",
                        [$reserva['id_reserva']]
                    );

                    if ($info_reserva) {
                        $mensaje = "Tu reserva para el recurso '{$nombre}' del día " .
                            date('d/m/Y H:i', strtotime($info_reserva['fecha_inicio'])) .
                            " ha sido cancelada debido a que el recurso no está disponible.";

                        $db->insert('notificaciones', [
                            'id_reserva' => $reserva['id_reserva'],
                            'id_usuario' => $info_reserva['id_usuario'],
                            'mensaje' => $mensaje,
                            'leido' => 0,
                            'fecha' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
        }

        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Recurso actualizado correctamente";
        redirect('listar.php');
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al actualizar el recurso: " . $db->getError();
        redirect('editar.php?id=' . $id_recurso);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Recurso - Sistema de Gestión de Recursos</title>
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
                <h1>Editar Recurso</h1>
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
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($recurso['nombre']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="id_tipo">Tipo de Recurso *</label>
                        <select id="id_tipo" name="id_tipo" required>
                            <option value="">Seleccione un tipo</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?php echo $tipo['id_tipo']; ?>" <?php echo ($recurso['id_tipo'] == $tipo['id_tipo']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <?php foreach ($estados as $estado): ?>
                                <option value="<?php echo $estado; ?>" <?php echo ($recurso['estado'] === $estado) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($estado); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="ubicacion">Ubicación</label>
                        <input type="text" id="ubicacion" name="ubicacion" value="<?php echo htmlspecialchars($recurso['ubicacion']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion"><?php echo htmlspecialchars($recurso['descripcion']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="disponible" name="disponible" <?php echo $recurso['disponible'] ? 'checked' : ''; ?>>
                            <label for="disponible">Disponible para reservas</label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2 class="form-title">Información Adicional</h2>

                <p><strong>Fecha de alta:</strong> <?php echo format_date($recurso['fecha_alta']); ?></p>

                <?php
                // Verificar si hay reservas activas para este recurso
                $reservas_activas = $db->getRow(
                    "SELECT COUNT(*) as total FROM reservas 
                     WHERE id_recurso = ? AND estado IN ('pendiente', 'confirmada') AND fecha_inicio > NOW()",
                    [$id_recurso]
                );
                ?>

                <p style="margin-top: 20px;">
                    <strong>Reservas activas:</strong> <?php echo $reservas_activas ? $reservas_activas['total'] : '0'; ?>
                </p>

                <div class="alert alert-warning" style="margin-top: 20px;">
                    <strong>Nota:</strong> Si cambia el estado a "Mantenimiento", "Dañado" o "Baja", o desmarca la opción "Disponible para reservas",
                    todas las reservas futuras para este recurso serán canceladas automáticamente y se notificará a los usuarios afectados.
                </div>
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