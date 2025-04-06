<?php

/**
 * Eliminación de recursos
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado y tenga permisos
require_login();
if (!has_role(ROL_ADMIN)) {
    $_SESSION['error'] = "No tienes permisos para eliminar recursos";
    redirect('listar.php');
    exit;
}

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de recurso no especificado";
    redirect('listar.php');
    exit;
}

$id_recurso = intval($_GET['id']);

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Verificar si el recurso existe
$recurso = $db->getRow("SELECT nombre FROM recursos WHERE id_recurso = ?", [$id_recurso]);

if (!$recurso) {
    $_SESSION['error'] = "El recurso no existe";
    redirect('listar.php');
    exit;
}

// Verificar si existen reservas para este recurso
$reservas = $db->getRow("SELECT COUNT(*) as total FROM reservas WHERE id_recurso = ?", [$id_recurso]);

if ($reservas && $reservas['total'] > 0) {
    $_SESSION['error'] = "No se puede eliminar el recurso porque existen reservas asociadas";
    redirect('listar.php');
    exit;
}

// Verificar si existen mantenimientos para este recurso
$mantenimientos = $db->getRow("SELECT COUNT(*) as total FROM mantenimiento WHERE id_recurso = ?", [$id_recurso]);

if ($mantenimientos && $mantenimientos['total'] > 0) {
    $_SESSION['error'] = "No se puede eliminar el recurso porque existen registros de mantenimiento asociados";
    redirect('listar.php');
    exit;
}

// Eliminar el recurso
$resultado = $db->query("DELETE FROM recursos WHERE id_recurso = ?", [$id_recurso]);

if ($resultado) {
    // Registrar la acción
    $log_data = [
        'id_usuario' => $_SESSION['usuario_id'],
        'accion' => 'eliminar',
        'entidad' => 'recursos',
        'id_entidad' => $id_recurso,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'fecha' => date('Y-m-d H:i:s'),
        'detalles' => 'Recurso eliminado: ' . $recurso['nombre']
    ];
    $db->insert('log_acciones', $log_data);

    // Redireccionar con mensaje de éxito
    $_SESSION['success'] = "Recurso eliminado correctamente";
} else {
    // Mostrar error
    $_SESSION['error'] = "Error al eliminar el recurso: " . $db->getError();
}

redirect('listar.php');
