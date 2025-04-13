<?php
// restablecer_contrasena.php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar si se ha proporcionado un token
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    $_SESSION['mensaje'] = "Token de restablecimiento no válido o caducado.";
    $_SESSION['tipo'] = "alert-error";
    redirect('index.php');
    exit;
}

// Verificar si el token es válido
$db = Database::getInstance();
$sql = "SELECT r.id_usuario, r.caducidad, u.nombre, u.apellido 
        FROM recuperacion_password r
        JOIN usuarios u ON r.id_usuario = u.id_usuario
        WHERE r.token = ? AND r.usado = 0 AND r.caducidad > NOW()";

$recuperacion = $db->getRow($sql, [$token]);

if (!$recuperacion) {
    $_SESSION['mensaje'] = "Token de restablecimiento no válido o caducado.";
    $_SESSION['tipo'] = "alert-error";
    redirect('index.php');
    exit;
}

// Si se ha enviado el formulario para cambiar la contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contrasena = isset($_POST['contrasena']) ? trim($_POST['contrasena']) : '';
    $confirmar_contrasena = isset($_POST['confirmar_contrasena']) ? trim($_POST['confirmar_contrasena']) : '';

    // Validar las contraseñas
    if (empty($contrasena) || empty($confirmar_contrasena)) {
        $_SESSION['mensaje'] = "Por favor completa todos los campos.";
        $_SESSION['tipo'] = "alert-error";
        redirect('restablecer_contrasena.php?token=' . $token);
        exit;
    }

    if (strlen($contrasena) < 8) {
        $_SESSION['mensaje'] = "La contraseña debe tener al menos 8 caracteres.";
        $_SESSION['tipo'] = "alert-error";
        redirect('restablecer_contrasena.php?token=' . $token);
        exit;
    }

    if ($contrasena !== $confirmar_contrasena) {
        $_SESSION['mensaje'] = "Las contraseñas no coinciden.";
        $_SESSION['tipo'] = "alert-error";
        redirect('restablecer_contrasena.php?token=' . $token);
        exit;
    }

    // Actualizar la contraseña del usuario
    $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

    $db->beginTransaction();

    try {
        // Actualizar la contraseña
        $resultado1 = $db->execute(
            "UPDATE usuarios SET contraseña = ? WHERE id_usuario = ?",
            [$contrasena_hash, $recuperacion['id_usuario']]
        );

        // Marcar el token como usado
        $resultado2 = $db->execute(
            "UPDATE recuperacion_password SET usado = 1 WHERE token = ?",
            [$token]
        );

        if ($resultado1 && $resultado2) {
            $db->commit();
            $_SESSION['mensaje'] = "Tu contraseña ha sido actualizada con éxito. Ya puedes iniciar sesión con tu nueva contraseña.";
            $_SESSION['tipo'] = "alert-success";
            redirect('index.php');
            exit;
        } else {
            throw new Exception("Error al actualizar la contraseña.");
        }
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['mensaje'] = "Ha ocurrido un error al actualizar tu contraseña. Por favor, intenta nuevamente.";
        $_SESSION['tipo'] = "alert-error";
        redirect('restablecer_contrasena.php?token=' . $token);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body class="login-page">
    <div class="login-container">
        <div class="login-image">
            <img src="assets/img/reset-password.svg" alt="Restablecer Contraseña" style="max-width: 80%; height: auto;">
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
                <h1>Restablecer Contraseña</h1>
                <p>Hola <?php echo htmlspecialchars($recuperacion['nombre'] . ' ' . $recuperacion['apellido']); ?>, ingresa tu nueva contraseña a continuación.</p>
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

            <form action="restablecer_contrasena.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
                <div class="form-group">
                    <label for="contrasena">Nueva Contraseña:</label>
                    <input type="password" id="contrasena" name="contrasena" required placeholder="Mínimo 8 caracteres">
                </div>

                <div class="form-group">
                    <label for="confirmar_contrasena">Confirmar Contraseña:</label>
                    <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" required placeholder="Repite la contraseña">
                </div>

                <button type="submit" class="login-button">Guardar Nueva Contraseña</button>
            </form>

            <div class="login-links">
                <a href="index.php">Volver al Inicio de Sesión</a>
            </div>
        </div>
    </div>
</body>

</html>