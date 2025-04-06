<?php

/**
 * Procesamiento de Reservas (confirmar, cancelar, completar)
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado
require_login();

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Verificar acción y ID
if (!isset($_GET['accion']) || !isset($_GET['id'])) {
    $_SESSION['error'] = "Acción o ID no especificados";
    redirect('listar.php');
    exit;
}

$accion = $_GET['accion'];
$id_reserva = intval($_GET['id']);

// Verificar que el ID es válido
if ($id_reserva <= 0) {
    $_SESSION['error'] = "ID de reserva no válido";
    redirect('listar.php');
    exit;
}

// Verificar que la reserva existe
$reserva = $db->getRow(
    "SELECT r.id_reserva, r.id_usuario, r.id_recurso, r.estado, r.fecha_inicio, r.fecha_fin,
            rc.nombre as recurso_nombre, u.nombre as usuario_nombre, u.apellido as usuario_apellido
     FROM reservas r
     JOIN recursos rc ON r.id_recurso = rc.id_recurso
     JOIN usuarios u ON r.id_usuario = u.id_usuario
     WHERE r.id_reserva = ?",
    [$id_reserva]
);

if (!$reserva) {
    $_SESSION['error'] = "La reserva no existe";
    redirect('listar.php');
    exit;
}

// Verificar permisos según la acción
$es_propietario = $reserva['id_usuario'] == $_SESSION['usuario_id'];
$es_admin = has_role([ROL_ADMIN, ROL_ACADEMICO]);

switch ($accion) {
    case 'confirmar':
        // Solo administradores o académicos pueden confirmar reservas
        if (!$es_admin) {
            $_SESSION['error'] = "No tienes permisos para confirmar reservas";
            redirect('listar.php');
            exit;
        }

        // Verificar que la reserva esté pendiente
        if ($reserva['estado'] !== 'pendiente') {
            $_SESSION['error'] = "Solo se pueden confirmar reservas pendientes";
            redirect('listar.php');
            exit;
        }

        // Cambiar estado a confirmada
        $resultado = $db->update('reservas', ['estado' => 'confirmada'], 'id_reserva = ?', [$id_reserva]);

        if ($resultado) {
            // Registrar la acción
            $log_data = [
                'id_usuario' => $_SESSION['usuario_id'],
                'accion' => 'confirmar',
                'entidad' => 'reservas',
                'id_entidad' => $id_reserva,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'fecha' => date('Y-m-d H:i:s')
            ];
            $db->insert('log_acciones', $log_data);

            // Crear notificación para el usuario
            if (!$es_propietario) {
                $mensaje = "Tu reserva para '{$reserva['recurso_nombre']}' del día " .
                    date('d/m/Y H:i', strtotime($reserva['fecha_inicio'])) .
                    " ha sido confirmada.";

                $db->insert('notificaciones', [
                    'id_reserva' => $id_reserva,
                    'id_usuario' => $reserva['id_usuario'],
                    'mensaje' => $mensaje,
                    'leido' => 0,
                    'fecha' => date('Y-m-d H:i:s')
                ]);
            }

            $_SESSION['success'] = "Reserva confirmada correctamente";
        } else {
            $_SESSION['error'] = "Error al confirmar la reserva: " . $db->getError();
        }
        break;

    case 'cancelar':
        // Verificar permisos (solo admin o propietario)
        if (!$es_admin && !$es_propietario) {
            $_SESSION['error'] = "No tienes permisos para cancelar esta reserva";
            redirect('listar.php');
            exit;
        }

        // Verificar que la reserva esté pendiente o confirmada
        if (!in_array($reserva['estado'], ['pendiente', 'confirmada'])) {
            $_SESSION['error'] = "Solo se pueden cancelar reservas pendientes o confirmadas";
            redirect('listar.php');
            exit;
        }

        // Cambiar estado a cancelada
        $resultado = $db->update('reservas', ['estado' => 'cancelada'], 'id_reserva = ?', [$id_reserva]);

        if ($resultado) {
            // Registrar la acción
            $log_data = [
                'id_usuario' => $_SESSION['usuario_id'],
                'accion' => 'cancelar',
                'entidad' => 'reservas',
                'id_entidad' => $id_reserva,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'fecha' => date('Y-m-d H:i:s')
            ];
            $db->insert('log_acciones', $log_data);

            // Crear notificación si no es el propietario quien cancela
            if (!$es_propietario) {
                $mensaje = "Tu reserva para '{$reserva['recurso_nombre']}' del día " .
                    date('d/m/Y H:i', strtotime($reserva['fecha_inicio'])) .
                    " ha sido cancelada por un administrador.";

                $db->insert('notificaciones', [
                    'id_reserva' => $id_reserva,
                    'id_usuario' => $reserva['id_usuario'],
                    'mensaje' => $mensaje,
                    'leido' => 0,
                    'fecha' => date('Y-m-d H:i:s')
                ]);
            }

            // Crear notificación para administradores si es el propietario quien cancela
            if ($es_propietario && !$es_admin) {
                $admins = $db->getRows("SELECT id_usuario FROM usuarios WHERE id_rol IN (?, ?) AND activo = 1", [ROL_ADMIN, ROL_ACADEMICO]);

                foreach ($admins as $admin) {
                    $mensaje = "La reserva para '{$reserva['recurso_nombre']}' del día " .
                        date('d/m/Y H:i', strtotime($reserva['fecha_inicio'])) .
                        " ha sido cancelada por {$reserva['usuario_nombre']} {$reserva['usuario_apellido']}.";

                    $db->insert('notificaciones', [
                        'id_reserva' => $id_reserva,
                        'id_usuario' => $admin['id_usuario'],
                        'mensaje' => $mensaje,
                        'leido' => 0,
                        'fecha' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            $_SESSION['success'] = "Reserva cancelada correctamente";
        } else {
            $_SESSION['error'] = "Error al cancelar la reserva: " . $db->getError();
        }
        break;

    case 'completar':
        // Solo administradores o académicos pueden completar reservas
        if (!$es_admin) {
            $_SESSION['error'] = "No tienes permisos para completar reservas";
            redirect('listar.php');
            exit;
        }

        // Verificar que la reserva esté confirmada
        if ($reserva['estado'] !== 'confirmada') {
            $_SESSION['error'] = "Solo se pueden completar reservas confirmadas";
            redirect('listar.php');
            exit;
        }

        // Cambiar estado a completada
        $resultado = $db->update('reservas', ['estado' => 'completada'], 'id_reserva = ?', [$id_reserva]);

        if ($resultado) {
            // Registrar la acción
            $log_data = [
                'id_usuario' => $_SESSION['usuario_id'],
                'accion' => 'completar',
                'entidad' => 'reservas',
                'id_entidad' => $id_reserva,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'fecha' => date('Y-m-d H:i:s')
            ];
            $db->insert('log_acciones', $log_data);

            $_SESSION['success'] = "Reserva marcada como completada correctamente";
        } else {
            $_SESSION['error'] = "Error al completar la reserva: " . $db->getError();
        }
        break;

    default:
        $_SESSION['error'] = "Acción no válida";
}

// Redireccionar al listado
redirect('listar.php');
