<?php

/**
 * Módulo de Reportes - Uso de Recursos
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

// Definir variables para filtros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$id_tipo = isset($_GET['tipo']) ? intval($_GET['tipo']) : 0;
$id_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$ordenar_por = isset($_GET['ordenar_por']) ? $_GET['ordenar_por'] : 'total_reservas';
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'DESC';

// Validar y formatear fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
    $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_fin = date('Y-m-d');
}

// Preparar filtros para la consulta
$filtros = [];
$params = [];

// Añadir filtro de fechas
$filtros[] = "r.fecha_inicio >= ?";
$params[] = $fecha_inicio . ' 00:00:00';

$filtros[] = "r.fecha_inicio <= ?";
$params[] = $fecha_fin . ' 23:59:59';

// Añadir filtro de tipo de recurso
if ($id_tipo > 0) {
    $filtros[] = "tr.id_tipo = ?";
    $params[] = $id_tipo;
}

// Añadir filtro de usuario
if ($id_usuario > 0) {
    $filtros[] = "r.id_usuario = ?";
    $params[] = $id_usuario;
}

// Añadir filtro de estado
if (!empty($estado)) {
    $filtros[] = "r.estado = ?";
    $params[] = $estado;
}

// Construir cláusula WHERE
$where = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Validar ordenamiento
$ordenamientos_validos = ['total_reservas', 'porcentaje_ocupacion', 'duracion_promedio', 'nombre_recurso'];
if (!in_array($ordenar_por, $ordenamientos_validos)) {
    $ordenar_por = 'total_reservas';
}

$ordenes_validos = ['ASC', 'DESC'];
if (!in_array($orden, $ordenes_validos)) {
    $orden = 'DESC';
}

// Consulta para obtener uso de recursos
$sql = "
    SELECT 
        rec.id_recurso,
        rec.nombre as nombre_recurso,
        tr.nombre as tipo_recurso,
        rec.ubicacion,
        COUNT(r.id_reserva) as total_reservas,
        SUM(
            TIMESTAMPDIFF(HOUR, 
                GREATEST(r.fecha_inicio, ?), 
                LEAST(r.fecha_fin, ?)
            )
        ) as horas_uso,
        AVG(
            TIMESTAMPDIFF(MINUTE, r.fecha_inicio, r.fecha_fin)
        ) / 60 as duracion_promedio,
        ROUND(
            (COUNT(r.id_reserva) / (
                SELECT COUNT(*) FROM reservas 
                WHERE fecha_inicio >= ? AND fecha_inicio <= ?
            )) * 100, 
            2
        ) as porcentaje_ocupacion
    FROM recursos rec
    LEFT JOIN reservas r ON rec.id_recurso = r.id_recurso AND r.fecha_inicio >= ? AND r.fecha_inicio <= ?
    LEFT JOIN tipos_recursos tr ON rec.id_tipo = tr.id_tipo
    $where
    GROUP BY rec.id_recurso
    ORDER BY $ordenar_por $orden
";

// Parámetros adicionales para cálculos en la consulta
$params_adicionales = [
    $fecha_inicio . ' 00:00:00', // GREATEST para horas_uso
    $fecha_fin . ' 23:59:59',    // LEAST para horas_uso
    $fecha_inicio . ' 00:00:00', // Para subquery de porcentaje
    $fecha_fin . ' 23:59:59',    // Para subquery de porcentaje
    $fecha_inicio . ' 00:00:00', // Para JOIN con reservas
    $fecha_fin . ' 23:59:59'     // Para JOIN con reservas
];

// Combinar los parámetros
$params = array_merge($params_adicionales, $params);

// Ejecutar consulta
$recursos_uso = $db->getRows($sql, $params);

// Obtener lista de tipos de recursos para filtrar
$tipos = $db->getRows(
    "SELECT id_tipo, nombre FROM tipos_recursos ORDER BY nombre"
);

// Obtener lista de usuarios activos para filtrar
$usuarios = $db->getRows(
    "SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) as nombre_completo, r.nombre as rol
     FROM usuarios u
     JOIN roles r ON u.id_rol = r.id_rol
     WHERE u.activo = 1
     ORDER BY u.nombre, u.apellido"
);

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Función para generar URL de ordenamiento
function sortUrl($campo)
{
    global $fecha_inicio, $fecha_fin, $id_tipo, $id_usuario, $estado, $ordenar_por, $orden;

    $nuevo_orden = ($ordenar_por == $campo && $orden == 'ASC') ? 'DESC' : 'ASC';

    return "?fecha_inicio=$fecha_inicio&fecha_fin=$fecha_fin&tipo=$id_tipo&usuario=$id_usuario&estado=$estado&ordenar_por=$campo&orden=$nuevo_orden";
}

// Calcular totales para mostrar resumen
$total_reservas = 0;
$total_horas = 0;
$recursos_mas_usados = [];

foreach ($recursos_uso as $recurso) {
    $total_reservas += $recurso['total_reservas'];
    $total_horas += $recurso['horas_uso'];

    // Guardar los 3 recursos más usados para mostrar en resumen
    if (count($recursos_mas_usados) < 3 && $recurso['total_reservas'] > 0) {
        $recursos_mas_usados[] = $recurso;
    }
}

// Obtener datos para el gráfico de distribución por tipo
$sql_distribucion = "
    SELECT 
        tr.nombre as tipo,
        COUNT(r.id_reserva) as total_reservas
    FROM tipos_recursos tr
    LEFT JOIN recursos rec ON tr.id_tipo = rec.id_tipo
    LEFT JOIN reservas r ON rec.id_recurso = r.id_recurso AND r.fecha_inicio BETWEEN ? AND ?
    GROUP BY tr.id_tipo
    ORDER BY total_reservas DESC
";

$distribucion_por_tipo = $db->getRows($sql_distribucion, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uso de Recursos - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/reportes.css">
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
                    <a href="../reportes/reportes_dashboard.php" class="nav-item active">Reportes</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Reporte de Uso de Recursos</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="report-filters">
                <h2 class="filter-title">Filtros de Búsqueda</h2>
                <form action="" method="GET">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-label" for="fecha_inicio">Fecha Inicio:</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" class="filter-input" value="<?php echo $fecha_inicio; ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="fecha_fin">Fecha Fin:</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" class="filter-input" value="<?php echo $fecha_fin; ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="tipo">Tipo de Recurso:</label>
                            <select id="tipo" name="tipo" class="filter-select">
                                <option value="0">Todos los tipos</option>
                                <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?php echo $tipo['id_tipo']; ?>" <?php echo ($id_tipo == $tipo['id_tipo']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-label" for="usuario">Usuario:</label>
                            <select id="usuario" name="usuario" class="filter-select">
                                <option value="0">Todos los usuarios</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?php echo $usuario['id_usuario']; ?>" <?php echo ($id_usuario == $usuario['id_usuario']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($usuario['nombre_completo'] . ' (' . $usuario['rol'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="estado">Estado de Reservas:</label>
                            <select id="estado" name="estado" class="filter-select">
                                <option value="">Todos los estados</option>
                                <option value="pendiente" <?php echo ($estado == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="confirmada" <?php echo ($estado == 'confirmada') ? 'selected' : ''; ?>>Confirmada</option>
                                <option value="cancelada" <?php echo ($estado == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                                <option value="completada" <?php echo ($estado == 'completada') ? 'selected' : ''; ?>>Completada</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="ordenar_por">Ordenar por:</label>
                            <select id="ordenar_por" name="ordenar_por" class="filter-select">
                                <option value="total_reservas" <?php echo ($ordenar_por == 'total_reservas') ? 'selected' : ''; ?>>Total de Reservas</option>
                                <option value="porcentaje_ocupacion" <?php echo ($ordenar_por == 'porcentaje_ocupacion') ? 'selected' : ''; ?>>Porcentaje de Ocupación</option>
                                <option value="duracion_promedio" <?php echo ($ordenar_por == 'duracion_promedio') ? 'selected' : ''; ?>>Duración Promedio</option>
                                <option value="nombre_recurso" <?php echo ($ordenar_por == 'nombre_recurso') ? 'selected' : ''; ?>>Nombre del Recurso</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="filtro-btn">Filtrar</button>
                        <a href="uso_recursos.php" class="filtro-btn btn-reset">Reiniciar</a>
                        <a href="exportar.php?reporte=uso_recursos&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&tipo=<?php echo $id_tipo; ?>&usuario=<?php echo $id_usuario; ?>&estado=<?php echo $estado; ?>" class="btn btn-secondary">
                            Exportar a CSV
                        </a>
                    </div>
                </form>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h2 class="card-title">Resumen del Período</h2>
                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_reservas); ?></div>
                            <div class="stat-label">Reservas Totales</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo number_format($total_horas); ?></div>
                            <div class="stat-label">Horas de Uso</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-highlight"><?php echo count($recursos_uso); ?></div>
                            <div class="stat-label">Recursos Analizados</div>
                        </div>
                    </div>

                    <?php if (!empty($recursos_mas_usados)): ?>
                        <h3 style="margin-top: 20px; font-size: 16px; color: var(--dark-color);">Recursos Más Utilizados:</h3>
                        <ul style="margin-top: 10px; padding-left: 20px;">
                            <?php foreach ($recursos_mas_usados as $recurso): ?>
                                <li>
                                    <?php echo htmlspecialchars($recurso['nombre_recurso']); ?> -
                                    <?php echo $recurso['total_reservas']; ?> reservas
                                    (<?php echo number_format($recurso['porcentaje_ocupacion'], 2); ?>% de ocupación)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2 class="card-title">Distribución de Uso por Tipo de Recurso</h2>
                    <div class="mini-chart" id="tipo-recursos-chart"></div>

                    <?php if (!empty($distribucion_por_tipo)): ?>
                        <table style="width: 100%; margin-top: 10px;">
                            <thead>
                                <tr>
                                    <th>Tipo de Recurso</th>
                                    <th class="number-cell">Total Reservas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($distribucion_por_tipo as $tipo): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tipo['tipo']); ?></td>
                                        <td class="number-cell"><?php echo $tipo['total_reservas']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Análisis Detallado de Uso de Recursos</h2>

                <?php if (empty($recursos_uso)): ?>
                    <div class="empty-state">
                        <p>No hay datos disponibles para los filtros seleccionados.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>
                                        <a href="<?php echo sortUrl('nombre_recurso'); ?>" class="sort-link">
                                            Recurso
                                            <?php if ($ordenar_por == 'nombre_recurso'): ?>
                                                <?php echo ($orden == 'ASC') ? '▲' : '▼'; ?>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Tipo</th>
                                    <th>Ubicación</th>
                                    <th class="number-cell">
                                        <a href="<?php echo sortUrl('total_reservas'); ?>" class="sort-link">
                                            Total Reservas
                                            <?php if ($ordenar_por == 'total_reservas'): ?>
                                                <?php echo ($orden == 'ASC') ? '▲' : '▼'; ?>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="number-cell">Horas de Uso</th>
                                    <th class="number-cell">
                                        <a href="<?php echo sortUrl('duracion_promedio'); ?>" class="sort-link">
                                            Duración Promedio (h)
                                            <?php if ($ordenar_por == 'duracion_promedio'): ?>
                                                <?php echo ($orden == 'ASC') ? '▲' : '▼'; ?>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="number-cell">
                                        <a href="<?php echo sortUrl('porcentaje_ocupacion'); ?>" class="sort-link">
                                            % Ocupación
                                            <?php if ($ordenar_por == 'porcentaje_ocupacion'): ?>
                                                <?php echo ($orden == 'ASC') ? '▲' : '▼'; ?>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recursos_uso as $recurso): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($recurso['nombre_recurso']); ?></td>
                                        <td><?php echo htmlspecialchars($recurso['tipo_recurso']); ?></td>
                                        <td><?php echo htmlspecialchars($recurso['ubicacion'] ?: 'No especificada'); ?></td>
                                        <td class="number-cell"><?php echo $recurso['total_reservas']; ?></td>
                                        <td class="number-cell"><?php echo number_format($recurso['horas_uso'], 1); ?></td>
                                        <td class="number-cell"><?php echo number_format($recurso['duracion_promedio'], 1); ?></td>
                                        <td class="number-cell"><?php echo number_format($recurso['porcentaje_ocupacion'], 2); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">Recomendaciones</h2>

                <div style="margin-top: 10px;">
                    <?php if (!empty($recursos_uso)): ?>
                        <h3 style="font-size: 16px; margin-bottom: 10px;">Basado en los datos analizados:</h3>

                        <ul style="padding-left: 20px; margin-bottom: 15px;">
                            <?php
                            // Identificar recursos subutilizados (menos del 10% de ocupación)
                            $recursos_subutilizados = array_filter($recursos_uso, function ($r) {
                                return $r['porcentaje_ocupacion'] < 10 && $r['total_reservas'] > 0;
                            });

                            // Identificar recursos muy utilizados (más del 70% de ocupación)
                            $recursos_muy_utilizados = array_filter($recursos_uso, function ($r) {
                                return $r['porcentaje_ocupacion'] > 70;
                            });

                            if (!empty($recursos_muy_utilizados)):
                            ?>
                                <li>
                                    <strong>Recursos muy solicitados:</strong> Considere adquirir más unidades de:
                                    <ul>
                                        <?php foreach (array_slice($recursos_muy_utilizados, 0, 3) as $recurso): ?>
                                            <li><?php echo htmlspecialchars($recurso['nombre_recurso']); ?> (<?php echo number_format($recurso['porcentaje_ocupacion'], 2); ?>% de ocupación)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php endif; ?>

                            <?php if (!empty($recursos_subutilizados)): ?>
                                <li>
                                    <strong>Recursos subutilizados:</strong> Considere promocionar más el uso de:
                                    <ul>
                                        <?php foreach (array_slice($recursos_subutilizados, 0, 3) as $recurso): ?>
                                            <li><?php echo htmlspecialchars($recurso['nombre_recurso']); ?> (solo <?php echo number_format($recurso['porcentaje_ocupacion'], 2); ?>% de ocupación)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php endif; ?>

                            <?php
                            // Recursos sin uso
                            $recursos_sin_uso = array_filter($recursos_uso, function ($r) {
                                return $r['total_reservas'] == 0;
                            });

                            if (count($recursos_sin_uso) > 0):
                            ?>
                                <li>
                                    <strong>Recursos sin uso:</strong> <?php echo count($recursos_sin_uso); ?> recursos no han sido reservados en el período analizado.
                                    Considere evaluar su disponibilidad o reubicación.
                                </li>
                            <?php endif; ?>

                            <?php
                            // Analizar duración promedio para detectar posibles problemas
                            $duracion_maxima = max(array_map(function ($r) {
                                return $r['duracion_promedio'];
                            }, $recursos_uso));
                            $recursos_larga_duracion = array_filter($recursos_uso, function ($r) use ($duracion_maxima) {
                                return $r['duracion_promedio'] > ($duracion_maxima * 0.8) && $r['total_reservas'] > 2;
                            });

                            if (!empty($recursos_larga_duracion)):
                            ?>
                                <li>
                                    <strong>Recursos con reservas de larga duración:</strong> Los siguientes recursos tienen reservas con duración significativamente mayor al promedio:
                                    <ul>
                                        <?php foreach (array_slice($recursos_larga_duracion, 0, 3) as $recurso): ?>
                                            <li><?php echo htmlspecialchars($recurso['nombre_recurso']); ?> (<?php echo number_format($recurso['duracion_promedio'], 1); ?> horas en promedio)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                    Esto podría indicar que estos recursos tienen demanda para períodos más largos. Considere establecer políticas específicas para su uso.
                                </li>
                            <?php endif; ?>
                        </ul>

                        <?php if ($total_reservas > 0): ?>
                            <div style="margin-top: 20px; padding: 15px; background-color: rgba(74, 144, 226, 0.1); border-radius: 4px;">
                                <h4 style="margin-bottom: 10px; color: var(--primary-color);">Sugerencias de optimización:</h4>
                                <p>
                                    <?php if (count($recursos_muy_utilizados) > count($recursos_subutilizados)): ?>
                                        Los datos muestran una alta demanda en ciertos recursos. Considere implementar un sistema de priorización o ampliar la disponibilidad de los recursos más solicitados.
                                    <?php elseif (count($recursos_sin_uso) > count($recursos_uso) * 0.3): ?>
                                        Una proporción significativa de recursos no está siendo utilizada. Recomendamos una revisión integral del inventario para optimizar la asignación de recursos.
                                    <?php else: ?>
                                        La distribución de uso de recursos parece equilibrada, pero hay oportunidades de optimización en recursos específicos. Revise las tendencias periódicamente.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No hay suficientes datos para generar recomendaciones.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/reportes.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar el gráfico de distribución por tipo de recurso
            const tipoRecursosChart = document.getElementById('tipo-recursos-chart');

            if (tipoRecursosChart) {
                // Aquí se implementaría la lógica para renderizar el gráfico
                // con una biblioteca como Chart.js

                // Por ahora, solo un mensaje temporal
                tipoRecursosChart.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6c757d;">Gráfico de distribución por tipo de recurso</div>';
            }

            // Agregar tooltip a los elementos de la tabla
            const tablaCeldas = document.querySelectorAll('.report-table td');
            tablaCeldas.forEach(celda => {
                celda.title = celda.textContent.trim();
            });

            // Destacar filas con alta/baja utilización
            const filas = document.querySelectorAll('.report-table tbody tr');
            filas.forEach(fila => {
                const celdaOcupacion = fila.querySelector('td:nth-child(7)');
                if (celdaOcupacion) {
                    const ocupacion = parseFloat(celdaOcupacion.textContent);

                    if (ocupacion > 70) {
                        fila.style.backgroundColor = 'rgba(40, 167, 69, 0.1)';
                    } else if (ocupacion < 10 && ocupacion > 0) {
                        fila.style.backgroundColor = 'rgba(255, 193, 7, 0.1)';
                    }
                }
            });
        });
    </script>
</body>

</html>