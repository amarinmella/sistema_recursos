<?php

/**
 * API para verificar disponibilidad de recursos
 * Endpoint utilizado por el calendario y formularios de reserva
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado
require_login();

// Verificar que se proporcionaron todos los parámetros
if (!isset($_GET['id_recurso']) || !isset($_GET['fecha_inicio']) || !isset($_GET['fecha_fin'])) {
    echo json_encode([
        'error' => true,
        'mensaje' => 'Faltan parámetros requeridos'
    ]);
    exit;
}

// Obtener y validar parámetros
$id_recurso = intval($_GET['id_recurso']);
$fecha_inicio = $_GET['fecha_inicio'];
$fecha_fin = $_GET['fecha_fin'];
$id_reserva = isset($_GET['id_reserva']) ? intval($_GET['id_reserva']) : 0;

// Validar fechas
if (!validateDateTime($fecha_inicio) || !validateDateTime($fecha_fin)) {
    echo json_encode([
        'error' => true,
        'mensaje' => 'Formato de fecha inválido'
    ]);
    exit;
}

// Validar que la fecha de inicio sea menor que la fecha de fin
if (strtotime($fecha_inicio) >= strtotime($fecha_fin)) {
    echo json_encode([
        'error' => true,
        'mensaje' => 'La fecha de inicio debe ser anterior a la fecha de fin'
    ]);
    exit;
}

// Validar que la fecha de inicio sea futura (excepto para administradores)
$es_admin = has_role([ROL_ADMIN, ROL_ACADEMICO]);
if (!$es_admin && strtotime($fecha_inicio) < time()) {
    echo json_encode([
        'error' => true,
        'mensaje' => 'La fecha de inicio debe ser futura'
    ]);
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Verificar que el recurso exista y esté disponible
$recurso = $db->getRow(
    "SELECT id_recurso, nombre, disponible, estado FROM recursos WHERE id_recurso = ?",
    [$id_recurso]
);

if (!$recurso) {
    echo json_encode([
        'error' => true,
        'mensaje' => 'El recurso no existe'
    ]);
    exit;
}

if (!$recurso['disponible'] || $recurso['estado'] != 'disponible') {
    echo json_encode([
        'error' => true,
        'mensaje' => 'El recurso no está disponible para reservas',
        'disponible' => false,
        'reservas' => []
    ]);
    exit;
}

// Consultar si hay reservas que se solapen con las fechas proporcionadas
$params = [$id_recurso, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin];
$sql = "SELECT id_reserva, fecha_inicio, fecha_fin, estado 
        FROM reservas 
        WHERE id_recurso = ? 
          AND estado IN ('pendiente', 'confirmada') 
          AND (
              (? BETWEEN fecha_inicio AND fecha_fin)
              OR (? BETWEEN fecha_inicio AND fecha_fin)
              OR (fecha_inicio BETWEEN ? AND ?)
          )";

// Si estamos editando una reserva existente, excluirla de la verificación
if ($id_reserva > 0) {
    $sql .= " AND id_reserva != ?";
    $params[] = $id_reserva;
}

$reservas_conflicto = $db->getRows($sql, $params);

// Verificar si hay mantenimientos programados que se solapen
$sql_mantenimiento = "SELECT id_mantenimiento, fecha_inicio, fecha_fin, estado
                      FROM mantenimiento
                      WHERE id_recurso = ?
                        AND estado IN ('pendiente', 'en progreso')
                        AND (
                            (? BETWEEN fecha_inicio AND IFNULL(fecha_fin, '2099-12-31'))
                            OR (? BETWEEN fecha_inicio AND IFNULL(fecha_fin, '2099-12-31'))
                            OR (fecha_inicio BETWEEN ? AND ?)
                        )";

$mantenimientos = $db->getRows($sql_mantenimiento, $params);

// Formatear las fechas para mostrar
foreach ($reservas_conflicto as &$reserva) {
    $reserva['fecha_inicio'] = date('d/m/Y H:i', strtotime($reserva['fecha_inicio']));
    $reserva['fecha_fin'] = date('d/m/Y H:i', strtotime($reserva['fecha_fin']));
}

foreach ($mantenimientos as &$mantenimiento) {
    $mantenimiento['fecha_inicio'] = date('d/m/Y H:i', strtotime($mantenimiento['fecha_inicio']));
    $mantenimiento['fecha_fin'] = $mantenimiento['fecha_fin']
        ? date('d/m/Y H:i', strtotime($mantenimiento['fecha_fin']))
        : 'No definida';
}

// Determinar disponibilidad
$disponible = count($reservas_conflicto) === 0 && count($mantenimientos) === 0;

// Preparar respuesta
$respuesta = [
    'error' => false,
    'disponible' => $disponible,
    'reservas' => $reservas_conflicto,
    'mantenimientos' => $mantenimientos,
    'recurso' => [
        'id' => $recurso['id_recurso'],
        'nombre' => $recurso['nombre'],
        'estado' => $recurso['estado']
    ]
];

// Función para validar formato de fecha y hora
function validateDateTime($dateTime)
{
    $format = 'Y-m-d H:i:s';
    $d = DateTime::createFromFormat($format, $dateTime);
    return $d && $d->format($format) === $dateTime;
}

// Devolver resultado en formato JSON
header('Content-Type: application/json');
echo json_encode($respuesta);
