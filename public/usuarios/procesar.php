<?php

/**
 * Procesamiento de Usuarios (activar, desactivar, eliminar)
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Acción no permitida";
    redirect('listar.php');
    exit;
}

// Validar token CSRF
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    $_SESSION['error'] = "Error de validación de seguridad. Inténtalo de nuevo.";
    redirect('listar.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Verificar acción y ID
if (!isset($_POST['accion']) || !isset($_POST['id'])) {
    $_SESSION['error'] = "Acción o ID no especificados";
    redirect('listar.php');
    exit;
}

$accion = $_POST['accion'];
$id_usuario = intval($_POST['id']);

// Verificar que el ID es válido
if ($id_usuario <= 0) {
    $_SESSION['error'] = "ID de usuario no válido";
    redirect('listar.php');
    exit;
}

// Verificar que el usuario existe
$usuario = $db->getRow("SELECT id_usuario, nombre, apellido, activo FROM usuarios WHERE id_usuario = ?", [$id_usuario]);

if (!$usuario) {
    $_SESSION['error'] = "El usuario no existe";
    redirect('listar.php');
    exit;
}

// Verificar que no se está actuando sobre el propio usuario logueado
if ($id_usuario == $_SESSION['usuario_id'] && $accion != 'ver') {
    $_SESSION['error'] = "No puedes realizar esta acción sobre tu propio usuario";
    redirect('listar.php');
    exit;
}

// Procesar según la acción
switch ($accion) {
    case 'activar':
        // Verificar que el usuario está inactivo
        if ($usuario['activo']) {
            $_SESSION['error'] = "El usuario ya está activo";
            redirect('listar.php');
            exit;
        }

        // Activar usuario
        $resultado = $db->update('usuarios', ['activo' => 1], 'id_usuario = ?', [$id_usuario]);

        if ($resultado) {
            // Registrar la acción
            $log_data = [
                'id_usuario' => $_SESSION['usuario_id'],
                'accion' => 'activar',
                'entidad' => 'usuarios',
                'id_entidad' => $id_usuario,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'fecha' => date('Y-m-d H:i:s'),
                'detalles' => 'Usuario activado: ' . $usuario['nombre'] . ' ' . $usuario['apellido']
            ];
            $db->insert('log_acciones', $log_data);

            $_SESSION['success'] = "Usuario activado correctamente";
        } else {
            $_SESSION['error'] = "Error al activar el usuario: " . $db->getError();
        }
        break;

    case 'desactivar':
        // Verificar que el usuario está activo
        if (!$usuario['activo']) {
            $_SESSION['error'] = "El usuario ya está inactivo";
            redirect('listar.php');
            exit;
        }

        // Desactivar usuario
        $resultado = $db->update('usuarios', ['activo' => 0], 'id_usuario = ?', [$id_usuario]);

        if ($resultado) {
            // Registrar la acción
            $log_data = [
                'id_usuario' => $_SESSION['usuario_id'],
                'accion' => 'desactivar',
                'entidad' => 'usuarios',
                'id_entidad' => $id_usuario,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'fecha' => date('Y-m-d H:i:s'),
                'detalles' => 'Usuario desactivado: ' . $usuario['nombre'] . ' ' . $usuario['apellido']
            ];
            $db->insert('log_acciones', $log_data);

            $_SESSION['success'] = "Usuario desactivado correctamente";
        } else {
            $_SESSION['error'] = "Error al desactivar el usuario: " . $db->getError();
        }
        break;

    case 'eliminar':
        // Verificar que no es el propio usuario
        if ($id_usuario == $_SESSION['usuario_id']) {
            $_SESSION['error'] = "No puedes eliminar tu propio usuario";
            redirect('listar.php');
            exit;
        }

        // Verificar si el usuario tiene reservas o acciones registradas
        $reservas = $db->getRow("SELECT COUNT(*) as total FROM reservas WHERE id_usuario = ?", [$id_usuario]);
        $mantenimientos = $db->getRow("SELECT COUNT(*) as total FROM mantenimiento WHERE id_usuario = ?", [$id_usuario]);

        if (($reservas && $reservas['total'] > 0) || ($mantenimientos && $mantenimientos['total'] > 0)) {
            $_SESSION['error'] = "No se puede eliminar el usuario porque tiene reservas o mantenimientos asociados. Considere desactivarlo en su lugar.";
            redirect('listar.php');
            exit;
        }

        try {
            // Iniciar transacción para asegurar la integridad de los datos
            $conn = $db->getConnection();
            $conn->begin_transaction();

            // Eliminar notificaciones asociadas al usuario
            $db->query("DELETE FROM notificaciones WHERE id_usuario = ?", [$id_usuario]);

            // Eliminar registros de log asociados al usuario
            $db->query("UPDATE log_acciones SET id_usuario = NULL WHERE id_usuario = ?", [$id_usuario]);

            // Eliminar el usuario
            $resultado = $db->query("DELETE FROM usuarios WHERE id_usuario = ?", [$id_usuario]);

            // Si todo está bien, confirmar los cambios
            $conn->commit();

            // Registrar la acción
            $log_data = [
                'id_usuario' => $_SESSION['usuario_id'],
                'accion' => 'eliminar',
                'entidad' => 'usuarios',
                'id_entidad' => $id_usuario,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'fecha' => date('Y-m-d H:i:s'),
                'detalles' => 'Usuario eliminado: ' . $usuario['nombre'] . ' ' . $usuario['apellido']
            ];
            $db->insert('log_acciones', $log_data);

            // Configurar mensaje de éxito
            $_SESSION['success'] = "Usuario eliminado correctamente";
        } catch (Exception $e) {
            // Si algo falla, revertir los cambios
            if (isset($conn)) {
                $conn->rollback();
            }

            // Registrar el error y mostrarlo
            error_log("Error al eliminar usuario ID $id_usuario: " . $e->getMessage());
            $_SESSION['error'] = "Error al eliminar el usuario: " . $e->getMessage();
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
