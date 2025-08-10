<?php

/**
 * Gestionar Incidencias - Bitácora
 * Permite a los administradores gestionar todas las incidencias del sistema
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
    redirect('../admin/dashboard.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_incidencia = $_POST['id_incidencia'] ?? '';
    $accion = $_POST['accion'] ?? '';
    $nuevo_estado = $_POST['nuevo_estado'] ?? '';
    $notas = trim($_POST['notas'] ?? '');
    
    if (!empty($id_incidencia) && !empty($accion)) {
        $incidencia = $db->getRow("SELECT * FROM bitacora_incidencias WHERE id_incidencia = ?", [$id_incidencia]);
        
        if ($incidencia) {
            switch ($accion) {
                case 'cambiar_estado':
                    if (!empty($nuevo_estado)) {
                        $fecha_resolucion = ($nuevo_estado === 'resuelta' || $nuevo_estado === 'cerrada') ? date('Y-m-d H:i:s') : null;
                        
                        $resultado = $db->update("bitacora_incidencias", [
                            'estado' => $nuevo_estado,
                            'fecha_resolucion' => $fecha_resolucion,
                            'notas_administrador' => $notas,
                            'id_administrador_resuelve' => $_SESSION['usuario_id']
                        ], "id_incidencia = ?", [$id_incidencia]);
                        
                        if ($resultado) {
                            // Registrar en log de acciones
                            $db->insert("log_acciones", [
                                'id_usuario' => $_SESSION['usuario_id'],
                                'accion' => 'cambiar_estado_incidencia',
                                'detalles' => "Estado de incidencia cambiado a: {$nuevo_estado}",
                                'fecha' => date('Y-m-d H:i:s')
                            ]);
                            
                            $_SESSION['success'] = "Estado de la incidencia actualizado exitosamente.";
                        } else {
                            $_SESSION['error'] = "Error al actualizar el estado de la incidencia.";
                        }
                    }
                    break;
                    
                case 'agregar_notas':
                    if (!empty($notas)) {
                        $notas_actuales = $incidencia['notas_administrador'] ?? '';
                        $notas_completas = $notas_actuales ? $notas_actuales . "\n\n" . date('Y-m-d H:i:s') . " - " . $_SESSION['usuario_nombre'] . ":\n" . $notas : $notas;
                        
                        $resultado = $db->update("bitacora_incidencias", [
                            'notas_administrador' => $notas_completas
                        ], "id_incidencia = ?", [$id_incidencia]);
                        
                        if ($resultado) {
                            $_SESSION['success'] = "Notas agregadas exitosamente.";
                        } else {
                            $_SESSION['error'] = "Error al agregar las notas.";
                        }
                    }
                    break;
            }
        }
    }
    
    redirect('gestionar.php');
    exit;
}

// Parámetros de paginación y filtros
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

$filtro_estado = $_GET['estado'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_recurso = $_GET['recurso'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// Construir la consulta base
$where_conditions = ["1=1"];
$params = [];

if (!empty($filtro_estado)) {
    $where_conditions[] = "bi.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_prioridad)) {
    $where_conditions[] = "bi.prioridad = ?";
    $params[] = $filtro_prioridad;
}

if (!empty($filtro_recurso)) {
    $where_conditions[] = "rc.id_recurso = ?";
    $params[] = $filtro_recurso;
}

if (!empty($busqueda)) {
    $where_conditions[] = "(bi.titulo LIKE ? OR bi.descripcion LIKE ? OR rc.nombre LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ?)";
    $search_term = "%{$busqueda}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener total de incidencias para paginación
$total_query = "SELECT COUNT(*) as total FROM bitacora_incidencias bi 
                JOIN recursos rc ON bi.id_recurso = rc.id_recurso 
                JOIN usuarios u ON bi.id_usuario = u.id_usuario
                WHERE {$where_clause}";
$total_result = $db->getRow($total_query, $params);

// Validar que el resultado sea válido
if ($total_result === false) {
    $total_incidencias = 0;
    $total_paginas = 1;
} else {
    $total_incidencias = $total_result['total'];
    $total_paginas = ceil($total_incidencias / $por_pagina);
}

// Obtener incidencias
$incidencias_query = "
    SELECT bi.*, rc.nombre as nombre_recurso, rc.ubicacion,
           t.nombre as tipo_recurso, r.fecha_inicio, r.fecha_fin,
           u.nombre as nombre_usuario, u.apellido as apellido_usuario, u.email as email_usuario,
           admin.nombre as nombre_admin, admin.apellido as apellido_admin
    FROM bitacora_incidencias bi
    JOIN recursos rc ON bi.id_recurso = rc.id_recurso
    JOIN tipos_recursos t ON rc.id_tipo = t.id_tipo
    JOIN reservas r ON bi.id_reserva = r.id_reserva
    JOIN usuarios u ON bi.id_usuario = u.id_usuario
    LEFT JOIN usuarios admin ON bi.id_administrador_resuelve = admin.id_usuario
    WHERE {$where_clause}
    ORDER BY 
        CASE bi.prioridad 
            WHEN 'critica' THEN 1 
            WHEN 'alta' THEN 2 
            WHEN 'media' THEN 3 
            WHEN 'baja' THEN 4 
        END,
        bi.fecha_reporte DESC
    LIMIT {$por_pagina} OFFSET {$offset}
";

$incidencias = $db->getRows($incidencias_query, $params);

// Validar que las incidencias sean un array válido
if ($incidencias === false) {
    $incidencias = [];
}

// Obtener recursos para el filtro
$recursos = $db->getRows("SELECT id_recurso, nombre, ubicacion FROM recursos ORDER BY nombre");

// Validar que los recursos sean un array válido
if ($recursos === false) {
    $recursos = [];
}

// Obtener estadísticas generales
$stats_query = "
    SELECT 
        COUNT(*) as total_incidencias,
        SUM(CASE WHEN estado = 'reportada' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'en_revision' THEN 1 ELSE 0 END) as en_revision,
        SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
        SUM(CASE WHEN estado = 'resuelta' THEN 1 ELSE 0 END) as resueltas,
        SUM(CASE WHEN estado = 'cerrada' THEN 1 ELSE 0 END) as cerradas,
        SUM(CASE WHEN prioridad = 'critica' THEN 1 ELSE 0 END) as criticas,
        SUM(CASE WHEN prioridad = 'alta' THEN 1 ELSE 0 END) as altas
    FROM bitacora_incidencias
";
$estadisticas = $db->getRow($stats_query);

// Validar que las estadísticas sean un array válido
if ($estadisticas === false) {
    $estadisticas = [
        'total_incidencias' => 0,
        'pendientes' => 0,
        'en_revision' => 0,
        'en_proceso' => 0,
        'resueltas' => 0,
        'cerradas' => 0,
        'criticas' => 0,
        'altas' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Incidencias - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-top: 5px;
        }
        
        .stat-card.criticas .stat-value { color: #dc3545; }
        .stat-card.altas .stat-value { color: #fd7e14; }
        .stat-card.pendientes .stat-value { color: #ffc107; }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        
        .prioridad-baja { color: #28a745; }
        .prioridad-media { color: #ffc107; }
        .prioridad-alta { color: #fd7e14; }
        .prioridad-critica { color: #dc3545; }
        
        .prioridad-badge {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .prioridad-badge.critica { background: #dc3545; color: white; }
        .prioridad-badge.alta { background: #fd7e14; color: white; }
        .prioridad-badge.media { background: #ffc107; color: #212529; }
        .prioridad-badge.baja { background: #28a745; color: white; }
        
        .acciones-rapidas {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-estado {
            padding: 4px 8px;
            font-size: 11px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-estado.revision { background: #ffc107; color: #212529; }
        .btn-estado.proceso { background: #17a2b8; color: white; }
        .btn-estado.resuelta { background: #28a745; color: white; }
        .btn-estado.cerrada { background: #6c757d; color: white; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 15px;
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
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #007bff;
            border-radius: 4px;
        }
        
        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination a:hover {
            background: #f8f9fa;
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
                <a href="../usuarios/listar.php" class="nav-item">Usuarios</a>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                <a href="../inventario/listar.php" class="nav-item">Inventario</a>
                <a href="gestionar.php" class="nav-item active">Gestionar Incidencias</a>
                <a href="../reportes/reportes_dashboard.php" class="nav-item">Reportes</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Gestionar Incidencias</h1>
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

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $estadisticas['total_incidencias']; ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card pendientes">
                    <div class="stat-value"><?php echo $estadisticas['pendientes']; ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $estadisticas['en_revision']; ?></div>
                    <div class="stat-label">En Revisión</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $estadisticas['en_proceso']; ?></div>
                    <div class="stat-label">En Proceso</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $estadisticas['resueltas']; ?></div>
                    <div class="stat-label">Resueltas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $estadisticas['cerradas']; ?></div>
                    <div class="stat-label">Cerradas</div>
                </div>
                <div class="stat-card criticas">
                    <div class="stat-value"><?php echo $estadisticas['criticas']; ?></div>
                    <div class="stat-label">Críticas</div>
                </div>
                <div class="stat-card altas">
                    <div class="stat-value"><?php echo $estadisticas['altas']; ?></div>
                    <div class="stat-label">Altas</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters">
                <form method="GET" action="">
                    <div class="filter-group">
                        <label for="busqueda">Buscar:</label>
                        <input type="text" name="busqueda" id="busqueda" 
                               placeholder="Título, descripción, recurso o usuario..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="estado">Estado:</label>
                        <select name="estado" id="estado">
                            <option value="">Todos los estados</option>
                            <option value="reportada" <?php echo $filtro_estado === 'reportada' ? 'selected' : ''; ?>>Reportada</option>
                            <option value="en_revision" <?php echo $filtro_estado === 'en_revision' ? 'selected' : ''; ?>>En Revisión</option>
                            <option value="en_proceso" <?php echo $filtro_estado === 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                            <option value="resuelta" <?php echo $filtro_estado === 'resuelta' ? 'selected' : ''; ?>>Resuelta</option>
                            <option value="cerrada" <?php echo $filtro_estado === 'cerrada' ? 'selected' : ''; ?>>Cerrada</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="prioridad">Prioridad:</label>
                        <select name="prioridad" id="prioridad">
                            <option value="">Todas las prioridades</option>
                            <option value="baja" <?php echo $filtro_prioridad === 'baja' ? 'selected' : ''; ?>>Baja</option>
                            <option value="media" <?php echo $filtro_prioridad === 'media' ? 'selected' : ''; ?>>Media</option>
                            <option value="alta" <?php echo $filtro_prioridad === 'alta' ? 'selected' : ''; ?>>Alta</option>
                            <option value="critica" <?php echo $filtro_prioridad === 'critica' ? 'selected' : ''; ?>>Crítica</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="recurso">Recurso:</label>
                        <select name="recurso" id="recurso">
                            <option value="">Todos los recursos</option>
                            <?php foreach ($recursos as $recurso): ?>
                                <option value="<?php echo $recurso['id_recurso']; ?>" <?php echo $filtro_recurso == $recurso['id_recurso'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($recurso['nombre']); ?> - <?php echo htmlspecialchars($recurso['ubicacion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="gestionar.php" class="btn btn-secondary">Limpiar</a>
                    </div>
                </form>
            </div>

            <!-- Lista de Incidencias -->
            <div class="card">
                <div class="card-title">
                    Incidencias del Sistema
                    <span style="float: right; font-size: 14px; color: #666;">
                        Total: <?php echo $total_incidencias; ?> incidencias
                    </span>
                </div>

                <?php if (empty($incidencias)): ?>
                    <div class="empty-state">
                        <div class="icon">📝</div>
                        <h3>No se encontraron incidencias</h3>
                        <p>No hay incidencias que coincidan con los filtros aplicados.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Prioridad</th>
                                <th>Recurso</th>
                                <th>Título</th>
                                <th>Usuario</th>
                                <th>Estado</th>
                                <th>Fecha Reporte</th>
                                <th>Fecha Reserva</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidencias as $incidencia): ?>
                                <tr>
                                    <td>
                                        <span class="prioridad-badge <?php echo $incidencia['prioridad']; ?>">
                                            <?php echo ucfirst($incidencia['prioridad']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($incidencia['nombre_recurso']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($incidencia['tipo_recurso']); ?> - <?php echo htmlspecialchars($incidencia['ubicacion']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($incidencia['titulo']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($incidencia['nombre_usuario'] . ' ' . $incidencia['apellido_usuario']); ?><br>
                                        <small><?php echo htmlspecialchars($incidencia['email_usuario']); ?></small>
                                    </td>
                                    <td>
                                        <span class="estado-badge estado-<?php echo $incidencia['estado']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $incidencia['estado'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_date($incidencia['fecha_reporte'], true); ?></td>
                                    <td>
                                        <?php echo format_date($incidencia['fecha_inicio'], true); ?><br>
                                        <small>a <?php echo format_date($incidencia['fecha_fin'], true); ?></small>
                                    </td>
                                    <td>
                                        <div class="acciones-rapidas">
                                            <a href="ver_incidencia.php?id=<?php echo $incidencia['id_incidencia']; ?>" 
                                               class="btn btn-small btn-primary">Ver</a>
                                            <button onclick="abrirModalEstado(<?php echo $incidencia['id_incidencia']; ?>, '<?php echo $incidencia['estado']; ?>')" 
                                                    class="btn btn-small btn-secondary">Estado</button>
                                            <button onclick="abrirModalNotas(<?php echo $incidencia['id_incidencia']; ?>)" 
                                                    class="btn btn-small btn-info">Notas</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="pagination">
                            <?php if ($pagina > 1): ?>
                                <a href="?pagina=<?php echo $pagina - 1; ?>&estado=<?php echo urlencode($filtro_estado); ?>&prioridad=<?php echo urlencode($filtro_prioridad); ?>&recurso=<?php echo urlencode($filtro_recurso); ?>&busqueda=<?php echo urlencode($busqueda); ?>">← Anterior</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <?php if ($i == $pagina): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?pagina=<?php echo $i; ?>&estado=<?php echo urlencode($filtro_estado); ?>&prioridad=<?php echo urlencode($filtro_prioridad); ?>&recurso=<?php echo urlencode($filtro_recurso); ?>&busqueda=<?php echo urlencode($busqueda); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($pagina < $total_paginas): ?>
                                <a href="?pagina=<?php echo $pagina + 1; ?>&estado=<?php echo urlencode($filtro_estado); ?>&prioridad=<?php echo urlencode($filtro_prioridad); ?>&recurso=<?php echo urlencode($filtro_recurso); ?>&busqueda=<?php echo urlencode($busqueda); ?>">Siguiente →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para cambiar estado -->
    <div id="modalEstado" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cambiar Estado de Incidencia</h3>
                <span class="close" onclick="cerrarModal('modalEstado')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="cambiar_estado">
                <input type="hidden" name="id_incidencia" id="modal_id_incidencia">
                
                <div class="form-group">
                    <label for="nuevo_estado">Nuevo Estado:</label>
                    <select name="nuevo_estado" id="nuevo_estado" required>
                        <option value="reportada">Reportada</option>
                        <option value="en_revision">En Revisión</option>
                        <option value="en_proceso">En Proceso</option>
                        <option value="resuelta">Resuelta</option>
                        <option value="cerrada">Cerrada</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notas">Notas (opcional):</label>
                    <textarea name="notas" id="notas" placeholder="Agregar comentarios sobre el cambio de estado..."></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Actualizar Estado</button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalEstado')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para agregar notas -->
    <div id="modalNotas" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Agregar Notas</h3>
                <span class="close" onclick="cerrarModal('modalNotas')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="agregar_notas">
                <input type="hidden" name="id_incidencia" id="modal_notas_id_incidencia">
                
                <div class="form-group">
                    <label for="notas_texto">Notas:</label>
                    <textarea name="notas" id="notas_texto" placeholder="Agregar comentarios o instrucciones..." required></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Agregar Notas</button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalNotas')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function abrirModalEstado(idIncidencia, estadoActual) {
            document.getElementById('modal_id_incidencia').value = idIncidencia;
            document.getElementById('nuevo_estado').value = estadoActual;
            document.getElementById('modalEstado').style.display = 'block';
        }
        
        function abrirModalNotas(idIncidencia) {
            document.getElementById('modal_notas_id_incidencia').value = idIncidencia;
            document.getElementById('modalNotas').style.display = 'block';
        }
        
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>

</html> 