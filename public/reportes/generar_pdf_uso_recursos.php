<?php

/**
 * Generador de PDF para reporte de uso de recursos
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

// Obtener instancia de la base de datos
$db = Database::getInstance();

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

// Obtener nombre del tipo seleccionado
$nombre_tipo = "Todos los tipos";
if ($id_tipo > 0) {
    $tipo_dato = $db->getRow("SELECT nombre FROM tipos_recursos WHERE id_tipo = ?", [$id_tipo]);
    if ($tipo_dato) {
        $nombre_tipo = $tipo_dato['nombre'];
    }
}

// Obtener nombre del usuario seleccionado
$nombre_usuario = "Todos los usuarios";
if ($id_usuario > 0) {
    $usuario_dato = $db->getRow("SELECT CONCAT(nombre, ' ', apellido) as nombre_completo FROM usuarios WHERE id_usuario = ?", [$id_usuario]);
    if ($usuario_dato) {
        $nombre_usuario = $usuario_dato['nombre_completo'];
    }
}

// Calcular totales para el resumen
$total_reservas = 0;
$total_horas = 0;

foreach ($recursos_uso as $recurso) {
    $total_reservas += $recurso['total_reservas'];
    $total_horas += $recurso['horas_uso'];
}

// Estética: Traducir nombre de estado si se ha filtrado
if (!empty($estado)) {
    switch ($estado) {
        case 'pendiente':
            $estado_str = "Pendiente";
            break;
        case 'confirmada':
            $estado_str = "Confirmada";
            break;
        case 'cancelada':
            $estado_str = "Cancelada";
            break;
        case 'completada':
            $estado_str = "Completada";
            break;
        default:
            $estado_str = $estado;
    }
} else {
    $estado_str = "Todos los estados";
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
        <title>Reporte de Uso de Recursos</title>
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
        
        <h1>Reporte de Uso de Recursos</h1>
        
        <div class='filters'>
            <strong>Filtros aplicados:</strong><br>
            Período: " . date('d/m/Y', strtotime($fecha_inicio)) . " - " . date('d/m/Y', strtotime($fecha_fin)) . "<br>
            Tipo de recurso: " . htmlspecialchars($nombre_tipo) . "<br>
            Usuario: " . htmlspecialchars($nombre_usuario) . "<br>
            Estado: " . htmlspecialchars($estado_str) . "
        </div>
        
        <div class='summary'>
            <h2>Resumen del Período</h2>
            <p><strong>Total de reservas:</strong> " . number_format($total_reservas) . "</p>
            <p><strong>Total de horas de uso:</strong> " . number_format($total_horas) . "</p>
            <p><strong>Recursos analizados:</strong> " . count($recursos_uso) . "</p>
        </div>
        
        <h2>Detalle por Recurso</h2>
        <table>
            <thead>
                <tr>
                    <th>Recurso</th>
                    <th>Tipo</th>
                    <th>Ubicación</th>
                    <th>Total Reservas</th>
                    <th>Horas de Uso</th>
                    <th>Duración Promedio (h)</th>
                    <th>% Ocupación</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($recursos_uso as $recurso) {
        echo "<tr>
            <td>" . htmlspecialchars($recurso['nombre_recurso']) . "</td>
            <td>" . htmlspecialchars($recurso['tipo_recurso']) . "</td>
            <td>" . htmlspecialchars($recurso['ubicacion'] ?: 'No especificada') . "</td>
            <td>" . $recurso['total_reservas'] . "</td>
            <td>" . number_format($recurso['horas_uso'], 1) . "</td>
            <td>" . number_format($recurso['duracion_promedio'], 1) . "</td>
            <td>" . number_format($recurso['porcentaje_ocupacion'], 2) . "%</td>
        </tr>";
    }

    echo "</tbody>
        </table>
        
        <div style='text-align: center; margin-top: 30px; font-size: 12px; color: #777;'>
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
    $pdf->SetTitle('Reporte de Uso de Recursos');
    $pdf->SetSubject('Uso de recursos del ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)));

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
    $pdf->Cell(0, 10, 'Reporte de Uso de Recursos', 0, 1, 'C');
    $pdf->Ln(5);

    // Información de filtros
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Filtros aplicados:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(40, 6, 'Período:', 0, 0);
    $pdf->Cell(0, 6, date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)), 0, 1);
    $pdf->Cell(40, 6, 'Tipo de recurso:', 0, 0);
    $pdf->Cell(0, 6, $nombre_tipo, 0, 1);
    $pdf->Cell(40, 6, 'Usuario:', 0, 0);
    $pdf->Cell(0, 6, $nombre_usuario, 0, 1);
    $pdf->Cell(40, 6, 'Estado:', 0, 0);
    $pdf->Cell(0, 6, $estado_str, 0, 1);
    $pdf->Ln(5);

    // Resumen
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Resumen del Período', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 6, 'Total de reservas:', 0, 0);
    $pdf->Cell(0, 6, number_format($total_reservas), 0, 1);
    $pdf->Cell(60, 6, 'Total de horas de uso:', 0, 0);
    $pdf->Cell(0, 6, number_format($total_horas), 0, 1);
    $pdf->Cell(60, 6, 'Recursos analizados:', 0, 0);
    $pdf->Cell(0, 6, count($recursos_uso), 0, 1);
    $pdf->Ln(5);

    // Tabla de recursos
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Detalle por Recurso', 0, 1);

    $pdf->SetFont('helvetica', 'B', 9);

    // Cabecera de la tabla
    $pdf->Cell(60, 7, 'Recurso', 1, 0, 'C');
    $pdf->Cell(40, 7, 'Tipo', 1, 0, 'C');
    $pdf->Cell(40, 7, 'Ubicación', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Total Reservas', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Horas de Uso', 1, 0, 'C');
    $pdf->Cell(35, 7, 'Duración Promedio (h)', 1, 0, 'C');
    $pdf->Cell(25, 7, '% Ocupación', 1, 1, 'C');

    // Datos de la tabla
    $pdf->SetFont('helvetica', '', 8);

    foreach ($recursos_uso as $recurso) {
        $pdf->Cell(60, 6, $recurso['nombre_recurso'], 1, 0);
        $pdf->Cell(40, 6, $recurso['tipo_recurso'], 1, 0);
        $pdf->Cell(40, 6, $recurso['ubicacion'] ?: 'No especificada', 1, 0);
        $pdf->Cell(25, 6, $recurso['total_reservas'], 1, 0, 'C');
        $pdf->Cell(25, 6, number_format($recurso['horas_uso'], 1), 1, 0, 'C');
        $pdf->Cell(35, 6, number_format($recurso['duracion_promedio'], 1), 1, 0, 'C');
        $pdf->Cell(25, 6, number_format($recurso['porcentaje_ocupacion'], 2) . '%', 1, 1, 'C');
    }

    // Pie de página
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Informe generado el ' . date('d/m/Y H:i') . ' por ' . $_SESSION['usuario_nombre'], 0, 0, 'C');

    // Salida del PDF
    $pdf->Output('reporte_uso_recursos_' . date('Ymd') . '.pdf', 'D');
}
