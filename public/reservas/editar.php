<?php

/**
 * Formulario para editar reservas
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado
require_login();

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de reserva no especificado";
    redirect('listar.php');
    exit;
}

$id_reserva = intval($_GET['id']);

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Obtener datos de la reserva
$sql = "SELECT r.*, u.nombre as usuario_nombre, u.apellido as usuario_apellido,
               u.email as usuario_email, rc.nombre as recurso_nombre, 
               rc.ubicacion as recurso_ubicacion
        FROM reservas r
        JOIN usuarios u ON r.id_usuario = u.id_usuario
        JOIN recursos rc ON r.id_recurso = rc.id_recurso
        WHERE r.id_reserva = ?";
$reserva = $db->getRow($sql, [$id_reserva]);

if (!$reserva) {
    $_SESSION['error'] = "La reserva no existe";
    redirect('listar.php');
    exit;
}

// Verificar permisos (solo propietario o admin/académico pueden editar)
$es_propietario = $reserva['id_usuario'] == $_SESSION['usuario_id'];
$es_admin = has_role([ROL_ADMIN, ROL_ACADEMICO]);

if (!$es_propietario && !$es_admin) {
    $_SESSION['error'] = "No tienes permisos para editar esta reserva";
    redirect('listar.php');
    exit;
}

// Verificar que la reserva esté en estado pendiente o confirmada
if (!in_array($reserva['estado'], ['pendiente', 'confirmada'])) {
    $_SESSION['error'] = "Solo se pueden editar reservas pendientes o confirmadas";
    redirect('ver.php?id=' . $id_reserva);
    exit;
}

// Verificar que la fecha de inicio sea futura
if (strtotime($reserva['fecha_inicio']) <= time()) {
    $_SESSION['error'] = "Solo se pueden editar reservas futuras";
    redirect('ver.php?id=' . $id_reserva);
    exit;
}

// Obtener lista de recursos disponibles (solo admin puede cambiar el recurso)
$recursos = [];
if ($es_admin) {
    $recursos = $db->getRows(
        "SELECT id_recurso, nombre, ubicacion FROM recursos 
         WHERE disponible = 1 AND estado = 'disponible' 
         ORDER BY nombre"
    );
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

// Separar las fechas y horas para el formulario
$fecha_inicio_parts = explode(' ', $reserva['fecha_inicio']);
$fecha_inicio = $fecha_inicio_parts[0];
$hora_inicio = substr($fecha_inicio_parts[1], 0, 5);

$fecha_fin_parts = explode(' ', $reserva['fecha_fin']);
$fecha_fin = $fecha_fin_parts[0];
$hora_fin = substr($fecha_fin_parts[1], 0, 5);

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos
    $id_recurso = $es_admin ? intval($_POST['id_recurso'] ?? 0) : $reserva['id_recurso'];
    $fecha_inicio_date = $_POST['fecha_inicio'] ?? '';
    $fecha_inicio_time = $_POST['hora_inicio'] ?? '';
    $fecha_fin_date = $_POST['fecha_fin'] ?? '';
    $fecha_fin_time = $_POST['hora_fin'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');

    // Combinar fecha y hora
    $fecha_inicio_new = $fecha_inicio_date . ' ' . $fecha_inicio_time . ':00';
    $fecha_fin_new = $fecha_fin_date . ' ' . $fecha_fin_time . ':00';

    // Validar datos
    $errores = [];

    if ($es_admin && $id_recurso <= 0) {
        $errores[] = "Debes seleccionar un recurso";
    } elseif ($es_admin) {
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
    } elseif (strtotime($fecha_inicio_new) < time()) {
        $errores[] = "La fecha de inicio debe ser futura";
    }

    if (empty($fecha_fin_date) || empty($fecha_fin_time)) {
        $errores[] = "La fecha y hora de fin son obligatorias";
    } elseif (strtotime($fecha_fin_new) <= strtotime($fecha_inicio_new)) {
        $errores[] = "La fecha de fin debe ser posterior a la fecha de inicio";
    }

    // Verificar disponibilidad (solo si cambian las fechas o el recurso)
    if (empty($errores) && (
        $fecha_inicio_new != $reserva['fecha_inicio'] ||
        $fecha_fin_new != $reserva['fecha_fin'] ||
        $id_recurso != $reserva['id_recurso']
    )) {
        $sql = "SELECT COUNT(*) as conflictos FROM reservas 
                WHERE id_recurso = ? AND id_reserva != ? AND estado IN ('pendiente', 'confirmada') 
                AND (
                    (? BETWEEN fecha_inicio AND fecha_fin)
                    OR (? BETWEEN fecha_inicio AND fecha_fin)
                    OR (fecha_inicio BETWEEN ? AND ?)
                )";

        $resultado = $db->getRow($sql, [$id_recurso, $id_reserva, $fecha_inicio_new, $fecha_fin_new, $fecha_inicio_new, $fecha_fin_new]);

        if ($resultado && $resultado['conflictos'] > 0) {
            $errores[] = "El recurso ya está reservado para el período seleccionado";
        }
    }

    // Si hay errores, mostrarlos
    if (!empty($errores)) {
        $_SESSION['error'] = implode('<br>', $errores);
        redirect('editar.php?id=' . $id_reserva);
        exit;
    }

    // Preparar datos para actualizar
    $data = [
        'fecha_inicio' => $fecha_inicio_new,
        'fecha_fin' => $fecha_fin_new,
        'descripcion' => $descripcion
    ];

    // Si es admin, también puede cambiar el recurso
    if ($es_admin) {
        $data['id_recurso'] = $id_recurso;
    }

    // Actualizar en la base de datos
    $resultado = $db->update('reservas', $data, 'id_reserva = ?', [$id_reserva]);

    if ($resultado) {
        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'actualizar',
            'entidad' => 'reservas',
            'id_entidad' => $id_reserva,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s')
        ];
        $db->insert('log_acciones', $log_data);

        // Crear notificación si no es el propietario quien edita
        if (!$es_propietario) {
            $mensaje = "Tu reserva para '{$reserva['recurso_nombre']}' ha sido modificada por un administrador. " .
                "Nueva fecha: " . date('d/m/Y H:i', strtotime($fecha_inicio_new)) . " - " .
                date('d/m/Y H:i', strtotime($fecha_fin_new));

            $db->insert('notificaciones', [
                'id_reserva' => $id_reserva,
                'id_usuario' => $reserva['id_usuario'],
                'mensaje' => $mensaje,
                'leido' => 0,
                'fecha' => date('Y-m-d H:i:s')
            ]);
        }

        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Reserva actualizada correctamente";
        redirect('ver.php?id=' . $id_reserva);
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al actualizar la reserva: " . $db->getError();
        redirect('editar.php?id=' . $id_reserva);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Reserva - Sistema de Gestión de Recursos</title>
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
                <h1>Editar Reserva</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <h2 class="form-title">Información de la Reserva</h2>

                <form action="" method="POST" class="reservation-form">
                    <?php if ($es_admin && !empty($recursos)): ?>
                        <div class="form-group">
                            <label for="id_recurso">Recurso *</label>
                            <select id="id_recurso" name="id_recurso" required>
                                <option value="">Seleccione un recurso</option>
                                <?php foreach ($recursos as $recurso): ?>
                                    <option value="<?php echo $recurso['id_recurso']; ?>" <?php echo ($reserva['id_recurso'] == $recurso['id_recurso']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($recurso['nombre'] . ($recurso['ubicacion'] ? ' (' . $recurso['ubicacion'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Recurso</label>
                            <p><strong><?php echo htmlspecialchars($reserva['recurso_nombre']); ?></strong>
                                <?php echo !empty($reserva['recurso_ubicacion']) ? '(' . htmlspecialchars($reserva['recurso_ubicacion']) . ')' : ''; ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="form-row" style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="fecha_inicio">Fecha de Inicio *</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="hora_inicio">Hora de Inicio *</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" value="<?php echo $hora_inicio; ?>" required>
                        </div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="fecha_fin">Fecha de Fin *</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="hora_fin">Hora de Fin *</label>
                            <input type="time" id="hora_fin" name="hora_fin" value="<?php echo $hora_fin; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción / Propósito</label>
                        <textarea id="descripcion" name="descripcion" rows="4"><?php echo htmlspecialchars($reserva['descripcion']); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <button type="button" id="btn-check" class="btn btn-secondary">Verificar Disponibilidad</button>
                        <a href="ver.php?id=<?php echo $id_reserva; ?>" class="btn btn-secondary">Cancelar</a>
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
                    <strong>Nota:</strong> Al modificar una reserva, se verifica que no haya conflictos con otras reservas existentes.
                </div>

                <div class="alert alert-warning" style="margin-top: 20px;">
                    <strong>Importante:</strong> Solo se pueden modificar reservas futuras y que estén en estado "Pendiente" o "Confirmada".
                </div>
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
                const idRecurso = <?php echo $es_admin ? "document.getElementById('id_recurso').value" : $reserva['id_recurso']; ?>;
                const fechaInicio = document.getElementById('fecha_inicio').value;
                const horaInicio = document.getElementById('hora_inicio').value;
                const fechaFin = document.getElementById('fecha_fin').value;
                const horaFin = document.getElementById('hora_fin').value;

                // Validar datos
                if (<?php echo $es_admin ? "!idRecurso" : "false"; ?>) {
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
                    id_reserva: <?php echo $id_reserva; ?>,
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
                <?php if ($es_admin): ?>
                    const recursoField = document.getElementById('id_recurso');
                    if (recursoField.value === '') {
                        showError(recursoField, 'Debe seleccionar un recurso');
                        hasError = true;
                    } else {
                        removeError(recursoField);
                    }
                <?php endif; ?>

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

                // Validar que fecha fin sea posterior a fecha inicio
                if (!hasError) {
                    const fechaInicioObj = new Date(`${fechaInicioField.value}T${horaInicioField.value}`);
                    const fechaFinObj = new Date(`${fechaFinField.value}T${horaFinField.value}`);

                    if (fechaFinObj <= fechaInicioObj) {
                        showError(fechaFinField, 'La fecha de fin debe ser posterior a la fecha de inicio');
                        hasError = true;
                    }
                }

                // Si hay errores, evitar el envío del formulario
                if (hasError) {
                    event.preventDefault();
                    return false;
                }
            });

            // Funciones para mostrar y quitar errores
            function showError(element, message) {
                // Buscar o crear el contenedor de error
                let errorDiv = element.parentNode.querySelector('.error-message');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    element.parentNode.appendChild(errorDiv);
                }
                errorDiv.textContent = message;
                element.classList.add('error');
            }

            function removeError(element) {
                const errorDiv = element.parentNode.querySelector('.error-message');
                if (errorDiv) {
                    errorDiv.remove();
                }
                element.classList.remove('error');
            }
        });
    </script>
</body>

</html>