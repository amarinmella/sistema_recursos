<?php

/**
 * Notificaciones para el Profesor
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar que el usuario esté logueado y sea profesor
require_login();
if ($_SESSION['usuario_rol'] != ROL_PROFESOR) {
    $_SESSION['error'] = "No tienes permisos para acceder a esta página";
    redirect('../index.php');
    exit;
}

// Obtener instancia de la base de datos
$db = Database::getInstance();

// Definir variables para filtrado
$filtro_leido = isset($_GET['leido']) ? $_GET['leido'] : '';
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener notificaciones del usuario
$condiciones = ["id_usuario = ?"];
$params = [$_SESSION['usuario_id']];

if ($filtro_leido !== '') {
    $condiciones[] = "leido = ?";
    $params[] = $filtro_leido;
}

// Construir cláusula WHERE
$where = " WHERE " . implode(" AND ", $condiciones);

// Consulta para obtener total de registros
$sql_total = "SELECT COUNT(*) as total FROM notificaciones $where";
$resultado_total = $db->getRow($sql_total, $params);
$total_registros = $resultado_total ? $resultado_total['total'] : 0;

// Calcular total de páginas
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Asegurar que la página actual sea válida
if ($pagina_actual < 1) {
    $pagina_actual = 1;
} elseif ($pag