<?php

/**
 * Gestión de Tipos de Recursos
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
    redirect('../index.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Procesar acciones
$mensaje = '';
$tipo_editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            
            if (empty($nombre)) {
                $mensaje = '<div class="alert alert-error">El nombre del tipo es obligatorio</div>';
            } else {
                // Verificar si ya existe
                $existe = $db->getRow("SELECT id_tipo FROM tipos_recursos WHERE nombre = ?", [$nombre]);
                if ($existe) {
                    $mensaje = '<div class="alert alert-error">Ya existe un tipo de recurso con ese nombre</div>';
                } else {
                    $resultado = $db->insert('tipos_recursos', [
                        'nombre' => $nombre,
                        'descripcion' => $descripcion
                    ]);
                    
                    if ($resultado) {
                        $mensaje = '<div class="alert alert-success">Tipo de recurso creado correctamente</div>';
                    } else {
                        $mensaje = '<div class="alert alert-error">Error al crear el tipo de recurso</div>';
                    }
                }
            }
            break;
            
        case 'editar':
            $id_tipo = intval($_POST['id_tipo'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            
            if (empty($nombre) || $id_tipo <= 0) {
                $mensaje = '<div class="alert alert-error">Datos incompletos para editar</div>';
            } else {
                // Verificar si ya existe otro con el mismo nombre
                $existe = $db->getRow("SELECT id_tipo FROM tipos_recursos WHERE nombre = ? AND id_tipo != ?", [$nombre, $id_tipo]);
                if ($existe) {
                    $mensaje = '<div class="alert alert-error">Ya existe otro tipo de recurso con ese nombre</div>';
                } else {
                    $resultado = $db->update('tipos_recursos', ['nombre' => $nombre, 'descripcion' => $descripcion], "id_tipo = ?", [$id_tipo]);
                    
                    if ($resultado) {
                        $mensaje = '<div class="alert alert-success">Tipo de recurso actualizado correctamente</div>';
                    } else {
                        $mensaje = '<div class="alert alert-error">Error al actualizar el tipo de recurso</div>';
                    }
                }
            }
            break;
            
        case 'eliminar':
            $id_tipo = intval($_POST['id_tipo'] ?? 0);
            
            if ($id_tipo <= 0) {
                $mensaje = '<div class="alert alert-error">ID de tipo inválido</div>';
            } else {
                // Verificar si hay recursos usando este tipo
                $recursos = $db->getRow("SELECT COUNT(*) as total FROM recursos WHERE id_tipo = ?", [$id_tipo]);
                if ($recursos['total'] > 0) {
                    $mensaje = '<div class="alert alert-error">No se puede eliminar: hay ' . $recursos['total'] . ' recursos usando este tipo</div>';
                } else {
                    $resultado = $db->delete('tipos_recursos', "id_tipo = ?", [$id_tipo]);
                    
                    if ($resultado) {
                        $mensaje = '<div class="alert alert-success">Tipo de recurso eliminado correctamente</div>';
                    } else {
                        $mensaje = '<div class="alert alert-error">Error al eliminar el tipo de recurso</div>';
                    }
                }
            }
            break;
            
        case 'cargar_editar':
            $id_tipo = intval($_POST['id_tipo'] ?? 0);
            if ($id_tipo > 0) {
                $tipo_editar = $db->getRow("SELECT * FROM tipos_recursos WHERE id_tipo = ?", [$id_tipo]);
            }
            break;
    }
}

// Obtener lista de tipos de recursos
$tipos = $db->getRows("SELECT * FROM tipos_recursos ORDER BY nombre");

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Tipos de Recursos - Sistema de Gestión</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .tipos-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .tipo-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            position: relative;
        }
        
        .tipo-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        
        .btn-edit {
            background: #007bff;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
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
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
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
                <a href="../recursos/listar.php" class="nav-item active">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                <a href="../inventario/listar.php" class="nav-item">Inventario</a>
                <a href="../reportes/index.php" class="nav-item">Reportes</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Gestionar Tipos de Recursos</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <a href="crear.php" class="btn btn-secondary">← Volver a Crear Recurso</a>
            </div>

            <?php echo $mensaje; ?>

            <div class="breadcrumb">
                <a href="../admin/dashboard.php">Dashboard</a> &gt;
                <a href="listar.php">Recursos</a> &gt;
                <a href="crear.php">Crear Recurso</a> &gt;
                <span>Gestionar Tipos</span>
            </div>

            <div class="card">
                <h2 class="form-title">Crear Nuevo Tipo de Recurso</h2>
                <form action="" method="POST" class="resource-form">
                    <input type="hidden" name="accion" value="crear">
                    <div class="form-group">
                        <label for="nombre">Nombre del Tipo *</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Crear Tipo</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2 class="form-title">Tipos de Recursos Existentes</h2>
                <div class="tipos-grid">
                    <?php foreach ($tipos as $tipo): ?>
                        <div class="tipo-item">
                            <div class="tipo-actions">
                                <button class="btn-small btn-edit" onclick="editarTipo(<?php echo $tipo['id_tipo']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>', '<?php echo htmlspecialchars($tipo['descripcion']); ?>')">Editar</button>
                                <button class="btn-small btn-delete" onclick="eliminarTipo(<?php echo $tipo['id_tipo']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')">Eliminar</button>
                            </div>
                            <h3><?php echo htmlspecialchars($tipo['nombre']); ?></h3>
                            <?php if (!empty($tipo['descripcion'])): ?>
                                <p><?php echo htmlspecialchars($tipo['descripcion']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Editar Tipo de Recurso</h2>
            <form action="" method="POST" class="resource-form">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" id="edit_id_tipo" name="id_tipo">
                <div class="form-group">
                    <label for="edit_nombre">Nombre del Tipo *</label>
                    <input type="text" id="edit_nombre" name="nombre" required>
                </div>
                <div class="form-group">
                    <label for="edit_descripcion">Descripción</label>
                    <textarea id="edit_descripcion" name="descripcion"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para confirmar eliminación -->
    <div id="modalEliminar" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Confirmar Eliminación</h2>
            <p>¿Estás seguro de que quieres eliminar el tipo de recurso "<span id="nombreEliminar"></span>"?</p>
            <p><strong>Nota:</strong> Solo se pueden eliminar tipos que no tengan recursos asociados.</p>
            <form action="" method="POST">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" id="delete_id_tipo" name="id_tipo">
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Funciones para los modales
        function editarTipo(id, nombre, descripcion) {
            document.getElementById('edit_id_tipo').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_descripcion').value = descripcion;
            document.getElementById('modalEditar').style.display = 'block';
        }

        function eliminarTipo(id, nombre) {
            document.getElementById('delete_id_tipo').value = id;
            document.getElementById('nombreEliminar').textContent = nombre;
            document.getElementById('modalEliminar').style.display = 'block';
        }

        function cerrarModal() {
            document.getElementById('modalEditar').style.display = 'none';
            document.getElementById('modalEliminar').style.display = 'none';
        }

        // Cerrar modal al hacer clic en X o fuera del modal
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Cerrar modal con X
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.onclick = function() {
                cerrarModal();
            }
        });
    </script>
</body>

</html> 