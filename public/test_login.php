<?php
// Archivo de prueba para diagnóstico de login
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

echo "<h1>Prueba de Conexión y Login</h1>";

// Generar hash para la contraseña 'profesor123'
echo "<h3>Hash para contraseña de profesor:</h3>";
$profesor_hash = password_hash('profesor123', PASSWORD_DEFAULT);
echo "<p>Hash para 'profesor123': <code>" . $profesor_hash . "</code></p>";
echo "<p>Consulta SQL para crear profesor:</p>";
echo "<pre>INSERT INTO usuarios (nombre, apellido, email, contraseña, id_rol, activo, fecha_registro) 
VALUES ('Juan', 'Martínez', 'juan.martinez@demquintanormal.cl', '$profesor_hash', 3, 1, NOW());</pre>";


echo "<h3>Hash para estudiante123: </h3>";
echo "<p>" . password_hash('estudiante123', PASSWORD_DEFAULT) . "</p>";

// Probar conexión a la base de datos
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    if ($conn->connect_error) {
        echo "<p style='color:red'>Error de conexión: " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color:green'>Conexión a la base de datos establecida correctamente</p>";

        // Verificar existencia de la tabla usuarios
        $result = $conn->query("SHOW TABLES LIKE 'usuarios'");
        if ($result->num_rows > 0) {
            echo "<p style='color:green'>La tabla 'usuarios' existe</p>";

            // Verificar campos de la tabla
            $result = $conn->query("DESCRIBE usuarios");
            echo "<h3>Estructura de la tabla usuarios:</h3>";
            echo "<ul>";
            while ($row = $result->fetch_assoc()) {
                echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
            }
            echo "</ul>";

            // Verificar usuario administrador
            $result = $conn->query("SELECT id_usuario, nombre, apellido, email, id_rol, contraseña FROM usuarios WHERE id_rol = 1");
            echo "<h3>Usuarios administradores encontrados:</h3>";

            if ($result->num_rows > 0) {
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Contraseña (primeros 20 chars)</th></tr>";

                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id_usuario'] . "</td>";
                    echo "<td>" . $row['nombre'] . " " . $row['apellido'] . "</td>";
                    echo "<td>" . $row['email'] . "</td>";
                    echo "<td>" . $row['id_rol'] . "</td>";
                    echo "<td>" . substr($row['contraseña'], 0, 20) . "...</td>";
                    echo "</tr>";
                }
                echo "</table>";

                // Prueba de verificación de contraseña
                echo "<h3>Prueba de password_verify:</h3>";

                $result = $conn->query("SELECT contraseña FROM usuarios WHERE id_rol = 1 LIMIT 1");
                if ($row = $result->fetch_assoc()) {
                    $hash = $row['contraseña'];
                    $test_password = 'admin123';

                    echo "<p>Hash almacenado: " . $hash . "</p>";
                    echo "<p>Contraseña de prueba: " . $test_password . "</p>";

                    if (password_verify($test_password, $hash)) {
                        echo "<p style='color:green'>La función password_verify funciona correctamente</p>";
                    } else {
                        echo "<p style='color:red'>Error: La función password_verify falla</p>";

                        // Crear nuevo hash para comparación
                        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
                        echo "<p>Nuevo hash generado: " . $new_hash . "</p>";

                        // Mostrar código para actualizar la contraseña
                        echo "<h4>Ejecuta esta consulta SQL para corregir la contraseña:</h4>";
                        echo "<pre>UPDATE usuarios SET contraseña = '$new_hash' WHERE id_rol = 1;</pre>";
                    }
                } else {
                    echo "<p style='color:red'>No se pudo obtener la contraseña del administrador</p>";
                }
            } else {
                echo "<p style='color:red'>No se encontraron usuarios administradores</p>";

                // Mostrar código para crear un administrador
                $hash = password_hash('admin123', PASSWORD_DEFAULT);
                echo "<h4>Ejecuta esta consulta SQL para crear un administrador:</h4>";
                echo "<pre>INSERT INTO usuarios (nombre, apellido, email, contraseña, id_rol, activo) 
VALUES ('Admin', 'Sistema', 'admin@sistema.edu', '$hash', 1, 1);</pre>";
            }

            // Verificar tabla roles
            $result = $conn->query("SELECT * FROM roles WHERE id_rol = 1");
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                echo "<h3>Rol de administrador:</h3>";
                echo "<p>ID: " . $row['id_rol'] . " - Nombre: " . $row['nombre'] . "</p>";
            } else {
                echo "<p style='color:red'>No se encontró el rol de administrador</p>";
            }
        } else {
            echo "<p style='color:red'>La tabla 'usuarios' no existe</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
