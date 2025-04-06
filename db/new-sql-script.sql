-- Script SQL Compatible con MariaDB 10.4.28 para Sistema de Gestión de Recursos
-- Creado para XAMPP (phpMyAdmin 5.2.1)

-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS sistema_recursos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_recursos;

-- Tabla de Roles
CREATE TABLE IF NOT EXISTS roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    permisos TEXT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de Usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    contraseña VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_login DATETIME,
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de Tipos de Recursos
CREATE TABLE IF NOT EXISTS tipos_recursos (
    id_tipo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de Recursos
CREATE TABLE IF NOT EXISTS recursos (
    id_recurso INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    id_tipo INT NOT NULL,
    estado ENUM('disponible', 'mantenimiento', 'dañado', 'baja') DEFAULT 'disponible',
    ubicacion VARCHAR(100),
    descripcion TEXT,
    disponible TINYINT(1) DEFAULT 1,
    fecha_alta DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_tipo) REFERENCES tipos_recursos(id_tipo) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de Reservas
CREATE TABLE IF NOT EXISTS reservas (
    id_reserva INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_recurso INT NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    descripcion TEXT,
    estado ENUM('pendiente', 'confirmada', 'cancelada', 'completada') DEFAULT 'pendiente',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de Notificaciones
CREATE TABLE IF NOT EXISTS notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT,
    id_usuario INT NOT NULL,
    mensaje TEXT NOT NULL,
    leido TINYINT(1) DEFAULT 0,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_reserva) REFERENCES reservas(id_reserva) ON DELETE SET NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de Mantenimiento
CREATE TABLE IF NOT EXISTS mantenimiento (
    id_mantenimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_recurso INT NOT NULL,
    id_usuario INT NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME,
    estado ENUM('pendiente', 'en progreso', 'completado') DEFAULT 'pendiente',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla para registrar acciones (auditoría)
CREATE TABLE IF NOT EXISTS log_acciones (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    accion VARCHAR(50) NOT NULL,
    entidad VARCHAR(50) NOT NULL,
    id_entidad INT,
    ip VARCHAR(45),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    detalles TEXT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla para tokens de recuperación de contraseña
CREATE TABLE IF NOT EXISTS tokens_recuperacion (
    id_token INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    token VARCHAR(100) NOT NULL,
    expira DATETIME NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Índices adicionales para mejorar el rendimiento
ALTER TABLE usuarios ADD INDEX idx_usuarios_email (email);
ALTER TABLE recursos ADD INDEX idx_recursos_estado (estado);
ALTER TABLE recursos ADD INDEX idx_recursos_tipo (id_tipo);
ALTER TABLE reservas ADD INDEX idx_reserva_fechas (fecha_inicio, fecha_fin);
ALTER TABLE reservas ADD INDEX idx_reserva_recurso (id_recurso);
ALTER TABLE notificaciones ADD INDEX idx_notificaciones_usuario (id_usuario);
ALTER TABLE mantenimiento ADD INDEX idx_mantenimiento_fechas (fecha_inicio, fecha_fin);

-- Insertar datos iniciales para roles
INSERT INTO roles (nombre, descripcion, permisos) VALUES
('administrador', 'Administrador del sistema con acceso total', '{"admin":true,"recursos":true,"usuarios":true,"reservas":true,"reportes":true}'),
('academico', 'Administrador académico con permisos de gestión', '{"admin":false,"recursos":true,"usuarios":true,"reservas":true,"reportes":true}'),
('profesor', 'Profesor con acceso a reservas', '{"admin":false,"recursos":false,"usuarios":false,"reservas":true,"reportes":false}'),
('estudiante', 'Estudiante con acceso limitado', '{"admin":false,"recursos":false,"usuarios":false,"reservas":true,"reportes":false}');

-- Insertar tipos de recursos básicos
INSERT INTO tipos_recursos (nombre, descripcion) VALUES
('aula', 'Sala de clases estándar'),
('laboratorio', 'Laboratorio equipado para prácticas'),
('proyector', 'Equipo de proyección portátil'),
('notebook', 'Computadora portátil'),
('sala_reuniones', 'Sala para reuniones y eventos');

-- Crear usuario administrador inicial (contraseña: admin123)
INSERT INTO usuarios (nombre, apellido, email, contraseña, id_rol) VALUES
('Admin', 'Sistema', 'admin@sistema.edu', '$2y$10$rNVcB7qUMq6xb2Br5Rb8b.XrtLbD0holtxIEqZznzCsKtUgbUVRnS', 1);

-- A continuación se incluyen los procedimientos almacenados y triggers como comentarios
-- Puedes añadirlos manualmente a través de phpMyAdmin después de importar este script

/*
-- Procedimiento para verificar disponibilidad de recursos
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

-- Trigger para actualizar el estado del recurso cuando entra en mantenimiento
DELIMITER //
CREATE TRIGGER actualizar_estado_recurso_mantenimiento
AFTER INSERT ON mantenimiento
FOR EACH ROW
BEGIN
    UPDATE recursos SET estado = 'mantenimiento', disponible = 0
    WHERE id_recurso = NEW.id_recurso AND NEW.estado IN ('pendiente', 'en progreso');
END //
DELIMITER ;

-- Trigger para actualizar el estado del recurso cuando finaliza el mantenimiento
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
*/
