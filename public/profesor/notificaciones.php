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
} elseif ($pagina_actual > $total_paginas && $total_paginas > 0) {
    $pagina_actual = $total_paginas;
}

// Recalcular offset en caso de ajuste
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Consulta para obtener las notificaciones con paginación
$sql = "SELECT * FROM notificaciones $where ORDER BY fecha DESC LIMIT ? OFFSET ?";
$params_pag = array_merge($params, [$registros_por_pagina, $offset]);
$notificaciones = $db->getRows($sql, $params_pag);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - Profesor</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .notification {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .notification.unread {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a,
        .pagination span {
            margin: 0 5px;
            text-decoration: none;
        }

        .filters {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="dashboard">
        <!-- Sidebar de navegación -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"></div>
                <div>Sistema de Gestión</div>
            </div>
            <div class="sidebar-nav">
                <a href="../admin/dashboard.php" class="nav-item">Dashboard</a>
                <!-- Otras opciones del menú -->
                <a href="notificaciones.php" class="nav-item active">Notificaciones</a>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="content">
            <div class="top-bar">
                <h1>Notificaciones</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="../logout.php" class="logout-btn">Cerrar sesión</a>
                </div>
            </div>

            <?php
            // Mostrar mensajes de éxito o error
            if (isset($_SESSION['success'])) {
                echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
                unset($_SESSION['success']);
            }
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']);
            }
            ?>

            <!-- Filtros para notificaciones -->
            <div class="filters">
                <form action="notificaciones.php" method="GET">
                    <label for="leido">Filtrar por estado:</label>
                    <select name="leido" id="leido">
                        <option value="">Todos</option>
                        <option value="0" <?php echo ($filtro_leido === '0') ? 'selected' : ''; ?>>No leídas</option>
                        <option value="1" <?php echo ($filtro_leido === '1') ? 'selected' : ''; ?>>Leídas</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </form>
            </div>

            <!-- Listado de notificaciones -->
            <div class="notifications-list">
                <?php if ($notificaciones && count($notificaciones) > 0): ?>
                    <?php foreach ($notificaciones as $notificacion): ?>
                        <div class="notification <?php echo ($notificacion['leido'] == 0) ? 'unread' : ''; ?>">
                            <p><?php echo htmlspecialchars($notificacion['mensaje']); ?></p>
                            <small><?php echo date('d/m/Y H:i', strtotime($notificacion['fecha'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No hay notificaciones para mostrar.</p>
                <?php endif; ?>
            </div>

            <!-- Paginación -->
            <div class="pagination">
                <?php if ($pagina_actual > 1): ?>
                    <a href="?pagina=<?php echo $pagina_actual - 1; ?>&leido=<?php echo urlencode($filtro_leido); ?>">&laquo; Anterior</a>
                <?php else: ?>
                    <span>&laquo; Anterior</span>
                <?php endif; ?>

                Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>

                <?php if ($pagina_actual < $total_paginas): ?>
                    <a href="?pagina=<?php echo $pagina_actual + 1; ?>&leido=<?php echo urlencode($filtro_leido); ?>">Siguiente &raquo;</a>
                <?php else: ?>
                    <span>Siguiente &raquo;</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>