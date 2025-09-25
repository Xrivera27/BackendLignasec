-- Base de datos para LIGNASEC
CREATE DATABASE IF NOT EXISTS lignasec_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE lignasec_db;

-- Tabla para contactos del formulario principal y popup
CREATE TABLE contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NULL,
    email VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NULL,
    nombre_empresa VARCHAR(255) NULL,
    direccion TEXT NULL,
    mensaje TEXT NULL,
    tipo_contacto ENUM('popup', 'pagina_contacto') NOT NULL DEFAULT 'pagina_contacto',
    estado ENUM('pendiente', 'contactado', 'resuelto') NOT NULL DEFAULT 'pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_cliente VARCHAR(45) NULL,
    user_agent TEXT NULL,
    INDEX idx_email (email),
    INDEX idx_tipo_contacto (tipo_contacto),
    INDEX idx_estado (estado),
    INDEX idx_fecha_creacion (fecha_creacion)
);

-- Tabla para suscripciones del newsletter
CREATE TABLE suscriptores_newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    fecha_suscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_baja TIMESTAMP NULL,
    ip_cliente VARCHAR(45) NULL,
    user_agent TEXT NULL,
    token_baja VARCHAR(64) NULL,
    INDEX idx_email (email),
    INDEX idx_estado (estado),
    INDEX idx_fecha_suscripcion (fecha_suscripcion)
);

-- Tabla para configuraciones del sitio
CREATE TABLE configuracion_sitio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave_config VARCHAR(100) NOT NULL UNIQUE,
    valor_config TEXT NOT NULL,
    descripcion TEXT NULL,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar configuraciones basicas
INSERT INTO configuracion_sitio (clave_config, valor_config, descripcion) VALUES 
('nombre_sitio', 'LIGNASEC', 'Nombre del sitio web'),
('telefono_empresa', '+504 2443-6618', 'Telefono principal de la empresa'),
('email_empresa', 'ventas@lignasec.com', 'Email principal de la empresa'),
('direccion_empresa', 'Col. El Sauce, La Ceiba', 'Direccion de la empresa');

-- Tabla para logs del sistema
CREATE TABLE logs_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_log ENUM('contacto', 'newsletter', 'error', 'seguridad') NOT NULL,
    mensaje TEXT NOT NULL,
    ip_cliente VARCHAR(45) NULL,
    user_agent TEXT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo_log (tipo_log),
    INDEX idx_fecha_creacion (fecha_creacion)
);

-- Tabla para administradores
CREATE TABLE administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(200) NOT NULL,
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    ultimo_acceso TIMESTAMP NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar usuario admin por defecto (password: admin123)
INSERT INTO administradores (usuario, email, password_hash, nombre_completo) VALUES 
('admin', 'admin@lignasec.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador LIGNASEC');