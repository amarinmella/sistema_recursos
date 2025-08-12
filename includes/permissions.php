<?php

/**
 * Sistema de permisos y funcionalidades del profesor
 */

/**
 * Obtiene las funcionalidades disponibles para el profesor
 * 
 * @return array Array con las funcionalidades disponibles
 */
function obtener_funcionalidades_profesor()
{
    return [
        'dashboard' => [
            'nombre' => 'Dashboard',
            'descripcion' => 'Panel principal del profesor',
            'url' => '../profesor/dashboard.php',
            'icono' => '🏠'
        ],
        'recursos' => [
            'nombre' => 'Recursos',
            'descripcion' => 'Ver recursos disponibles',
            'url' => '../recursos/listar.php',
            'icono' => '📋'
        ],
        'reservas' => [
            'nombre' => 'Mis Reservas',
            'descripcion' => 'Gestionar mis reservas',
            'url' => '../reservas/listar.php',
            'icono' => '📅'
        ],
        'calendario' => [
            'nombre' => 'Calendario',
            'descripcion' => 'Ver calendario de reservas',
            'url' => '../reservas/calendario.php',
            'icono' => '🗓️'
        ],
        'incidencias' => [
            'nombre' => 'Gestionar Incidencias',
            'descripcion' => 'Gestionar incidencias reportadas',
            'url' => '../bitacora/gestionar.php',
            'icono' => '⚠️'
        ],
        'perfil' => [
            'nombre' => 'Mi Perfil',
            'descripcion' => 'Gestionar mi perfil personal',
            'url' => '../profesor/perfil.php',
            'icono' => '👤'
        ]
    ];
}

/**
 * Verifica si el profesor puede acceder a una funcionalidad específica
 * 
 * @param string $funcionalidad Nombre de la funcionalidad
 * @return bool True si puede acceder, false en caso contrario
 */
function profesor_puede_acceder($funcionalidad)
{
    if (!is_logged_in() || $_SESSION['usuario_rol'] != ROL_PROFESOR) {
        return false;
    }

    $funcionalidades_permitidas = [
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

    return in_array($funcionalidad, $funcionalidades_permitidas);
}

/**
 * Verifica si el profesor puede editar una reserva específica
 * 
 * @param int $id_reserva ID de la reserva
 * @return bool True si puede editar, false en caso contrario
 */
function profesor_puede_editar_reserva($id_reserva)
{
    if (!is_logged_in() || $_SESSION['usuario_rol'] != ROL_PROFESOR) {
        return false;
    }

    $db = Database::getInstance();
    $reserva = $db->getRow(
        "SELECT id_usuario FROM reservas WHERE id_reserva = ?",
        [$id_reserva]
    );

    return $reserva && $reserva['id_usuario'] == $_SESSION['usuario_id'];
}

/**
 * Verifica si el profesor puede eliminar una reserva específica
 * 
 * @param int $id_reserva ID de la reserva
 * @return bool True si puede eliminar, false en caso contrario
 */
function profesor_puede_eliminar_reserva($id_reserva)
{
    if (!is_logged_in() || $_SESSION['usuario_rol'] != ROL_PROFESOR) {
        return false;
    }

    $db = Database::getInstance();
    $reserva = $db->getRow(
        "SELECT id_usuario, estado FROM reservas WHERE id_reserva = ?",
        [$id_reserva]
    );

    // Solo puede eliminar sus propias reservas y solo si están pendientes
    return $reserva && 
           $reserva['id_usuario'] == $_SESSION['usuario_id'] && 
           $reserva['estado'] == 'pendiente';
}

/**
 * Verifica si el profesor puede editar una incidencia específica
 * 
 * @param int $id_incidencia ID de la incidencia
 * @return bool True si puede editar, false en caso contrario
 */
function profesor_puede_editar_incidencia($id_incidencia)
{
    if (!is_logged_in() || $_SESSION['usuario_rol'] != ROL_PROFESOR) {
        return false;
    }

    $db = Database::getInstance();
    $incidencia = $db->getRow(
        "SELECT id_usuario, fecha_reporte, estado FROM bitacora_incidencias WHERE id_incidencia = ?",
        [$id_incidencia]
    );

    if (!$incidencia || $incidencia['id_usuario'] != $_SESSION['usuario_id']) {
        return false;
    }

    // Solo puede editar sus propias incidencias y solo durante los primeros 5 minutos
    $tiempo_transcurrido = time() - strtotime($incidencia['fecha_reporte']);
    $limite_tiempo = 5 * 60; // 5 minutos en segundos

    return $tiempo_transcurrido <= $limite_tiempo && $incidencia['estado'] == 'reportada';
}

/**
 * Valida una acción específica en reservas
 * 
 * @param string $accion Acción a validar (crear, editar, eliminar)
 * @param array $datos Datos de la reserva
 * @return bool True si la acción es válida, false en caso contrario
 */
function validar_accion_reserva_profesor($accion, $datos)
{
    if (!is_logged_in() || $_SESSION['usuario_rol'] != ROL_PROFESOR) {
        return false;
    }

    switch ($accion) {
        case 'crear':
            // Validar que el recurso esté disponible
            if (empty($datos['id_recurso']) || empty($datos['fecha_inicio']) || empty($datos['fecha_fin'])) {
                return false;
            }
            return true;

        case 'editar':
            return profesor_puede_editar_reserva($datos['id_reserva'] ?? 0);

        case 'eliminar':
            return profesor_puede_eliminar_reserva($datos['id_reserva'] ?? 0);

        default:
            return false;
    }
}

/**
 * Valida una acción específica en incidencias
 * 
 * @param string $accion Acción a validar (crear, editar, eliminar)
 * @param array $datos Datos de la incidencia
 * @return bool True si la acción es válida, false en caso contrario
 */
function validar_accion_incidencia_profesor($accion, $datos)
{
    if (!is_logged_in() || $_SESSION['usuario_rol'] != ROL_PROFESOR) {
        return false;
    }

    switch ($accion) {
        case 'crear':
            // Validar campos obligatorios
            if (empty($datos['titulo']) || empty($datos['descripcion']) || empty($datos['id_recurso'])) {
                return false;
            }
            return true;

        case 'editar':
            return profesor_puede_editar_incidencia($datos['id_incidencia'] ?? 0);

        case 'eliminar':
            // Los profesores no pueden eliminar incidencias
            return false;

        default:
            return false;
    }
} 