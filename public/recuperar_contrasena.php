<?php
// recuperar_contrasena.php
session_start();
require_once '../config/config.php';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body class="login-page">
    <div class="login-container">
        <div class="login-image">
            <img src="assets/img/password-reset.svg" alt="Recuperar Contraseña" style="max-width: 80%; height: auto;">
        </div>
        <div class="login-form">
            <div class="login-logo">
                <div class="login-logo-icon"></div>
                <div class="login-logo-text">
                    Gestión de Recursos e<br>
                    Infraestructura Digital
                </div>
            </div>

            <div class="login-heading">
                <h1>Recuperación de Contraseña</h1>
                <p>Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
            </div>

            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert <?php echo isset($_SESSION['tipo']) ? $_SESSION['tipo'] : 'alert-info'; ?>">
                    <?php
                    echo $_SESSION['mensaje'];
                    unset($_SESSION['mensaje']);
                    unset($_SESSION['tipo']);
                    ?>
                </div>
            <?php endif; ?>

            <form action="procesar_recuperacion.php" method="post">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required placeholder="Ingresa tu correo electrónico">
                </div>

                <button type="submit" class="login-button">Enviar Correo de Recuperación</button>
            </form>

            <div class="login-links">
                <a href="index.php">Volver al Inicio de Sesión</a>
            </div>
        </div>
    </div>
</body>

</html>