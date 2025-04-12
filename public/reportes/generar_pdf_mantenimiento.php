<?php

/**
 * Generador de PDF para reporte de mantenimiento de recursos
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
    $_SESSION['error'] = "No tienes permisos para acceder a esta función";
    redirect('../admin/dashboard.php');
    exit;
}

// Obtener parámetros del reporte
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-90 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$id_tipo = isset($_GET['tipo']) ? intval($_GET['tipo']) : 0;
$id_recurso = isset($_GET['recurso']) ? intval($_GET['recurso']) : 0;
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$agrupacion = isset($_GET['agrupacion']) ? $_GET['agrupacion'] : 'recurso';

// Validar y formatear fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
    $fecha_inicio = date('Y-m-d', strtotime('-90 days'));
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_fin = date('Y-m-d');
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Preparar filtros para la consulta
$filtros = [];
$params = [];

// Añadir filtro de fechas
$filtros[] = "m.fecha_inicio >= ?";
$params[] = $fecha_inicio . ' 00:00:00';

$filtros[] = "m.fecha_inicio <= ?";
$params[] = $fecha_fin . ' 23:59:59';

// Añadir filtro de tipo de recurso
if ($id_tipo > 0) {
    $filtros[] = "tr.id_tipo = ?";
    $params[] = $id_tipo;
}

// Añadir filtro de recurso específico
if ($id_recurso > 0) {
    $filtros[] = "r.id_recurso = ?";
    $params[] = $id_recurso;
}

// Añadir filtro de estado
if (!empty($estado)) {
    $filtros[] = "m.estado = ?";
    $params[] = $estado;
}

// Construir cláusula WHERE
$where = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Generar consulta según agrupación seleccionada
switch ($agrupacion) {
    case 'recurso':
        $sql = "
            SELECT 
                r.id_recurso,
                r.nombre as nombre_recurso,
                tr.nombre as tipo_recurso,
                COUNT(*) as total_mantenimientos,
                SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
            FROM mantenimiento m
            JOIN recursos r ON m.id_recurso = r.id_recurso
            JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
            $where
            GROUP BY r.id_recurso
            ORDER BY total_mantenimientos DESC
        ";
        break;

    case 'mensual':
        $sql = "
            SELECT 
                DATE_FORMAT(m.fecha_inicio, '%Y-%m') as mes,
                DATE_FORMAT(m.fecha_inicio, '%m/%Y') as mes_formateado,
                COUNT(*) as total_mantenimientos,
                SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
            FROM mantenimiento m
            JOIN recursos r ON m.id_recurso = r.id_recurso
            JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
            $where
            GROUP BY DATE_FORMAT(m.fecha_inicio, '%Y-%m')
            ORDER BY DATE_FORMAT(m.fecha_inicio, '%Y-%m')
        ";
        break;

    case 'tipo':
        $sql = "
            SELECT 
                tr.id_tipo,
                tr.nombre as tipo_recurso,
                COUNT(*) as total_mantenimientos,
                SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
            FROM mantenimiento m
            JOIN recursos r ON m.id_recurso = r.id_recurso
            JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
            $where
            GROUP BY tr.id_tipo
            ORDER BY total_mantenimientos DESC
        ";
        break;

    case 'responsable':
        $sql = "
            SELECT 
                u.id_usuario,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_responsable,
                ro.nombre as rol,
                COUNT(*) as total_mantenimientos,
                SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
            FROM mantenimiento m
            JOIN recursos r ON m.id_recurso = r.id_recurso
            JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
            JOIN usuarios u ON m.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            $where
            GROUP BY u.id_usuario
            ORDER BY total_mantenimientos DESC
        ";
        break;

    default:
        $sql = "
            SELECT 
                r.id_recurso,
                r.nombre as nombre_recurso,
                tr.nombre as tipo_recurso,
                COUNT(*) as total_mantenimientos,
                SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio
            FROM mantenimiento m
            JOIN recursos r ON m.id_recurso = r.id_recurso
            JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
            $where
            GROUP BY r.id_recurso
            ORDER BY total_mantenimientos DESC
        ";
}

// Obtener datos para el reporte
$estadisticas = $db->getRows($sql, $params);

// Consulta para obtener el resumen total
$sql_resumen = "
    SELECT 
        COUNT(*) as total_mantenimientos,
        SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN m.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN m.estado = 'completado' THEN 1 ELSE 0 END) as completados,
        AVG(TIMESTAMPDIFF(HOUR, m.fecha_inicio, IFNULL(m.fecha_fin, NOW()))) as duracion_promedio,
        COUNT(DISTINCT m.id_recurso) as total_recursos_afectados
    FROM mantenimiento m
    JOIN recursos r ON m.id_recurso = r.id_recurso
    JOIN tipos_recursos tr ON r.id_tipo = tr.id_tipo
    $where
";

$resumen = $db->getRow($sql_resumen, $params);

// Obtener nombre del tipo de recurso seleccionado
$nombre_tipo = "Todos los tipos";
if ($id_tipo > 0) {
    $tipo_dato = $db->getRow("SELECT nombre FROM tipos_recursos WHERE id_tipo = ?", [$id_tipo]);
    if ($tipo_dato) {
        $nombre_tipo = $tipo_dato['nombre'];
    }
}

// Obtener nombre del recurso seleccionado
$nombre_recurso = "Todos los recursos";
if ($id_recurso > 0) {
    $recurso_dato = $db->getRow("SELECT nombre FROM recursos WHERE id_recurso = ?", [$id_recurso]);
    if ($recurso_dato) {
        $nombre_recurso = $recurso_dato['nombre'];
    }
}

// Estética: Traducir nombre de estado si se ha filtrado
if (!empty($estado)) {
    switch ($estado) {
        case 'pendiente':
            $estado_str = "Pendiente";
            break;
        case 'en_progreso':
            $estado_str = "En Progreso";
            break;
        case 'completado':
            $estado_str = "Completado";
            break;
        default:
            $estado_str = $estado;
    }
} else {
    $estado_str = "Todos los estados";
}

// Traducir tipo de agrupación
switch ($agrupacion) {
    case 'recurso':
        $agrupacion_str = "Por Recurso";
        break;
    case 'mensual':
        $agrupacion_str = "Mensual";
        break;
    case 'tipo':
        $agrupacion_str = "Por Tipo de Recurso";
        break;
    case 'responsable':
        $agrupacion_str = "Por Responsable";
        break;
    default:
        $agrupacion_str = $agrupacion;
}

// Función para formatear horas
function formatear_horas($horas)
{
    if (is_null($horas)) return "N/A";

    if ($horas < 1) {
        return round($horas * 60) . " min";
    } elseif ($horas < 24) {
        return round($horas, 1) . " horas";
    } else {
        $dias = floor($horas / 24);
        $horas_restantes = $horas % 24;
        return $dias . " días " . round($horas_restantes, 1) . " h";
    }
}

// Función segura para number_format que evita errores con valores nulos
function number_format_safe($number, $decimals = 0, $dec_point = '.', $thousands_sep = ',')
{
    return is_null($number) ? 'N/A' : number_format((float)$number, $decimals, $dec_point, $thousands_sep);
}

// Configuración para el PDF
// Requiere tener instalada una librería como TCPDF, FPDF o similar
// Verificaremos si está disponible la librería TCPDF
if (!class_exists('TCPDF') && file_exists(BASE_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once BASE_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
}

if (!class_exists('TCPDF')) {
    // Si no está disponible TCPDF, usar una solución alternativa
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Reporte de Mantenimiento de Recursos</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            h1 { color: #4a90e2; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .summary { margin: 20px 0; }
            .filters { margin-bottom: 20px; padding: 10px; background: #f8f9fa; border: 1px solid #eee; }
        </style>
    </head>
    <body>
        <div style='text-align:right;'>
            <button onclick='window.print()'>Imprimir</button>
        </div>
        
        <h1>Reporte de Mantenimiento de Recursos</h1>
        
        <div class='filters'>
            <strong>Filtros aplicados:</strong><br>
            Período: " . date('d/m/Y', strtotime($fecha_inicio)) . " - " . date('d/m/Y', strtotime($fecha_fin)) . "<br>
            Tipo de recurso: " . htmlspecialchars($nombre_tipo) . "<br>
            Recurso: " . htmlspecialchars($nombre_recurso) . "<br>
            Estado: " . htmlspecialchars($estado_str) . "<br>
            Agrupación: " . htmlspecialchars($agrupacion_str) . "
        </div>
        
        <div class='summary'>
            <h2>Resumen del Período</h2>
            <p><strong>Total de mantenimientos:</strong> " . number_format_safe($resumen['total_mantenimientos']) . "</p>
            <p><strong>Pendientes:</strong> " . number_format_safe($resumen['pendientes']) . " 
               (" . number_format_safe(($resumen['total_mantenimientos'] > 0 ? ($resumen['pendientes'] / $resumen['total_mantenimientos']) * 100 : 0), 1) . "%)</p>
            <p><strong>En progreso:</strong> " . number_format_safe($resumen['en_progreso']) . " 
               (" . number_format_safe(($resumen['total_mantenimientos'] > 0 ? ($resumen['en_progreso'] / $resumen['total_mantenimientos']) * 100 : 0), 1) . "%)</p>
            <p><strong>Completados:</strong> " . number_format_safe($resumen['completados']) . " 
               (" . number_format_safe(($resumen['total_mantenimientos'] > 0 ? ($resumen['completados'] / $resumen['total_mantenimientos']) * 100 : 0), 1) . "%)</p>
            <p><strong>Duración promedio:</strong> " . formatear_horas($resumen['duracion_promedio']) . "</p>
            <p><strong>Recursos afectados:</strong> " . number_format_safe($resumen['total_recursos_afectados']) . "</p>
        </div>";

    // Tabla de estadísticas según la agrupación
    echo "<h2>Estadísticas Detalladas - " . htmlspecialchars($agrupacion_str) . "</h2>";

    if (empty($estadisticas)) {
        echo "<p>No hay datos disponibles para los filtros seleccionados.</p>";
    } else {
        echo "<table>
            <thead>
                <tr>";

        switch ($agrupacion) {
            case 'recurso':
                echo "<th>Recurso</th><th>Tipo</th>";
                break;
            case 'mensual':
                echo "<th>Mes</th>";
                break;
            case 'tipo':
                echo "<th>Tipo de Recurso</th>";
                break;
            case 'responsable':
                echo "<th>Responsable</th><th>Rol</th>";
                break;
        }

        echo "<th>Total Mantenimientos</th>
                <th>Pendientes</th>
                <th>En Progreso</th>
                <th>Completados</th>
                <th>Duración Promedio</th>
            </tr>
            </thead>
            <tbody>";

        foreach ($estadisticas as $item) {
            echo "<tr>";

            switch ($agrupacion) {
                case 'recurso':
                    echo "<td>" . htmlspecialchars($item['nombre_recurso']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['tipo_recurso']) . "</td>";
                    break;
                case 'mensual':
                    echo "<td>" . htmlspecialchars($item['mes_formateado']) . "</td>";
                    break;
                case 'tipo':
                    echo "<td>" . htmlspecialchars($item['tipo_recurso']) . "</td>";
                    break;
                case 'responsable':
                    echo "<td>" . htmlspecialchars($item['nombre_responsable']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['rol']) . "</td>";
                    break;
            }

            echo "<td>" . $item['total_mantenimientos'] . "</td>";
            echo "<td>" . $item['pendientes'] . "</td>";
            echo "<td>" . $item['en_progreso'] . "</td>";
            echo "<td>" . $item['completados'] . "</td>";
            echo "<td>" . formatear_horas($item['duracion_promedio']) . "</td>";
            echo "</tr>";
        }

        echo "</tbody>
        </table>";
    }

    echo "<div style='text-align: center; margin-top: 30px; font-size: 12px; color: #777;'>
            Informe generado el " . date('d/m/Y H:i') . " por " . htmlspecialchars($_SESSION['usuario_nombre']) . "
        </div>
    </body>
    </html>";
} else {
    // Usar TCPDF para generar el PDF
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

    // Configurar el documento
    $pdf->SetCreator('Sistema de Gestión de Recursos');
    $pdf->SetAuthor($_SESSION['usuario_nombre']);
    $pdf->SetTitle('Reporte de Mantenimiento de Recursos');
    $pdf->SetSubject('Mantenimiento de recursos del ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)));

    // Establecer margenes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    // Desactivar encabezado y pie de página predeterminados
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Agregar página
    $pdf->AddPage();

    // Establecer fuente
    $pdf->SetFont('helvetica', 'B', 16);

    // Título
    $pdf->Cell(0, 10, 'Reporte de Mantenimiento de Recursos', 0, 1, 'C');
    $pdf->Ln(5);

    // Información de filtros
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Filtros aplicados:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(40, 6, 'Período:', 0, 0);
    $pdf->Cell(0, 6, date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)), 0, 1);
    $pdf->Cell(40, 6, 'Tipo de recurso:', 0, 0);
    $pdf->Cell(0, 6, $nombre_tipo, 0, 1);
    $pdf->Cell(40, 6, 'Recurso:', 0, 0);
    $pdf->Cell(0, 6, $nombre_recurso, 0, 1);
    $pdf->Cell(40, 6, 'Estado:', 0, 0);
    $pdf->Cell(0, 6, $estado_str, 0, 1);
    $pdf->Cell(40, 6, 'Agrupación:', 0, 0);
    $pdf->Cell(0, 6, $agrupacion_str, 0, 1);
    $pdf->Ln(5);

    // Resumen
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Resumen del Período', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 6, 'Total de mantenimientos:', 0, 0);
    $pdf->Cell(0, 6, number_format_safe($resumen['total_mantenimientos']), 0, 1);

    $pdf->Cell(60, 6, 'Pendientes:', 0, 0);
    $porcentaje_pendientes = $resumen['total_mantenimientos'] > 0 ? ($resumen['pendientes'] / $resumen['total_mantenimientos']) * 100 : 0;
    $pdf->Cell(0, 6, number_format_safe($resumen['pendientes']) . ' (' . number_format_safe($porcentaje_pendientes, 1) . '%)', 0, 1);

    $pdf->Cell(60, 6, 'En progreso:', 0, 0);
    $porcentaje_progreso = $resumen['total_mantenimientos'] > 0 ? ($resumen['en_progreso'] / $resumen['total_mantenimientos']) * 100 : 0;
    $pdf->Cell(0, 6, number_format_safe($resumen['en_progreso']) . ' (' . number_format_safe($porcentaje_progreso, 1) . '%)', 0, 1);

    $pdf->Cell(60, 6, 'Completados:', 0, 0);
    $porcentaje_completados = $resumen['total_mantenimientos'] > 0 ? ($resumen['completados'] / $resumen['total_mantenimientos']) * 100 : 0;
    $pdf->Cell(0, 6, number_format_safe($resumen['completados']) . ' (' . number_format_safe($porcentaje_completados, 1) . '%)', 0, 1);

    $pdf->Cell(60, 6, 'Duración promedio:', 0, 0);
    $pdf->Cell(0, 6, formatear_horas($resumen['duracion_promedio']), 0, 1);

    $pdf->Cell(60, 6, 'Recursos afectados:', 0, 0);
    $pdf->Cell(0, 6, number_format_safe($resumen['total_recursos_afectados']), 0, 1);

    $pdf->Ln(5);

    // Tabla de estadísticas según la agrupación
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Estadísticas Detalladas - ' . $agrupacion_str, 0, 1);

    if (empty($estadisticas)) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 10, 'No hay datos disponibles para los filtros seleccionados.', 0, 1);
    } else {
        $pdf->SetFont('helvetica', 'B', 9);

        // Cabecera de la tabla según agrupación
        switch ($agrupacion) {
            case 'recurso':
                $pdf->Cell(60, 7, 'Recurso', 1, 0, 'C');
                $pdf->Cell(40, 7, 'Tipo', 1, 0, 'C');
                $ancho_resto = 50;
                break;
            case 'mensual':
                $pdf->Cell(30, 7, 'Mes', 1, 0, 'C');
                $ancho_resto = 60;
                break;
            case 'tipo':
                $pdf->Cell(60, 7, 'Tipo de Recurso', 1, 0, 'C');
                $ancho_resto = 50;
                break;
            case 'responsable':
                $pdf->Cell(60, 7, 'Responsable', 1, 0, 'C');
                $pdf->Cell(40, 7, 'Rol', 1, 0, 'C');
                $ancho_resto = 50;
                break;
        }

        // Resto de cabeceras comunes
        $pdf->Cell($ancho_resto, 7, 'Total Mantenimientos', 1, 0, 'C');
        $pdf->Cell($ancho_resto, 7, 'Pendientes', 1, 0, 'C');
        $pdf->Cell($ancho_resto, 7, 'En Progreso', 1, 0, 'C');
        $pdf->Cell($ancho_resto, 7, 'Completados', 1, 0, 'C');
        $pdf->Cell($ancho_resto, 7, 'Duración Promedio', 1, 1, 'C');

        // Datos de la tabla
        $pdf->SetFont('helvetica', '', 8);

        foreach ($estadisticas as $item) {
            switch ($agrupacion) {
                case 'recurso':
                    $pdf->Cell(60, 6, $item['nombre_recurso'], 1, 0);
                    $pdf->Cell(40, 6, $item['tipo_recurso'], 1, 0);
                    break;
                case 'mensual':
                    $pdf->Cell(30, 6, $item['mes_formateado'], 1, 0);
                    break;
                case 'tipo':
                    $pdf->Cell(60, 6, $item['tipo_recurso'], 1, 0);
                    break;
                case 'responsable':
                    $pdf->Cell(60, 6, $item['nombre_responsable'], 1, 0);
                    $pdf->Cell(40, 6, $item['rol'], 1, 0);
                    break;
            }

            $pdf->Cell($ancho_resto, 6, $item['total_mantenimientos'], 1, 0, 'C');
            $pdf->Cell($ancho_resto, 6, $item['pendientes'], 1, 0, 'C');
            $pdf->Cell($ancho_resto, 6, $item['en_progreso'], 1, 0, 'C');
            $pdf->Cell($ancho_resto, 6, $item['completados'], 1, 0, 'C');
            $pdf->Cell($ancho_resto, 6, formatear_horas($item['duracion_promedio']), 1, 1, 'C');
        }
    }

    // Pie de página
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Informe generado el ' . date('d/m/Y H:i') . ' por ' . $_SESSION['usuario_nombre'], 0, 0, 'C');

    // Salida del PDF
    $pdf->Output('reporte_mantenimiento_' . date('Ymd') . '.pdf', 'D');
}
