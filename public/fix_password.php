<?php
// Archivo para arreglar contraseñas
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir archivos necesarios
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

echo "<h1>Herramienta de reparación de contraseñas</h1>";

// Crear un usuario de prueba con contraseña conocida
$email_test = 'test.estudiante@demquintanormal.cl';
$password_test = 'test123';
$hash_test = password_hash($password_test, PASSWORD_DEFAULT);

try {
    $db = Database::getInstance();

    // Verificar si el usuario de prueba ya existe
    $sql = "SELECT id_usuario FROM usuarios WHERE email = ?";
    $usuario = $db->getRow($sql, [$email_test]);

    if ($usuario) {
        // Actualizar contraseña del usuario existente
        $db->update('usuarios', ['contraseña' => $hash_test], 'id_usuario = ?', [$usuario['id_usuario']]);
        echo "<p style='color:green'>Usuario de prueba actualizado con éxito</p>";
    } else {
        // Crear usuario de prueba
        $data = [
            'nombre' => 'Usuario',
            'apellido' => 'Prueba',
            'email' => $email_test,
            'contraseña' => $hash_test,
            'id_rol' => 4, // Estudiante
            'activo' => 1,
            'fecha_registro' => date('Y-m-d H:i:s')
        ];

        $resultado = $db->insert('usuarios', $data);
        if ($resultado) {
            echo "<p style='color:green'>Usuario de prueba creado con éxito</p>";
        } else {
            echo "<p style='color:red'>Error al crear usuario de prueba: " . $db->getError() . "</p>";
        }
    }

    echo "<h2>Credenciales de prueba:</h2>";
    echo "<p><strong>Email:</strong> $email_test</p>";
    echo "<p><strong>Contraseña:</strong> $password_test</p>";
    echo "<p><strong>Hash:</strong> $hash_test</p>";

    // Verificar la función password_verify
    echo "<h2>Prueba de password_verify:</h2>";
    $verify_result = password_verify($password_test, $hash_test);
    echo "<p>¿La función password_verify funciona? " . ($verify_result ? "SÍ" : "NO") . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
