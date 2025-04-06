<?php

/**
 * Perfil del Estudiante
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado y sea estudiante
require_login();
if ($_SESSION['usuario_rol'] != ROL_ESTUDIANTE) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Obtener datos del usuario actual
$usuario = $db->getRow(
    "SELECT * FROM usuarios WHERE id_usuario = ?",
    [$_SESSION['usuario_id']]
);

if (!$usuario) {
    $_SESSION['error'] = "No se encontró información del usuario";
    redirect('dashboard.php');
    exit;
}

// Verificar si hay mensaje de éxito o error
$mensaje = '';
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';

    // Validar datos
    $errores = [];

    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio";
    }

    if (empty($apellido)) {
        $errores[] = "El apellido es obligatorio";
    }

    if (empty($email)) {
        $errores[] = "El email es obligatorio";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El formato del email no es válido";
    } else {
        // Verificar si el email ya existe en otro usuario
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE email = ? AND id_usuario != ?";
        $result = $db->getRow($sql, [$email, $_SESSION['usuario_id']]);

        if ($result && $result['total'] > 0) {
            $errores[] = "El email ya está registrado por otro usuario";
        }
    }

    // Verificar si se quiere cambiar la contraseña
    $cambiar_password = !empty($password_nueva) || !empty($confirmar_password);

    if ($cambiar_password) {
        // Verificar contraseña actual
        if (empty($password_actual)) {
            $errores[] = "Debes ingresar tu contraseña actual para cambiarla";
        } elseif (!password_verify($password_actual, $usuario['contraseña'])) {
            $errores[] = "La contraseña actual es incorrecta";
        }

        // Validar nueva contraseña
        if (empty($password_nueva)) {
            $errores[] = "La nueva contraseña no puede estar vacía";
        } elseif (strlen($password_nueva) < 6) {
            $errores[] = "La nueva contraseña debe tener al menos 6 caracteres";
        }

        // Verificar que las contraseñas coincidan
        if ($password_nueva !== $confirmar_password) {
            $errores[] = "Las contraseñas no coinciden";
        }
    }

    // Si hay errores, mostrarlos
    if (!empty($errores)) {
        $_SESSION['error'] = implode('<br>', $errores);
        redirect('perfil.php');
        exit;
    }

    // Preparar datos para actualizar
    $data = [
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email' => $email
    ];

    // Añadir contraseña si se está cambiando
    if ($cambiar_password) {
        $data['contraseña'] = password_hash($password_nueva, PASSWORD_DEFAULT);
    }

    // Actualizar en la base de datos
    $resultado = $db->update('usuarios', $data, 'id_usuario = ?', [$_SESSION['usuario_id']]);

    if ($resultado) {
        // Actualizar nombre en la sesión
        $_SESSION['usuario_nombre'] = $nombre . ' ' . $apellido;

        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'actualizar',
            'entidad' => 'usuarios',
            'id_entidad' => $_SESSION['usuario_id'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s'),
            'detalles' => 'Actualización de perfil'
        ];
        $db->insert('log_acciones', $log_data);

        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Perfil actualizado correctamente" . ($cambiar_password ? ". La contraseña ha sido cambiada." : "");
        redirect('perfil.php');
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al actualizar el perfil: " . $db->getError();
        redirect('perfil.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema de Gestión de Recursos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"></div>
                <div>Sistema de Gestión</div>
            </div>
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">Dashboard</a>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Mis Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="perfil.php" class="nav-item active">Mi Perfil</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Mi Perfil</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <h2 class="form-title">Información Personal</h2>

                <form action="" method="POST" class="profile-form">
                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="apellido">Apellido *</label>
                        <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($usuario['apellido']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                    </div>

                    <h3 class="form-subtitle">Cambiar Contraseña</h3>
                    <p class="form-note">Solo complete estos campos si desea cambiar su contraseña.</p>

                    <div class="form-group">
                        <label for="password_actual">Contraseña Actual</label>
                        <input type="password" id="password_actual" name="password_actual">
                    </div>

                    <div class="form-group">
                        <label for="password_nueva">Nueva Contraseña</label>
                        <input type="password" id="password_nueva" name="password_nueva">
                        <small>La contraseña debe tener al menos 6 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="confirmar_password">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirmar_password" name="confirmar_password">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2 class="card-title">Información de Cuenta</h2>
                <div class="account-info">
                    <p><strong>Rol:</strong> Estudiante</p>
                    <p><strong>Fecha de registro:</strong> <?php echo format_date($usuario['fecha_registro']); ?></p>
                    <p><strong>Último acceso:</strong> <?php echo $usuario['ultimo_login'] ? format_date($usuario['ultimo_login'], true) : 'Nunca'; ?></p>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Mis Estadísticas</h2>
                <div class="student-stats">
                    <?php
                    // Obtener estadísticas de uso
                    $stats = $db->getRow(
                        "SELECT 
                            COUNT(*) as total_reservas,
                            SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as reservas_completadas,
                            SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as reservas_canceladas,
                            COUNT(DISTINCT id_recurso) as recursos_distintos
                        FROM reservas
                        WHERE id_usuario = ?",
                        [$_SESSION['usuario_id']]
                    );

                    // Recurso más reservado
                    $recurso_favorito = $db->getRow(
                        "SELECT r.id_recurso, r.nombre, COUNT(*) as total
                        FROM reservas res
                        JOIN recursos r ON res.id_recurso = r.id_recurso
                        WHERE res.id_usuario = ?
                        GROUP BY r.id_recurso, r.nombre
                        ORDER BY total DESC
                        LIMIT 1",
                        [$_SESSION['usuario_id']]
                    );
                    ?>

                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['total_reservas'] ?? 0; ?></div>
                            <div class="stat-label">Total de Reservas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['reservas_completadas'] ?? 0; ?></div>
                            <div class="stat-label">Reservas Completadas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['recursos_distintos'] ?? 0; ?></div>
                            <div class="stat-label">Recursos Utilizados</div>
                        </div>
                    </div>

                    <?php if ($recurso_favorito && $recurso_favorito['total'] > 0): ?>
                        <p class="favorite-resource">
                            <strong>Tu recurso favorito:</strong>
                            <?php echo htmlspecialchars($recurso_favorito['nombre']); ?>
                            (<?php echo $recurso_favorito['total']; ?> reservas)
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
                    const form = document.querySelector('.profile-form');

                    form.addEventListener('submit', function(event) {
                        let hasError = false;

                        // Validar campos obligatorios
                        const nombreField = document.getElementById('nombre');
                        const apellidoField = document.getElementById('apellido');
                        const emailField = document.getElementById('email');

                        // Campos de contraseña
                        const passwordActualField = document.getElementById('password_actual');
                        const passwordNuevaField = document.getElementById('password_nueva');
                        const confirmarPasswordField = document.getElementById('confirmar_password');

                        // Validar nombre
                        if (nombreField.value.trim() === '') {
                            showError(nombreField, 'El nombre es obligatorio');
                            hasError = true;
                        } else {
                            removeError(nombreField);
                        }

                        // Validar apellido
                        if (apellidoField.value.trim() === '') {
                            showError(apellidoField, 'El apellido es obligatorio');
                            hasError = true;
                        } else {
                            removeError(apellidoField);
                        }

                        // Validar email
                        if (emailField.value.trim() === '') {
                            showError(emailField, 'El email es obligatorio');
                            hasError = true;
                        } else if (!isValidEmail(emailField.value)) {
                            showError(emailField, 'El formato del email no es válido');
                            hasError = true;
                        } else {
                            removeError(emailField);
                        }

                        // Validar contraseñas solo si se está intentando cambiarlas
                        if (passwordNuevaField.value !== '' || confirmarPasswordField.value !== '') {
                            // Validar contraseña actual
                            if (passwordActualField.value === '') {
                                showError(passwordActualField, 'Debes ingresar tu contraseña actual para cambiarla');
                                hasError = true;
                            } else {
                                removeError(passwordActualField);
                            }

                            // Validar nueva contraseña
                            if (passwordNuevaField.value === '') {
                                showError(passwordNuevaField, 'La nueva contraseña no puede estar vacía');
                                hasError = true;
                            } else if (passwordNuevaField.value.length < 6) {
                                showError(passwordNuevaField, 'La nueva contraseña debe tener al menos 6 caracteres');
                                hasError = true;
                            } else {
                                removeError(passwordNuevaField);
                            }

                            // Validar confirmación de contraseña
                            if (passwordNuevaField.value !== confirmarPasswordField.value) {
                                showError(confirmarPasswordField, 'Las contraseñas no coinciden');
                                hasError = true;
                            } else {
                                removeError(confirmarPasswordField);
                            }
                        }

                        // Si hay errores, prevenir envío del formulario
                        if (hasError) {
                            event.preventDefault();
                        }
                    });

                    // Función para validar email
                    function isValidEmail(email) {
                        const re = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
                        return re.test(email);
                    }

                    // Función para mostrar error
                    function showError(field, message) {
                        // Remover error previo si existe
                        removeError(field);

                        // Crear elemento de error
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message';
                        errorDiv.textContent = message;

                        // Añadir borde rojo al campo
                        field.style.borderColor = '#e74c3c';

                        // Insertar mensaje de error después del campo
                        field.parentNode.