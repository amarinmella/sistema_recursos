<?php

/**
 * Cierre de sesión
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../config/config.php';
require_once '../includes/functions.php';

// Registrar logout en el log (si es necesario)
if (isset($_SESSION['usuario_id'])) {
    require_once '../config/database.php';
    $db = Database::getInstance();
    $data = [
        'id_usuario' => $_SESSION['usuario_id'],
        'accion' => 'logout',
        'ip' => $_SERVER['REMOTE_ADDR'],
        'fecha' => date('Y-m-d H:i:s')
    ];
    $db->insert('log_acciones', $data);
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se utiliza un cookie de sesión, eliminarlo
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Mensaje de éxito
session_start();
$_SESSION['success'] = "Has cerrado sesión correctamente";

// Redireccionar al login
redirect('index.php');
