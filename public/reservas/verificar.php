<?php

/**
 * Verificación de disponibilidad de recursos para reservas
 * 
 * Este script responde solicitudes AJAX para verificar si un recurso está disponible
 * en un período específico.
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado
require_login();

// Configuración de cabeceras para JSON
header('Content-Type: application/json');

// Verificar que se han proporcionado todos los parámetros necesarios
if (!isset($_GET['id_recurso']) || !isset($_GET['fecha_inicio']) || !isset($_GET['fecha_fin'])) {
    echo json_encode([
        'disponible' => false,
        'error' => 'Faltan parámetros requeridos'
    ]);
    exit;
}

// Obtener parámetros
$id_recurso = intval($_GET['id_recurso']);
$fecha_inicio = $_GET['fecha_inicio'];
$fecha_fin = $_GET['fecha_fin'];

// Validar parámetros
if ($id_recurso <= 0) {
    echo json_encode([
        'disponible' => false,
        'error' => 'ID de recurso no válido'
    ]);
    exit;
}

if (strtotime($fecha_inicio) === false || strtotime($fecha_fin) === false) {
    echo json_encode([
        'disponible' => false,
        'error' => 'Formato de fecha no válido'
    ]);
    exit;
}

if (strtotime($fecha_inicio) >= strtotime($fecha_fin)) {
    echo json_encode([
        'disponible' => false,
        'error' => 'La fecha de fin debe ser posterior a la fecha de inicio'
    ]);
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Verificar si el recurso existe y está disponible
$recurso = $db->getRow(
    "SELECT id_recurso FROM recursos WHERE id_recurso = ? AND disponible = 1 AND estado = 'disponible'",
    [$id_recurso]
);

if (!$recurso) {
    echo json_encode([
        'disponible' => false,
        'error' => 'El recurso no existe o no está disponible'
    ]);
    exit;
}

// Verificar si hay reservas que se superponen en el período especificado
$sql = "SELECT id_reserva, fecha_inicio, fecha_fin FROM reservas 
        WHERE id_recurso = ? AND estado IN ('pendiente', 'confirmada') 
        AND (
            (? BETWEEN fecha_inicio AND fecha_fin)
            OR (? BETWEEN fecha_inicio AND fecha_fin)
            OR (fecha_inicio BETWEEN ? AND ?)
        )";

$reservas = $db->getRows($sql, [$id_recurso, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin]);

// Formatear los resultados para la respuesta
$reservas_formateadas = [];
foreach ($reservas as $reserva) {
    $reservas_formateadas[] = [
        'id_reserva' => $reserva['id_reserva'],
        'fecha_inicio' => date('d/m/Y H:i', strtotime($reserva['fecha_inicio'])),
        'fecha_fin' => date('d/m/Y H:i', strtotime($reserva['fecha_fin']))
    ];
}

// Responder con la disponibilidad
echo json_encode([
    'disponible' => empty($reservas),
    'reservas' => $reservas_formateadas
]);
