<?php

/**
 * Procesa el formulario de inicio de sesión
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
    exit;
}

// Obtener datos del formulario
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validar datos básicos
$errors = [];

if (empty($email)) {
    $errors[] = "El email es obligatorio";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "El formato del email no es válido";
}

if (empty($password)) {
    $errors[] = "La contraseña es obligatoria";
}

// Si hay errores, redirigir al formulario
if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    redirect('index.php');
    exit;
}

try {
    // Obtener instancia de la base de datos
    $db = Database::getInstance();

    // Consultar usuario por email
    $sql = "SELECT id_usuario, nombre, apellido, email, contraseña, id_rol, activo 
            FROM usuarios 
            WHERE email = ?";

    $usuario = $db->getRow($sql, [$email]);

    // Verificar si existe el usuario y la contraseña es correcta
    if ($usuario && password_verify($password, $usuario['contraseña'])) {
        // Verificar si la cuenta está activa
        if (!$usuario['activo']) {
            $_SESSION['error'] = "Tu cuenta se encuentra desactivada. Contacta al administrador.";
            redirect('index.php');
            exit;
        }

        // Actualizar último login
        $db->update(
            'usuarios',
            ['ultimo_login' => date('Y-m-d H:i:s')],
            'id_usuario = ?',
            [$usuario['id_usuario']]
        );

        // Iniciar sesión del usuario
        $_SESSION['usuario_id'] = $usuario['id_usuario'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_rol'] = $usuario['id_rol'];

        // Registrar el login en el log
        $data = [
            'id_usuario' => $usuario['id_usuario'],
            'accion' => 'login',
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s')
        ];
        $db->insert('log_acciones', $data);

        // Redirigir según el rol
        switch ($usuario['id_rol']) {
            case ROL_ADMIN:
                redirect('admin/dashboard.php');
                break;
            case ROL_ACADEMICO:
                redirect('academico/dashboard.php');
                break;
            case ROL_PROFESOR:
                redirect('profesor/dashboard.php');
                break;
            case ROL_ESTUDIANTE:
                redirect('estudiante/dashboard.php');
                break;
            default:
                redirect('dashboard.php');
        }
    } else {
        // Credenciales inválidas
        $_SESSION['error'] = "Email o contraseña incorrectos";
        redirect('index.php');
    }
} catch (Exception $e) {
    // Error en el proceso
    error_log("Error en el inicio de sesión: " . $e->getMessage());
    $_SESSION['error'] = "Ha ocurrido un error al procesar la solicitud";
    redirect('index.php');
}
