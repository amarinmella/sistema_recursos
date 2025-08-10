<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['requiere_cambio'])) {
    redirect('index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';

    if (empty($password) || strlen($password) < 6) {
        $_SESSION['error'] = "La contraseña debe tener al menos 6 caracteres";
    } elseif ($password !== $confirmar_password) {
        $_SESSION['error'] = "Las contraseñas no coinciden";
    } else {
        $db = Database::getInstance();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $db->update(
            'usuarios',
            ['contraseña' => $hashed_password, 'requiere_cambio_contrasena' => 0],
            'id_usuario = ?',
            [$_SESSION['usuario_id']]
        );

        unset($_SESSION['requiere_cambio']);
        $_SESSION['success'] = "Contraseña actualizada correctamente. Por favor, inicia sesión de nuevo.";
        redirect('logout.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar Contraseña</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-form">
            <h1>Cambiar Contraseña</h1>
            <p>Por tu seguridad, debes cambiar tu contraseña para continuar.</p>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="password">Nueva Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirmar_password">Confirmar Nueva Contraseña:</label>
                    <input type="password" id="confirmar_password" name="confirmar_password" required>
                </div>
                <button type="submit" class="login-button">Cambiar Contraseña</button>
            </form>
        </div>
    </div>
</body>
</html>
