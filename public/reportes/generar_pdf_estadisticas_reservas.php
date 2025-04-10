<?php

/**
 * Generador de PDF para reporte de estadísticas de reservas
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
$agrupacion = isset($_GET['agrupacion']) ? $_GET['agrupacion'] : 'diaria';

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

// Generar consulta según agrupación seleccionada
switch ($agrupacion) {
    case 'diaria':
        $sql = "
            SELECT 
                DATE(r.fecha_inicio) as fecha,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            $where
            GROUP BY DATE(r.fecha_inicio)
            ORDER BY DATE(r.fecha_inicio)
        ";
        break;

    case 'semanal':
        $sql = "
            SELECT 
                YEARWEEK(r.fecha_inicio, 1) as semana,
                MIN(DATE(r.fecha_inicio)) as fecha_inicio_semana,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            $where
            GROUP BY YEARWEEK(r.fecha_inicio, 1)
            ORDER BY YEARWEEK(r.fecha_inicio, 1)
        ";
        break;

    case 'mensual':
        $sql = "
            SELECT 
                DATE_FORMAT(r.fecha_inicio, '%Y-%m') as mes,
                DATE_FORMAT(r.fecha_inicio, '%m/%Y') as mes_formateado,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            $where
            GROUP BY DATE_FORMAT(r.fecha_inicio, '%Y-%m')
            ORDER BY DATE_FORMAT(r.fecha_inicio, '%Y-%m')
        ";
        break;

    case 'usuario':
        $sql = "
            SELECT 
                u.id_usuario,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            $where
            GROUP BY u.id_usuario
            ORDER BY total_reservas DESC
        ";
        break;

    case 'recurso':
        $sql = "
            SELECT 
                rc.id_recurso,
                rc.nombre as nombre_recurso,
                tr.nombre as tipo_recurso,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            $where
            GROUP BY rc.id_recurso
            ORDER BY total_reservas DESC
        ";
        break;

    default:
        $sql = "
            SELECT 
                DATE(r.fecha_inicio) as fecha,
                COUNT(*) as total_reservas,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas r
            JOIN recursos rc ON r.id_recurso = rc.id_recurso
            JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
            $where
            GROUP BY DATE(r.fecha_inicio)
            ORDER BY DATE(r.fecha_inicio)
        ";
}

// Obtener datos para el reporte
$estadisticas = $db->getRows($sql, $params);

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
$total_pendientes = 0;
$total_confirmadas = 0;
$total_canceladas = 0;
$total_completadas = 0;

foreach ($estadisticas as $item) {
    $total_reservas += $item['total_reservas'];
    $total_pendientes += $item['pendientes'];
    $total_confirmadas += $item['confirmadas'];
    $total_canceladas += $item['canceladas'];
    $total_completadas += $item['completadas'];
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

// Traducir tipo de agrupación
$agrupacion_str = "";
switch ($agrupacion) {
    case 'diaria':
        $agrupacion_str = "Diaria";
        break;
    case 'semanal':
        $agrupacion_str = "Semanal";
        break;
    case 'mensual':
        $agrupacion_str = "Mensual";
        break;
    case 'usuario':
        $agrupacion_str = "Por Usuario";
        break;
    case 'recurso':
        $agrupacion_str = "Por Recurso";
        break;
    default:
        $agrupacion_str = "Diaria";
}

// Configuración para el PDF
// Verificamos si está disponible la librería TCPDF
if (!class_exists('TCPDF') && file_exists(BASE_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once BASE_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
}

if (!class_exists('TCPDF')) {
    // Si no está disponible TCPDF, usar una solución alternativa HTML
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Estadísticas de Reservas</title>
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
        
        <h1>Estadísticas de Reservas</h1>
        
        <div class='filters'>
            <strong>Filtros aplicados:</strong><br>
            Período: " . date('d/m/Y', strtotime($fecha_inicio)) . " - " . date('d/m/Y', strtotime($fecha_fin)) . "<br>
            Tipo de recurso: " . htmlspecialchars($nombre_tipo) . "<br>
            Usuario: " . htmlspecialchars($nombre_usuario) . "<br>
            Estado: " . htmlspecialchars($estado_str) . "<br>
            Agrupación: " . htmlspecialchars($agrupacion_str) . "
        </div>
        
        <div class='summary'>
            <h2>Resumen del Período</h2>
            <p><strong>Total de reservas:</strong> " . number_format($total_reservas) . "</p>
            <p><strong>Pendientes:</strong> " . number_format($total_pendientes) . "</p>
            <p><strong>Confirmadas:</strong> " . number_format($total_confirmadas) . "</p>
            <p><strong>Canceladas:</strong> " . number_format($total_canceladas) . "</p>
            <p><strong>Completadas:</strong> " . number_format($total_completadas) . "</p>
        </div>
        
        <h2>Detalles de Estadísticas</h2>
        <table>
            <thead>
                <tr>";

    // Cabeceras según agrupación
    switch ($agrupacion) {
        case 'diaria':
            echo "<th>Fecha</th>";
            break;
        case 'semanal':
            echo "<th>Semana</th>";
            break;
        case 'mensual':
            echo "<th>Mes</th>";
            break;
        case 'usuario':
            echo "<th>Usuario</th>";
            break;
        case 'recurso':
            echo "<th>Recurso</th>";
            echo "<th>Tipo</th>";
            break;
    }

    echo "
                    <th>Total Reservas</th>
                    <th>Pendientes</th>
                    <th>Confirmadas</th>
                    <th>Canceladas</th>
                    <th>Completadas</th>
                </tr>
            </thead>
            <tbody>";

    // Datos según agrupación
    foreach ($estadisticas as $item) {
        echo "<tr>";

        switch ($agrupacion) {
            case 'diaria':
                echo "<td>" . date('d/m/Y', strtotime($item['fecha'])) . "</td>";
                break;
            case 'semanal':
                echo "<td>Semana del " . date('d/m/Y', strtotime($item['fecha_inicio_semana'])) . "</td>";
                break;
            case 'mensual':
                echo "<td>" . $item['mes_formateado'] . "</td>";
                break;
            case 'usuario':
                echo "<td>" . htmlspecialchars($item['nombre_usuario']) . "</td>";
                break;
            case 'recurso':
                echo "<td>" . htmlspecialchars($item['nombre_recurso']) . "</td>";
                echo "<td>" . htmlspecialchars($item['tipo_recurso']) . "</td>";
                break;
        }

        echo "
                <td>" . $item['total_reservas'] . "</td>
                <td>" . $item['pendientes'] . "</td>
                <td>" . $item['confirmadas'] . "</td>
                <td>" . $item['canceladas'] . "</td>
                <td>" . $item['completadas'] . "</td>
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
    $pdf->SetTitle('Estadísticas de Reservas');
    $pdf->SetSubject('Estadísticas de reservas del ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)));

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
    $pdf->Cell(0, 10, 'Estadísticas de Reservas', 0, 1, 'C');
    $pdf->Ln(5);

    // Información de filtros
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Filtros aplicados:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 6, 'Período:', 0, 0);
    $pdf->Cell(0, 6, date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)), 0, 1);
    $pdf->Cell(60, 6, 'Tipo de recurso:', 0, 0);
    $pdf->Cell(0, 6, $nombre_tipo, 0, 1);
    $pdf->Cell(60, 6, 'Usuario:', 0, 0);
    $pdf->Cell(0, 6, $nombre_usuario, 0, 1);
    $pdf->Cell(60, 6, 'Estado:', 0, 0);
    $pdf->Cell(0, 6, $estado_str, 0, 1);
    $pdf->Cell(60, 6, 'Agrupación:', 0, 0);
    $pdf->Cell(0, 6, $agrupacion_str, 0, 1);
    $pdf->Ln(5);

    // Resumen
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Resumen del Período', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 6, 'Total de reservas:', 0, 0);
    $pdf->Cell(0, 6, number_format($total_reservas), 0, 1);
    $pdf->Cell(60, 6, 'Pendientes:', 0, 0);
    $pdf->Cell(0, 6, number_format($total_pendientes), 0, 1);
    $pdf->Cell(60, 6, 'Confirmadas:', 0, 0);
    $pdf->Cell(0, 6, number_format($total_confirmadas), 0, 1);
    $pdf->Cell(60, 6, 'Canceladas:', 0, 0);
    $pdf->Cell(0, 6, number_format($total_canceladas), 0, 1);
    $pdf->Cell(60, 6, 'Completadas:', 0, 0);
    $pdf->Cell(0, 6, number_format($total_completadas), 0, 1);
    $pdf->Ln(5);

    // Tabla de estadísticas
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Detalles de Estadísticas', 0, 1);

    $pdf->SetFont('helvetica', 'B', 9);

    // Cabeceras de la tabla según agrupación
    switch ($agrupacion) {
        case 'diaria':
            $pdf->Cell(40, 7, 'Fecha', 1, 0, 'C');
            $pdf->Cell(40, 7, 'Total Reservas', 1, 0, 'C');
            $pdf->Cell(40, 7, 'Pendientes', 1, 0, 'C');
            $pdf->Cell(40, 7, 'Confirmadas', 1, 0, 'C');
            $pdf->Cell(40, 7, 'Canceladas', 1, 0, 'C');
            $pdf->Cell(40, 7, 'Completadas', 1, 1, 'C');
            break;

        case 'semanal':
            $pdf->Cell(50, 7, 'Semana', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Total Reservas', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Pendientes', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Confirmadas', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Canceladas', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Completadas', 1, 1, 'C');
            break;

        case 'mensual':
            $pdf->Cell(40, 7, 'Mes', 1, 0, 'C');
            $pdf->Cell(40, 7, 'Total Reservas', 1, 0, 'C');
            $pdf->Cell(40, 7, 'Pendientes', 1, 0, 'C');
            $pdf->Cell(40, 7, 'Confirmadas', 1, 0, 'C');
            $pdf->Cell(40, 7, 'Canceladas', 1, 0, 'C');
            $pdf->Cell(40, 7, 'Completadas', 1, 1, 'C');
            break;

        case 'usuario':
            $pdf->Cell(60, 7, 'Usuario', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Total Reservas', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Pendientes', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Confirmadas', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Canceladas', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Completadas', 1, 1, 'C');
            break;

        case 'recurso':
            $pdf->Cell(60, 7, 'Recurso', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Tipo', 1, 0, 'C');
            $pdf->Cell(25, 7, 'Total', 1, 0, 'C');
            $pdf->Cell(25, 7, 'Pendientes', 1, 0, 'C');
            $pdf->Cell(25, 7, 'Confirmadas', 1, 0, 'C');
            $pdf->Cell(25, 7, 'Canceladas', 1, 0, 'C');
            $pdf->Cell(25, 7, 'Completadas', 1, 1, 'C');
            break;
    }

    // Datos de la tabla
    $pdf->SetFont('helvetica', '', 8);

    foreach ($estadisticas as $item) {
        switch ($agrupacion) {
            case 'diaria':
                $pdf->Cell(40, 6, date('d/m/Y', strtotime($item['fecha'])), 1, 0);
                $pdf->Cell(40, 6, $item['total_reservas'], 1, 0, 'C');
                $pdf->Cell(40, 6, $item['pendientes'], 1, 0, 'C');
                $pdf->Cell(40, 6, $item['confirmadas'], 1, 0, 'C');
                $pdf->Cell(40, 6, $item['canceladas'], 1, 0, 'C');
                $pdf->Cell(40, 6, $item['completadas'], 1, 1, 'C');
                break;

            case 'semanal':
                $pdf->Cell(50, 6, 'Semana del ' . date('d/m/Y', strtotime($item['fecha_inicio_semana'])), 1, 0);
                $pdf->Cell(35, 6, $item['total_reservas'], 1, 0, 'C');
                $pdf->Cell(35, 6, $item['pendientes'], 1, 0, 'C');
                $pdf->Cell(35, 6, $item['confirmadas'], 1, 0, 'C');
                $pdf->Cell(35, 6, $item['canceladas'], 1, 0, 'C');
                $pdf->Cell(35, 6, $item['completadas'], 1, 1, 'C');
                break;

            case 'mensual':
                $pdf->Cell(40, 6, $item['mes_formateado'], 1, 0);
                $pdf->Cell(40, 6, $item['total_reservas'], 1, 0, 'C');
                $pdf->Cell(40, 6, $item['pendientes'], 1, 0, 'C');
                $pdf->Cell(40, 6, $item['confirmadas'], 1, 0, 'C');
                $pdf->Cell(40, 6, $item['canceladas'], 1, 0, 'C');
                $pdf->Cell(40, 6, $item['completadas'], 1, 1, 'C');
                break;

            case 'usuario':
                $pdf->Cell(60, 6, $item['nombre_usuario'], 1, 0);
                $pdf->Cell(35, 6, $item['total_reservas'], 1, 0, 'C');
                $pdf->Cell(35, 6, $item['pendientes'], 1, 0, 'C');
                $pdf->Cell(35, 6, $item['confirmadas'], 1, 0, 'C');
                $pdf->Cell(35, 6, $item['canceladas'], 1, 0, 'C');
                $pdf->Cell(35, 6, $item['completadas'], 1, 1, 'C');
                break;

            case 'recurso':
                $pdf->Cell(60, 6, $item['nombre_recurso'], 1, 0);
                $pdf->Cell(35, 6, $item['tipo_recurso'], 1, 0);
                $pdf->Cell(25, 6, $item['total_reservas'], 1, 0, 'C');
                $pdf->Cell(25, 6, $item['pendientes'], 1, 0, 'C');
                $pdf->Cell(25, 6, $item['confirmadas'], 1, 0, 'C');
                $pdf->Cell(25, 6, $item['canceladas'], 1, 0, 'C');
                $pdf->Cell(25, 6, $item['completadas'], 1, 1, 'C');
                break;
        }
    }

    // Pie de página
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Informe generado el ' . date('d/m/Y H:i') . ' por ' . $_SESSION['usuario_nombre'], 0, 0, 'C');

    // Salida del PDF
    $pdf->Output('estadisticas_reservas_' . date('Ymd') . '.pdf', 'D');
}
