<?php

/**
 * Reportar Incidencia - Bitácora
 * Permite a los usuarios reportar problemas con los recursos que han reservado
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

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reserva = $_POST['id_reserva'] ?? '';
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $prioridad = $_POST['prioridad'] ?? 'media';
    
    // Validaciones
    $errores = [];
    
    if (empty($id_reserva)) {
        $errores[] = "Debe seleccionar una reserva";
    }
    
    if (empty($titulo)) {
        $errores[] = "El título es obligatorio";
    }
    
    if (empty($descripcion)) {
        $errores[] = "La descripción es obligatoria";
    }
    
    if (empty($errores)) {
        // Verificar que la reserva pertenece al usuario actual
        $reserva = $db->getRow("
            SELECT r.*, rc.nombre as nombre_recurso, rc.id_recurso
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            WHERE r.id_reserva = ? AND r.id_usuario = ?
        ", [$id_reserva, $_SESSION['usuario_id']]);
        
        if ($reserva) {
            // Insertar la incidencia
            $resultado = $db->insert("bitacora_incidencias", [
                'id_reserva' => $id_reserva,
                'id_usuario' => $_SESSION['usuario_id'],
                'id_recurso' => $reserva['id_recurso'],
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'prioridad' => $prioridad
            ]);
            
            if ($resultado) {
                // Registrar en log de acciones
                $db->insert("log_acciones", [
                    'id_usuario' => $_SESSION['usuario_id'],
                    'accion' => 'reportar_incidencia',
                    'detalles' => "Incidencia reportada: {$titulo} - Recurso: {$reserva['nombre_recurso']}",
                    'fecha' => date('Y-m-d H:i:s')
                ]);
                
                $_SESSION['success'] = "Incidencia reportada exitosamente. Los administradores han sido notificados.";
                redirect('mis_incidencias.php');
                exit;
            } else {
                $errores[] = "Error al reportar la incidencia. Intente nuevamente.";
            }
        } else {
            $errores[] = "La reserva seleccionada no es válida";
        }
    }
}

// Obtener las reservas activas del usuario para mostrar en el dropdown
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

// Obtener incidencias recientes del usuario
$incidencias_recientes = $db->getRows("
    SELECT bi.*, rc.nombre as nombre_recurso
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
                <a href="../admin/dashboard.php" class="nav-item">Dashboard</a>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="reportar.php" class="nav-item active">Reportar Incidencia</a>
                <a href="mis_incidencias.php" class="nav-item">Mis Incidencias</a>
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="gestionar.php" class="nav-item">Gestionar Incidencias</a>
                <?php endif; ?>
                <a href="../reportes/index.php" class="nav-item">Reportes</a>
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
                    
                    <?php if (empty($reservas_usuario)): ?>
                        <div class="alert alert-warning">
                            <p>No tienes reservas activas en este momento. Solo puedes reportar incidencias para recursos que hayas reservado.</p>
                            <a href="../reservas/calendario.php" class="btn btn-primary">Ir al Calendario de Reservas</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="id_reserva">Seleccionar Recurso Reservado:</label>
                                <select name="id_reserva" id="id_reserva" required>
                                    <option value="">-- Seleccione una reserva --</option>
                                    <?php foreach ($reservas_usuario as $reserva): ?>
                                        <option value="<?php echo $reserva['id_reserva']; ?>">
                                            <?php echo htmlspecialchars($reserva['nombre_recurso']); ?> 
                                            (<?php echo htmlspecialchars($reserva['tipo_recurso']); ?>) - 
                                            <?php echo htmlspecialchars($reserva['ubicacion']); ?> - 
                                            <?php echo format_date($reserva['fecha_inicio'], true); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="titulo">Título de la Incidencia:</label>
                                <input type="text" name="titulo" id="titulo" 
                                       placeholder="Ej: Teclado con teclas faltantes" 
                                       value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="descripcion">Descripción Detallada:</label>
                                <textarea name="descripcion" id="descripcion" 
                                          placeholder="Describe detalladamente el problema encontrado con el recurso..." required><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="prioridad">Prioridad:</label>
                                <select name="prioridad" id="prioridad">
                                    <option value="baja" <?php echo ($_POST['prioridad'] ?? '') === 'baja' ? 'selected' : ''; ?>>Baja - No afecta el uso básico</option>
                                    <option value="media" <?php echo ($_POST['prioridad'] ?? '') === 'media' ? 'selected' : ''; ?>>Media - Afecta parcialmente el uso</option>
                                    <option value="alta" <?php echo ($_POST['prioridad'] ?? '') === 'alta' ? 'selected' : ''; ?>>Alta - Dificulta significativamente el uso</option>
                                    <option value="critica" <?php echo ($_POST['prioridad'] ?? '') === 'critica' ? 'selected' : ''; ?>>Crítica - Imposibilita el uso</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Reportar Incidencia</button>
                                <a href="mis_incidencias.php" class="btn btn-secondary">Ver Mis Incidencias</a>
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
</body>

</html> 