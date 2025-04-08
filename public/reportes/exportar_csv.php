<?php

/**
 * Exportación a CSV para reporte de uso de recursos
 * Este archivo debe guardarse como public/reportes/exportar_csv.php
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

// Verificar que se especificó un tipo de reporte
if (!isset($_GET['reporte']) || empty($_GET['reporte'])) {
    $_SESSION['error'] = "Tipo de reporte no especificado";
    redirect('reportes_dashboard.php');
    exit;
}

$tipo_reporte = $_GET['reporte'];

// Validar el tipo de reporte
$reportes_validos = ['uso_recursos', 'estadisticas_reservas', 'usuarios', 'reservas', 'recursos', 'mantenimientos'];
if (!in_array($tipo_reporte, $reportes_validos)) {
    $_SESSION['error'] = "Tipo de reporte no válido";
    redirect('reportes_dashboard.php');
    exit;
}

// Obtener parámetros del reporte
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$id_tipo = isset($_GET['tipo']) ? intval($_GET['tipo']) : 0;
$id_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Según el tipo de reporte, exportar los datos correspondientes
switch ($tipo_reporte) {
    case 'uso_recursos':
        exportar_uso_recursos($db, $fecha_inicio, $fecha_fin, $id_tipo, $id_usuario, $estado);
        break;

    case 'estadisticas_reservas':
        // Implementar exportación de estadísticas de reservas
        $_SESSION['error'] = "Exportación de estadísticas de reservas aún no implementada";
        redirect('estadisticas_reservas.php');
        break;

    case 'usuarios':
        // Implementar exportación de usuarios
        $_SESSION['error'] = "Exportación de usuarios aún no implementada";
        redirect('usuarios.php');
        break;

    case 'reservas':
        // Implementar exportación de reservas
        $_SESSION['error'] = "Exportación de reservas aún no implementada";
        redirect('reservas.php');
        break;

    case 'recursos':
        // Implementar exportación de recursos
        $_SESSION['error'] = "Exportación de recursos aún no implementada";
        redirect('recursos.php');
        break;

    case 'mantenimientos':
        // Implementar exportación de mantenimientos
        $_SESSION['error'] = "Exportación de mantenimientos aún no implementada";
        redirect('mantenimientos.php');
        break;

    default:
        $_SESSION['error'] = "Tipo de reporte no válido";
        redirect('reportes_dashboard.php');
        break;
}

/**
 * Exporta el reporte de uso de recursos a CSV
 */
function exportar_uso_recursos($db, $fecha_inicio, $fecha_fin, $id_tipo, $id_usuario, $estado)
{
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
        ORDER BY total_reservas DESC
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

    if (!$recursos_uso) {
        $_SESSION['error'] = "No se encontraron datos para exportar";
        redirect('uso_recursos.php');
        exit;
    }

    // Establecer cabeceras para la descarga del CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=uso_recursos_' . date('Ymd') . '.csv');

    // Crear el archivo CSV en la salida estándar
    $output = fopen('php://output', 'w');

    // Añadir BOM para UTF-8
    fprintf($output, "\xEF\xBB\xBF");

    // Escribir la cabecera del CSV
    fputcsv($output, [
        'Recurso',
        'Tipo',
        'Ubicación',
        'Total Reservas',
        'Horas de Uso',
        'Duración Promedio (h)',
        '% Ocupación'
    ]);

    // Escribir los datos
    foreach ($recursos_uso as $recurso) {
        fputcsv($output, [
            $recurso['nombre_recurso'],
            $recurso['tipo_recurso'],
            $recurso['ubicacion'] ?: 'No especificada',
            $recurso['total_reservas'],
            number_format($recurso['horas_uso'], 1),
            number_format($recurso['duracion_promedio'], 1),
            number_format($recurso['porcentaje_ocupacion'], 2) . '%'
        ]);
    }

    // Cerrar el archivo
    fclose($output);
    exit;
}
