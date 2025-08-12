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

/**
 * Genera y almacena un token CSRF en la sesión
 *
 * @return string El token generado
 */
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida un token CSRF
 *
 * @param string $token El token enviado desde el formulario
 * @return bool True si el token es válido, false en caso contrario
 */
function validate_csrf_token($token)
{
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    // Una vez usado, el token se debe regenerar para evitar ataques de tipo "replay"
    unset($_SESSION['csrf_token']);
    return true;
}

/**
 * Genera un campo de input HTML para el token CSRF
 *
 * @return string El campo de input HTML
 */
function csrf_input()
{
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Obtiene el icono para una funcionalidad específica
 * 
 * @param string $funcionalidad Nombre de la funcionalidad
 * @return string Icono emoji correspondiente
 */
function obtener_icono_funcionalidad($funcionalidad)
{
    $iconos = [
        'dashboard' => '🏠',
        'usuarios' => '👥',
        'recursos' => '📋',
        'reservas' => '📅',
        'calendario' => '🗓️',
        'mantenimiento' => '🔧',
        'inventario' => '📦',
        'incidencias' => '⚠️',
        'notificaciones' => '🔔',
        'reportes' => '📊',
        'perfil' => '👤'
    ];

    return $iconos[$funcionalidad] ?? '📄';
}

/**
 * Genera el menú de navegación según el rol del usuario
 * 
 * @param string $pagina_activa Página actualmente activa
 * @return string HTML del menú de navegación
 */
function generar_menu_navegacion($pagina_activa = '')
{
    if (!is_logged_in()) {
        return '';
    }

    $rol = $_SESSION['usuario_rol'];
    $menu_html = '';

    // Menú para administradores
    if ($rol == ROL_ADMIN) {
        $menu_items = [
            'dashboard' => [
                'url' => '../admin/dashboard.php',
                'texto' => '🏠 Dashboard',
                'icono' => '🏠'
            ],
            'usuarios' => [
                'url' => '../usuarios/listar.php',
                'texto' => '👥 Usuarios',
                'icono' => '👥'
            ],
            'recursos' => [
                'url' => '../recursos/listar.php',
                'texto' => '📋 Recursos',
                'icono' => '📋'
            ],
            'reservas' => [
                'url' => '../reservas/listar.php',
                'texto' => '📅 Reservas',
                'icono' => '📅'
            ],
            'calendario' => [
                'url' => '../reservas/calendario.php',
                'texto' => '🗓️ Calendario',
                'icono' => '🗓️'
            ],
            'mantenimiento' => [
                'url' => '../mantenimiento/listar.php',
                'texto' => '🔧 Mantenimiento',
                'icono' => '🔧'
            ],
            'inventario' => [
                'url' => '../inventario/listar.php',
                'texto' => '📦 Inventario',
                'icono' => '📦'
            ],
            'incidencias' => [
                'url' => '../bitacora/gestionar.php',
                'texto' => '⚠️ Gestionar Incidencias',
                'icono' => '⚠️'
            ],
            'notificaciones' => [
                'url' => '../admin/notificaciones_incidencias.php',
                'texto' => '🔔 Notificaciones',
                'icono' => '🔔'
            ],
            'reportes' => [
                'url' => '../reportes/reportes_dashboard.php',
                'texto' => '📊 Reportes',
                'icono' => '📊'
            ]
        ];
    }
    // Menú para profesores
    elseif ($rol == ROL_PROFESOR) {
        $menu_items = [
            'dashboard' => [
                'url' => '../profesor/dashboard.php',
                'texto' => '🏠 Dashboard',
                'icono' => '🏠'
            ],
            'recursos' => [
                'url' => '../recursos/listar.php',
                'texto' => '📋 Recursos',
                'icono' => '📋'
            ],
            'reservas' => [
                'url' => '../reservas/listar.php',
                'texto' => '📅 Mis Reservas',
                'icono' => '📅'
            ],
            'calendario' => [
                'url' => '../reservas/calendario.php',
                'texto' => '🗓️ Calendario',
                'icono' => '🗓️'
            ],
            'incidencias' => [
                'url' => '../bitacora/gestionar.php',
                'texto' => '⚠️ Gestionar Incidencias',
                'icono' => '⚠️'
            ],
            'perfil' => [
                'url' => '../profesor/perfil.php',
                'texto' => '👤 Mi Perfil',
                'icono' => '👤'
            ]
        ];
    }
    // Menú para académicos
    elseif ($rol == ROL_ACADEMICO) {
        $menu_items = [
            'dashboard' => [
                'url' => '../profesor/dashboard.php',
                'texto' => '🏠 Dashboard',
                'icono' => '🏠'
            ],
            'recursos' => [
                'url' => '../recursos/listar.php',
                'texto' => '📋 Recursos',
                'icono' => '📋'
            ],
            'reservas' => [
                'url' => '../reservas/listar.php',
                'texto' => '📅 Mis Reservas',
                'icono' => '📅'
            ],
            'calendario' => [
                'url' => '../reservas/calendario.php',
                'texto' => '🗓️ Calendario',
                'icono' => '🗓️'
            ],
            'incidencias' => [
                'url' => '../bitacora/gestionar.php',
                'texto' => '⚠️ Gestionar Incidencias',
                'icono' => '⚠️'
            ],
            'perfil' => [
                'url' => '../profesor/perfil.php',
                'texto' => '👤 Mi Perfil',
                'icono' => '👤'
            ]
        ];
    }
    // Menú para estudiantes
    elseif ($rol == ROL_ESTUDIANTE) {
        $menu_items = [
            'dashboard' => [
                'url' => '../profesor/dashboard.php',
                'texto' => '🏠 Dashboard',
                'icono' => '🏠'
            ],
            'recursos' => [
                'url' => '../recursos/listar.php',
                'texto' => '📋 Recursos',
                'icono' => '📋'
            ],
            'reservas' => [
                'url' => '../reservas/listar.php',
                'texto' => '📅 Mis Reservas',
                'icono' => '📅'
            ],
            'calendario' => [
                'url' => '../reservas/calendario.php',
                'texto' => '🗓️ Calendario',
                'icono' => '🗓️'
            ],
            'incidencias' => [
                'url' => '../bitacora/reportar.php',
                'texto' => '⚠️ Reportar Incidencia',
                'icono' => '⚠️'
            ],
            'perfil' => [
                'url' => '../profesor/perfil.php',
                'texto' => '👤 Mi Perfil',
                'icono' => '👤'
            ]
        ];
    }
    else {
        return '';
    }

    // Generar HTML del menú
    foreach ($menu_items as $key => $item) {
        $active_class = ($pagina_activa == $key) ? 'active' : '';
        $menu_html .= '<a href="' . $item['url'] . '" class="nav-item ' . $active_class . '">';
        $menu_html .= '<span class="nav-icon">' . $item['icono'] . '</span>';
        $menu_html .= '<span class="nav-text">' . $item['texto'] . '</span>';
        $menu_html .= '</a>';
    }

    return $menu_html;
}
