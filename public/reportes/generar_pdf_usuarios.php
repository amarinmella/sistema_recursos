<?php

/**
 * Generador de PDF para reporte de actividad de usuarios
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
$id_rol = isset($_GET['rol']) ? intval($_GET['rol']) : 0;
$id_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$agrupacion = isset($_GET['agrupacion']) ? $_GET['agrupacion'] : 'usuario';

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
$filtros[] = "r.fecha_inicio >= ?";
$params[] = $fecha_inicio . ' 00:00:00';

$filtros[] = "r.fecha_inicio <= ?";
$params[] = $fecha_fin . ' 23:59:59';

// Añadir filtro de rol
if ($id_rol > 0) {
    $filtros[] = "ro.id_rol = ?";
    $params[] = $id_rol;
}

// Añadir filtro de usuario específico
if ($id_usuario > 0) {
    $filtros[] = "u.id_usuario = ?";
    $params[] = $id_usuario;
}

// Construir cláusula WHERE
$where = !empty($filtros) ? " WHERE " . implode(" AND ", $filtros) : "";

// Generar consulta según agrupación seleccionada
switch ($agrupacion) {
    case 'usuario':
        $sql = "
            SELECT 
                u.id_usuario,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
                ro.nombre as rol,
                COUNT(r.id_reserva) as total_reservas,
                COUNT(DISTINCT r.id_recurso) as recursos_distintos,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                AVG(TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin)) as duracion_promedio,
                MIN(r.fecha_inicio) as primera_reserva,
                MAX(r.fecha_inicio) as ultima_reserva,
                DATEDIFF(NOW(), MAX(r.fecha_inicio)) as dias_ultima_actividad
            FROM reservas r
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            $where
            GROUP BY u.id_usuario
            ORDER BY total_reservas DESC
        ";
        break;

    case 'rol':
        $sql = "
            SELECT 
                ro.id_rol,
                ro.nombre as rol,
                COUNT(DISTINCT u.id_usuario) as total_usuarios,
                COUNT(r.id_reserva) as total_reservas,
                COUNT(DISTINCT r.id_recurso) as recursos_distintos,
                ROUND(COUNT(r.id_reserva) / COUNT(DISTINCT u.id_usuario), 2) as reservas_por_usuario,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                AVG(TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin)) as duracion_promedio
            FROM reservas r
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            $where
            GROUP BY ro.id_rol
            ORDER BY total_reservas DESC
        ";
        break;

    case 'mensual':
        $sql = "
            SELECT 
                DATE_FORMAT(r.fecha_inicio, '%Y-%m') as mes,
                DATE_FORMAT(r.fecha_inicio, '%m/%Y') as mes_formateado,
                COUNT(DISTINCT u.id_usuario) as usuarios_activos,
                COUNT(r.id_reserva) as total_reservas,
                COUNT(DISTINCT r.id_recurso) as recursos_distintos,
                ROUND(COUNT(r.id_reserva) / COUNT(DISTINCT u.id_usuario), 2) as reservas_por_usuario,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                AVG(TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin)) as duracion_promedio
            FROM reservas r
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            $where
            GROUP BY DATE_FORMAT(r.fecha_inicio, '%Y-%m')
            ORDER BY mes
        ";
        break;

    case 'frecuencia':
        $sql = "
            SELECT 
                CASE 
                    WHEN num_reservas >= 20 THEN 'Muy frecuente (20+ reservas)'
                    WHEN num_reservas >= 10 THEN 'Frecuente (10-19 reservas)'
                    WHEN num_reservas >= 5 THEN 'Regular (5-9 reservas)'
                    WHEN num_reservas >= 2 THEN 'Ocasional (2-4 reservas)'
                    ELSE 'Único (1 reserva)'
                END as categoria_frecuencia,
                COUNT(DISTINCT u.id_usuario) as total_usuarios,
                SUM(num_reservas) as total_reservas,
                ROUND(AVG(num_reservas), 2) as promedio_reservas_por_usuario
            FROM (
                SELECT 
                    id_usuario, 
                    COUNT(*) as num_reservas
                FROM reservas
                WHERE fecha_inicio >= ? AND fecha_inicio <= ?
                GROUP BY id_usuario
            ) reservas_por_usuario
            JOIN usuarios u ON reservas_por_usuario.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            " . (($id_rol > 0) ? " WHERE ro.id_rol = " . intval($id_rol) : "") . "
            GROUP BY categoria_frecuencia
            ORDER BY MIN(num_reservas) DESC
        ";

        // Ajustar parámetros solo para esta consulta específica
        $params_frecuencia = [
            $fecha_inicio . ' 00:00:00',
            $fecha_fin . ' 23:59:59'
        ];

        $estadisticas = $db->getRows($sql, $params_frecuencia);
        break;

    default:
        $sql = "
            SELECT 
                u.id_usuario,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
                ro.nombre as rol,
                COUNT(r.id_reserva) as total_reservas,
                COUNT(DISTINCT r.id_recurso) as recursos_distintos,
                SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                AVG(TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin)) as duracion_promedio
            FROM reservas r
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            JOIN roles ro ON u.id_rol = ro.id_rol
            $where
            GROUP BY u.id_usuario
            ORDER BY total_reservas DESC
        ";
}

// Obtener datos para el reporte, si no se hizo ya en el caso de 'frecuencia'
if ($agrupacion != 'frecuencia') {
    $estadisticas = $db->getRows($sql, $params);
}

// Consulta resumen general
$sql_resumen = "
    SELECT 
        COUNT(DISTINCT u.id_usuario) as total_usuarios_activos,
        COUNT(r.id_reserva) as total_reservas,
        COUNT(DISTINCT r.id_recurso) as total_recursos_utilizados,
        ROUND(COUNT(r.id_reserva) / COUNT(DISTINCT u.id_usuario), 2) as promedio_reservas_por_usuario,
        SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) / COUNT(r.id_reserva) * 100 as tasa_cancelacion,
        AVG(TIMESTAMPDIFF(HOUR, r.fecha_inicio, r.fecha_fin)) as duracion_promedio_horas
    FROM reservas r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    JOIN roles ro ON u.id_rol = ro.id_rol
    WHERE r.fecha_inicio >= ? AND r.fecha_inicio <= ?
    " . (($id_rol > 0) ? " AND ro.id_rol = " . intval($id_rol) : "") . "
    " . (($id_usuario > 0) ? " AND u.id_usuario = " . intval($id_usuario) : "") . "
";

$resumen = $db->getRow($sql_resumen, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

// Consulta para usuarios inactivos (sin reservas en el período)
$sql_inactivos = "
    SELECT 
        COUNT(*) as total_inactivos,
        COUNT(*) / (SELECT COUNT(*) FROM usuarios WHERE activo = 1) * 100 as porcentaje_inactivos
    FROM usuarios u
    WHERE u.activo = 1
    AND u.id_usuario NOT IN (
        SELECT DISTINCT id_usuario 
        FROM reservas 
        WHERE fecha_inicio >= ? AND fecha_inicio <= ?
    )
    " . (($id_rol > 0) ? " AND u.id_rol = " . intval($id_rol) : "") . "
";

$usuarios_inactivos = $db->getRow($sql_inactivos, [
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
]);

// Obtener nombre del rol seleccionado
$nombre_rol = "Todos los roles";
if ($id_rol > 0) {
    $rol_dato = $db->getRow("SELECT nombre FROM roles WHERE id_rol = ?", [$id_rol]);
    if ($rol_dato) {
        $nombre_rol = $rol_dato['nombre'];
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

// Traducir tipo de agrupación
switch ($agrupacion) {
    case 'usuario':
        $agrupacion_str = "Por Usuario";
        break;
    case 'rol':
        $agrupacion_str = "Por Rol";
        break;
    case 'mensual':
        $agrupacion_str = "Mensual";
        break;
    case 'frecuencia':
        $agrupacion_str = "Por Frecuencia de Uso";
        break;
    default:
        $agrupacion_str = ucfirst($agrupacion);
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
        <title>Reporte de Actividad de Usuarios</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            h1 { color: #4a90e2; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .summary { margin: 20px 0; }
            .filters { margin-bottom: 20px; padding: 10px; background: #f8f9fa; border: 1px solid #eee; }
            .number-cell { text-align: right; }
        </style>
    </head>
    <body>
        <div style='text-align:right;'>
            <button onclick='window.print()'>Imprimir</button>
        </div>
        
        <h1>Reporte de Actividad de Usuarios</h1>
        
        <div class='filters'>
            <strong>Filtros aplicados:</strong><br>
            Período: " . date('d/m/Y', strtotime($fecha_inicio)) . " - " . date('d/m/Y', strtotime($fecha_fin)) . "<br>
            Rol: " . htmlspecialchars($nombre_rol) . "<br>
            Usuario: " . htmlspecialchars($nombre_usuario) . "<br>
            Agrupación: " . htmlspecialchars($agrupacion_str) . "
        </div>
        
        <div class='summary'>
            <h2>Resumen del Período</h2>
            <p><strong>Usuarios activos:</strong> " . number_format_safe($resumen['total_usuarios_activos']) . "</p>
            <p><strong>Reservas realizadas:</strong> " . number_format_safe($resumen['total_reservas']) . "</p>
            <p><strong>Reservas por usuario:</strong> " . number_format_safe($resumen['promedio_reservas_por_usuario'], 1) . "</p>
            <p><strong>Tasa de cancelación:</strong> " . number_format_safe($resumen['tasa_cancelacion'], 1) . "%</p>
            <p><strong>Duración promedio:</strong> " . formatear_horas($resumen['duracion_promedio_horas']) . "</p>
            <p><strong>Recursos distintos utilizados:</strong> " . number_format_safe($resumen['total_recursos_utilizados']) . "</p>
            <p><strong>Usuarios inactivos:</strong> " . number_format_safe($usuarios_inactivos['total_inactivos']) . " (" . number_format_safe($usuarios_inactivos['porcentaje_inactivos'], 1) . "%)</p>
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
            case 'usuario':
                echo "<th>Usuario</th><th>Rol</th>";
                break;
            case 'rol':
                echo "<th>Rol</th><th class=\"number-cell\">Usuarios</th>";
                break;
            case 'mensual':
                echo "<th>Mes</th><th class=\"number-cell\">Usuarios Activos</th>";
                break;
            case 'frecuencia':
                echo "<th>Categoría de Frecuencia</th><th class=\"number-cell\">Usuarios</th>";
                break;
        }

        echo "<th class=\"number-cell\">Total Reservas</th>";

        if ($agrupacion != 'frecuencia') {
            echo "<th class=\"number-cell\">Recursos</th>
                <th class=\"number-cell\">Pendientes</th>
                <th class=\"number-cell\">Confirmadas</th>
                <th class=\"number-cell\">Canceladas</th>
                <th class=\"number-cell\">Completadas</th>
                <th class=\"number-cell\">Duración Promedio</th>";
        } else {
            echo "<th class=\"number-cell\">Promedio por Usuario</th>";
        }

        if ($agrupacion == 'usuario') {
            echo "<th>Última Actividad</th>";
        }

        echo "</tr>
            </thead>
            <tbody>";

        foreach ($estadisticas as $item) {
            echo "<tr>";

            switch ($agrupacion) {
                case 'usuario':
                    echo "<td>" . htmlspecialchars($item['nombre_usuario']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['rol']) . "</td>";
                    break;
                case 'rol':
                    echo "<td>" . htmlspecialchars($item['rol']) . "</td>";
                    echo "<td class=\"number-cell\">" . $item['total_usuarios'] . "</td>";
                    break;
                case 'mensual':
                    echo "<td>" . htmlspecialchars($item['mes_formateado']) . "</td>";
                    echo "<td class=\"number-cell\">" . $item['usuarios_activos'] . "</td>";
                    break;
                case 'frecuencia':
                    echo "<td>" . htmlspecialchars($item['categoria_frecuencia']) . "</td>";
                    echo "<td class=\"number-cell\">" . $item['total_usuarios'] . "</td>";
                    break;
            }

            echo "<td class=\"number-cell\">" . $item['total_reservas'] . "</td>";

            if ($agrupacion != 'frecuencia') {
                echo "<td class=\"number-cell\">" . $item['recursos_distintos'] . "</td>";
                echo "<td class=\"number-cell\">" . $item['pendientes'] . "</td>";
                echo "<td class=\"number-cell\">" . $item['confirmadas'] . "</td>";
                echo "<td class=\"number-cell\">" . $item['canceladas'] . "</td>";
                echo "<td class=\"number-cell\">" . $item['completadas'] . "</td>";
                echo "<td class=\"number-cell\">" . formatear_horas($item['duracion_promedio']) . "</td>";
            } else {
                echo "<td class=\"number-cell\">" . number_format_safe($item['promedio_reservas_por_usuario'], 1) . "</td>";
            }

            if ($agrupacion == 'usuario') {
                echo "<td>";
                if (isset($item['dias_ultima_actividad'])) {
                    if ($item['dias_ultima_actividad'] == 0) {
                        echo "Hoy";
                    } elseif ($item['dias_ultima_actividad'] == 1) {
                        echo "Ayer";
                    } else {
                        echo "Hace " . $item['dias_ultima_actividad'] . " días";
                    }
                } else {
                    echo "N/A";
                }
                echo "</td>";
            }

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
    $pdf->SetTitle('Reporte de Actividad de Usuarios');
    $pdf->SetSubject('Actividad de usuarios del ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)));

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
    $pdf->Cell(0, 10, 'Reporte de Actividad de Usuarios', 0, 1, 'C');
    $pdf->Ln(5);

    // Información de filtros
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Filtros aplicados:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(40, 6, 'Período:', 0, 0);
    $pdf->Cell(0, 6, date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)), 0, 1);
    $pdf->Cell(40, 6, 'Rol:', 0, 0);
    $pdf->Cell(0, 6, $nombre_rol, 0, 1);
    $pdf->Cell(40, 6, 'Usuario:', 0, 0);
    $pdf->Cell(0, 6, $nombre_usuario, 0, 1);
    $pdf->Cell(40, 6, 'Agrupación:', 0, 0);
    $pdf->Cell(0, 6, $agrupacion_str, 0, 1);
    $pdf->Ln(5);

    // Resumen
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Resumen del Período', 0, 1);
    $pdf->SetFont('helvetica', '', 10);

    $pdf->Cell(60, 6, 'Usuarios activos:', 0, 0);
    $pdf->Cell(0, 6, number_format_safe($resumen['total_usuarios_activos']), 0, 1);

    $pdf->Cell(60, 6, 'Reservas realizadas:', 0, 0);
    $pdf->Cell(0, 6, number_format_safe($resumen['total_reservas']), 0, 1);

    $pdf->Cell(60, 6, 'Reservas por usuario:', 0, 0);
    $pdf->Cell(0, 6, number_format_safe($resumen['promedio_reservas_por_usuario'], 1), 0, 1);

    $pdf->Cell(60, 6, 'Tasa de cancelación:', 0, 0);
    $pdf->Cell(0, 6, number_format_safe($resumen['tasa_cancelacion'], 1) . '%', 0, 1);

    $pdf->Cell(60, 6, 'Duración promedio:', 0, 0);
    $pdf->Cell(0, 6, formatear_horas($resumen['duracion_promedio_horas']), 0, 1);

    $pdf->Cell(60, 6, 'Recursos utilizados:', 0, 0);
    $pdf->Cell(0, 6, number_format_safe($resumen['total_recursos_utilizados']), 0, 1);

    $pdf->Cell(60, 6, 'Usuarios inactivos:', 0, 0);
    $pdf->Cell(0, 6, number_format_safe($usuarios_inactivos['total_inactivos']) . ' (' . number_format_safe($usuarios_inactivos['porcentaje_inactivos'], 1) . '%)', 0, 1);

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
            case 'usuario':
                $pdf->Cell(50, 7, 'Usuario', 1, 0, 'C');
                $pdf->Cell(30, 7, 'Rol', 1, 0, 'C');
                $ancho_resto = 28;
                break;
            case 'rol':
                $pdf->Cell(45, 7, 'Rol', 1, 0, 'C');
                $pdf->Cell(30, 7, 'Usuarios', 1, 0, 'C');
                $ancho_resto = 30;
                break;
            case 'mensual':
                $pdf->Cell(30, 7, 'Mes', 1, 0, 'C');
                $pdf->Cell(30, 7, 'Usuarios Activos', 1, 0, 'C');
                $ancho_resto = 35;
                break;
            case 'frecuencia':
                $pdf->Cell(60, 7, 'Categoría de Frecuencia', 1, 0, 'C');
                $pdf->Cell(40, 7, 'Usuarios', 1, 0, 'C');
                $pdf->Cell(40, 7, 'Total Reservas', 1, 0, 'C');
                $pdf->Cell(40, 7, 'Promedio por Usuario', 1, 1, 'C');
                $incluir_columnas_extras = false;
                break;
            default:
                $incluir_columnas_extras = true;
                $ancho_resto = 30;
        }

        // Añadir resto de columnas (excepto para frecuencia)
        $incluir_columnas_extras = $agrupacion != 'frecuencia';

        if ($incluir_columnas_extras) {
            $pdf->Cell($ancho_resto, 7, 'Total Reservas', 1, 0, 'C');
            $pdf->Cell($ancho_resto, 7, 'Recursos', 1, 0, 'C');
            $pdf->Cell($ancho_resto, 7, 'Pendientes', 1, 0, 'C');
            $pdf->Cell($ancho_resto, 7, 'Confirmadas', 1, 0, 'C');
            $pdf->Cell($ancho_resto, 7, 'Canceladas', 1, 0, 'C');
            $pdf->Cell($ancho_resto, 7, 'Completadas', 1, 0, 'C');
            $pdf->Cell($ancho_resto, 7, 'Duración', 1, 0, 'C');

            // Solo para agrupación por usuario, añadir columna de última actividad
            if ($agrupacion == 'usuario') {
                $pdf->Cell(35, 7, 'Última Actividad', 1, 0, 'C');
            }

            $pdf->Ln();
        }

        // Datos de la tabla
        $pdf->SetFont('helvetica', '', 8);

        if ($agrupacion == 'frecuencia') {
            // Caso especial para frecuencia que ya tiene las cabeceras completadas
            foreach ($estadisticas as $item) {
                $pdf->Cell(60, 6, $item['categoria_frecuencia'], 1, 0);
                $pdf->Cell(40, 6, $item['total_usuarios'], 1, 0, 'C');
                $pdf->Cell(40, 6, $item['total_reservas'], 1, 0, 'C');
                $pdf->Cell(40, 6, number_format_safe($item['promedio_reservas_por_usuario'], 1), 1, 1, 'C');
            }
        } else {
            // Para el resto de agrupaciones
            foreach ($estadisticas as $item) {
                switch ($agrupacion) {
                    case 'usuario':
                        $pdf->Cell(50, 6, $item['nombre_usuario'], 1, 0);
                        $pdf->Cell(30, 6, $item['rol'], 1, 0);
                        break;
                    case 'rol':
                        $pdf->Cell(45, 6, $item['rol'], 1, 0);
                        $pdf->Cell(30, 6, $item['total_usuarios'], 1, 0, 'C');
                        break;
                    case 'mensual':
                        $pdf->Cell(30, 6, $item['mes_formateado'], 1, 0);
                        $pdf->Cell(30, 6, $item['usuarios_activos'], 1, 0, 'C');
                        break;
                }

                $pdf->Cell($ancho_resto, 6, $item['total_reservas'], 1, 0, 'C');
                $pdf->Cell($ancho_resto, 6, $item['recursos_distintos'], 1, 0, 'C');
                $pdf->Cell($ancho_resto, 6, $item['pendientes'], 1, 0, 'C');
                $pdf->Cell($ancho_resto, 6, $item['confirmadas'], 1, 0, 'C');
                $pdf->Cell($ancho_resto, 6, $item['canceladas'], 1, 0, 'C');
                $pdf->Cell($ancho_resto, 6, $item['completadas'], 1, 0, 'C');
                $pdf->Cell($ancho_resto, 6, formatear_horas($item['duracion_promedio']), 1, 0, 'C');

                // Solo para agrupación por usuario, añadir dato de última actividad
                if ($agrupacion == 'usuario') {
                    $ultima_actividad = "";
                    if (isset($item['dias_ultima_actividad'])) {
                        if ($item['dias_ultima_actividad'] == 0) {
                            $ultima_actividad = "Hoy";
                        } elseif ($item['dias_ultima_actividad'] == 1) {
                            $ultima_actividad = "Ayer";
                        } else {
                            $ultima_actividad = "Hace " . $item['dias_ultima_actividad'] . " días";
                        }
                    } else {
                        $ultima_actividad = "N/A";
                    }
                    $pdf->Cell(35, 6, $ultima_actividad, 1, 0);
                }

                $pdf->Ln();
            }
        }
    }

    // Añadir sección de análisis si hay datos
    if (!empty($estadisticas)) {
        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Análisis de Patrones de Uso', 0, 1);

        $pdf->SetFont('helvetica', '', 10);

        // Separar el análisis en secciones

        // 1. Participación de usuarios
        $pdf->MultiCell(0, 6, 'Participación de usuarios: En el período analizado, ' .
            number_format_safe($resumen['total_usuarios_activos']) . ' usuarios realizaron reservas, mientras que ' .
            number_format_safe($usuarios_inactivos['total_inactivos']) . ' usuarios (' .
            number_format_safe($usuarios_inactivos['porcentaje_inactivos'], 1) . '%) no registraron actividad.', 0, 'L');

        $pdf->Ln(2);

        // 2. Comportamiento de reservas
        if ($resumen['total_reservas'] > 0) {
            $pdf->MultiCell(0, 6, 'Comportamiento de reservas: En promedio, cada usuario activo realiza ' .
                number_format_safe($resumen['promedio_reservas_por_usuario'], 1) . ' reservas. La tasa de cancelación es del ' .
                number_format_safe($resumen['tasa_cancelacion'], 1) . '%.', 0, 'L');
        }

        $pdf->Ln(2);

        // 3. Recomendaciones
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Recomendaciones:', 0, 1);
        $pdf->SetFont('helvetica', '', 10);

        // Generar recomendaciones basadas en los datos
        if ($usuarios_inactivos['porcentaje_inactivos'] > 30) {
            $pdf->MultiCell(0, 6, '• Implementar una campaña de reactivación para el ' .
                number_format_safe($usuarios_inactivos['porcentaje_inactivos'], 1) .
                '% de usuarios inactivos mediante comunicaciones personalizadas o incentivos.', 0, 'L');
        }

        if ($resumen['tasa_cancelacion'] > 20) {
            $pdf->MultiCell(0, 6, '• Revisar el proceso de reservas para reducir la alta tasa de cancelaciones (' .
                number_format_safe($resumen['tasa_cancelacion'], 1) .
                '%). Considerar enviar recordatorios o implementar una política de cancelación más efectiva.', 0, 'L');
        }

        if ($resumen['promedio_reservas_por_usuario'] < 2 && $resumen['total_usuarios_activos'] > 10) {
            $pdf->MultiCell(0, 6, '• Promover un mayor uso del sistema entre los usuarios actuales, ya que la media de ' .
                number_format_safe($resumen['promedio_reservas_por_usuario'], 1) .
                ' reservas por usuario es relativamente baja.', 0, 'L');
        }

        $pdf->MultiCell(0, 6, '• Realizar un seguimiento periódico de la actividad de usuarios para detectar tendencias y ajustar estrategias según sea necesario.', 0, 'L');
    }

    // Pie de página
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Informe generado el ' . date('d/m/Y H:i') . ' por ' . $_SESSION['usuario_nombre'], 0, 0, 'C');

    // Salida del PDF
    $pdf->Output('reporte_actividad_usuarios_' . date('Ymd') . '.pdf', 'D');
}
