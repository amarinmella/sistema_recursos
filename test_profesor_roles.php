<?php

/**
 * Archivo de prueba para verificar el sistema de roles del profesor
 * Este archivo debe ejecutarse desde el navegador para probar las funcionalidades
 */

// Incluir archivos necesarios
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/permissions.php';

// Simular sesión de profesor (para pruebas)
session_start();
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_rol'] = ROL_PROFESOR;
$_SESSION['usuario_nombre'] = 'Profesor Test';

echo "<h1>Prueba del Sistema de Roles - Profesor</h1>";

// Probar funciones de permisos
echo "<h2>1. Prueba de Funciones de Permisos</h2>";

echo "<h3>Funcionalidades Disponibles:</h3>";
$funcionalidades = obtener_funcionalidades_profesor();
foreach ($funcionalidades as $key => $funcionalidad) {
    echo "<p><strong>{$funcionalidad['nombre']}:</strong> {$funcionalidad['descripcion']}</p>";
}

echo "<h3>Verificación de Accesos:</h3>";
$accesos = [
    'recursos_lectura',
    'reservas_crear',
    'reservas_editar',
    'reservas_eliminar',
    'reservas_listar',
    'calendario_ver',
    'incidencias_crear',
    'incidencias_editar',
    'incidencias_eliminar',
    'incidencias_listar'
];

foreach ($accesos as $acceso) {
    $puede = profesor_puede_acceder($acceso) ? '✅ SÍ' : '❌ NO';
    echo "<p><strong>{$acceso}:</strong> {$puede}</p>";
}

echo "<h2>2. Prueba de Menú de Navegación</h2>";
echo "<h3>Menú para Profesor:</h3>";
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9; font-family: Arial, sans-serif;'>";
echo generar_menu_navegacion('dashboard');
echo "</div>";

echo "<h3>Iconos por Funcionalidad:</h3>";
$funcionalidades = ['dashboard', 'usuarios', 'recursos', 'reservas', 'calendario', 'mantenimiento', 'inventario', 'incidencias', 'notificaciones', 'reportes', 'perfil'];
echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 20px 0;'>";
foreach ($funcionalidades as $func) {
    $icono = obtener_icono_funcionalidad($func);
    echo "<div style='border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: white;'>";
    echo "<div style='font-size: 24px; text-align: center; margin-bottom: 5px;'>{$icono}</div>";
    echo "<div style='text-align: center; font-weight: bold;'>" . ucfirst($func) . "</div>";
    echo "</div>";
}
echo "</div>";

echo "<h2>3. Prueba de Validaciones</h2>";

// Probar validación de reserva
echo "<h3>Validación de Reserva:</h3>";
$validacion_reserva = validar_accion_reserva_profesor('crear', [
    'id_recurso' => 1,
    'fecha_inicio' => '2024-01-15 10:00:00',
    'fecha_fin' => '2024-01-15 12:00:00'
]);
echo "<p><strong>Crear Reserva:</strong> " . ($validacion_reserva['valido'] ? '✅ Válido' : '❌ Inválido') . "</p>";
if (!$validacion_reserva['valido']) {
    echo "<p>Mensaje: {$validacion_reserva['mensaje']}</p>";
}

// Probar validación de incidencia
echo "<h3>Validación de Incidencia:</h3>";
$validacion_incidencia = validar_accion_incidencia_profesor('crear');
echo "<p><strong>Crear Incidencia:</strong> " . ($validacion_incidencia['valido'] ? '✅ Válido' : '❌ Inválido') . "</p>";

echo "<h2>4. Enlaces de Prueba</h2>";
echo "<p><a href='public/profesor/dashboard.php' target='_blank'>Dashboard del Profesor</a></p>";
echo "<p><a href='public/recursos/listar.php' target='_blank'>Listado de Recursos</a></p>";
echo "<p><a href='public/reservas/listar.php' target='_blank'>Mis Reservas</a></p>";
echo "<p><a href='public/reservas/calendario.php' target='_blank'>Calendario</a></p>";
echo "<p><a href='public/bitacora/gestionar.php' target='_blank'>Gestionar Incidencias</a></p>";

echo "<h2>5. Información del Sistema</h2>";
echo "<p><strong>Rol actual:</strong> " . nombre_rol($_SESSION['usuario_rol']) . "</p>";
echo "<p><strong>Usuario:</strong> {$_SESSION['usuario_nombre']}</p>";
echo "<p><strong>ID de Usuario:</strong> {$_SESSION['usuario_id']}</p>";

echo "<hr>";
echo "<p><em>Este archivo es solo para pruebas. En producción, debe eliminarse.</em></p>";
?> 
 
 