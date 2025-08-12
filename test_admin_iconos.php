<?php

/**
 * Archivo de prueba para verificar los iconos del administrador
 * Este archivo debe ejecutarse desde el navegador para probar las funcionalidades
 */

// Incluir archivos necesarios
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/permissions.php';

// Simular sesión de administrador (para pruebas)
session_start();
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_rol'] = ROL_ADMIN;
$_SESSION['usuario_nombre'] = 'Admin Sistema';

echo "<h1>Prueba de Iconos - Administrador</h1>";

echo "<h2>1. Menú de Navegación para Administrador</h2>";
echo "<h3>Menú Completo:</h3>";
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9; font-family: Arial, sans-serif;'>";
echo generar_menu_navegacion('dashboard');
echo "</div>";

echo "<h2>2. Iconos por Funcionalidad del Administrador</h2>";
$funcionalidades_admin = ['dashboard', 'usuarios', 'recursos', 'reservas', 'calendario', 'mantenimiento', 'inventario', 'incidencias', 'notificaciones', 'reportes'];
echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 20px 0;'>";
foreach ($funcionalidades_admin as $func) {
    $icono = obtener_icono_funcionalidad($func);
    echo "<div style='border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: white;'>";
    echo "<div style='font-size: 24px; text-align: center; margin-bottom: 5px;'>{$icono}</div>";
    echo "<div style='text-align: center; font-weight: bold;'>" . ucfirst($func) . "</div>";
    echo "</div>";
}
echo "</div>";

echo "<h2>3. Comparación de Menús</h2>";
echo "<h3>Menú para Administrador:</h3>";
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9; margin-bottom: 20px;'>";
echo generar_menu_navegacion('dashboard');
echo "</div>";

// Cambiar a profesor para comparar
$_SESSION['usuario_rol'] = ROL_PROFESOR;
echo "<h3>Menú para Profesor:</h3>";
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>";
echo generar_menu_navegacion('dashboard');
echo "</div>";

// Volver a administrador
$_SESSION['usuario_rol'] = ROL_ADMIN;

echo "<h2>4. Enlaces de Prueba del Administrador</h2>";
echo "<p><a href='public/admin/dashboard.php' target='_blank'>🏠 Dashboard del Administrador</a></p>";
echo "<p><a href='public/usuarios/listar.php' target='_blank'>👥 Listado de Usuarios</a></p>";
echo "<p><a href='public/recursos/listar.php' target='_blank'>📋 Listado de Recursos</a></p>";
echo "<p><a href='public/reservas/listar.php' target='_blank'>📅 Listado de Reservas</a></p>";
echo "<p><a href='public/reservas/calendario.php' target='_blank'>🗓️ Calendario</a></p>";
echo "<p><a href='public/mantenimiento/listar.php' target='_blank'>🔧 Mantenimientos</a></p>";
echo "<p><a href='public/inventario/listar.php' target='_blank'>📦 Inventario</a></p>";
echo "<p><a href='public/bitacora/gestionar.php' target='_blank'>⚠️ Gestionar Incidencias</a></p>";
echo "<p><a href='public/admin/notificaciones_incidencias.php' target='_blank'>🔔 Notificaciones</a></p>";
echo "<p><a href='public/reportes/reportes_dashboard.php' target='_blank'>📊 Reportes</a></p>";

echo "<h2>5. Información del Sistema</h2>";
echo "<p><strong>Rol actual:</strong> " . nombre_rol($_SESSION['usuario_rol']) . "</p>";
echo "<p><strong>Usuario:</strong> {$_SESSION['usuario_nombre']}</p>";
echo "<p><strong>ID de Usuario:</strong> {$_SESSION['usuario_id']}</p>";

echo "<h2>6. Verificación de Iconos</h2>";
echo "<p><strong>Iconos implementados:</strong></p>";
echo "<ul>";
foreach ($funcionalidades_admin as $func) {
    $icono = obtener_icono_funcionalidad($func);
    echo "<li><strong>" . ucfirst($func) . ":</strong> {$icono}</li>";
}
echo "</ul>";

echo "<hr>";
echo "<p><em>Este archivo es solo para pruebas. En producción, debe eliminarse.</em></p>";
?> 
 
 