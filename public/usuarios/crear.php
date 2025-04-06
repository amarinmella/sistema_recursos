<?php

/**
 * Formulario para crear usuarios
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado y tenga permisos
require_login();
if (!has_role(ROL_ADMIN)) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Obtener roles
$db = Database::getInstance();
$roles = $db->getRows("SELECT id_rol, nombre FROM roles ORDER BY nombre");

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
    $password = $_POST['password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    $id_rol = intval($_POST['id_rol'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

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
        // Verificar si el email ya existe
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE email = ?";
        $result = $db->getRow($sql, [$email]);

        if ($result && $result['total'] > 0) {
            $errores[] = "El email ya está registrado";
        }
    }

    if (empty($password)) {
        $errores[] = "La contraseña es obligatoria";
    } elseif (strlen($password) < 6) {
        $errores[] = "La contraseña debe tener al menos 6 caracteres";
    }

    if ($password !== $confirmar_password) {
        $errores[] = "Las contraseñas no coinciden";
    }

    if ($id_rol <= 0) {
        $errores[] = "Debes seleccionar un rol";
    }

    // Si hay errores, mostrarlos
    if (!empty($errores)) {
        $_SESSION['error'] = implode('<br>', $errores);
        redirect('crear.php');
        exit;
    }

    // Preparar datos para insertar
    $data = [
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email' => $email,
        'contraseña' => password_hash($password, PASSWORD_DEFAULT),
        'id_rol' => $id_rol,
        'activo' => $activo,
        'fecha_registro' => date('Y-m-d H:i:s')
    ];

    // Insertar en la base de datos
    $resultado = $db->insert('usuarios', $data);

    if ($resultado) {
        // Registrar la acción
        $log_data = [
            'id_usuario' => $_SESSION['usuario_id'],
            'accion' => 'crear',
            'entidad' => 'usuarios',
            'id_entidad' => $resultado,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'fecha' => date('Y-m-d H:i:s')
        ];
        $db->insert('log_acciones', $log_data);

        // Redireccionar con mensaje de éxito
        $_SESSION['success'] = "Usuario creado correctamente";
        redirect('listar.php');
        exit;
    } else {
        // Mostrar error
        $_SESSION['error'] = "Error al crear el usuario: " . $db->getError();
        redirect('crear.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario - Sistema de Gestión de Recursos</title>
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
                <a href="../admin/dashboard.php" class="nav-item">Dashboard</a>
                <a href="../usuarios/listar.php" class="nav-item active">Usuarios</a>
                <a href="../recursos/listar.php" class="nav-item">Recursos</a>
                <a href="../reservas/listar.php" class="nav-item">Reservas</a>
                <a href="../reservas/calendario.php" class="nav-item">Calendario</a>
                <a href="../mantenimiento/listar.php" class="nav-item">Mantenimiento</a>
                <a href="../reportes/index.php" class="nav-item">Reportes</a>
            </div>
        </div>

        <div class="content">
            <div class="top-bar">
                <h1>Crear Nuevo Usuario</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card">
                <h2 class="form-title">Información del Usuario</h2>

                <form action="" method="POST" class="user-form">
                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>

                    <div class="form-group">
                        <label for="apellido">Apellido *</label>
                        <input type="text" id="apellido" name="apellido" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña *</label>
                        <input type="password" id="password" name="password" required>
                        <small>La contraseña debe tener al menos 6 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="confirmar_password">Confirmar Contraseña *</label>
                        <input type="password" id="confirmar_password" name="confirmar_password" required>
                    </div>

                    <div class="form-group">
                        <label for="id_rol">Rol *</label>
                        <select id="id_rol" name="id_rol" required>
                            <option value="">Seleccione un rol</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id_rol']; ?>">
                                    <?php echo htmlspecialchars($rol['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="activo" name="activo" checked>
                            <label for="activo">Usuario activo</label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Usuario</button>
                        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2 class="form-title">Información sobre Roles</h2>

                <ul>
                    <li><strong>Administrador:</strong> Acceso completo a todas las funcionalidades del sistema.</li>
                    <li><strong>Académico:</strong> Puede gestionar recursos y reservas, pero no administrar usuarios.</li>
                    <li><strong>Profesor:</strong> Puede realizar y gestionar sus propias reservas.</li>
                    <li><strong>Estudiante:</strong> Acceso limitado para realizar reservas según disponibilidad.</li>
                </ul>

                <p class="mt-4">
                    <strong>Nota:</strong> Los usuarios inactivos no podrán iniciar sesión en el sistema.
                </p>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.user-form');

            form.addEventListener('submit', function(event) {
                let hasError = false;

                // Obtener campos
                const nombreField = document.getElementById('nombre');
                const apellidoField = document.getElementById('apellido');
                const emailField = document.getElementById('email');
                const passwordField = document.getElementById('password');
                const confirmarField = document.getElementById('confirmar_password');
                const rolField = document.getElementById('id_rol');

                // Validar campos
                if (nombreField.value.trim() === '') {
                    showError(nombreField, 'El nombre es obligatorio');
                    hasError = true;
                } else {
                    removeError(nombreField);
                }

                if (apellidoField.value.trim() === '') {
                    showError(apellidoField, 'El apellido es obligatorio');
                    hasError = true;
                } else {
                    removeError(apellidoField);
                }

                if (emailField.value.trim() === '') {
                    showError(emailField, 'El email es obligatorio');
                    hasError = true;
                } else if (!isValidEmail(emailField.value)) {
                    showError(emailField, 'El formato del email no es válido');
                    hasError = true;
                } else {
                    removeError(emailField);
                }

                if (passwordField.value === '') {
                    showError(passwordField, 'La contraseña es obligatoria');
                    hasError = true;
                } else if (passwordField.value.length < 6) {
                    showError(passwordField, 'La contraseña debe tener al menos 6 caracteres');
                    hasError = true;
                } else {
                    removeError(passwordField);
                }

                if (confirmarField.value === '') {
                    showError(confirmarField, 'Debes confirmar la contraseña');
                    hasError = true;
                } else if (confirmarField.value !== passwordField.value) {
                    showError(confirmarField, 'Las contraseñas no coinciden');
                    hasError = true;
                } else {
                    removeError(confirmarField);
                }

                if (rolField.value === '') {
                    showError(rolField, 'Debes seleccionar un rol');
                    hasError = true;
                } else {
                    removeError(rolField);
                }

                // Si hay errores, prevenir envío del formulario
                if (hasError) {
                    event.preventDefault();
                }
            });

            // Validar formato de email
            function isValidEmail(email) {
                const regex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
                return regex.test(email);
            }

            // Mostrar error
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
                field.parentNode.appendChild(errorDiv);
            }

            // Remover error
            function removeError(field) {
                field.style.borderColor = '';

                // Buscar y eliminar mensajes de error existentes
                const parent = field.parentNode;
                const errorDiv = parent.querySelector('.error-message');

                if (errorDiv) {
                    parent.removeChild(errorDiv);
                }
            }
        });
    </script>
</body>

</html>