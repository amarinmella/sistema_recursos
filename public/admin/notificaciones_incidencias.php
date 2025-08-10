<?php

/**
 * Notificaciones de Incidencias - Dashboard Administrador
 * Muestra todas las notificaciones de incidencias para el administrador
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario sea administrador
require_login();
if ($_SESSION['usuario_rol'] != ROL_ADMIN) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_notificacion = $_POST['id_notificacion'] ?? '';
    $accion = $_POST['accion'] ?? '';
    
    if (!empty($id_notificacion) && !empty($accion)) {
        switch ($accion) {
            case 'marcar_leida':
                $resultado = $db->update("notificaciones_incidencias", [
                    'leida' => 1
                ], "id_notificacion = ?", [$id_notificacion]);
                
                if ($resultado) {
                    $_SESSION['success'] = "Notificación marcada como leída.";
                } else {
                    $_SESSION['error'] = "Error al marcar la notificación.";
                }
                break;
                
            case 'marcar_todas_leidas':
                $resultado = $db->update("notificaciones_incidencias", [
                    'leida' => 1
                ], "id_usuario_destino = ? AND leida = 0", [$_SESSION['usuario_id']]);
                
                if ($resultado) {
                    $_SESSION['success'] = "Todas las notificaciones han sido marcadas como leídas.";
                } else {
                    $_SESSION['error'] = "Error al marcar las notificaciones.";
                }
                break;
        }
        
        redirect('notificaciones_incidencias.php');
        exit;
    }
}

// Parámetros de paginación y filtros
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_leida = $_GET['leida'] ?? '';

// Construir la consulta base
$where_conditions = ["n.id_usuario_destino = ?"];
$params = [$_SESSION['usuario_id']];

if (!empty($filtro_tipo)) {
    $where_conditions[] = "n.tipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_leida !== '') {
    $where_conditions[] = "n.leida = ?";
    $params[] = (int)$filtro_leida;
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener notificaciones
$notificaciones = $db->getRows("
    SELECT n.*, bi.titulo, bi.descripcion, bi.prioridad, bi.estado as estado_incidencia,
           rc.nombre as nombre_recurso, rc.ubicacion,
           u.nombre, u.apellido
    FROM notificaciones_incidencias n
    JOIN bitacora_incidencias bi ON n.id_incidencia = bi.id_incidencia
    JOIN recursos rc ON bi.id_recurso = rc.id_recurso
    JOIN usuarios u ON bi.id_usuario = u.id_usuario
    WHERE {$where_clause}
    ORDER BY n.fecha_creacion DESC
    LIMIT {$por_pagina} OFFSET {$offset}
", $params);

// Contar total de notificaciones para paginación
$total_notificaciones = $db->getRow("
    SELECT COUNT(*) as total
    FROM notificaciones_incidencias n
    WHERE {$where_clause}
", $params)['total'] ?? 0;

$total_paginas = ceil($total_notificaciones / $por_pagina);

// Obtener estadísticas de notificaciones
$stats = $db->getRow("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
        SUM(CASE WHEN tipo = 'nueva_incidencia' THEN 1 ELSE 0 END) as nuevas,
        SUM(CASE WHEN tipo = 'actualizacion_incidencia' THEN 1 ELSE 0 END) as actualizaciones,
        SUM(CASE WHEN tipo = 'incidencia_resuelta' THEN 1 ELSE 0 END) as resueltas
    FROM notificaciones_incidencias
    WHERE id_usuario_destino = ?
", [$_SESSION['usuario_id']]);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones de Incidencias - Panel de Administración</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .notificaciones-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .filtros {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filtros form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filtros select, .filtros input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .notificacion-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .notificacion-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .notificacion-item.no-leida {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        .notificacion-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .notificacion-tipo {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .tipo-nueva_incidencia { background: #e3f2fd; color: #1976d2; }
        .tipo-actualizacion_incidencia { background: #fff3e0; color: #f57c00; }
        .tipo-incidencia_resuelta { background: #e8f5e8; color: #388e3c; }
        
        .notificacion-fecha {
            color: #666;
            font-size: 14px;
        }
        
        .notificacion-contenido {
            margin-bottom: 15px;
        }
        
        .notificacion-titulo {
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        
        .notificacion-mensaje {
            color: #666;
            line-height: 1.5;
        }
        
        .notificacion-detalles {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .detalle-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .detalle-label {
            font-weight: bold;
            color: #555;
        }
        
        .notificacion-acciones {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .paginacion {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .paginacion a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
        }
        
        .paginacion a:hover {
            background: #f8f9fa;
        }
        
        .paginacion .activa {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .acciones-bulk {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
                <a href="dashboard.php" class="nav-item">Dashboard</a>
                <a href="../usuarios/listar.php" class="nav-item">Usuarios</a>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                <a href="../inventario/listar.php" class="nav-item">Inventario</a>
                <a href="../bitacora/gestionar.php" class="nav-item">Gestionar Incidencias</a>
                <a href="notificaciones_incidencias.php" class="nav-item active">Notificaciones</a>
                <a href="../reportes/reportes_dashboard.php" class="nav-item">Reportes</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Notificaciones de Incidencias</h1>
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

            <div class="notificaciones-container">
                <!-- Estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Notificaciones</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['no_leidas']; ?></div>
                        <div class="stat-label">No Leídas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['nuevas']; ?></div>
                        <div class="stat-label">Nuevas Incidencias</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['actualizaciones']; ?></div>
                        <div class="stat-label">Actualizaciones</div>
                    </div>
                </div>

                <!-- Acciones en lote -->
                <?php if ($stats['no_leidas'] > 0): ?>
                    <div class="acciones-bulk">
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="accion" value="marcar_todas_leidas">
                            <button type="submit" class="btn btn-secondary">Marcar Todas como Leídas</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="filtros">
                    <form method="GET" action="">
                        <select name="tipo">
                            <option value="">Todos los tipos</option>
                            <option value="nueva_incidencia" <?php echo $filtro_tipo === 'nueva_incidencia' ? 'selected' : ''; ?>>Nueva Incidencia</option>
                            <option value="actualizacion_incidencia" <?php echo $filtro_tipo === 'actualizacion_incidencia' ? 'selected' : ''; ?>>Actualización</option>
                            <option value="incidencia_resuelta" <?php echo $filtro_tipo === 'incidencia_resuelta' ? 'selected' : ''; ?>>Resuelta</option>
                        </select>
                        
                        <select name="leida">
                            <option value="">Todas</option>
                            <option value="0" <?php echo $filtro_leida === '0' ? 'selected' : ''; ?>>No leídas</option>
                            <option value="1" <?php echo $filtro_leida === '1' ? 'selected' : ''; ?>>Leídas</option>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="notificaciones_incidencias.php" class="btn btn-secondary">Limpiar</a>
                    </form>
                </div>

                <!-- Lista de notificaciones -->
                <?php if (empty($notificaciones)): ?>
                    <div class="alert alert-info">
                        No hay notificaciones que mostrar con los filtros actuales.
                    </div>
                <?php else: ?>
                    <?php foreach ($notificaciones as $notificacion): ?>
                        <div class="notificacion-item <?php echo $notificacion['leida'] ? '' : 'no-leida'; ?>">
                            <div class="notificacion-header">
                                <div>
                                    <span class="notificacion-tipo tipo-<?php echo $notificacion['tipo']; ?>">
                                        <?php 
                                        switch($notificacion['tipo']) {
                                            case 'nueva_incidencia': echo 'Nueva Incidencia'; break;
                                            case 'actualizacion_incidencia': echo 'Actualización'; break;
                                            case 'incidencia_resuelta': echo 'Resuelta'; break;
                                            default: echo ucfirst($notificacion['tipo']);
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="notificacion-fecha">
                                    <?php echo format_date($notificacion['fecha_creacion'], true); ?>
                                </div>
                            </div>
                            
                            <div class="notificacion-contenido">
                                <div class="notificacion-titulo">
                                    <?php echo htmlspecialchars($notificacion['titulo']); ?>
                                </div>
                                <div class="notificacion-mensaje">
                                    <?php echo htmlspecialchars($notificacion['mensaje']); ?>
                                </div>
                            </div>
                            
                            <div class="notificacion-detalles">
                                <div class="detalle-item">
                                    <span class="detalle-label">Usuario:</span>
                                    <span><?php echo htmlspecialchars($notificacion['nombre'] . ' ' . $notificacion['apellido']); ?></span>
                                </div>
                                <div class="detalle-item">
                                    <span class="detalle-label">Recurso:</span>
                                    <span><?php echo htmlspecialchars($notificacion['nombre_recurso']); ?></span>
                                </div>
                                <div class="detalle-item">
                                    <span class="detalle-label">Ubicación:</span>
                                    <span><?php echo htmlspecialchars($notificacion['ubicacion']); ?></span>
                                </div>
                                <div class="detalle-item">
                                    <span class="detalle-label">Prioridad:</span>
                                    <span class="prioridad-<?php echo $notificacion['prioridad']; ?>">
                                        <?php echo ucfirst($notificacion['prioridad']); ?>
                                    </span>
                                </div>
                                <div class="detalle-item">
                                    <span class="detalle-label">Estado:</span>
                                    <span><?php echo ucfirst(str_replace('_', ' ', $notificacion['estado_incidencia'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="notificacion-acciones">
                                <?php if (!$notificacion['leida']): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="id_notificacion" value="<?php echo $notificacion['id_notificacion']; ?>">
                                        <input type="hidden" name="accion" value="marcar_leida">
                                        <button type="submit" class="btn btn-small btn-secondary">Marcar como Leída</button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="../bitacora/gestionar.php?id=<?php echo $notificacion['id_incidencia']; ?>" 
                                   class="btn btn-small btn-primary">Ver Incidencia</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                    <div class="paginacion">
                        <?php if ($pagina > 1): ?>
                            <a href="?pagina=<?php echo $pagina - 1; ?>&tipo=<?php echo $filtro_tipo; ?>&leida=<?php echo $filtro_leida; ?>">Anterior</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <a href="?pagina=<?php echo $i; ?>&tipo=<?php echo $filtro_tipo; ?>&leida=<?php echo $filtro_leida; ?>" 
                               class="<?php echo $i === $pagina ? 'activa' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($pagina < $total_paginas): ?>
                            <a href="?pagina=<?php echo $pagina + 1; ?>&tipo=<?php echo $filtro_tipo; ?>&leida=<?php echo $filtro_leida; ?>">Siguiente</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html> 