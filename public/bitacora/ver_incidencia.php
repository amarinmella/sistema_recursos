<?php

/**
 * Ver Incidencia - Bitácora
 * Permite ver los detalles completos de una incidencia específica
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

// Obtener ID de la incidencia
$id_incidencia = $_GET['id'] ?? '';

if (empty($id_incidencia)) {
    $_SESSION['error'] = "ID de incidencia no válido";
    redirect('mis_incidencias.php');
    exit;
}

// Obtener datos de la incidencia
$incidencia = $db->getRow("
    SELECT bi.*, rc.nombre as nombre_recurso, rc.ubicacion, rc.descripcion as descripcion_recurso,
           t.nombre as tipo_recurso, u.nombre, u.apellido, u.email,
           r.fecha_inicio, r.fecha_fin, r.estado as estado_reserva
    FROM bitacora_incidencias bi
    JOIN recursos rc ON bi.id_recurso = rc.id_recurso
    JOIN tipos_recursos t ON rc.id_tipo = t.id_tipo
    JOIN usuarios u ON bi.id_usuario = u.id_usuario
    JOIN reservas r ON bi.id_reserva = r.id_reserva
    WHERE bi.id_incidencia = ?
", [$id_incidencia]);

if (!$incidencia) {
    $_SESSION['error'] = "Incidencia no encontrada";
    redirect('mis_incidencias.php');
    exit;
}

// Verificar que el usuario tenga acceso a esta incidencia
if ($_SESSION['usuario_rol'] != ROL_ADMIN && $_SESSION['usuario_rol'] != ROL_ACADEMICO && 
    $incidencia['id_usuario'] != $_SESSION['usuario_id']) {
    $_SESSION['error'] = "No tienes permisos para ver esta incidencia";
    redirect('mis_incidencias.php');
    exit;
}

// Obtener historial de cambios si es administrador
$historial_cambios = [];
if (has_role([ROL_ADMIN, ROL_ACADEMICO])) {
    $historial_cambios = $db->getRows("
        SELECT la.*, u.nombre, u.apellido
        FROM log_acciones la
        JOIN usuarios u ON la.id_usuario = u.id_usuario
        WHERE la.accion LIKE '%incidencia%' 
        AND la.detalles LIKE '%{$id_incidencia}%'
        ORDER BY la.fecha DESC
    ");
}

// Procesar formulario de comentarios (solo para administradores)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_role([ROL_ADMIN, ROL_ACADEMICO])) {
    $comentario = trim($_POST['comentario'] ?? '');
    $nuevo_estado = $_POST['nuevo_estado'] ?? '';
    
    if (!empty($comentario) || !empty($nuevo_estado)) {
        $updates = [];
        $params = [];
        
        if (!empty($nuevo_estado)) {
            $updates[] = "estado = ?";
            $params[] = $nuevo_estado;
            
            if ($nuevo_estado === 'resuelta' || $nuevo_estado === 'cerrada') {
                $updates[] = "fecha_resolucion = ?";
                $params[] = date('Y-m-d H:i:s');
            }
        }
        
        if (!empty($comentario)) {
            $notas_actuales = $incidencia['notas_administrador'] ?? '';
            $notas_completas = $notas_actuales ? $notas_actuales . "\n\n" . date('Y-m-d H:i:s') . " - " . $_SESSION['usuario_nombre'] . ":\n" . $comentario : $comentario;
            
            $updates[] = "notas_administrador = ?";
            $params[] = $notas_completas;
        }
        
        if (!empty($updates)) {
            $updates[] = "id_administrador_resuelve = ?";
            $params[] = $_SESSION['usuario_id'];
            
            $params[] = $id_incidencia;
            
            $resultado = $db->update("bitacora_incidencias", [], implode(", ", $updates), "id_incidencia = ?", $params);
            
            if ($resultado) {
                // Registrar en log de acciones
                $db->insert("log_acciones", [
                    'id_usuario' => $_SESSION['usuario_id'],
                    'accion' => 'actualizar_incidencia',
                    'detalles' => "Incidencia {$id_incidencia} actualizada: " . (!empty($nuevo_estado) ? "Estado: {$nuevo_estado}" : "") . (!empty($comentario) ? " Comentario agregado" : ""),
                    'fecha' => date('Y-m-d H:i:s')
                ]);
                
                $_SESSION['success'] = "Incidencia actualizada exitosamente";
                redirect("ver_incidencia.php?id={$id_incidencia}");
                exit;
            } else {
                $_SESSION['error'] = "Error al actualizar la incidencia";
            }
        }
    }
}

// Actualizar la incidencia después de posibles cambios
$incidencia = $db->getRow("
    SELECT bi.*, rc.nombre as nombre_recurso, rc.ubicacion, rc.descripcion as descripcion_recurso,
           t.nombre as tipo_recurso, u.nombre, u.apellido, u.email,
           r.fecha_inicio, r.fecha_fin, r.estado as estado_reserva
    FROM bitacora_incidencias bi
    JOIN recursos rc ON bi.id_recurso = rc.id_recurso
    JOIN tipos_recursos t ON rc.id_tipo = t.id_tipo
    JOIN usuarios u ON bi.id_usuario = u.id_usuario
    JOIN reservas r ON bi.id_reserva = r.id_reserva
    WHERE bi.id_incidencia = ?
", [$id_incidencia]);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Incidencia - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .incidencia-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .incidencia-header {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .incidencia-titulo {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .incidencia-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
        }
        
        .meta-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }
        
        .meta-value {
            color: #333;
        }
        
        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .estado-reportada { background: #e3f2fd; color: #1976d2; }
        .estado-en_revision { background: #fff3e0; color: #f57c00; }
        .estado-en_proceso { background: #e8f5e8; color: #388e3c; }
        .estado-resuelta { background: #e8f5e8; color: #388e3c; }
        .estado-cerrada { background: #f5f5f5; color: #616161; }
        
        .prioridad-baja { color: #28a745; }
        .prioridad-media { color: #ffc107; }
        .prioridad-alta { color: #fd7e14; }
        .prioridad-critica { color: #dc3545; }
        
        .incidencia-descripcion {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .descripcion-titulo {
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .descripcion-texto {
            line-height: 1.6;
            color: #666;
        }
        
        .reserva-info {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .reserva-titulo {
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .reserva-detalles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .comentarios-admin {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .comentarios-titulo {
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .comentario-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 10px;
            border-left: 3px solid #007bff;
        }
        
        .comentario-fecha {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .comentario-texto {
            color: #333;
            line-height: 1.5;
        }
        
        .formulario-admin {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .formulario-titulo {
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .historial-cambios {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .historial-titulo {
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .cambio-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 10px;
            border-left: 3px solid #28a745;
        }
        
        .cambio-fecha {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .cambio-usuario {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .cambio-accion {
            color: #333;
            margin-bottom: 5px;
        }
        
        .cambio-detalles {
            color: #666;
            font-style: italic;
        }
        
        .acciones {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .acciones-titulo {
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="../admin/dashboard.php" class="nav-item">Dashboard</a>
                    <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                    <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                    <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                    <a href="gestionar.php" class="nav-item">Gestionar Incidencias</a>
                    <a href="../admin/notificaciones_incidencias.php" class="nav-item">Notificaciones</a>
                <?php else: ?>
                    <a href="../profesor/dashboard.php" class="nav-item">Dashboard</a>
                    <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                    <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                    <a href="reportar.php" class="nav-item">Reportar Incidencia</a>
                    <a href="mis_incidencias.php" class="nav-item">Mis Incidencias</a>
                <?php endif; ?>
                <a href="../reportes/index.php" class="nav-item">Reportes</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Detalles de la Incidencia</h1>
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

            <div class="incidencia-container">
                <!-- Header de la incidencia -->
                <div class="incidencia-header">
                    <div class="incidencia-titulo">
                        <?php echo htmlspecialchars($incidencia['titulo']); ?>
                    </div>
                    
                    <div class="incidencia-meta">
                        <div class="meta-item">
                            <div class="meta-label">Estado</div>
                            <div class="meta-value">
                                <span class="estado-badge estado-<?php echo $incidencia['estado']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $incidencia['estado'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Prioridad</div>
                            <div class="meta-value">
                                <span class="prioridad-<?php echo $incidencia['prioridad']; ?>">
                                    <?php echo ucfirst($incidencia['prioridad']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Fecha de Reporte</div>
                            <div class="meta-value">
                                <?php echo format_date($incidencia['fecha_reporte'], true); ?>
                            </div>
                        </div>
                        
                        <?php if ($incidencia['fecha_resolucion']): ?>
                            <div class="meta-item">
                                <div class="meta-label">Fecha de Resolución</div>
                                <div class="meta-value">
                                    <?php echo format_date($incidencia['fecha_resolucion'], true); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Descripción de la incidencia -->
                <div class="incidencia-descripcion">
                    <div class="descripcion-titulo">Descripción del Problema</div>
                    <div class="descripcion-texto">
                        <?php echo nl2br(htmlspecialchars($incidencia['descripcion'])); ?>
                    </div>
                </div>

                <!-- Información de la reserva -->
                <div class="reserva-info">
                    <div class="reserva-titulo">Información de la Reserva</div>
                    <div class="reserva-detalles">
                        <div class="meta-item">
                            <div class="meta-label">Recurso</div>
                            <div class="meta-value"><?php echo htmlspecialchars($incidencia['nombre_recurso']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Tipo de Recurso</div>
                            <div class="meta-value"><?php echo htmlspecialchars($incidencia['tipo_recurso']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Ubicación</div>
                            <div class="meta-value"><?php echo htmlspecialchars($incidencia['ubicacion']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Usuario</div>
                            <div class="meta-value"><?php echo htmlspecialchars($incidencia['nombre'] . ' ' . $incidencia['apellido']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Email</div>
                            <div class="meta-value"><?php echo htmlspecialchars($incidencia['email']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Estado de la Reserva</div>
                            <div class="meta-value"><?php echo ucfirst($incidencia['estado_reserva']); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Fecha de Inicio</div>
                            <div class="meta-value"><?php echo format_date($incidencia['fecha_inicio'], true); ?></div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Fecha de Fin</div>
                            <div class="meta-value"><?php echo format_date($incidencia['fecha_fin'], true); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Comentarios del administrador -->
                <?php if (!empty($incidencia['notas_administrador'])): ?>
                    <div class="comentarios-admin">
                        <div class="comentarios-titulo">Comentarios del Administrador</div>
                        <div class="comentario-item">
                            <div class="comentario-texto">
                                <?php echo nl2br(htmlspecialchars($incidencia['notas_administrador'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Formulario para administradores -->
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <div class="formulario-admin">
                        <div class="formulario-titulo">Actualizar Incidencia</div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="nuevo_estado">Cambiar Estado:</label>
                                <select name="nuevo_estado" id="nuevo_estado">
                                    <option value="">-- Mantener estado actual --</option>
                                    <option value="en_revision">En Revisión</option>
                                    <option value="en_proceso">En Proceso</option>
                                    <option value="resuelta">Resuelta</option>
                                    <option value="cerrada">Cerrada</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="comentario">Agregar Comentario:</label>
                                <textarea name="comentario" id="comentario" 
                                          placeholder="Escribe un comentario sobre esta incidencia..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Actualizar Incidencia</button>
                            </div>
                        </form>
                    </div>

                    <!-- Historial de cambios -->
                    <?php if (!empty($historial_cambios)): ?>
                        <div class="historial-cambios">
                            <div class="historial-titulo">Historial de Cambios</div>
                            <?php foreach ($historial_cambios as $cambio): ?>
                                <div class="cambio-item">
                                    <div class="cambio-fecha">
                                        <?php echo format_date($cambio['fecha'], true); ?>
                                    </div>
                                    <div class="cambio-usuario">
                                        <?php echo htmlspecialchars($cambio['nombre'] . ' ' . $cambio['apellido']); ?>
                                    </div>
                                    <div class="cambio-accion">
                                        <?php echo ucfirst(str_replace('_', ' ', $cambio['accion'])); ?>
                                    </div>
                                    <div class="cambio-detalles">
                                        <?php echo htmlspecialchars($cambio['detalles']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Acciones -->
                <div class="acciones">
                    <div class="acciones-titulo">Acciones</div>
                    <div class="btn-group">
                        <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                            <a href="gestionar.php" class="btn btn-secondary">Volver a Gestión</a>
                        <?php else: ?>
                            <a href="mis_incidencias.php" class="btn btn-secondary">Volver a Mis Incidencias</a>
                        <?php endif; ?>
                        
                        <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                            <a href="../admin/notificaciones_incidencias.php" class="btn btn-primary">Ver Notificaciones</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html> 