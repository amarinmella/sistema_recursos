-- Estructura para la bitácora de incidencias
-- Este archivo debe ejecutarse después de la estructura principal de la base de datos

-- Tabla para almacenar las incidencias reportadas por los usuarios
CREATE TABLE IF NOT EXISTS bitacora_incidencias (
    id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT NOT NULL,
    id_usuario INT NOT NULL,
    id_recurso INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    prioridad ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
    estado ENUM('reportada', 'en_revision', 'en_proceso', 'resuelta', 'cerrada') DEFAULT 'reportada',
    fecha_reporte TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion TIMESTAMP NULL,
    notas_administrador TEXT NULL,
    id_administrador_resuelve INT NULL,
    FOREIGN KEY (id_reserva) REFERENCES reservas(id_reserva) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso) ON DELETE CASCADE,
    FOREIGN KEY (id_administrador_resuelve) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

-- Tabla para las notificaciones de incidencias
CREATE TABLE IF NOT EXISTS notificaciones_incidencias (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_incidencia INT NOT NULL,
    id_usuario_destino INT NOT NULL,
    tipo ENUM('nueva_incidencia', 'actualizacion_incidencia', 'incidencia_resuelta') NOT NULL,
    mensaje TEXT NOT NULL,
    leida BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_incidencia) REFERENCES bitacora_incidencias(id_incidencia) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_destino) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);

-- Índices para mejorar el rendimiento
CREATE INDEX idx_incidencias_recurso ON bitacora_incidencias(id_recurso);
CREATE INDEX idx_incidencias_usuario ON bitacora_incidencias(id_usuario);
CREATE INDEX idx_incidencias_estado ON bitacora_incidencias(estado);
CREATE INDEX idx_incidencias_fecha ON bitacora_incidencias(fecha_reporte);
CREATE INDEX idx_notificaciones_usuario ON notificaciones_incidencias(id_usuario_destino);
CREATE INDEX idx_notificaciones_leida ON notificaciones_incidencias(leida);

-- Trigger para crear notificación automática cuando se reporta una incidencia
DELIMITER //
CREATE TRIGGER trigger_notificar_incidencia
AFTER INSERT ON bitacora_incidencias
FOR EACH ROW
BEGIN
    -- Notificar a todos los administradores
    INSERT INTO notificaciones_incidencias (id_incidencia, id_usuario_destino, tipo, mensaje)
    SELECT 
        NEW.id_incidencia,
        u.id_usuario,
        'nueva_incidencia',
        CONCAT('Nueva incidencia reportada: ', NEW.titulo, ' - Recurso: ', 
               (SELECT nombre FROM recursos WHERE id_recurso = NEW.id_recurso))
    FROM usuarios u
    WHERE u.id_rol = 1; -- ROL_ADMIN = 1
END//
DELIMITER ;

-- Trigger para actualizar notificaciones cuando cambia el estado de una incidencia
DELIMITER //
CREATE TRIGGER trigger_notificar_actualizacion_incidencia
AFTER UPDATE ON bitacora_incidencias
FOR EACH ROW
BEGIN
    IF NEW.estado != OLD.estado THEN
        -- Notificar al usuario que reportó la incidencia
        INSERT INTO notificaciones_incidencias (id_incidencia, id_usuario_destino, tipo, mensaje)
        VALUES (
            NEW.id_incidencia,
            NEW.id_usuario,
            'actualizacion_incidencia',
            CONCAT('Tu incidencia "', NEW.titulo, '" ha cambiado de estado a: ', NEW.estado)
        );
        
        -- Si se resolvió, notificar también
        IF NEW.estado = 'resuelta' OR NEW.estado = 'cerrada' THEN
            INSERT INTO notificaciones_incidencias (id_incidencia, id_usuario_destino, tipo, mensaje)
            VALUES (
                NEW.id_incidencia,
                NEW.id_usuario,
                'incidencia_resuelta',
                CONCAT('Tu incidencia "', NEW.titulo, '" ha sido resuelta')
            );
        END IF;
    END IF;
END//
DELIMITER ; 