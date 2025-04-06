<?php
/**
 * Ver detalles de mantenimiento
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
               r.estado as recurso_estado,
               tr.nombre as tipo_recurso,
               u.nombre as usuario_nombre, 
               u.apellido as usuario_apellido
        FROM mantenimiento m
        JOIN recursos r ON m.id_recurso = r.id_recurso
        JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
        JOIN usuarios u ON m.id_usuario = u.id_usuario
        WHERE m.id_mantenimiento = ?";

$mantenimiento = $db->getRow($sql, [$id_mantenimiento]);

if (!$mantenimiento) {
    $_SESSION['error'] = "El mantenimiento no existe";
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

// Obtener historial de cambios del mantenimiento
$sql_log = "SELECT l.*, CONCAT(u.nombre, ' ', u.apellido) as usuario
            FROM log_acciones l
            JOIN usuarios u ON l.id_usuario = u.id_usuario
            WHERE l.entidad = 'mantenimiento' AND l.id_entidad = ?
            ORDER BY l.fecha DESC";

$historial = $db->getRows($sql_log, [$id_mantenimiento]);

// Procesar acción para completar mantenimiento
if (isset($_POST['accion']) && $_POST['accion'] === 'completar') {
    // Actualizar el estado del mantenimiento a completado
    $fecha_fin = date('Y-m-d H:i:s');
    $resultado = $db->update('mantenimiento', [
        'estado' => 'completado',
        'fecha_fin' => $fecha_fin
    ], 'id_mantenimiento = ?', [$id_mantenimiento]);
    
    if ($resultado) {
        // Actualizar el estado del recurso a disponible
        $db->update('recursos', [
            'estado' => 'disponible',
            'disponible' => 1
        ], 'id_recurso = ?', [$mantenimiento['id_recurso']]);
        
        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'actualizar',
            'entidad' => 'mantenimiento',
            'id_entidad' => $id_mantenimiento,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s'),
            'detalles' => 'Mantenimiento completado'
        ];
        $db->insert('log_acciones', $log_data);
        
        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Mantenimiento marcado como completado correctamente";
        redirect('ver.php?id=' . $id_mantenimiento);
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al completar el mantenimiento: " . $db->getError();
        redirect('ver.php?id=' . $id_mantenimiento);
        exit;
    }
}

// Procesar acción para iniciar mantenimiento
if (isset($_POST['accion']) && $_POST['accion'] === 'iniciar') {
    // Actualizar el estado del mantenimiento a en progreso
    $resultado = $db->update('mantenimiento', [
        'estado' => 'en progreso'
    ], 'id_mantenimiento = ?', [$id_mantenimiento]);
    
    if ($resultado) {
        // Asegurar que el recurso esté marcado como en mantenimiento
        $db->update('recursos', [
            'estado' => 'mantenimiento',
            'disponible' => 0
        ], 'id_recurso = ?', [$mantenimiento['id_recurso']]);
        
        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'actualizar',
            'entidad' => 'mantenimiento',
            'id_entidad' => $id_mantenimiento,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s'),
            'detalles' => 'Mantenimiento iniciado'
        ];
        $db->insert('log_acciones', $log_data);
        
        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Mantenimiento iniciado correctamente";
        redirect('ver.php?id=' . $id_mantenimiento);
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al iniciar el mantenimiento: " . $db->getError();
        redirect('ver.php?id=' . $id_mantenimiento);
        exit;
    }
}

// Procesar acción para cancelar mantenimiento
if (isset($_POST['accion']) && $_POST['accion'] === 'cancelar') {
    // Verificar si el mantenimiento ya está completado
    if ($mantenimiento['estado'] === 'completado') {
        $_SESSION['error'] = "No se puede cancelar un mantenimiento que ya está completado";
        redirect('ver.php?id=' . $id_mantenimiento);
        exit;
    }
    
    // Actualizar el estado del mantenimiento a completado (pero marcando como cancelado en detalles)
    $fecha_fin = date('Y-m-d H:i:s');
    $resultado = $db->update('mantenimiento', [
        'estado' => 'completado',
        'fecha_fin' => $fecha_fin,
        'descripcion' => $mantenimiento['descripcion'] . "\n\n[CANCELADO: " . date('d/m/Y H:i') . "]"
    ], 'id_mantenimiento = ?', [$id_mantenimiento]);
    
    if ($resultado) {
        // Actualizar el estado del recurso a disponible
        $db->update('recursos', [
            'estado' => 'disponible',
            'disponible' => 1
        ], 'id_recurso = ?', [$mantenimiento['id_recurso']]);
        
        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'actualizar',
            'entidad' => 'mantenimiento',
            'id_entidad' => $id_mantenimiento,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s'),
            'detalles' => 'Mantenimiento cancelado'
        ];
        $db->insert('log_acciones', $log_data);
        
        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Mantenimiento cancelado correctamente";
        redirect('ver.php?id=' . $id_mantenimiento);
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al cancelar el mantenimiento: " . $db->getError();
        redirect('ver.php?id=' . $id_mantenimiento);
        exit;
    }
}

// Función para obtener clase CSS según el estado
function getEstadoClass($estado) {
    switch ($estado) {
        case 'pendiente':
            return 'badge-warning';
        case 'en progreso':
            return 'badge-primary';
        case 'completado':
            return 'badge-success';
        default:
            return 'badge-secondary';
    }
}

// Obtener reservas afectadas por este mantenimiento
$sql_reservas = "
    SELECT r.id_reserva, r.fecha_inicio, r.fecha_fin, r.estado,
           CONCAT(u.nombre, ' ', u.apellido) as usuario
    FROM reservas r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.id_recurso = ?
    AND (
        (r.fecha_inicio BETWEEN ? AND IFNULL(?, NOW() + INTERVAL 30 DAY))
        OR (r.fecha_fin BETWEEN ? AND IFNULL(?, NOW() + INTERVAL 30 DAY))
        OR (r.fecha_inicio <= ? AND r.fecha_fin >= ?)
    )
    AND r.estado IN ('pendiente', 'confirmada')
    ORDER BY r.fecha_inicio
";

$reservas_afectadas = $db->getRows($sql_reservas, [
    $mantenimiento['id_recurso'],
    $mantenimiento['fecha_inicio'],
    $mantenimiento['fecha_fin'],
    $mantenimiento['fecha_inicio'],
    $mantenimiento['fecha_fin'],
    $mantenimiento['fecha_inicio'],
    $mantenimiento['fecha_inicio']
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Mantenimiento - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .estado-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            min-width: 100px;
        }
        
        .info-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .info-section:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        
        .info-value {
            margin-bottom: 15px;
        }
        
        .descripcion-box {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            white-space: pre-line;
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
                <h1>Detalles de Mantenimiento</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>
            
            <?php echo $mensaje; ?>
            
            <div style="margin-bottom: 20px;">
                <a href="listar.php" class="btn btn-secondary">&laquo; Volver a la lista</a>
                <a href="editar.php?id=<?php echo $id_mantenimiento; ?>" class="btn btn-primary">Editar Mantenimiento</a>
            </div>
            
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 class="card-title">Mantenimiento #<?php echo $id_mantenimiento; ?></h2>
                    <div class="estado-badge <?php echo getEstadoClass($mantenimiento['estado']); ?>">
                        <?php echo ucfirst($mantenimiento['estado']); ?>
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="info-label">Recurso:</div>
                    <div class="info-value">
                        <strong><?php echo htmlspecialchars($mantenimiento['recurso_nombre']); ?></strong>
                        <?php if (!empty($mantenimiento['recurso_ubicacion'])): ?>
                            (<?php echo htmlspecialchars($mantenimiento['recurso_ubicacion']); ?>)
                        <?php endif; ?>
                        <br>
                        <small>Tipo: <?php echo htmlspecialchars($mantenimiento['tipo_recurso']); ?></small>
                        <br>
                        <small>Estado actual del recurso: 
                            <span class="badge <?php echo $mantenimiento['recurso_estado'] === 'disponible' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo ucfirst($mantenimiento['recurso_estado']); ?>
                            </span>
                        </small>
                    </div>
                    
                    <div class="info-label">Responsable:</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($mantenimiento['usuario_nombre'] . ' ' . $mantenimiento['usuario_apellido']); ?>
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="info-label">Fecha de Inicio:</div>
                    <div class="info-value">
                        <?php echo date('d/m/Y H:i', strtotime($mantenimiento['fecha_inicio'])); ?>
                    </div>
                    
                    <div class="info-label">Fecha de Finalización:</div>
                    <div class="info-value">
                        <?php if ($mantenimiento['fecha_fin']): ?>
                            <?php echo date('d/m/Y H:i', strtotime($mantenimiento['fecha_fin'])); ?>
                            <br>
                            <small>Duración: 
                                <?php 
                                $inicio = new DateTime($mantenimiento['fecha_inicio']);
                                $fin = new DateTime($mantenimiento['fecha_fin']);
                                $duracion = $inicio->diff($fin);
                                
                                if ($duracion->days > 0) {
                                    echo $duracion->days . ' día(s), ';
                                }
                                
                                echo $duracion->h . ' hora(s), ' . $duracion->i . ' minuto(s)';
                                ?>
                            </small>
                        <?php else: ?>
                            <span class="badge badge-warning">No finalizado</span>
                            
                            <?php if ($mantenimiento['estado'] !== 'completado'): ?>
                                <form action="" method="POST" style="display: inline-block; margin-left: 10px;">
                                    <input type="hidden" name="accion" value="completar">
                                    <button type="submit" class="btn btn-success" onclick="return confirm('¿Estás seguro de marcar este mantenimiento como completado?')">
                                        Marcar como Completado
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-label">Fecha de Registro:</div>
                    <div class="info-value">
                        <?php echo date('d/m/Y H:i', strtotime($mantenimiento['fecha_registro'])); ?>
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="info-label">Descripción del Mantenimiento:</div>
                    <div class="info-value">
                        <div class="descripcion-box">
                            <?php echo nl2br(htmlspecialchars($mantenimiento['descripcion'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                    <?php if ($mantenimiento['estado'] === 'pendiente'): ?>
                        <form action="" method="POST" style="display: inline-block; margin-right: 10px;">
                            <input type="hidden" name="accion" value="cancelar">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de cancelar este mantenimiento? El recurso volverá a estar disponible.')">
                                Cancelar Mantenimiento
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="editar.php?id=<?php echo $id_mantenimiento; ?>" class="btn btn-secondary">
                        Editar Información
                    </a>
                </div>
            </div>
            
            <?php if (!empty($reservas_afectadas)): ?>
                <div class="card">
                    <h2 class="card-title">Reservas Afectadas por este Mantenimiento</h2>
                    
                    <div class="alert alert-warning">
                        <strong>Nota:</strong> Las siguientes reservas podrían verse afectadas por este mantenimiento.
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
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservas_afectadas as $reserva): ?>
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
                                        <td>
                                            <a href="../reservas/ver.php?id=<?php echo $reserva['id_reserva']; ?>" class="accion-btn btn-editar">
                                                Ver Detalles
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($historial)): ?>
                <div class="card">
                    <h2 class="card-title">Historial de Acciones</h2>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Detalles</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial as $log): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($log['fecha'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['usuario']); ?></td>
                                        <td><?php echo ucfirst($log['accion']); ?></td>
                                        <td><?php echo htmlspecialchars($log['detalles'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>" value="iniciar">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('¿Estás seguro de iniciar este mantenimiento?')">
                                Iniciar Mantenimiento
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($mantenimiento['estado'] !== 'completado'): ?>
                        <form action="" method="POST" style="display: inline-block; margin-right: 10px;">
                            <input type="hidden" name="accion