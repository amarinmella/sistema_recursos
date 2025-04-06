<?php

/**
 * Formulario para editar mantenimiento
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

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de mantenimiento no especificado";
    redirect('listar.php');
    exit;
}

$id_mantenimiento = intval($_GET['id']);

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Obtener datos del mantenimiento
$sql = "SELECT m.*, 
               r.nombre as recurso_nombre, 
               r.ubicacion as recurso_ubicacion,
               tr.nombre as tipo_recurso
        FROM mantenimiento m
        JOIN recursos r ON m.id_recurso = r.id_recurso
        JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
        WHERE m.id_mantenimiento = ?";

$mantenimiento = $db->getRow($sql, [$id_mantenimiento]);

if (!$mantenimiento) {
    $_SESSION['error'] = "El mantenimiento no existe";
    redirect('listar.php');
    exit;
}

// Verificar si el mantenimiento ya está completado
$es_completado = ($mantenimiento['estado'] === 'completado');

// Obtener lista de recursos disponibles
$sql_recursos = "SELECT r.id_recurso, r.nombre, r.ubicacion, t.nombre as tipo 
                FROM recursos r
                JOIN tipos_recursos t ON r.id_tipo = t.id_tipo
                ORDER BY t.nombre, r.nombre";
$recursos = $db->getRows($sql_recursos);

// Agrupar recursos por tipo para mostrar en el select
$recursos_por_tipo = [];
foreach ($recursos as $recurso) {
    $tipo = $recurso['tipo'];
    if (!isset($recursos_por_tipo[$tipo])) {
        $recursos_por_tipo[$tipo] = [];
    }
    $recursos_por_tipo[$tipo][] = $recurso;
}

// Formatear fecha y hora para los inputs
$fecha_inicio_parts = explode(' ', $mantenimiento['fecha_inicio']);
$fecha_inicio = $fecha_inicio_parts[0];
$hora_inicio = substr($fecha_inicio_parts[1], 0, 5);

$fecha_fin = '';
$hora_fin = '';
if ($mantenimiento['fecha_fin']) {
    $fecha_fin_parts = explode(' ', $mantenimiento['fecha_fin']);
    $fecha_fin = $fecha_fin_parts[0];
    $hora_fin = substr($fecha_fin_parts[1], 0, 5);
}

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
    $id_recurso = intval($_POST['id_recurso'] ?? 0);
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estado = $_POST['estado'] ?? 'pendiente';

    // Combinar fecha y hora
    $fecha_inicio_completa = $fecha_inicio . ' ' . $hora_inicio . ':00';
    $fecha_fin_completa = !empty($fecha_fin) && !empty($hora_fin) ? $fecha_fin . ' ' . $hora_fin . ':00' : null;

    // Validar datos
    $errores = [];

    if ($id_recurso <= 0) {
        $errores[] = "Debes seleccionar un recurso";
    } else {
        // Verificar que el recurso exista
        $recurso_check = $db->getRow(
            "SELECT id_recurso FROM recursos WHERE id_recurso = ?",
            [$id_recurso]
        );

        if (!$recurso_check) {
            $errores[] = "El recurso seleccionado no existe";
        }
    }

    if (empty($fecha_inicio) || empty($hora_inicio)) {
        $errores[] = "La fecha y hora de inicio son obligatorias";
    }

    if (empty($descripcion)) {
        $errores[] = "La descripción del mantenimiento es obligatoria";
    }

    // Validar la fecha de fin (si se proporciona)
    if (!empty($fecha_fin) && !empty($hora_fin)) {
        if (strtotime($fecha_fin_completa) <= strtotime($fecha_inicio_completa)) {
            $errores[] = "La fecha de finalización debe ser posterior a la fecha de inicio";
        }
    }

    // Si el estado es completado, debe haber una fecha de fin
    if ($estado === 'completado' && (empty($fecha_fin) || empty($hora_fin))) {
        $errores[] = "Debe especificar una fecha de finalización si el estado es Completado";
    }

    // Si hay errores, mostrarlos
    if (!empty($errores)) {
        $_SESSION['error'] = implode('<br>', $errores);
        redirect('editar.php?id=' . $id_mantenimiento);
        exit;
    }

    // Preparar datos para actualizar
    $data = [
        'id_recurso' => $id_recurso,
        'descripcion' => $descripcion,
        'fecha_inicio' => $fecha_inicio_completa,
        'fecha_fin' => $fecha_fin_completa,
        'estado' => $estado
    ];

    // Actualizar en la base de datos
    $resultado = $db->update('mantenimiento', $data, 'id_mantenimiento = ?', [$id_mantenimiento]);

    if ($resultado) {
        // Actualizar el estado del recurso según el estado del mantenimiento
        if ($estado === 'completado') {
            $db->update('recursos', [
                'estado' => 'disponible',
                'disponible' => 1
            ], 'id_recurso = ?', [$id_recurso]);
        } else {
            // Si está pendiente o en progreso, el recurso debe estar en mantenimiento
            $db->update('recursos', [
                'estado' => 'mantenimiento',
                'disponible' => 0
            ], 'id_recurso = ?', [$id_recurso]);
        }

        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'actualizar',
            'entidad' => 'mantenimiento',
            'id_entidad' => $id_mantenimiento,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s'),
            'detalles' => 'Mantenimiento actualizado'
        ];
        $db->insert('log_acciones', $log_data);

        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Mantenimiento actualizado correctamente";
        redirect('ver.php?id=' . $id_mantenimiento);
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al actualizar el mantenimiento: " . $db->getError();
        redirect('editar.php?id=' . $id_mantenimiento);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Mantenimiento - Sistema de Gestión de Recursos</title>
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
                <h1>Editar Mantenimiento</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div style="margin-bottom: 20px;">
                <a href="ver.php?id=<?php echo $id_mantenimiento; ?>" class="btn btn-secondary">&laquo; Volver a Detalles</a>
            </div>

            <div class="card">
                <h2 class="form-title">Información del Mantenimiento</h2>

                <?php if ($es_completado): ?>
                    <div class="alert alert-warning">
                        <strong>Nota:</strong> Este mantenimiento ya está completado. Algunos campos pueden estar bloqueados para edición.
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="maintenance-form">
                    <div class="form-group">
                        <label for="id_recurso">Recurso *</label>
                        <select id="id_recurso" name="id_recurso" required <?php echo $es_completado ? 'disabled' : ''; ?>>
                            <option value="">Seleccione un recurso</option>
                            <?php foreach ($recursos_por_tipo as $tipo => $recursos_tipo): ?>
                                <optgroup label="<?php echo htmlspecialchars($tipo); ?>">
                                    <?php foreach ($recursos_tipo as $recurso): ?>
                                        <option value="<?php echo $recurso['id_recurso']; ?>" <?php echo ($mantenimiento['id_recurso'] == $recurso['id_recurso']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($recurso['nombre'] . ($recurso['ubicacion'] ? ' (' . $recurso['ubicacion'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($es_completado): ?>
                            <input type="hidden" name="id_recurso" value="<?php echo $mantenimiento['id_recurso']; ?>">
                        <?php endif; ?>
                    </div>

                    <div class="form-row" style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="fecha_inicio">Fecha de Inicio *</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" required <?php echo $es_completado ? 'disabled' : ''; ?>>
                            <?php if ($es_completado): ?>
                                <input type="hidden" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                            <?php endif; ?>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="hora_inicio">Hora de Inicio *</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" value="<?php echo $hora_inicio; ?>" required <?php echo $es_completado ? 'disabled' : ''; ?>>
                            <?php if ($es_completado): ?>
                                <input type="hidden" name="hora_inicio" value="<?php echo $hora_inicio; ?>">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="fecha_fin">Fecha de Finalización <?php echo ($estado === 'completado') ? '*' : ''; ?></label>
                            <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>" <?php echo ($estado === 'completado') ? 'required' : ''; ?>>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="hora_fin">Hora de Finalización <?php echo ($estado === 'completado') ? '*' : ''; ?></label>
                            <input type="time" id="hora_fin" name="hora_fin" value="<?php echo $hora_fin; ?>" <?php echo ($estado === 'completado') ? 'required' : ''; ?>>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción del Mantenimiento *</label>
                        <textarea id="descripcion" name="descripcion" rows="5" required><?php echo htmlspecialchars($mantenimiento['descripcion']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado *</label>
                        <select id="estado" name="estado" required>
                            <option value="pendiente" <?php echo ($mantenimiento['estado'] === 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="en progreso" <?php echo ($mantenimiento['estado'] === 'en progreso') ? 'selected' : ''; ?>>En Progreso</option>
                            <option value="completado" <?php echo ($mantenimiento['estado'] === 'completado') ? 'selected' : ''; ?>>Completado</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <a href="ver.php?id=<?php echo $id_mantenimiento; ?>" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2 class="form-title">Información Adicional</h2>

                <div class="alert alert-info">
                    <strong>Nota:</strong> Al editar un mantenimiento, se actualiza automáticamente el estado del recurso.
                </div>

                <div class="alert alert-warning" style="margin-top: 20px;">
                    <strong>Importante:</strong> Si marca el mantenimiento como completado, el recurso volverá a estar disponible para reservas.
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.maintenance-form');
            const estadoField = document.getElementById('estado');
            const fechaFinField = document.getElementById('fecha_fin');
            const horaFinField = document.getElementById('hora_fin');

            // Evento para el cambio de estado
            estadoField.addEventListener('change', function() {
                if (this.value === 'completado') {
                    // Hacer obligatorios los campos de fecha y hora de fin
                    fechaFinField.setAttribute('required', 'required');
                    horaFinField.setAttribute('required', 'required');

                    // Si no tienen valor, establecer fecha y hora actuales
                    if (fechaFinField.value === '') {
                        fechaFinField.value = new Date().toISOString().split('T')[0];
                    }

                    if (horaFinField.value === '') {
                        horaFinField.value = new Date().toTimeString().slice(0, 5);
                    }
                } else {
                    // Hacer opcionales los campos de fecha y hora de fin
                    fechaFinField.removeAttribute('required');
                    horaFinField.removeAttribute('required');
                }
            });

            // Validación del formulario
            form.addEventListener('submit', function(event) {
                let hasError = false;

                // Si el estado es completado, verificar que haya fecha de fin
                if (estadoField.value === 'completado' && (fechaFinField.value === '' || horaFinField.value === '')) {
                    alert('Debe especificar una fecha de finalización si el estado es Completado');
                    hasError = true;
                }

                // Validar fechas de finalización (si se proporcionan)
                if (fechaFinField.value !== '' && horaFinField.value !== '') {
                    const fechaInicioField = document.getElementById('fecha_inicio');
                    const horaInicioField = document.getElementById('hora_inicio');

                    // Verificar que la fecha de fin sea posterior a la de inicio
                    const fechaInicioObj = new Date(`${fechaInicioField.value}T${horaInicioField.value}`);
                    const fechaFinObj = new Date(`${fechaFinField.value}T${horaFinField.value}`);

                    if (fechaFinObj <= fechaInicioObj) {
                        alert('La fecha de finalización debe ser posterior a la fecha de inicio');
                        hasError = true;
                    }
                }

                if (hasError) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>

</html>