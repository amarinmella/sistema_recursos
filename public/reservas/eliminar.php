<?php

/**
 * eliminar.php
 * Archivo para eliminar reservas en el módulo de reservas.
 * Se permite que el propietario de la reserva o usuarios con permisos de gestión (Admin o Académico)
 * puedan eliminar la reserva.
 */

session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado
require_login();

// Verificar que se proporcionó el ID de la reserva
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No se especificó la reserva a eliminar.";
    redirect('listar.php');
    exit;
}

$id_reserva = intval($_GET['id']);

// Obtener la instancia de la base de datos
$db = Database::getInstance();

// Verificar si la reserva existe
$reserva = $db->getRow("SELECT * FROM reservas WHERE id_reserva = ?", [$id_reserva]);
if (!$reserva) {
    $_SESSION['error'] = "La reserva no existe.";
    redirect('listar.php');
    exit;
}

// Determinar si el usuario es propietario de la reserva
$es_propietario = ($reserva['id_usuario'] == $_SESSION['usuario_id']);

// Determinar si el usuario tiene permisos de gestión (Admin o Académico)
$puede_gestionar = has_role([ROL_ADMIN, ROL_ACADEMICO]);

// Permitir la eliminación si es propietario o tiene permisos de gestión
if (!$es_propietario && !$puede_gestionar) {
    $_SESSION['error'] = "No tienes permisos para eliminar esta reserva.";
    redirect('listar.php');
    exit;
}

// (Opcional) Verificar que la reserva no haya iniciado para permitir su eliminación
if (strtotime($reserva['fecha_inicio']) <= time()) {
    $_SESSION['error'] = "No se puede eliminar una reserva que ya inició.";
    redirect('listar.php');
    exit;
}

// Ejecutar la eliminación de la reserva
$resultado = $db->delete('reservas', 'id_reserva = ?', [$id_reserva]);

if ($resultado) {
    $_SESSION['success'] = "La reserva se eliminó correctamente.";

    // Opcional: registrar la acción en un log
    $log_data = [
        'id_usuario' => $_SESSION['usuario_id'],
        'accion'     => 'eliminar',
        'entidad'    => 'reserva',
        'id_entidad' => $id_reserva,
        'ip'         => $_SERVER['REMOTE_ADDR'],
        'fecha'      => date('Y-m-d H:i:s'),
        'detalles'   => 'Reserva eliminada'
    ];
    $db->insert('log_acciones', $log_data);
} else {
    $_SESSION['error'] = "Error al eliminar la reserva. Por favor, intenta nuevamente.";
}

redirect('listar.php');
exit;
