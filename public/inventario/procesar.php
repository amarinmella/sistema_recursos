<?php

/**
 * Procesar Acciones de Inventario
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado y tenga permisos de administrador
require_login();
if (!has_role(ROL_ADMIN)) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Verificar que se proporcionó una acción
if (!isset($_GET['accion']) || empty($_GET['accion'])) {
    $_SESSION['error'] = "Acción no especificada";
    redirect('listar.php');
    exit;
}

$accion = $_GET['accion'];

// Obtener instancia de la base de datos
$db = Database::getInstance();

switch ($accion) {
    case 'eliminar':
        // Verificar que se proporcionó un ID
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            $_SESSION['error'] = "ID de item no especificado";
            redirect('listar.php');
            exit;
        }

        $id_item = intval($_GET['id']);

        // Verificar que el item existe
        $item = $db->getRow("SELECT nombre FROM inventario WHERE id_item = ?", [$id_item]);
        
        if (!$item) {
            $_SESSION['error'] = "El item no existe";
            redirect('listar.php');
            exit;
        }

        // Intentar eliminar el item
        $resultado = $db->delete('inventario', 'id_item = ?', [$id_item]);

        if ($resultado) {
            // Registrar la acción
            $log_data = [
                'id_usuario' => $_SESSION['usuario_id'],
                'accion' => 'eliminar',
                'entidad' => 'inventario',
                'id_entidad' => $id_item,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'fecha' => date('Y-m-d H:i:s')
            ];
            $db->insert('log_acciones', $log_data);

            $_SESSION['success'] = "Item '" . htmlspecialchars($item['nombre']) . "' eliminado correctamente";
        } else {
            $_SESSION['error'] = "Error al eliminar el item: " . $db->getError();
        }
        break;

    default:
        $_SESSION['error'] = "Acción no válida";
        break;
}

// Redireccionar de vuelta al listado
redirect('listar.php');
exit; 