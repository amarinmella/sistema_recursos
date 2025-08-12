<?php

/**
 * Gestionar Incidencias - Bitácora
 * Permite a los administradores y profesores gestionar incidencias del sistema
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

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_incidencia = $_POST['id_incidencia'] ?? '';
    $accion = $_POST['accion'] ?? '';
    
    if (!empty($id_incidencia) && !empty($accion)) {
        $incidencia = $db->getRow("SELECT * FROM bitacora_incidencias WHERE id_incidencia = ?", [$id_incidencia]);
        
        if ($incidencia) {
            // Verificar permisos específicos para profesores
            if ($es_profesor) {
                // Los profesores solo pueden editar sus propias incidencias
                if ($incidencia['id_usuario'] != $_SESSION['usuario_id']) {
                    $_SESSION['error'] = "Solo puedes editar tus propias incidencias";
                    redirect('gestionar.php');
                    exit;
                }
                
                // Verificar límite de tiempo para edición (5 minutos)
                if ($accion == 'editar' && !profesor_puede_editar_incidencia($id_incidencia)) {
                    $_SESSION['error'] = "No puedes editar esta incidencia (han pasado más de 5 minutos)";
                    redirect('gestionar.php');
                    exit;
                }
            }
            
            switch ($accion) {
                case 'eliminar':
                    // Solo administradores pueden eliminar
                    if (!$es_admin) {
                        $_SESSION['error'] = "No tienes permisos para eliminar incidencias";
                        redirect('gestionar.php');
                        exit;
                    }
                    
                    $resultado = $db->delete("bitacora_incidencias", "id_incidencia = ?", [$id_incidencia]);
                    if ($resultado) {
                        $_SESSION['success'] = "Incidencia eliminada exitosamente.";
                    } else {
                        $_SESSION['error'] = "Error al eliminar la incidencia.";
                    }
                    break;
                    
                case 'editar':
                    $titulo = trim($_POST['titulo'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '');
                    $prioridad = $_POST['prioridad'] ?? '';
                    
                    if (!empty($titulo) && !empty($descripcion) && !empty($prioridad)) {
                        $resultado = $db->update("bitacora_incidencias", [
                            'titulo' => $titulo,
                            'descripcion' => $descripcion,
                            'prioridad' => $prioridad
                        ], "id_incidencia = ?", [$id_incidencia]);
                        
                        if ($resultado) {
                            $_SESSION['success'] = "Incidencia actualizada exitosamente.";
                        } else {
                            $_SESSION['error'] = "Error al actualizar la incidencia.";
                        }
                    } else {
                        $_SESSION['error'] = "Todos los campos son obligatorios.";
                    }
                    break;
            }
        }
    }
    
    redirect('gestionar.php');
    exit;
}

// Configuración de paginación
$elementos_por_pagina = 10;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $elementos_por_pagina;

// Filtros
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_prioridad = isset($_GET['prioridad']) ? $_GET['prioridad'] : '';
$filtro_recurso = isset($_GET['recurso']) ? $_GET['recurso'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Construir consulta SQL
$sql = "SELECT bi.*, r.nombre as nombre_recurso, r.ubicacion, u.nombre, u.apellido
        FROM bitacora_incidencias bi
        LEFT JOIN recursos r ON bi.id_recurso = r.id_recurso
        LEFT JOIN usuarios u ON bi.id_usuario = u.id_usuario";

$condiciones = [];
$params = [];

// Aplicar filtros
if (!empty($filtro_estado)) {
    $condiciones[] = "bi.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_prioridad)) {
    $condiciones[] = "bi.prioridad = ?";
    $params[] = $filtro_prioridad;
}

if (!empty($filtro_recurso)) {
    $condiciones[] = "bi.id_recurso = ?";
    $params[] = $filtro_recurso;
}

if (!empty($busqueda)) {
    $condiciones[] = "(bi.titulo LIKE ? OR bi.descripcion LIKE ? OR r.nombre LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Si es profesor, solo mostrar sus incidencias
if ($es_profesor) {
    $condiciones[] = "bi.id_usuario = ?";
    $params[] = $_SESSION['usuario_id'];
}

if (!empty($condiciones)) {
    $sql .= " WHERE " . implode(' AND ', $condiciones);
}

$sql .= " ORDER BY bi.fecha_reporte DESC";

// Obtener total de registros para paginación
$sql_count = str_replace("SELECT bi.*, r.nombre as nombre_recurso, r.ubicacion, u.nombre, u.apellido", "SELECT COUNT(*)", $sql);
$total_registros = $db->getRow($sql_count, $params)['COUNT(*)'] ?? 0;
$total_paginas = ceil($total_registros / $elementos_por_pagina);

// Obtener incidencias con paginación
$sql .= " LIMIT $elementos_por_pagina OFFSET $offset";
$incidencias = $db->getRows($sql, $params);

// Obtener recursos para filtro
$recursos = $db->getRows("SELECT id_recurso, nombre FROM recursos ORDER BY nombre");

// Estados y prioridades para filtros
$estados = ['reportada', 'en_revision', 'en_proceso', 'resuelta', 'cerrada'];
$prioridades = ['baja', 'media', 'alta', 'critica'];

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Incidencias - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive-tables.css">
    <style>
        .filtros {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filtro-grupo {
            display: flex;
            flex-direction: column;
        }
        
        .filtro-grupo label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .filtro-grupo select,
        .filtro-grupo input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filtros-acciones {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-filtrar {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-limpiar {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .incidencias-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .incidencias-table th,
        .incidencias-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .incidencias-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-reportada {
            background-color: #ffc107;
            color: black;
        }
        
        .badge-en_revision {
            background-color: #17a2b8;
            color: white;
        }
        
        .badge-en_proceso {
            background-color: #007bff;
            color: white;
        }
        
        .badge-resuelta {
            background-color: #28a745;
            color: white;
        }
        
        .badge-cerrada {
            background-color: #6c757d;
            color: white;
        }
        
        .badge-baja {
            background-color: #28a745;
            color: white;
        }
        
        .badge-media {
            background-color: #ffc107;
            color: black;
        }
        
        .badge-alta {
            background-color: #fd7e14;
            color: white;
        }
        
        .badge-critica {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 12px;
            margin: 2px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
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
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
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
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
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
                <?php echo generar_menu_navegacion('incidencias'); ?>
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

            <?php echo $mensaje; ?>

            <!-- Filtros -->
            <div class="filtros">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" action="">
                    <div class="filtros-grid">
                        <div class="filtro-grupo">
                            <label for="busqueda">Buscar:</label>
                            <input type="text" id="busqueda" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Título, descripción, recurso...">
                        </div>
                        
                        <div class="filtro-grupo">
                            <label for="estado">Estado:</label>
                            <select id="estado" name="estado">
                                <option value="">Todos los estados</option>
                                <?php foreach ($estados as $est): ?>
                                    <option value="<?php echo $est; ?>" <?php echo $filtro_estado == $est ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $est)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filtro-grupo">
                            <label for="prioridad">Prioridad:</label>
                            <select id="prioridad" name="prioridad">
                                <option value="">Todas las prioridades</option>
                                <?php foreach ($prioridades as $pri): ?>
                                    <option value="<?php echo $pri; ?>" <?php echo $filtro_prioridad == $pri ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($pri); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filtro-grupo">
                            <label for="recurso">Recurso:</label>
                            <select id="recurso" name="recurso">
                                <option value="">Todos los recursos</option>
                                <?php foreach ($recursos as $rec): ?>
                                    <option value="<?php echo $rec['id_recurso']; ?>" <?php echo $filtro_recurso == $rec['id_recurso'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rec['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filtros-acciones">
                        <button type="submit" class="btn-filtrar">Filtrar</button>
                        <a href="gestionar.php" class="btn-limpiar">Limpiar Filtros</a>
                        <a href="reportar.php" class="btn-filtrar">Reportar Nueva Incidencia</a>
                    </div>
                </form>
            </div>

            <!-- Tabla de Incidencias -->
            <div class="card">
                <div class="card-title">Listado de Incidencias</div>
                <?php if (empty($incidencias)): ?>
                    <p>No se encontraron incidencias con los filtros aplicados.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="incidencias-table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Recurso</th>
                                    <th>Reportado por</th>
                                    <th>Estado</th>
                                    <th>Prioridad</th>
                                    <th>Fecha Reporte</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incidencias as $incidencia): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($incidencia['titulo']); ?></strong>
                                            <br><small style="color: #6c757d;"><?php echo htmlspecialchars(substr($incidencia['descripcion'], 0, 50)) . (strlen($incidencia['descripcion']) > 50 ? '...' : ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($incidencia['nombre_recurso'] ?: 'No especificado'); ?></td>
                                        <td><?php echo htmlspecialchars($incidencia['nombre'] . ' ' . $incidencia['apellido']); ?></td>
                                        <td>
                                            <?php
                                            $estado = $incidencia['estado'];
                                            $badgeClass = 'badge-' . $estado;
                                            echo '<span class="badge ' . $badgeClass . '">' . ucfirst(str_replace('_', ' ', $estado)) . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $prioridad = $incidencia['prioridad'];
                                            $badgeClass = 'badge-' . $prioridad;
                                            echo '<span class="badge ' . $badgeClass . '">' . ucfirst($prioridad) . '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo format_date($incidencia['fecha_reporte'], true); ?></td>
                                        <td>
                                            <a href="ver.php?id=<?php echo $incidencia['id_incidencia']; ?>" class="btn btn-small btn-primary">Ver</a>
                                            <?php if ($es_admin): ?>
                                                <button onclick="confirmarEliminar(<?php echo $incidencia['id_incidencia']; ?>, '<?php echo htmlspecialchars($incidencia['titulo']); ?>')" 
                                                        class="btn btn-small btn-danger">Eliminar</button>
                                            <?php elseif ($es_profesor && $incidencia['id_usuario'] == $_SESSION['usuario_id'] && profesor_puede_editar_incidencia($incidencia['id_incidencia'])): ?>
                                                <button onclick="abrirModalEditar(<?php echo $incidencia['id_incidencia']; ?>, '<?php echo htmlspecialchars($incidencia['titulo']); ?>', '<?php echo htmlspecialchars($incidencia['descripcion']); ?>', '<?php echo $incidencia['prioridad']; ?>')" 
                                                        class="btn btn-small btn-warning">Editar</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

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

    <!-- Modal para editar incidencia -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Incidencia</h3>
                <span class="close" onclick="cerrarModal('modalEditar')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id_incidencia" id="modal_editar_id_incidencia">
                
                <div class="form-group">
                    <label for="titulo_editar">Título:</label>
                    <input type="text" name="titulo" id="titulo_editar" required>
                </div>
                
                <div class="form-group">
                    <label for="descripcion_editar">Descripción:</label>
                    <textarea name="descripcion" id="descripcion_editar" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="prioridad_editar">Prioridad:</label>
                    <select name="prioridad" id="prioridad_editar" required>
                        <option value="baja">Baja</option>
                        <option value="media">Media</option>
                        <option value="alta">Alta</option>
                        <option value="critica">Crítica</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Actualizar Incidencia</button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalEditar')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function abrirModalEditar(idIncidencia, titulo, descripcion, prioridad) {
            document.getElementById('modal_editar_id_incidencia').value = idIncidencia;
            document.getElementById('titulo_editar').value = titulo;
            document.getElementById('descripcion_editar').value = descripcion;
            document.getElementById('prioridad_editar').value = prioridad;
            document.getElementById('modalEditar').style.display = 'block';
        }
        
        function confirmarEliminar(idIncidencia, titulo) {
            if (confirm('¿Estás seguro de que deseas eliminar la incidencia "' + titulo + '"? Esta acción no se puede deshacer.')) {
                // Crear un formulario temporal para enviar la acción de eliminar
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                var accionInput = document.createElement('input');
                accionInput.type = 'hidden';
                accionInput.name = 'accion';
                accionInput.value = 'eliminar';
                form.appendChild(accionInput);
                
                var idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id_incidencia';
                idInput.value = idIncidencia;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
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
