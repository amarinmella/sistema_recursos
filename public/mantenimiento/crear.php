<?php

/**
 * Formulario para crear nuevo mantenimiento
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

// Obtener recurso preseleccionado si viene como parámetro
$id_recurso_selected = isset($_GET['recurso']) ? intval($_GET['recurso']) : 0;

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

    // Si hay errores, mostrarlos
    if (!empty($errores)) {
        $_SESSION['error'] = implode('<br>', $errores);
        redirect('crear.php');
        exit;
    }

    // Preparar datos para insertar
    $data = [
        'id_recurso' => $id_recurso,
        'id_usuario' => $_SESSION['usuario_id'],
        'descripcion' => $descripcion,
        'fecha_inicio' => $fecha_inicio_completa,
        'fecha_fin' => $fecha_fin_completa,
        'estado' => $estado,
        'fecha_registro' => date('Y-m-d H:i:s')
    ];

    // Insertar en la base de datos
    $resultado = $db->insert('mantenimiento', $data);

    if ($resultado) {
        $id_mantenimiento = $db->lastInsertId();

        // Actualizar el estado del recurso a "mantenimiento" si está en progreso o pendiente
        if ($estado == 'pendiente' || $estado == 'en progreso') {
            $db->update('recursos', [
                'estado' => 'mantenimiento',
                'disponible' => 0
            ], 'id_recurso = ?', [$id_recurso]);
        }

        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'crear',
            'entidad' => 'mantenimiento',
            'id_entidad' => $id_mantenimiento,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s')
        ];
        $db->insert('log_acciones', $log_data);

        // Obtener info del recurso para notificaciones
        $recurso_info = $db->getRow(
            "SELECT nombre FROM recursos WHERE id_recurso = ?",
            [$id_recurso]
        );

        // Notificar a los usuarios que tengan reservas para este recurso
        $sql_reservas = "
            SELECT DISTINCT r.id_usuario, r.id_reserva
            FROM reservas r
            WHERE r.id_recurso = ?
            AND r.estado IN ('pendiente', 'confirmada')
            AND r.fecha_inicio > NOW()
        ";

        $usuarios_afectados = $db->getRows($sql_reservas, [$id_recurso]);

        foreach ($usuarios_afectados as $usuario) {
            $mensaje_notif = "Se ha programado mantenimiento para el recurso '{$recurso_info['nombre']}' " .
                "a partir de " . date('d/m/Y H:i', strtotime($fecha_inicio_completa)) . ". " .
                "Esto podría afectar a tus reservas.";

            $db->insert('notificaciones', [
                'id_reserva' => $usuario['id_reserva'],
                'id_usuario' => $usuario['id_usuario'],
                'mensaje' => $mensaje_notif,
                'leido' => 0,
                'fecha' => date('Y-m-d H:i:s')
            ]);
        }

        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Mantenimiento registrado correctamente";
        redirect('ver.php?id=' . $id_mantenimiento);
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al registrar el mantenimiento: " . $db->getError();
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
    <title>Registrar Mantenimiento - Sistema de Gestión de Recursos</title>
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
                <h1>Registrar Mantenimiento</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <h2 class="form-title">Información del Mantenimiento</h2>

                <form action="" method="POST" class="maintenance-form">
                    <div class="form-group">
                        <label for="id_recurso">Recurso *</label>
                        <select id="id_recurso" name="id_recurso" required>
                            <option value="">Seleccione un recurso</option>
                            <?php foreach ($recursos_por_tipo as $tipo => $recursos_tipo): ?>
                                <optgroup label="<?php echo htmlspecialchars($tipo); ?>">
                                    <?php foreach ($recursos_tipo as $recurso): ?>
                                        <option value="<?php echo $recurso['id_recurso']; ?>" <?php echo ($id_recurso_selected == $recurso['id_recurso']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($recurso['nombre'] . ($recurso['ubicacion'] ? ' (' . $recurso['ubicacion'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row" style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="fecha_inicio">Fecha de Inicio *</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="hora_inicio">Hora de Inicio *</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" value="<?php echo date('H:i'); ?>" required>
                        </div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="fecha_fin">Fecha de Finalización (si está programada)</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" value="">
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="hora_fin">Hora de Finalización</label>
                            <input type="time" id="hora_fin" name="hora_fin" value="">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción del Mantenimiento *</label>
                        <textarea id="descripcion" name="descripcion" rows="5" required placeholder="Describa el problema o el mantenimiento a realizar, partes a reemplazar, etc."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado *</label>
                        <select id="estado" name="estado" required>
                            <option value="pendiente">Pendiente</option>
                            <option value="en progreso">En Progreso</option>
                            <option value="completado">Completado</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Registrar Mantenimiento</button>
                        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2 class="form-title">Información Adicional</h2>

                <div class="alert alert-info">
                    <strong>Nota:</strong> Al registrar un mantenimiento, el recurso se marcará automáticamente como "En mantenimiento" y no estará disponible para reservas.
                </div>

                <div class="alert alert-warning" style="margin-top: 20px;">
                    <strong>Importante:</strong> Si hay reservas existentes para este recurso durante el período de mantenimiento, se notificará a los usuarios afectados automáticamente.
                </div>
            </div>

            <?php if ($id_recurso_selected > 0):
                // Si se especificó un recurso, mostrar las reservas futuras para ese recurso
                $reservas_futuras = $db->getRows(
                    "SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado,
                            CONCAT(u.nombre, ' ', u.apellido) as usuario
                     FROM reservas r
                     JOIN usuarios u ON r.id_usuario = u.id_usuario
                     WHERE r.id_recurso = ?
                     AND r.fecha_inicio > NOW() 
                     AND r.estado IN ('pendiente', 'confirmada')
                     ORDER BY r.fecha_inicio
                     LIMIT 10",
                    [$id_recurso_selected]
                );
            ?>
                <div class="card">
                    <h2 class="form-title">Reservas Futuras para este Recurso</h2>

                    <?php if (empty($reservas_futuras)): ?>
                        <p>No hay reservas programadas para este recurso en el futuro.</p>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>Advertencia:</strong> Este recurso tiene reservas futuras que podrían verse afectadas por el mantenimiento.
                        </div>

                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuario</th>
                                        <th>Fecha Inicio</th>
                                        <th>Fecha Fin</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservas_futuras as $reserva): ?>
                                        <tr>
                                            <td><?php echo $reserva['id_reserva']; ?></td>
                                            <td><?php echo htmlspecialchars($reserva['usuario']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_inicio'])); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_fin'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo ($reserva['estado'] == 'confirmada') ? 'badge-success' : 'badge-warning'; ?>">
                                                    <?php echo ucfirst($reserva['estado']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.maintenance-form');

            // Validación del formulario
            form.addEventListener('submit', function(event) {
                let hasError = false;

                // Validar recurso
                const recursoField = document.getElementById('id_recurso');
                if (recursoField.value === '') {
                    showError(recursoField, 'Debe seleccionar un recurso');
                    hasError = true;
                } else {
                    removeError(recursoField);
                }

                // Validar fecha y hora de inicio
                const fechaInicioField = document.getElementById('fecha_inicio');
                const horaInicioField = document.getElementById('hora_inicio');

                if (fechaInicioField.value === '' || horaInicioField.value === '') {
                    showError(fechaInicioField, 'Debe especificar la fecha y hora de inicio');
                    hasError = true;
                } else {
                    removeError(fechaInicioField);
                }

                // Validar descripción
                const descripcionField = document.getElementById('descripcion');
                if (descripcionField.value.trim() === '') {
                    showError(descripcionField, 'La descripción es obligatoria');
                    hasError = true;
                } else {
                    removeError(descripcionField);
                }

                // Validar fechas de finalización (si se proporcionan)
                const fechaFinField = document.getElementById('fecha_fin');
                const horaFinField = document.getElementById('hora_fin');

                if ((fechaFinField.value !== '' && horaFinField.value === '') ||
                    (fechaFinField.value === '' && horaFinField.value !== '')) {
                    showError(fechaFinField, 'Debe especificar tanto la fecha como la hora de finalización, o dejar ambos campos vacíos');
                    hasError = true;
                } else if (fechaFinField.value !== '' && horaFinField.value !== '') {
                    // Verificar que la fecha de fin sea posterior a la de inicio
                    const fechaInicioObj = new Date(`${fechaInicioField.value}T${horaInicioField.value}`);
                    const fechaFinObj = new Date(`${fechaFinField.value}T${horaFinField.value}`);

                    if (fechaFinObj <= fechaInicioObj) {
                        showError(fechaFinField, 'La fecha de finalización debe ser posterior a la fecha de inicio');
                        hasError = true;
                    } else {
                        removeError(fechaFinField);
                    }
                } else {
                    removeError(fechaFinField);
                }

                // Cambiar el estado si se fija una fecha de finalización
                const estadoField = document.getElementById('estado');
                if (fechaFinField.value !== '' && horaFinField.value !== '' && !hasError) {
                    // Si se marca una fecha de fin, sugerir el estado completado
                    if (estadoField.value === 'pendiente') {
                        if (confirm('Has especificado una fecha de finalización. ¿Quieres cambiar el estado a "Completado"?')) {
                            estadoField.value = 'completado';
                        }
                    }
                }

                if (hasError) {
                    event.preventDefault();
                }
            });

            // Funciones para mostrar y ocultar errores
            function showError(field, message) {
                // Verificar si ya existe un mensaje de error
                let errorElement = field.parentElement.querySelector('.error-message');

                if (!errorElement) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'error-message';
                    field.parentElement.appendChild(errorElement);
                }

                errorElement.textContent = message;
                field.classList.add('error');
            }

            function removeError(field) {
                const errorElement = field.parentElement.querySelector('.error-message');

                if (errorElement) {
                    errorElement.remove();
                }

                field.classList.remove('error');
            }

            // Evento para el cambio de estado
            const estadoField = document.getElementById('estado');
            estadoField.addEventListener('change', function() {
                const fechaFinField = document.getElementById('fecha_fin');
                const horaFinField = document.getElementById('hora_fin');

                // Si el estado es "completado", sugerir poner la fecha de finalización
                if (this.value === 'completado' && fechaFinField.value === '') {
                    fechaFinField.value = new Date().toISOString().split('T')[0];
                    horaFinField.value = new Date().toTimeString().slice(0, 5);
                }
            });
        });
    </script>
</body>

</html>