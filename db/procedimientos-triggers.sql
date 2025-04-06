-- Procedimientos Almacenados y Triggers para Sistema de Gestión de Recursos
-- Estos deben agregarse manualmente después de crear las tablas básicas

-- 1. PROCEDIMIENTO: Verificar disponibilidad de recursos
-- Uso: CALL verificar_disponibilidad(1, '2023-01-15 10:00:00', '2023-01-15 12:00:00');

DELIMITER //
CREATE PROCEDURE verificar_disponibilidad(IN p_id_recurso INT, IN p_fecha_inicio DATETIME, IN p_fecha_fin DATETIME)
BEGIN
    SELECT COUNT(*) AS reservas_conflicto
    FROM reservas
    WHERE id_recurso = p_id_recurso
      AND estado IN ('pendiente', 'confirmada')
      AND (
          (p_fecha_inicio BETWEEN fecha_inicio AND fecha_fin)
          OR (p_fecha_fin BETWEEN fecha_inicio AND fecha_fin)
          OR (fecha_inicio BETWEEN p_fecha_inicio AND p_fecha_fin)
      );
END //
DELIMITER ;

-- 2. TRIGGER: Actualizar estado del recurso cuando entra en mantenimiento

DELIMITER //
CREATE TRIGGER actualizar_estado_recurso_mantenimiento
AFTER INSERT ON mantenimiento
FOR EACH ROW
BEGIN
    UPDATE recursos SET estado = 'mantenimiento', disponible = 0
    WHERE id_recurso = NEW.id_recurso AND NEW.estado IN ('pendiente', 'en progreso');
END //
DELIMITER ;

-- 3. TRIGGER: Actualizar estado del recurso cuando finaliza el mantenimiento

DELIMITER //
CREATE TRIGGER actualizar_estado_recurso_fin_mantenimiento
AFTER UPDATE ON mantenimiento
FOR EACH ROW
BEGIN
    IF NEW.estado = 'completado' AND OLD.estado != 'completado' THEN
        UPDATE recursos SET estado = 'disponible', disponible = 1
        WHERE id_recurso = NEW.id_recurso;
    END IF;
END //
DELIMITER ;

-- 4. PROCEDIMIENTO: Cancelar reservas de un recurso
-- Uso: CALL cancelar_reservas_recurso(1, 'El recurso no está disponible');

DELIMITER //
CREATE PROCEDURE cancelar_reservas_recurso(IN p_id_recurso INT, IN p_motivo VARCHAR(255))
BEGIN
    DECLARE v_reserva_id INT;
    DECLARE v_usuario_id INT;
    DECLARE v_mensaje TEXT;
    DECLARE v_recurso_nombre VARCHAR(100);
    DECLARE done INT DEFAULT FALSE;
    
    -- Obtener nombre del recurso
    SELECT nombre INTO v_recurso_nombre FROM recursos WHERE id_recurso = p_id_recurso;
    
    -- Cursor para reservas pendientes y confirmadas
    DECLARE cur CURSOR FOR 
        SELECT id_reserva, id_usuario
        FROM reservas
        WHERE id_recurso = p_id_recurso AND estado IN ('pendiente', 'confirmada');
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Iniciar transacción
    START TRANSACTION;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_reserva_id, v_usuario_id;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Actualizar estado de la reserva
        UPDATE reservas 
        SET estado = 'cancelada' 
        WHERE id_reserva = v_reserva_id;
        
        -- Crear mensaje de notificación
        SET v_mensaje = CONCAT('Su reserva para el recurso "', v_recurso_nombre, '" ha sido cancelada. Motivo: ', p_motivo);
        
        -- Insertar notificación
        INSERT INTO notificaciones (id_reserva, id_usuario, mensaje, leido)
        VALUES (v_reserva_id, v_usuario_id, v_mensaje, 0);
    END LOOP;
    
    CLOSE cur;
    
    -- Commit transacción
    COMMIT;
END //
DELIMITER ;

-- 5. TRIGGER: Registrar acción de creación de reserva

DELIMITER //
CREATE TRIGGER registrar_creacion_reserva
AFTER INSERT ON reservas
FOR EACH ROW
BEGIN
    INSERT INTO log_acciones (id_usuario, accion, entidad, id_entidad, ip, fecha)
    VALUES (NEW.id_usuario, 'crear', 'reservas', NEW.id_reserva, NULL, NOW());
END //
DELIMITER ;

-- 6. TRIGGER: Registrar cambio de estado de reserva

DELIMITER //
CREATE TRIGGER registrar_cambio_estado_reserva
AFTER UPDATE ON reservas
FOR EACH ROW
BEGIN
    IF OLD.estado != NEW.estado THEN
        INSERT INTO log_acciones (id_usuario, accion, entidad, id_entidad, detalles, fecha)
        VALUES (
            NEW.id_usuario, 
            'actualizar', 
            'reservas', 
            NEW.id_reserva, 
            CONCAT('Cambio de estado: ', OLD.estado, ' -> ', NEW.estado),
            NOW()
        );
    END IF;
END //
DELIMITER ;
