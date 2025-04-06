<?php

/**
 * Punto de entrada principal de la aplicación
 * Combina la funcionalidad de login y routing
 */

// Iniciar sesión
session_start();

// Incluir archivos de configuración
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar si el usuario ya está logueado
if (isset($_SESSION['usuario_id'])) {
    // Redirigir según el rol del usuario
    switch ($_SESSION['usuario_rol']) {
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
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="./assets/css/styles.css">
</head>

<body>
    <div class="login-container">
        <div class="login-image">
            <!-- Imagen calendario o ilustración -->
            <svg width="300" height="300" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path fill="#ffffff" d="M19 19H5V8h14m-3-7v2H8V1H6v2H5c-1.11 0-2 .89-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-1V1m-1 11h-5v5h5v-5Z" />
            </svg>
        </div>
        <div class="login-form">
            <div class="login-logo">
                <div class="login-logo-icon"></div>
                <div class="login-logo-text">
                    Gestión de Recursos e<br>
                    Infraestructura Digital
                </div>
            </div>
            <h1>Bienvenido de nuevo!</h1>
            <p class="subtitle">Inicia sesión para acceder a tu cuenta.</p>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form action="./procesar_login.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="login-button">Iniciar Sesión</button>

                <div class="login-links">
                    <a href="registro.php">Crear una cuenta</a>
                    <a href="recuperar_password.php">Olvidaste la contraseña</a>
                </div>
            </form>
        </div>
    </div>

    <script src="./assets/js/validation.js"></script>
</body>

</html>