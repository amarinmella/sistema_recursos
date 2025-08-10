<?php

/**
 * Módulo de Reportes - Exportación de Datos
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado y tenga permisos
require_login();

// Solo administradores y académicos pueden acceder a reportes
if (!has_role([ROL_ADMIN, ROL_ACADEMICO])) {
    $_SESSION['error'] = "No tienes permisos para acceder al módulo de reportes";
    redirect('../admin/dashboard.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Verificar si se solicita exportación directa
if (isset($_GET['reporte']) && !empty($_GET['reporte'])) {
    $tipo_reporte = $_GET['reporte'];

    // Validar el tipo de reporte solicitado
    $reportes_validos = ['uso_recursos', 'estadisticas_reservas', 'usuarios', 'reservas', 'recursos', 'mantenimientos'];

    if (in_array($tipo_reporte, $reportes_validos)) {
        // Procesar la exportación directa
        exportarReporte($tipo_reporte, $_GET);
        exit;
    }
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

// Si se envió el formulario de exportación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exportar'])) {
    $tipo_reporte = $_POST['tipo_reporte'];
    $formato = $_POST['formato'];
    $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');

    // Validar el tipo de reporte
    $reportes_validos = ['uso_recursos', 'estadisticas_reservas', 'usuarios', 'reservas', 'recursos', 'mantenimientos'];

    if (!in_array($tipo_reporte, $reportes_validos)) {
        $_SESSION['error'] = "Tipo de reporte no válido";
        redirect('exportar.php');
        exit;
    }

    // Validar el formato
    $formatos_validos = ['csv', 'excel', 'pdf'];

    if (!in_array($formato, $formatos_validos)) {
        $_SESSION['error'] = "Formato de exportación no válido";
        redirect('exportar.php');
        exit;
    }

    // Exportar el reporte
    exportarReporte($tipo_reporte, $_POST);
    exit;
}

// Función para exportar reportes
function exportarReporte($tipo_reporte, $parametros)
{
    global $db;

    // Obtener parámetros comunes
    $fecha_inicio = $parametros['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $fecha_fin = $parametros['fecha_fin'] ?? date('Y-m-d');
    $formato = $parametros['formato'] ?? 'csv';

    // Validar fechas
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
        $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
        $fecha_fin = date('Y-m-d');
    }

    // Definir el nombre del archivo
    $nombre_archivo = $tipo_reporte . '_' . date('Ymd');

    // Manejar exportación según el formato
    switch ($formato) {
        case 'pdf':
            // Redirigir a los archivos específicos de generación de PDF
            switch ($tipo_reporte) {
                case 'uso_recursos':
                    // Redirige al script específico para el informe PDF de uso de recursos
                    $queryParams = http_build_query([
                        'fecha_inicio' => $fecha_inicio,
                        'fecha_fin' => $fecha_fin,
                        'tipo' => $parametros['tipo'] ?? 0,
                        'usuario' => $parametros['usuario'] ?? 0,
                        'estado' => $parametros['estado'] ?? ''
                    ]);
                    header("Location: generar_pdf_uso_recursos.php?$queryParams");
                    exit;

                case 'estadisticas_reservas':
                    // Redirige al script específico para el informe PDF de estadísticas de reservas
                    $queryParams = http_build_query([
                        'fecha_inicio' => $fecha_inicio,
                        'fecha_fin' => $fecha_fin,
                        'visualizacion' => $parametros['visualizacion'] ?? 'mensual',
                        'tipo' => $parametros['tipo'] ?? 0,
                        'usuario' => $parametros['usuario'] ?? 0,
                        'estado' => $parametros['estado'] ?? ''
                    ]);
                    header("Location: generar_pdf_estadisticas_reservas.php?$queryParams");
                    exit;

                case 'usuarios':
                    // Redirige al script específico para el informe PDF de usuarios
                    $queryParams = http_build_query([
                        'rol' => $parametros['rol'] ?? 0,
                        'activo' => $parametros['activo'] ?? '',
                        'busqueda' => $parametros['busqueda'] ?? ''
                    ]);
                    header("Location: generar_pdf_usuarios.php?$queryParams");
                    exit;

                case 'reservas':
                    // Redirige al script específico para el informe PDF de reservas
                    $queryParams = http_build_query([
                        'fecha_inicio' => $fecha_inicio,
                        'fecha_fin' => $fecha_fin,
                        'recurso' => $parametros['recurso'] ?? 0,
                        'usuario' => $parametros['usuario'] ?? 0,
                        'estado' => $parametros['estado'] ?? ''
                    ]);
                    header("Location: generar_pdf_reservas.php?$queryParams");
                    exit;

                case 'recursos':
                    // Redirige al script específico para el informe PDF de recursos
                    $queryParams = http_build_query([
                        'tipo' => $parametros['tipo'] ?? 0,
                        'estado' => $parametros['estado'] ?? '',
                        'busqueda' => $parametros['busqueda'] ?? ''
                    ]);
                    header("Location: generar_pdf_recursos.php?$queryParams");
                    exit;

                case 'mantenimientos':
                    // Redirige al script específico para el informe PDF de mantenimientos
                    $queryParams = http_build_query([
                        'fecha_inicio' => $fecha_inicio,
                        'fecha_fin' => $fecha_fin,
                        'recurso' => $parametros['recurso'] ?? 0,
                        'usuario' => $parametros['usuario'] ?? 0,
                        'estado' => $parametros['estado'] ?? ''
                    ]);
                    header("Location: generar_pdf_mantenimientos.php?$queryParams");
                    exit;

                default:
                    $_SESSION['error'] = "Tipo de reporte no soportado para exportación a PDF";
                    redirect('exportar.php');
                    exit;
            }
            break;

        case 'csv':
            // Redirigir al script específico de exportación CSV
            $queryParams = http_build_query(array_merge(['reporte' => $tipo_reporte], $parametros));
            header("Location: exportar_csv.php?$queryParams");
            exit;

        case 'excel':
            // Redirigir al script específico de exportación Excel
            $queryParams = http_build_query(array_merge(['reporte' => $tipo_reporte], $parametros));
            header("Location: exportar_excel.php?$queryParams");
            exit;

        default:
            $_SESSION['error'] = "Formato de exportación no soportado";
            redirect('exportar.php');
            exit;
    }
}

// Obtener tipos de reportes disponibles para el formulario
$tipos_reportes = [
    'uso_recursos' => 'Uso de Recursos',
    'estadisticas_reservas' => 'Estadísticas de Reservas',
    'usuarios' => 'Usuarios del Sistema',
    'reservas' => 'Listado de Reservas',
    'recursos' => 'Inventario de Recursos',
    'mantenimientos' => 'Registro de Mantenimientos'
];

// Obtener formatos de exportación disponibles
$formatos_exportacion = [
    'csv' => 'CSV (Valores Separados por Comas)',
    'excel' => 'Excel (XLSX)',
    'pdf' => 'PDF (Documento Portable)'
];

// Obtener lista de tipos de recursos para filtros
$tipos_recursos = $db->getRows("SELECT id_tipo, nombre FROM tipos_recursos ORDER BY nombre");

// Obtener lista de usuarios para filtros
$usuarios = $db->getRows(
    "SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) as nombre_completo 
     FROM usuarios u 
     WHERE u.activo = 1 
     ORDER BY u.apellido, u.nombre"
);

// Obtener lista de roles para filtros
$roles = $db->getRows("SELECT id_rol, nombre FROM roles ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportación de Datos - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/reportes.css">
    <link rel="stylesheet" href="../assets/css/pdf-buttons.css">
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
                    <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                    <a href="../inventario/listar.php" class="nav-item">Inventario</a>
                    <a href="../reportes/reportes_dashboard.php" class="nav-item active">Reportes</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Exportación de Datos</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="breadcrumb">
                <a href="../admin/dashboard.php">Dashboard</a> &gt;
                <a href="reportes_dashboard.php">Reportes</a> &gt;
                <span>Exportación de Datos</span>
            </div>

            <div class="card">
                <h2 class="card-title">Configuración de Exportación</h2>
                <p>Seleccione el tipo de reporte, formato y parámetros para la exportación.</p>

                <form action="" method="POST" class="export-form">
                    <div class="form-group">
                        <label for="tipo_reporte">Tipo de Reporte</label>
                        <select id="tipo_reporte" name="tipo_reporte" class="form-control" required>
                            <option value="">Seleccione un tipo de reporte</option>
                            <?php foreach ($tipos_reportes as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="formato">Formato de Exportación</label>
                        <select id="formato" name="formato" class="form-control" required>
                            <option value="">Seleccione un formato</option>
                            <?php foreach ($formatos_exportacion as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campos dinámicos según el tipo de reporte -->
                    <div id="params-container">
                        <!-- Aquí se cargarán los campos de parámetros dinámicamente -->
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="exportar" class="btn btn-primary">Exportar</button>
                        <a href="reportes_dashboard.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2 class="card-title">Exportaciones Directas</h2>
                <p>Acceda directamente a reportes predefinidos para una exportación rápida.</p>

                <div class="export-options">
                    <div class="export-option">
                        <div class="export-title">Uso de Recursos (CSV)</div>
                        <div class="export-description">Análisis de uso de recursos en el último mes, incluye estadísticas de horas y reservas.</div>
                        <a href="exportar_csv.php?reporte=uso_recursos&fecha_inicio=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&fecha_fin=<?php echo date('Y-m-d'); ?>" class="btn btn-primary">Exportar</a>
                    </div>

                    <div class="export-option">
                        <div class="export-title">Recursos Disponibles (PDF)</div>
                        <div class="export-description">Lista de todos los recursos actualmente disponibles para reserva.</div>
                        <a href="reportes/generar_pdf_recursos_disponibles.php" class="btn btn-primary">Exportar Recursos Disponibles (PDF)</a>
                    </div>

                    <div class="export-option">
                        <div class="export-title">Reservas Pendientes (CSV)</div>
                        <div class="export-description">Lista de todas las reservas pendientes de confirmación.</div>
                        <a href="exportar_csv.php?reporte=reservas&estado=pendiente" class="btn btn-primary">Exportar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tipoReporte = document.getElementById('tipo_reporte');
            const paramsContainer = document.getElementById('params-container');

            // Cambiar los parámetros según el tipo de reporte seleccionado
            tipoReporte.addEventListener('change', function() {
                const selectedType = this.value;
                let html = '';

                // Campos comunes para la mayoría de los reportes
                const camposFecha = `
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_inicio">Fecha Inicio</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="fecha_fin">Fecha Fin</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo date('Y-m-d'); ?>" class="form-control">
                        </div>
                    </div>
                `;

                // Generar campos específicos según el tipo de reporte
                switch (selectedType) {
                    case 'uso_recursos':
                        html = camposFecha + `
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="tipo">Tipo de Recurso</label>
                                    <select id="tipo" name="tipo" class="form-control">
                                        <option value="0">Todos los tipos</option>
                                        <?php foreach ($tipos_recursos as $tipo): ?>
                                            <option value="<?php echo $tipo['id_tipo']; ?>"><?php echo htmlspecialchars($tipo['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="usuario">Usuario</label>
                                    <select id="usuario" name="usuario" class="form-control">
                                        <option value="0">Todos los usuarios</option>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <option value="<?php echo $usuario['id_usuario']; ?>"><?php echo htmlspecialchars($usuario['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="estado">Estado de Reservas</label>
                                    <select id="estado" name="estado" class="form-control">
                                        <option value="">Todos los estados</option>
                                        <option value="pendiente">Pendiente</option>
                                        <option value="confirmada">Confirmada</option>
                                        <option value="cancelada">Cancelada</option>
                                        <option value="completada">Completada</option>
                                    </select>
                                </div>
                            </div>
                        `;
                        break;

                    case 'estadisticas_reservas':
                        html = camposFecha + `
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="visualizacion">Modo de Visualización</label>
                                    <select id="visualizacion" name="visualizacion" class="form-control">
                                        <option value="diario">Diario</option>
                                        <option value="semanal">Semanal</option>
                                        <option value="mensual" selected>Mensual</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="tipo">Tipo de Recurso</label>
                                    <select id="tipo" name="tipo" class="form-control">
                                        <option value="0">Todos los tipos</option>
                                        <?php foreach ($tipos_recursos as $tipo): ?>
                                            <option value="<?php echo $tipo['id_tipo']; ?>"><?php echo htmlspecialchars($tipo['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="usuario">Usuario</label>
                                    <select id="usuario" name="usuario" class="form-control">
                                        <option value="0">Todos los usuarios</option>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <option value="<?php echo $usuario['id_usuario']; ?>"><?php echo htmlspecialchars($usuario['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        `;
                        break;

                    case 'usuarios':
                        html = `
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="rol">Rol</label>
                                    <select id="rol" name="rol" class="form-control">
                                        <option value="0">Todos los roles</option>
                                        <?php foreach ($roles as $rol): ?>
                                            <option value="<?php echo $rol['id_rol']; ?>"><?php echo htmlspecialchars($rol['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="activo">Estado</label>
                                    <select id="activo" name="activo" class="form-control">
                                        <option value="">Todos</option>
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="busqueda">Búsqueda</label>
                                    <input type="text" id="busqueda" name="busqueda" class="form-control" placeholder="Nombre, apellido o email">
                                </div>
                            </div>
                        `;
                        break;

                    case 'reservas':
                        html = camposFecha + `
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="recurso">Recurso</label>
                                    <select id="recurso" name="recurso" class="form-control">
                                        <option value="0">Todos los recursos</option>
                                        <?php
                                        $recursos = $db->getRows("SELECT id_recurso, nombre FROM recursos ORDER BY nombre");
                                        foreach ($recursos as $recurso):
                                        ?>
                                            <option value="<?php echo $recurso['id_recurso']; ?>"><?php echo htmlspecialchars($recurso['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="usuario">Usuario</label>
                                    <select id="usuario" name="usuario" class="form-control">
                                        <option value="0">Todos los usuarios</option>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <option value="<?php echo $usuario['id_usuario']; ?>"><?php echo htmlspecialchars($usuario['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="estado">Estado</label>
                                    <select id="estado" name="estado" class="form-control">
                                        <option value="">Todos los estados</option>
                                        <option value="pendiente">Pendiente</option>
                                        <option value="confirmada">Confirmada</option>
                                        <option value="cancelada">Cancelada</option>
                                        <option value="completada">Completada</option>
                                    </select>
                                </div>
                            </div>
                        `;
                        break;

                    case 'recursos':
                        html = `
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="tipo">Tipo de Recurso</label>
                                    <select id="tipo" name="tipo" class="form-control">
                                        <option value="0">Todos los tipos</option>
                                        <?php foreach ($tipos_recursos as $tipo): ?>
                                            <option value="<?php echo $tipo['id_tipo']; ?>"><?php echo htmlspecialchars($tipo['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="estado">Estado</label>
                                    <select id="estado" name="estado" class="form-control">
                                        <option value="">Todos los estados</option>
                                        <option value="disponible">Disponible</option>
                                        <option value="mantenimiento">Mantenimiento</option>
                                        <option value="dañado">Dañado</option>
                                        <option value="baja">Baja</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="busqueda">Búsqueda</label>
                                    <input type="text" id="busqueda" name="busqueda" class="form-control" placeholder="Nombre o ubicación">
                                </div>
                            </div>
                        `;
                        break;

                    case 'mantenimientos':
                        html = camposFecha + `
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="recurso">Recurso</label>
                                    <select id="recurso" name="recurso" class="form-control">
                                        <option value="0">Todos los recursos</option>
                                        <?php
                                        $recursos = $db->getRows("SELECT id_recurso, nombre FROM recursos ORDER BY nombre");
                                        foreach ($recursos as $recurso):
                                        ?>
                                            <option value="<?php echo $recurso['id_recurso']; ?>"><?php echo htmlspecialchars($recurso['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="usuario">Responsable</label>
                                    <select id="usuario" name="usuario" class="form-control">
                                        <option value="0">Todos los responsables</option>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <option value="<?php echo $usuario['id_usuario']; ?>"><?php echo htmlspecialchars($usuario['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="estado">Estado</label>
                                    <select id="estado" name="estado" class="form-control">
                                        <option value="">Todos los estados</option>
                                        <option value="pendiente">Pendiente</option>
                                        <option value="en progreso">En Progreso</option>
                                        <option value="completado">Completado</option>
                                    </select>
                                </div>
                            </div>
                        `;
                        break;

                    default:
                        html = '<p>Seleccione un tipo de reporte para ver las opciones disponibles.</p>';
                        break;
                }

                paramsContainer.innerHTML = html;
            });

            // Validación del formulario
            const form = document.querySelector('.export-form');
            form.addEventListener('submit', function(event) {
                const tipoReporte = document.getElementById('tipo_reporte');
                const formato = document.getElementById('formato');

                if (tipoReporte.value === '') {
                    alert('Debe seleccionar un tipo de reporte');
                    event.preventDefault();
                    return;
                }

                if (formato.value === '') {
                    alert('Debe seleccionar un formato de exportación');
                    event.preventDefault();
                    return;
                }
            });
        });
    </script>
</body>

</html>