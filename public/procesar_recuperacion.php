<?php
// procesar_recuperacion.php

session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y limpiar el email
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    // Validar que se ingrese un email
    if (empty($email)) {
        $_SESSION['mensaje'] = "Por favor ingresa tu correo electrónico.";
        $_SESSION['tipo'] = "alert-error";
        redirect('recuperar_contrasena.php');
        exit;
    }

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['mensaje'] = "Por favor ingresa un correo electrónico válido.";
        $_SESSION['tipo'] = "alert-error";
        redirect('recuperar_contrasena.php');
        exit;
    }

    // Obtener instancia de la base de datos
    $db = Database::getInstance();

    // Verificar si el email existe y el usuario está activo
    $usuario = $db->getRow(
        "SELECT id_usuario, nombre, apellido FROM usuarios WHERE email = ? AND activo = 1",
        [$email]
    );

    // Por seguridad, se envía el mismo mensaje si no se encontró el usuario
    if (!$usuario) {
        $_SESSION['mensaje'] = "Si tu correo está registrado, recibirás un mensaje con instrucciones para restablecer tu contraseña.";
        $_SESSION['tipo'] = "alert-success";
        redirect('recuperar_contrasena.php');
        exit;
    }

    // Generar un token único para la recuperación
    $token = bin2hex(random_bytes(32));
    $caducidad = date('Y-m-d H:i:s', strtotime('+1 hour')); // El token caduca en 1 hora

    // Insertar el token en la tabla de recuperación usando el método insert()
    $resultado = $db->insert('recuperacion_password', [
        'id_usuario' => $usuario['id_usuario'],
        'token'      => $token,
        'caducidad'  => $caducidad,
        'usado'      => 0
    ]);

    if (!$resultado) {
        $_SESSION['mensaje'] = "Ha ocurrido un error al procesar tu solicitud. Por favor, intenta nuevamente más tarde.";
        $_SESSION['tipo'] = "alert-error";
        redirect('recuperar_contrasena.php');
        exit;
    }

    // Preparar el enlace de recuperación para el correo
    $enlace = BASE_URL . 'public/restablecer_contrasena.php?token=' . $token;
    $asunto = "Recuperación de Contraseña - Sistema de Gestión de Recursos";

    // Cuerpo del mensaje en formato HTML
    $mensaje = "
    <html>
    <head>
        <title>Recuperación de Contraseña</title>
    </head>
    <body>
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #4a90e2; color: white; padding: 10px 20px; border-radius: 5px 5px 0 0;'>
                <h2 style='margin: 0;'>Recuperación de Contraseña</h2>
            </div>
            <div style='border: 1px solid #ddd; border-top: none; padding: 20px; border-radius: 0 0 5px 5px;'>
                <p>Hola " . htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) . ",</p>
                <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta. Si no realizaste esta solicitud, ignora este mensaje.</p>
                <p>Para restablecer tu contraseña, haz clic en el siguiente enlace (válido por 1 hora):</p>
                <p style='text-align: center;'>
                    <a href='" . $enlace . "' style='display: inline-block; background-color: #4a90e2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Restablecer Contraseña</a>
                </p>
                <p>O copia y pega el siguiente enlace en tu navegador:</p>
                <p style='word-break: break-all;'>" . $enlace . "</p>
                <p>Este enlace caducará el " . date('d/m/Y H:i', strtotime($caducidad)) . ".</p>
                <p>Si tienes alguna pregunta, contacta al administrador del sistema.</p>
                <p>Saludos,<br>Sistema de Gestión de Recursos</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Preparar las cabeceras del correo
    $cabeceras = "MIME-Version: 1.0" . "\r\n";
    $cabeceras .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $cabeceras .= "From: Sistema de Gestión de Recursos <no-reply@sistema-gestion.com>" . "\r\n";

    // Enviar correo utilizando la función mail de PHP
    $enviado = mail($email, $asunto, $mensaje, $cabeceras);

    // Configurar mensajes de respuesta dependiendo del resultado del envío
    if ($enviado) {
        $_SESSION['mensaje'] = "Hemos enviado un correo con instrucciones para restablecer tu contraseña. Por favor, revisa tu bandeja de entrada (y carpeta de spam).";
        $_SESSION['tipo'] = "alert-success";
    } else {
        $_SESSION['mensaje'] = "Ha ocurrido un error al enviar el correo. Por favor, intenta nuevamente más tarde.";
        $_SESSION['tipo'] = "alert-error";
    }

    redirect('recuperar_contrasena.php');
    exit;
} else {
    // Si se intenta acceder directamente sin enviar el formulario, redirige a recuperar_contrasena.php
    redirect('recuperar_contrasena.php');
    exit;
}
