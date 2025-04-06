<?php

/**
 * Funciones auxiliares para el sistema
 */

/**
 * Redirige a una URL
 * 
 * @param string $url URL a la que redirigir
 * @return void
 */
function redirect($url)
{
    header("Location: $url");
    exit();
}

/**
 * Verifica si el usuario ha iniciado sesión
 * 
 * @return bool true si ha iniciado sesión, false en caso contrario
 */
function is_logged_in()
{
    return isset($_SESSION['usuario_id']);
}

/**
 * Verifica si el usuario tiene un rol específico
 * 
 * @param int|array $roles Rol o roles permitidos
 * @return bool true si el usuario tiene el rol, false en caso contrario
 */
function has_role($roles)
{
    if (!is_logged_in()) {
        return false;
    }

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    return in_array($_SESSION['usuario_rol'], $roles);
}

/**
 * Verifica si el usuario está autorizado, si no, redirige al login
 * 
 * @return void
 */
function require_login()
{
    if (!is_logged_in()) {
        $_SESSION['error'] = "Debes iniciar sesión para acceder a esta página";
        redirect('index.php');
    }
}

/**
 * Sanitiza una entrada para prevenir XSS
 * 
 * @param string $input Texto a sanitizar
 * @return string Texto sanitizado
 */
function sanitize($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Formatea una fecha en formato legible
 * 
 * @param string $date Fecha en formato Y-m-d H:i:s
 * @param bool $with_time Incluir hora en el formato
 * @return string Fecha formateada
 */
function format_date($date, $with_time = false)
{
    if (empty($date)) {
        return 'N/A';
    }

    $format = $with_time ? 'd/m/Y H:i' : 'd/m/Y';
    return date($format, strtotime($date));
}

/**
 * Devuelve el nombre del rol según su ID
 * 
 * @param int $id_rol ID del rol
 * @return string Nombre del rol
 */
function nombre_rol($id_rol)
{
    $roles = [
        ROL_ADMIN => 'Administrador',
        ROL_ACADEMICO => 'Académico',
        ROL_PROFESOR => 'Profesor',
        ROL_ESTUDIANTE => 'Estudiante'
    ];

    return $roles[$id_rol] ?? 'Desconocido';
}

/**
 * Obtiene el estado de un recurso en formato legible
 * 
 * @param string $estado Estado del recurso
 * @return string Estado formateado con HTML
 */
function estado_recurso($estado)
{
    $estados = [
        'disponible' => '<span class="badge badge-success">Disponible</span>',
        'mantenimiento' => '<span class="badge badge-warning">En Mantenimiento</span>',
        'dañado' => '<span class="badge badge-danger">Dañado</span>',
        'baja' => '<span class="badge badge-secondary">De Baja</span>'
    ];

    return $estados[$estado] ?? '<span class="badge badge-info">' . ucfirst($estado) . '</span>';
}

/**
 * Obtiene el estado de una reserva en formato legible
 * 
 * @param string $estado Estado de la reserva
 * @return string Estado formateado con HTML
 */
function estado_reserva($estado)
{
    $estados = [
        'pendiente' => '<span class="badge badge-warning">Pendiente</span>',
        'confirmada' => '<span class="badge badge-success">Confirmada</span>',
        'cancelada' => '<span class="badge badge-danger">Cancelada</span>',
        'completada' => '<span class="badge badge-info">Completada</span>'
    ];

    return $estados[$estado] ?? '<span class="badge badge-secondary">' . ucfirst($estado) . '</span>';
}

/**
 * Genera un token aleatorio
 * 
 * @param int $length Longitud del token
 * @return string Token generado
 */
function generate_token($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}
