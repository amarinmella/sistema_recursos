<?php

/**
 * Mis Incidencias - Bitácora
 * Permite a los usuarios ver todas las incidencias que han reportado
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

// Parámetros de paginación y filtros
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

$filtro_estado = $_GET['estado'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// Construir la consulta base
$where_conditions = ["bi.id_usuario = ?"];
$params = [$_SESSION['usuario_id']];

if (!empty($filtro_estado)) {
    $where_conditions[] = "bi.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_prioridad)) {
    $where_conditions[] = "bi.prioridad = ?";
    $params[] = $filtro_prioridad;
}

if (!empty($busqueda)) {
    $where_conditions[] = "(bi.titulo LIKE ? OR bi.descripcion LIKE ? OR rc.nombre LIKE ?)";
    $search_term = "%{$busqueda}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener total de incidencias para paginación
$total_query = "SELECT COUNT(*) as total FROM bitacora_incidencias bi 
                JOIN recursos rc ON bi.id_recurso = rc.id_recurso 
                WHERE {$where_clause}";
$total_result = $db->getRow($total_query, $params);
$total_incidencias = $total_result['total'];
$total_paginas = ceil($total_incidencias / $por_pagina);

// Obtener incidencias del usuario
$incidencias_query = "
    SELECT bi.*, rc.nombre as nombre_recurso, rc.ubicacion,
           t.nombre as tipo_recurso, r.fecha_inicio, r.fecha_fin
    FROM bitacora_incidencias bi
    JOIN recursos rc ON bi.id_recurso = rc.id_recurso
    JOIN tipos_recursos t ON rc.id_tipo = t.id_tipo
    JOIN reservas r ON bi.id_reserva = r.id_reserva
    WHERE {$where_clause}
    ORDER BY bi.fecha_reporte DESC
    LIMIT {$por_pagina} OFFSET {$offset}
";

$incidencias = $db->getRows($incidencias_query, $params);

// Obtener estadísticas del usuario
$stats_query = "
    SELECT 
        COUNT(*) as total_incidencias,
        SUM(CASE WHEN estado = 'reportada' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'en_revision' THEN 1 ELSE 0 END) as en_revision,
        SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
        SUM(CASE WHEN estado = 'resuelta' THEN 1 ELSE 0 END) as resueltas,
        SUM(CASE WHEN estado = 'cerrada' THEN 1 ELSE 0 END) as cerradas
    FROM bitacora_incidencias 
    WHERE id_usuario = ?
";
$estadisticas = $db->getRow($stats_query, [$_SESSION['usuario_id']]);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Incidencias - Sistema de Gestión de Recursos</title>
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
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 20px;
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
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="reportar.php" class="nav-item">Reportar Incidencia</a>
                <a href="mis_incidencias.php" class="nav-item active">Mis Incidencias</a>
                <?php if (has_role([ROL_ADMIN, ROL_ACADEMICO])): ?>
                    <a href="gestionar.php" class="nav-item">Gestionar Incidencias</a>
                <?php endif; ?>
                <a href="../reportes/index.php" class="nav-item">Reportes</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Mis Incidencias</h1>
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
                <div class="stat-card">
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
            </div>

            <!-- Filtros -->
            <div class="filters">
                <form method="GET" action="">
                    <div class="filter-group">
                        <label for="busqueda">Buscar:</label>
                        <input type="text" name="busqueda" id="busqueda" 
                               placeholder="Título, descripción o recurso..." 
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
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="mis_incidencias.php" class="btn btn-secondary">Limpiar</a>
                    </div>
                </form>
            </div>

            <!-- Lista de Incidencias -->
            <div class="card">
                <div class="card-title">
                    Incidencias Reportadas
                    <a href="reportar.php" class="btn btn-primary" style="float: right;">Reportar Nueva Incidencia</a>
                </div>

                <?php if (empty($incidencias)): ?>
                    <div class="empty-state">
                        <div class="icon">📝</div>
                        <h3>No se encontraron incidencias</h3>
                        <p>
                            <?php if (!empty($busqueda) || !empty($filtro_estado) || !empty($filtro_prioridad)): ?>
                                No hay incidencias que coincidan con los filtros aplicados.
                                <a href="mis_incidencias.php">Limpiar filtros</a>
                            <?php else: ?>
                                Aún no has reportado ninguna incidencia.
                                <a href="reportar.php">Reportar tu primera incidencia</a>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Recurso</th>
                                <th>Título</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                                <th>Fecha Reporte</th>
                                <th>Fecha Reserva</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidencias as $incidencia): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($incidencia['nombre_recurso']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($incidencia['tipo_recurso']); ?> - <?php echo htmlspecialchars($incidencia['ubicacion']); ?></small>
                                    </td>
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
                                        <?php echo format_date($incidencia['fecha_inicio'], true); ?><br>
                                        <small>a <?php echo format_date($incidencia['fecha_fin'], true); ?></small>
                                    </td>
                                    <td>
                                        <a href="ver_incidencia.php?id=<?php echo $incidencia['id_incidencia']; ?>" 
                                           class="btn btn-small btn-primary">Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="pagination">
                            <?php if ($pagina > 1): ?>
                                <a href="?pagina=<?php echo $pagina - 1; ?>&estado=<?php echo urlencode($filtro_estado); ?>&prioridad=<?php echo urlencode($filtro_prioridad); ?>&busqueda=<?php echo urlencode($busqueda); ?>">← Anterior</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <?php if ($i == $pagina): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?pagina=<?php echo $i; ?>&estado=<?php echo urlencode($filtro_estado); ?>&prioridad=<?php echo urlencode($filtro_prioridad); ?>&busqueda=<?php echo urlencode($busqueda); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($pagina < $total_paginas): ?>
                                <a href="?pagina=<?php echo $pagina + 1; ?>&estado=<?php echo urlencode($filtro_estado); ?>&prioridad=<?php echo urlencode($filtro_prioridad); ?>&busqueda=<?php echo urlencode($busqueda); ?>">Siguiente →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html> 