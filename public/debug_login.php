<?php
// Guardar como debug_login.php en la carpeta public
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$email = 'juan.martinez@demquintanormal.cl'; // Cambia a tu email de prueba
$password = 'profesor123'; // Cambia a tu contraseña de prueba

$db = Database::getInstance();
$sql = "SELECT id_usuario, nombre, apellido, email, contraseña, id_rol, activo FROM usuarios WHERE email = ?";
$usuario = $db->getRow($sql, [$email]);

echo "<h1>Depuración de Login</h1>";

if ($usuario) {
    echo "<p style='color:green'>Usuario encontrado en la base de datos.</p>";
    echo "<ul>";
    echo "<li>ID: {$usuario['id_usuario']}</li>";
    echo "<li>Nombre: {$usuario['nombre']} {$usuario['apellido']}</li>";
    echo "<li>Email: {$usuario['email']}</li>";
    echo "<li>Rol: {$usuario['id_rol']}</li>";
    echo "<li>Activo: {$usuario['activo']}</li>";
    echo "<li>Hash almacenado: {$usuario['contraseña']}</li>";
    echo "</ul>";

    $password_verify_result = password_verify($password, $usuario['contraseña']);
    echo "<p>Resultado de password_verify: " . ($password_verify_result ? "ÉXITO" : "FALLO") . "</p>";

    // Generar un nuevo hash para comparar
    $new_hash = password_hash($password, PASSWORD_DEFAULT);
    echo "<p>Nuevo hash generado: $new_hash</p>";

    // Verificar si el nuevo hash funcionaría
    echo "<p>Verificación con nuevo hash: " . (password_verify($password, $new_hash) ? "ÉXITO" : "FALLO") . "</p>";
} else {
    echo "<p style='color:red'>Usuario no encontrado en la base de datos.</p>";
    echo "<p>Email buscado: $email</p>";
}
