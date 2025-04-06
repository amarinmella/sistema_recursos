<?php

/**
 * Formulario para crear reservas
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado
require_login();

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Verificar si se especificó un recurso
$recurso_id = isset($_GET['recurso']) ? intval($_GET['recurso']) : 0;
$recurso = null;

if ($recurso_id > 0) {
    // Obtener información del recurso
    $recurso = $db->getRow("SELECT * FROM recursos WHERE id_recurso = ?", [$recurso_id]);

    // Verificar si el recurso existe y está disponible
    if (!$recurso || !$recurso['disponible'] || $recurso['estado'] !== 'disponible') {
        $_SESSION['error'] = "El recurso seleccionado no está disponible para reservas";
        redirect('listar.php');
        exit;
    }
}

// Obtener lista de recursos disponibles para reserva
$recursos = $db->getRows(
    "SELECT id_recurso, nombre, ubicacion FROM recursos 
     WHERE disponible = 1 AND estado = 'disponible' 
     ORDER BY nombre"
);

if (empty($recursos) && !$recurso) {
    $_SESSION['error'] = "No hay recursos disponibles para reservar";
    redirect('listar.php');
    exit;
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
    $fecha_inicio_date = $_POST['fecha_inicio'] ?? '';
    $fecha_inicio_time = $_POST['hora_inicio'] ?? '';
    $fecha_fin_date = $_POST['fecha_fin'] ?? '';
    $fecha_fin_time = $_POST['hora_fin'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');

    // Combinar fecha y hora
    $fecha_inicio = $fecha_inicio_date . ' ' . $fecha_inicio_time . ':00';
    $fecha_fin = $fecha_fin_date . ' ' . $fecha_fin_time . ':00';

    // Validar datos
    $errores = [];

    if ($id_recurso <= 0) {
        $errores[] = "Debes seleccionar un recurso";
    } else {
        // Verificar que el recurso exista y esté disponible
        $recurso_check = $db->getRow(
            "SELECT id_recurso FROM recursos WHERE id_recurso = ? AND disponible = 1 AND estado = 'disponible'",
            [$id_recurso]
        );

        if (!$recurso_check) {
            $errores[] = "El recurso seleccionado no está disponible para reservas";
        }
    }

    if (empty($fecha_inicio_date) || empty($fecha_inicio_time)) {
        $errores[] = "La fecha y hora de inicio son obligatorias";
    } elseif (strtotime($fecha_inicio) < time()) {
        $errores[] = "La fecha de inicio debe ser futura";
    }

    if (empty($fecha_fin_date) || empty($fecha_fin_time)) {
        $errores[] = "La fecha y hora de fin son obligatorias";
    } elseif (strtotime($fecha_fin) <= strtotime($fecha_inicio)) {
        $errores[] = "La fecha de fin debe ser posterior a la fecha de inicio";
    }

    // Verificar disponibilidad
    if (empty($errores)) {
        $sql = "SELECT COUNT(*) as conflictos FROM reservas 
                WHERE id_recurso = ? AND estado IN ('pendiente', 'confirmada') 
                AND (
                    (? BETWEEN fecha_inicio AND fecha_fin)
                    OR (? BETWEEN fecha_inicio AND fecha_fin)
                    OR (fecha_inicio BETWEEN ? AND ?)
                )";

        $resultado = $db->getRow($sql, [$id_recurso, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin]);

        if ($resultado && $resultado['conflictos'] > 0) {
            $errores[] = "El recurso ya está reservado para el período seleccionado";
        }
    }

    // Si hay errores, mostrarlos
    if (!empty($errores)) {
        $_SESSION['error'] = implode('<br>', $errores);
        redirect('crear.php' . ($recurso_id > 0 ? "?recurso={$recurso_id}" : ""));
        exit;
    }

    // Determinar estado inicial (los administradores y académicos confirman automáticamente)
    $estado = has_role([ROL_ADMIN, ROL_ACADEMICO]) ? 'confirmada' : 'pendiente';

    // Preparar datos para insertar
    $data = [
        'id_recurso' => $id_recurso,
        'id_usuario' => $_SESSION['usuario_id'],
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin' => $fecha_fin,
        'descripcion' => $descripcion,
        'estado' => $estado,
        'fecha_creacion' => date('Y-m-d H:i:s')
    ];

    // Insertar en la base de datos
    $resultado = $db->insert('reservas', $data);

    if ($resultado) {
        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'crear',
            'entidad' => 'reservas',
            'id_entidad' => $resultado,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s')
        ];
        $db->insert('log_acciones', $log_data);

        // Si es una reserva pendiente, crear notificación para administradores y académicos
        if ($estado === 'pendiente') {
            // Obtener nombre del recurso
            $nombre_recurso = $db->getRow("SELECT nombre FROM recursos WHERE id_recurso = ?", [$id_recurso])['nombre'] ?? 'Recurso';

            // Crear notificación para administradores y académicos
            $admins = $db->getRows("SELECT id_usuario FROM usuarios WHERE id_rol IN (?, ?) AND activo = 1", [ROL_ADMIN, ROL_ACADEMICO]);

            foreach ($admins as $admin) {
                $notificacion_data = [
                    'id_reserva' => $resultado,
                    'id_usuario' => $admin['id_usuario'],
                    'mensaje' => "Nueva reserva pendiente para '{$nombre_recurso}' el " . date('d/m/Y', strtotime($fecha_inicio)),
                    'leido' => 0,
                    'fecha' => date('Y-m-d H:i:s')
                ];

                $db->insert('notificaciones', $notificacion_data);
            }
        }

        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Reserva creada correctamente" . ($estado === 'pendiente' ? ". Debe ser confirmada por un administrador." : "");
        redirect('listar.php');
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al crear la reserva: " . $db->getError();
        redirect('crear.php' . ($recurso_id > 0 ? "?recurso={$recurso_id}" : ""));
        exit;
    }
}

// Obtener fecha y hora actuales para los valores por defecto
$fecha_actual = date('Y-m-d');
$hora_actual = date('H:i');

// Sugerir fecha fin (2 horas después)
$fecha_fin = date('Y-m-d');
$hora_fin = date('H:i', strtotime('+2 hours'));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Reserva - Sistema de Gestión de Recursos</title>
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
                <a href="../reservas/listar.php" class="nav-item active">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                    <a href="../reportes/index.php" class="nav-item">Reportes</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Crear Nueva Reserva</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <h2 class="form-title">Información de la Reserva</h2>

                <form action="" method="POST" class="reservation-form">
                    <div class="form-group">
                        <label for="id_recurso">Recurso *</label>
                        <select id="id_recurso" name="id_recurso" required <?php echo $recurso ? 'disabled' : ''; ?>>
                            <option value="">Seleccione un recurso</option>
                            <?php foreach ($recursos as $rec): ?>
                                <option value="<?php echo $rec['id_recurso']; ?>" <?php echo ($recurso && $recurso['id_recurso'] == $rec['id_recurso']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rec['nombre'] . ($rec['ubicacion'] ? ' (' . $rec['ubicacion'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($recurso): ?>
                            <input type="hidden" name="id_recurso" value="<?php echo $recurso['id_recurso']; ?>">
                        <?php endif; ?>
                    </div>

                    <div class="form-row" style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="fecha_inicio">Fecha de Inicio *</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_actual; ?>" min="<?php echo $fecha_actual; ?>" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="hora_inicio">Hora de Inicio *</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" value="<?php echo $hora_actual; ?>" required>
                        </div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="fecha_fin">Fecha de Fin *</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>" min="<?php echo $fecha_actual; ?>" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="hora_fin">Hora de Fin *</label>
                            <input type="time" id="hora_fin" name="hora_fin" value="<?php echo $hora_fin; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción / Propósito</label>
                        <textarea id="descripcion" name="descripcion" rows="4"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Crear Reserva</button>
                        <button type="button" id="btn-check" class="btn btn-secondary">Verificar Disponibilidad</button>
                        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>

            <div id="disponibilidad-resultado" class="card" style="display: none;">
                <h2 class="card-title">Resultado de Verificación</h2>
                <div id="disponibilidad-contenido">
                    <!-- Aquí se mostrará el resultado de la verificación de disponibilidad -->
                </div>
            </div>

            <div class="card">
                <h2 class="form-title">Información Adicional</h2>

                <div class="alert alert-info">
                    <strong>Nota:</strong> Las reservas pueden estar en los siguientes estados:
                    <ul style="margin-top: 10px; margin-bottom: 0;">
                        <li><strong>Pendiente:</strong> La reserva ha sido creada pero requiere confirmación.</li>
                        <li><strong>Confirmada:</strong> La reserva ha sido aprobada y el recurso está reservado para el período indicado.</li>
                        <li><strong>Cancelada:</strong> La reserva ha sido cancelada y el recurso está disponible para otras reservas.</li>
                        <li><strong>Completada:</strong> La reserva ha finalizado y el recurso ha sido utilizado según lo previsto.</li>
                    </ul>
                </div>

                <?php if (!has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <div class="alert alert-warning" style="margin-top: 20px;">
                        Las reservas creadas por usuarios regulares quedan en estado "Pendiente" hasta que sean confirmadas por un administrador.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.reservation-form');
            const btnCheck = document.getElementById('btn-check');
            const disponibilidadResultado = document.getElementById('disponibilidad-resultado');
            const disponibilidadContenido = document.getElementById('disponibilidad-contenido');

            // Función para validar y verificar disponibilidad
            function verificarDisponibilidad() {
                // Obtener valores del formulario
                const idRecurso = document.getElementById('id_recurso').value;
                const fechaInicio = document.getElementById('fecha_inicio').value;
                const horaInicio = document.getElementById('hora_inicio').value;
                const fechaFin = document.getElementById('fecha_fin').value;
                const horaFin = document.getElementById('hora_fin').value;

                // Validar datos
                if (!idRecurso) {
                    alert('Debe seleccionar un recurso');
                    return false;
                }

                if (!fechaInicio || !horaInicio) {
                    alert('Debe especificar la fecha y hora de inicio');
                    return false;
                }

                if (!fechaFin || !horaFin) {
                    alert('Debe especificar la fecha y hora de fin');
                    return false;
                }

                // Convertir a objetos Date
                const fechaInicioObj = new Date(`${fechaInicio}T${horaInicio}`);
                const fechaFinObj = new Date(`${fechaFin}T${horaFin}`);

                // Validar fechas
                if (fechaInicioObj < new Date()) {
                    alert('La fecha de inicio debe ser futura');
                    return false;
                }

                if (fechaFinObj <= fechaInicioObj) {
                    alert('La fecha de fin debe ser posterior a la fecha de inicio');
                    return false;
                }

                // Mostrar información de verificación
                disponibilidadContenido.innerHTML = '<p>Verificando disponibilidad...</p>';
                disponibilidadResultado.style.display = 'block';

                // Construir parámetros para la consulta
                const params = new URLSearchParams({
                    id_recurso: idRecurso,
                    fecha_inicio: `${fechaInicio} ${horaInicio}:00`,
                    fecha_fin: `${fechaFin} ${horaFin}:00`
                });

                // Realizar solicitud AJAX para verificar disponibilidad
                fetch(`verificar_disponibilidad.php?${params.toString()}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.disponible) {
                            disponibilidadContenido.innerHTML = `
                                <div class="alert alert-success">
                                    <strong>Disponible:</strong> El recurso está disponible para el período seleccionado.
                                </div>
                            `;
                        } else {
                            let message = `
                                <div class="alert alert-error">
                                    <strong>No Disponible:</strong> El recurso ya está reservado para el período seleccionado.
                                </div>
                                <p>Reservas existentes en conflicto:</p>
                                <ul>
                            `;

                            data.reservas.forEach(reserva => {
                                message += `<li>Desde ${reserva.fecha_inicio} hasta ${reserva.fecha_fin}</li>`;
                            });

                            message += '</ul>';
                            disponibilidadContenido.innerHTML = message;
                        }
                    })
                    .catch(error => {
                        disponibilidadContenido.innerHTML = `
                            <div class="alert alert-error">
                                <strong>Error:</strong> No se pudo verificar la disponibilidad. Intente nuevamente.
                            </div>
                        `;
                        console.error('Error:', error);
                    });

                return true;
            }

            // Evento para el botón de verificar
            btnCheck.addEventListener('click', function(event) {
                event.preventDefault();
                verificarDisponibilidad();
            });

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

                // Validar fecha y hora de fin
                const fechaFinField = document.getElementById('fecha_fin');
                const horaFinField = document.getElementById('hora_fin');

                if (fechaFinField.value === '' || horaFinField.value === '') {
                    showError(fechaFinField, 'Debe especificar la fecha y hora de fin');
                    hasError = true;
                } else {
                    removeError(fechaFinField);
                }

                // Validar que la fecha de inicio sea futura
                if (fechaInicioField.value !== '' && horaInicioField.value !== '') {
                    const fechaInicioObj = new Date(`${fechaInicioField.value}T${horaInicioField.value}`);

                    if (fechaInicioObj < new Date()) {
                        showError(fechaInicioField, 'La fecha de inicio debe ser futura');
                        hasError = true;
                    }
                }

                // Validar que la fecha de fin sea posterior a la de inicio
                if (fechaInicioField.value !== '' && horaInicioField.value !== '' &&
                    fechaFinField.value !== '' && horaFinField.value !== '') {

                    const fechaInicioObj = new Date(`${fechaInicioField.value}T${horaInicioField.value}`);
                    const fechaFinObj = new Date(`${fechaFinField.value}T${horaFinField.value}`);

                    if (fechaFinObj <= fechaInicioObj) {
                        showError(fechaFinField, 'La fecha de fin debe ser posterior a la fecha de inicio');
                        hasError = true;
                    }
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