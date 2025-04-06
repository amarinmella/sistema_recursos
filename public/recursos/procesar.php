<?php

/**
 * Procesamiento de Recursos (eliminación)
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
    $_SESSION['error'] = "No tienes permisos para realizar esta acción";
    redirect('listar.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Verificar acción y ID
if (!isset($_GET['accion']) || !isset($_GET['id'])) {
    $_SESSION['error'] = "Acción o ID no especificados";
    redirect('listar.php');
    exit;
}

$accion = $_GET['accion'];
$id_recurso = intval($_GET['id']);

// Verificar que el ID es válido
if ($id_recurso <= 0) {
    $_SESSION['error'] = "ID de recurso no válido";
    redirect('listar.php');
    exit;
}

// Verificar que el recurso existe
$recurso = $db->getRow("SELECT id_recurso, nombre FROM recursos WHERE id_recurso = ?", [$id_recurso]);

if (!$recurso) {
    $_SESSION['error'] = "El recurso no existe";
    redirect('listar.php');
    exit;
}

// Procesar según la acción
switch ($accion) {
    case 'eliminar':
        // Verificar si existen reservas para este recurso
        $reservas = $db->getRow("SELECT COUNT(*) as total FROM reservas WHERE id_recurso = ?", [$id_recurso]);

        if ($reservas && $reservas['total'] > 0) {
            $_SESSION['error'] = "No se puede eliminar el recurso porque existen reservas asociadas. Considere cambiar su estado a 'baja' en su lugar.";
            redirect('listar.php');
            exit;
        }

        // Verificar si existen mantenimientos para este recurso
        $mantenimientos = $db->getRow("SELECT COUNT(*) as total FROM mantenimiento WHERE id_recurso = ?", [$id_recurso]);

        if ($mantenimientos && $mantenimientos['total'] > 0) {
            $_SESSION['error'] = "No se puede eliminar el recurso porque existen registros de mantenimiento asociados. Considere cambiar su estado a 'baja' en su lugar.";
            redirect('listar.php');
            exit;
        }

        try {
            // Iniciar transacción para asegurar la integridad de los datos
            $conn = $db->getConnection();
            $conn->begin_transaction();

            // Eliminar el recurso
            $resultado = $db->query("DELETE FROM recursos WHERE id_recurso = ?", [$id_recurso]);

            // Si todo está bien, confirmar los cambios
            $conn->commit();

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

            // Configurar mensaje de éxito
            $_SESSION['success'] = "Recurso eliminado correctamente";
        } catch (Exception $e) {
            // Si algo falla, revertir los cambios
            if (isset($conn)) {
                $conn->rollback();
            }

            // Registrar el error y mostrarlo
            error_log("Error al eliminar recurso ID $id_recurso: " . $e->getMessage());
            $_SESSION['error'] = "Error al eliminar el recurso: " . $e->getMessage();
        }

        // Redireccionar en cualquier caso
        redirect('listar.php');
        exit;
        break;

    default:
        $_SESSION['error'] = "Acción no válida";
}

// Redireccionar al listado
redirect('listar.php');
