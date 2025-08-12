<?php

/**
 * Reportar Incidencia - Bitácora
 * Permite a los usuarios reportar problemas con los recursos
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

// Verificar permisos específicos
$es_admin = has_role([ROL_ADMIN, ROL_ACADEMICO]);
$es_profesor = $_SESSION['usuario_rol'] == ROL_PROFESOR;

if (!$es_admin && !$es_profesor) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reserva = $_POST['id_reserva'] ?? '';
    $id_recurso = $_POST['id_recurso'] ?? '';
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $prioridad = $_POST['prioridad'] ?? 'media';
    $fecha_incidencia = $_POST['fecha_incidencia'] ?? date('Y-m-d H:i:s');
    
    // Validaciones
    $errores = [];
    
    if (empty($titulo)) {
        $errores[] = "El título es obligatorio";
    }
    
    if (empty($descripcion)) {
        $errores[] = "La descripción es obligatoria";
    }
    
    // Para profesores, el recurso es obligatorio
    if ($es_profesor && empty($id_recurso)) {
        $errores[] = "Debe seleccionar un recurso";
    }
    
    if (empty($errores)) {
        $recurso_info = null;
        
        // Si se seleccionó una reserva, obtener información del recurso
        if (!empty($id_reserva)) {
            $reserva = $db->getRow("
                SELECT r.*, rc.nombre as nombre_recurso, rc.id_recurso
                FROM reservas r
                JOIN recursos rc ON r.id_recurso = rc.id_recurso
                WHERE r.id_reserva = ? AND r.id_usuario = ?
            ", [$id_reserva, $_SESSION['usuario_id']]);
            
            if ($reserva) {
                $recurso_info = $reserva;
                $id_recurso = $reserva['id_recurso'];
            } else {
                $errores[] = "La reserva seleccionada no es válida";
            }
        } elseif (!empty($id_recurso)) {
            // Si se seleccionó directamente un recurso
            $recurso = $db->getRow("SELECT * FROM recursos WHERE id_recurso = ?", [$id_recurso]);
            if ($recurso) {
                $recurso_info = $recurso;
            } else {
                $errores[] = "El recurso seleccionado no es válido";
            }
        }
        
        if (empty($errores) && $recurso_info) {
            // Insertar la incidencia
            $datos_incidencia = [
                'id_usuario' => $_SESSION['usuario_id'],
                'id_recurso' => $id_recurso,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'prioridad' => $prioridad,
                'fecha_reporte' => $fecha_incidencia
            ];
            
            // Agregar id_reserva si existe
            if (!empty($id_reserva)) {
                $datos_incidencia['id_reserva'] = $id_reserva;
            }
            
            $resultado = $db->insert("bitacora_incidencias", $datos_incidencia);
            
            if ($resultado) {
                // Registrar en log de acciones
                $db->insert("log_acciones", [
                    'id_usuario' => $_SESSION['usuario_id'],
                    'accion' => 'reportar_incidencia',
                    'detalles' => "Incidencia reportada: {$titulo} - Recurso: {$recurso_info['nombre']}",
                    'fecha' => date('Y-m-d H:i:s')
                ]);
                
                // Crear notificación para administradores
                $administradores = $db->getRows("SELECT id_usuario FROM usuarios WHERE id_rol = ?", [ROL_ADMIN]);
                foreach ($administradores as $admin) {
                    $db->insert("notificaciones_incidencias", [
                        'id_incidencia' => $resultado,
                        'id_usuario_destino' => $admin['id_usuario'],
                        'tipo' => 'nueva_incidencia',
                        'mensaje' => "Nueva incidencia reportada: {$titulo} - Recurso: {$recurso_info['nombre']}",
                        'fecha_creacion' => date('Y-m-d H:i:s')
                    ]);
                }
                
                $_SESSION['success'] = "Incidencia reportada exitosamente. Los administradores han sido notificados.";
                redirect('gestionar.php');
                exit;
            } else {
                $errores[] = "Error al reportar la incidencia. Intente nuevamente.";
            }
        }
    }
}

// Obtener las reservas activas del usuario para mostrar en el dropdown (solo si es profesor)
$reservas_usuario = [];
if ($es_profesor) {
    $reservas_usuario = $db->getRows("
        SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado,
               rc.nombre as nombre_recurso, rc.ubicacion,
               t.nombre as tipo_recurso
        FROM reservas r
        JOIN recursos rc ON r.id_recurso = rc.id_recurso
        JOIN tipos_recursos t ON rc.id_tipo = t.id_tipo
        WHERE r.id_usuario = ? 
        AND r.estado IN ('confirmada', 'en_progreso')
        AND r.fecha_fin >= CURDATE()
        ORDER BY r.fecha_inicio DESC
    ", [$_SESSION['usuario_id']]);
}

// Obtener todos los recursos disponibles
$recursos_disponibles = $db->getRows("
    SELECT r.id_recurso, r.nombre, r.ubicacion, t.nombre as tipo_recurso
    FROM recursos r
    JOIN tipos_recursos t ON r.id_tipo = t.id_tipo
    WHERE r.estado = 'disponible'
    ORDER BY r.nombre
");

// Obtener incidencias recientes del usuario
$incidencias_recientes = $db->getRows("
    SELECT bi.id_incidencia, bi.titulo, bi.estado, bi.prioridad, bi.fecha_reporte,
           rc.nombre as nombre_recurso
    FROM bitacora_incidencias bi
    JOIN recursos rc ON bi.id_recurso = rc.id_recurso
    WHERE bi.id_usuario = ?
    ORDER BY bi.fecha_reporte DESC
    LIMIT 5
", [$_SESSION['usuario_id']]);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportar Incidencia - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .incidencia-form {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .prioridad-baja { color: #28a745; }
        .prioridad-media { color: #ffc107; }
        .prioridad-alta { color: #fd7e14; }
        .prioridad-critica { color: #dc3545; }
        
        .reserva-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .incidencias-recientes {
            margin-top: 30px;
        }
        
        .estado-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .estado-reportada { background: #e3f2fd; color: #1976d2; }
        .estado-en_revision { background: #fff3e0; color: #f57c00; }
        .estado-en_proceso { background: #e8f5e8; color: #388e3c; }
        .estado-resuelta { background: #e8f5e8; color: #388e3c; }
        .estado-cerrada { background: #f5f5f5; color: #616161; }
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
                <?php echo generar_menu_navegacion('incidencias'); ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Reportar Incidencia</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errores as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="incidencia-form">
                <div class="card">
                    <div class="card-title">Reportar Nueva Incidencia</div>
                    
                    <?php if (empty($reservas_usuario) && empty($recursos_disponibles)): ?>
                        <div class="alert alert-warning">
                            <p>No tienes reservas activas ni recursos disponibles para reportar incidencias.</p>
                            <p>Puedes reportar incidencias para recursos que ya hayas reservado o para recursos que estén disponibles.</p>
                            <a href="../reservas/calendario.php" class="btn btn-primary">Ir al Calendario de Reservas</a>
                            <a href="../recursos/listar.php" class="btn btn-primary">Ver Recursos Disponibles</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" onsubmit="return validarFormulario()">
                            <?php if ($es_profesor && !empty($reservas_usuario)): ?>
                                <div class="form-group">
                                    <label for="id_reserva">Seleccionar Recurso Reservado (Opcional):</label>
                                    <select name="id_reserva" id="id_reserva" onchange="actualizarRecursoSeleccionado()">
                                        <option value="">-- Seleccione una reserva --</option>
                                        <?php foreach ($reservas_usuario as $reserva): ?>
                                            <option value="<?php echo $reserva['id_reserva']; ?>">
                                                <?php echo htmlspecialchars($reserva['nombre_recurso']); ?> - 
                                                <?php echo format_date($reserva['fecha_inicio'], true); ?> a 
                                                <?php echo format_date($reserva['fecha_fin'], true); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text">Si seleccionas una reserva, el recurso se asignará automáticamente.</small>
                                </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="id_recurso">Seleccionar Recurso <?php echo $es_profesor ? '(Obligatorio si no seleccionas una reserva)' : '(Opcional)'; ?>:</label>
                                <select name="id_recurso" id="id_recurso" <?php echo $es_profesor ? 'required' : ''; ?>>
                                    <option value="">-- Seleccione un recurso --</option>
                                    <?php foreach ($recursos_disponibles as $recurso): ?>
                                        <option value="<?php echo $recurso['id_recurso']; ?>">
                                            <?php echo htmlspecialchars($recurso['nombre']); ?> - 
                                            <?php echo htmlspecialchars($recurso['ubicacion']); ?> - 
                                            <?php echo htmlspecialchars($recurso['tipo_recurso']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="titulo">Título de la Incidencia:</label>
                                <input type="text" name="titulo" id="titulo" 
                                       value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>" 
                                       placeholder="Ej: Problema con el proyector, Equipo no funciona, etc." required>
                            </div>

                            <div class="form-group">
                                <label for="descripcion">Descripción Detallada:</label>
                                <textarea name="descripcion" id="descripcion" 
                                          placeholder="Describe detalladamente el problema o anormalidad observada..." required><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="prioridad">Prioridad:</label>
                                <select name="prioridad" id="prioridad" required>
                                    <option value="baja" <?php echo ($_POST['prioridad'] ?? '') === 'baja' ? 'selected' : ''; ?>>Baja</option>
                                    <option value="media" <?php echo ($_POST['prioridad'] ?? 'media') === 'media' ? 'selected' : ''; ?>>Media</option>
                                    <option value="alta" <?php echo ($_POST['prioridad'] ?? '') === 'alta' ? 'selected' : ''; ?>>Alta</option>
                                    <option value="critica" <?php echo ($_POST['prioridad'] ?? '') === 'critica' ? 'selected' : ''; ?>>Crítica</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="fecha_incidencia">Fecha de Reporte (Opcional):</label>
                                <input type="datetime-local" name="fecha_incidencia" id="fecha_incidencia" 
                                       value="<?php echo htmlspecialchars($_POST['fecha_incidencia'] ?? date('Y-m-d H:i')); ?>">
                                <small class="form-text">Si no especificas una fecha, se usará la fecha y hora actual.</small>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Reportar Incidencia</button>
                                <a href="gestionar.php" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (!empty($incidencias_recientes)): ?>
                    <div class="incidencias-recientes">
                        <div class="card">
                            <div class="card-title">Mis Incidencias Recientes</div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Recurso</th>
                                        <th>Título</th>
                                        <th>Estado</th>
                                        <th>Prioridad</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incidencias_recientes as $incidencia): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($incidencia['nombre_recurso']); ?></td>
                                            <td><?php echo htmlspecialchars($incidencia['titulo']); ?></td>
                                            <td>
                                                <span class="estado-badge estado-<?php echo $incidencia['estado']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $incidencia['estado'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="prioridad-<?php echo $incidencia['prioridad']; ?>">
                                                    <?php echo ucfirst($incidencia['prioridad']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo format_date($incidencia['fecha_reporte'], true); ?></td>
                                            <td>
                                                <a href="ver_incidencia.php?id=<?php echo $incidencia['id_incidencia']; ?>" 
                                                   class="btn btn-small btn-primary">Ver</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="margin-top: 15px;">
                                <a href="mis_incidencias.php" class="btn btn-secondary">Ver Todas las Incidencias</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Función para actualizar el recurso seleccionado cuando se elige una reserva
        function actualizarRecursoSeleccionado() {
            const reservaSelect = document.getElementById('id_reserva');
            const recursoSelect = document.getElementById('id_recurso');
            
            if (reservaSelect.value) {
                // Si se selecciona una reserva, deshabilitar la selección manual de recurso
                recursoSelect.disabled = true;
                recursoSelect.value = '';
            } else {
                // Si no hay reserva seleccionada, habilitar la selección manual de recurso
                recursoSelect.disabled = false;
            }
        }
        
        // Función para validar el formulario antes de enviar
        function validarFormulario() {
            const reservaSelect = document.getElementById('id_reserva');
            const recursoSelect = document.getElementById('id_recurso');
            const titulo = document.getElementById('titulo').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            
            // Validar que se haya seleccionado un recurso (ya sea por reserva o directamente)
            if (!reservaSelect.value && !recursoSelect.value) {
                alert('Debes seleccionar un recurso (ya sea por reserva o directamente)');
                return false;
            }
            
            // Validar campos obligatorios
            if (!titulo) {
                alert('El título es obligatorio');
                return false;
            }
            
            if (!descripcion) {
                alert('La descripción es obligatoria');
                return false;
            }
            
            return true;
        }
        
        // Agregar validación al formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!validarFormulario()) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>

</html> 