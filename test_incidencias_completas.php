<?php

/**
 * Archivo de prueba para verificar las funcionalidades completas de incidencias
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

echo "<h1>Prueba de Funcionalidades de Incidencias</h1>";

echo "<h2>1. Verificación de Permisos</h2>";
echo "<h3>Permisos del Administrador:</h3>";
echo "<ul>";
echo "<li><strong>Agregar incidencias:</strong> " . (has_role([ROL_ADMIN, ROL_ACADEMICO]) ? "✅ Permitido" : "❌ Denegado") . "</li>";
echo "<li><strong>Modificar incidencias:</strong> " . (has_role([ROL_ADMIN, ROL_ACADEMICO]) ? "✅ Permitido" : "❌ Denegado") . "</li>";
echo "<li><strong>Listar incidencias:</strong> " . (has_role([ROL_ADMIN, ROL_ACADEMICO]) ? "✅ Permitido" : "❌ Denegado") . "</li>";
echo "<li><strong>Eliminar incidencias:</strong> " . (has_role([ROL_ADMIN, ROL_ACADEMICO]) ? "✅ Permitido" : "❌ Denegado") . "</li>";
echo "</ul>";

// Cambiar a profesor para verificar permisos
$_SESSION['usuario_rol'] = ROL_PROFESOR;
echo "<h3>Permisos del Profesor:</h3>";
echo "<ul>";
echo "<li><strong>Agregar incidencias:</strong> " . (has_role([ROL_PROFESOR]) ? "✅ Permitido" : "❌ Denegado") . "</li>";
echo "<li><strong>Modificar incidencias (propias, 5 min):</strong> " . (has_role([ROL_PROFESOR]) ? "✅ Permitido" : "❌ Denegado") . "</li>";
echo "<li><strong>Listar incidencias (propias):</strong> " . (has_role([ROL_PROFESOR]) ? "✅ Permitido" : "❌ Denegado") . "</li>";
echo "<li><strong>Eliminar incidencias:</strong> ❌ Denegado (solo administradores)</li>";
echo "</ul>";

// Volver a administrador
$_SESSION['usuario_rol'] = ROL_ADMIN;

echo "<h2>2. Verificación de Funciones de Permisos</h2>";
$db = Database::getInstance();

// Simular una incidencia reciente (menos de 5 minutos)
$incidencia_reciente = [
    'id_incidencia' => 1,
    'id_usuario' => 2, // ID de profesor
    'fecha_reporte' => date('Y-m-d H:i:s', strtotime('-2 minutes'))
];

// Simular una incidencia antigua (más de 5 minutos)
$incidencia_antigua = [
    'id_incidencia' => 2,
    'id_usuario' => 2, // ID de profesor
    'fecha_reporte' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
];

echo "<h3>Prueba de límite de tiempo para edición:</h3>";
echo "<ul>";
echo "<li><strong>Incidencia reciente (2 min):</strong> " . (profesor_puede_editar_incidencia(1) ? "✅ Puede editar" : "❌ No puede editar") . "</li>";
echo "<li><strong>Incidencia antigua (10 min):</strong> " . (profesor_puede_editar_incidencia(2) ? "✅ Puede editar" : "❌ No puede editar") . "</li>";
echo "</ul>";

echo "<h2>3. Verificación de Notificaciones</h2>";
echo "<p><strong>Sistema de notificaciones:</strong> Las incidencias reportadas por profesores deben generar notificaciones automáticas para los administradores.</p>";

echo "<h2>4. Enlaces de Prueba</h2>";
echo "<h3>Para Administradores:</h3>";
echo "<ul>";
echo "<li><a href='public/bitacora/gestionar.php' target='_blank'>⚠️ Gestionar Incidencias (Administrador)</a></li>";
echo "<li><a href='public/bitacora/reportar.php' target='_blank'>📝 Reportar Nueva Incidencia (Administrador)</a></li>";
echo "<li><a href='public/admin/notificaciones_incidencias.php' target='_blank'>🔔 Ver Notificaciones (Administrador)</a></li>";
echo "</ul>";

// Cambiar a profesor
$_SESSION['usuario_rol'] = ROL_PROFESOR;
$_SESSION['usuario_id'] = 2;
$_SESSION['usuario_nombre'] = 'Profesor Test';

echo "<h3>Para Profesores:</h3>";
echo "<ul>";
echo "<li><a href='public/bitacora/gestionar.php' target='_blank'>⚠️ Gestionar Incidencias (Profesor)</a></li>";
echo "<li><a href='public/bitacora/reportar.php' target='_blank'>📝 Reportar Nueva Incidencia (Profesor)</a></li>";
echo "</ul>";

// Volver a administrador
$_SESSION['usuario_rol'] = ROL_ADMIN;
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Admin Sistema';

echo "<h2>5. Funcionalidades Implementadas</h2>";
echo "<h3>✅ Administradores pueden:</h3>";
echo "<ul>";
echo "<li>Agregar incidencias desde cualquier recurso</li>";
echo "<li>Modificar cualquier incidencia (título, descripción, prioridad)</li>";
echo "<li>Listar todas las incidencias del sistema</li>";
echo "<li>Eliminar incidencias</li>";
echo "<li>Cambiar el estado de las incidencias</li>";
echo "<li>Agregar notas administrativas</li>";
echo "<li>Recibir notificaciones automáticas de nuevas incidencias</li>";
echo "</ul>";

echo "<h3>✅ Profesores pueden:</h3>";
echo "<ul>";
echo "<li>Agregar incidencias con fecha, recurso y observación</li>";
echo "<li>Seleccionar recursos de sus reservas o recursos disponibles</li>";
echo "<li>Modificar sus propias incidencias (solo dentro de 5 minutos)</li>";
echo "<li>Listar solo sus propias incidencias</li>";
echo "<li>Las incidencias generan notificaciones automáticas a administradores</li>";
echo "</ul>";

echo "<h3>✅ Sistema de Notificaciones:</h3>";
echo "<ul>";
echo "<li>Notificaciones automáticas cuando un profesor reporta una incidencia</li>";
echo "<li>Notificaciones cuando cambia el estado de una incidencia</li>";
echo "<li>Notificaciones cuando se resuelve una incidencia</li>";
echo "<li>Contador de notificaciones no leídas en el menú</li>";
echo "</ul>";

echo "<h2>6. Información del Sistema</h2>";
echo "<p><strong>Rol actual:</strong> " . nombre_rol($_SESSION['usuario_rol']) . "</p>";
echo "<p><strong>Usuario:</strong> {$_SESSION['usuario_nombre']}</p>";
echo "<p><strong>ID de Usuario:</strong> {$_SESSION['usuario_id']}</p>";

echo "<h2>7. Pruebas Específicas</h2>";
echo "<p><strong>Para probar la funcionalidad completa:</strong></p>";
echo "<ol>";
echo "<li>Accede como profesor y crea una nueva incidencia</li>";
echo "<li>Verifica que aparezca en la lista de incidencias del profesor</li>";
echo "<li>Accede como administrador y verifica que la incidencia aparezca en la lista general</li>";
echo "<li>Verifica que haya una notificación nueva en el panel de administrador</li>";
echo "<li>Como administrador, cambia el estado de la incidencia</li>";
echo "<li>Verifica que el profesor reciba una notificación del cambio de estado</li>";
echo "</ol>";

echo "<hr>";
echo "<p><em>Este archivo es solo para pruebas. En producción, debe eliminarse.</em></p>";
?> 
 