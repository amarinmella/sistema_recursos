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
                    // Implementar para otros tipos de reportes
                    $_SESSION['error'] = "La exportación a PDF para el reporte de estadísticas de reservas está en desarrollo";
                    redirect('exportar.php');
                    break;
                
                case 'usuarios':
                    // Implementar para otros tipos de reportes
                    $_SESSION['error'] = "La exportación a PDF para el reporte de usuarios está en desarrollo";
                    redirect('exportar.php');
                    break;
                
                case 'reservas':
                    // Implementar para otros tipos de reportes
                    $_SESSION['error'] = "La exportación a PDF para el reporte de reservas está en desarrollo";
                    redirect