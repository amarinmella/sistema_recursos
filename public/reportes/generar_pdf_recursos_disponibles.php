<?php

/**
 * generar_pdf_recursos_disponibles.php
 * Módulo para generar PDF de Recursos Disponibles
 */

// Iniciar sesión
session_start();

// Incluir configuración y librerías
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Asegúrate de tener FPDF en includes/fpdf/fpdf.php
require_once '../../includes/fpdf/fpdf.php';

// Verificar permisos
require_login();
if (!has_role([ROL_ADMIN, ROL_ACADEMICO])) {
    die('No tienes permisos para generar este PDF.');
}

// Obtener instancia de base de datos
$db = Database::getInstance();


// Consulta de recursos disponibles
$sql = "
    SELECT
        rc.id_recurso,
        rc.nombre AS recurso,
        tr.nombre AS tipo,
        rc.cantidad_total - IFNULL(SUM(CASE WHEN r.estado != 'cancelada' THEN 1 ELSE 0 END), 0) AS disponibles
    FROM recursos rc
    LEFT JOIN tipos_recursos tr ON rc.id_tipo = tr.id_tipo
    LEFT JOIN reservas r ON rc.id_recurso = r.id_recurso AND r.estado != 'cancelada'
    GROUP BY rc.id_recurso, rc.nombre, tr.nombre, rc.cantidad_total
    HAVING disponibles > 0
    ORDER BY rc.nombre ASC
";
$recursos = $db->getRows($sql, []);

// Instanciar PDF\$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Recursos Disponibles', 0, 1, 'C');
$pdf->Ln(5);

// Cabecera de tabla
$pdf->SetFont('Arial', 'B', 12);
$w = [20, 100, 60, 30]; // Anchos de columnas: ID, Recurso, Tipo, Disponibles
$pdf->Cell($w[0], 8, 'ID', 1, 0, 'C');
$pdf->Cell($w[1], 8, 'Recurso', 1, 0, 'L');
$pdf->Cell($w[2], 8, 'Tipo', 1, 0, 'L');
$pdf->Cell($w[3], 8, 'Disp.', 1, 1, 'C');

// Datos
$pdf->SetFont('Arial', '', 12);
foreach ($recursos as $row) {
    $pdf->Cell($w[0], 8, $row['id_recurso'], 1, 0, 'C');
    $pdf->Cell($w[1], 8, utf8_decode($row['recurso']), 1, 0, 'L');
    $pdf->Cell($w[2], 8, utf8_decode($row['tipo']), 1, 0, 'L');
    $pdf->Cell($w[3], 8, $row['disponibles'], 1, 1, 'C');
}

// Salida al navegador
$pdf->Output('I', 'recursos_disponibles.pdf');
exit;
