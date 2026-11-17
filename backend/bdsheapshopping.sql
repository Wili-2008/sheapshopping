-- SHEAP SHOPPING - Aguiper
-- Estructura limpia: sin usuarios, productos, categorias ni pedidos precargados.
-- MySQL 8 / MariaDB reciente
-- No elimina datos existentes. Para comenzar de cero, importar en una base vacia.
CREATE DATABASE IF NOT EXISTS bdsheapshopping CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bdsheapshopping;

CREATE TABLE IF NOT EXISTS usuarios (
 id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL,
 correo VARCHAR(150) NOT NULL UNIQUE,
 contrasena VARCHAR(255) NOT NULL COMMENT 'Hash de password_hash() en PHP',
 rol ENUM('admin','cliente') NOT NULL DEFAULT 'cliente',
 fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categorias (
 id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
 id_producto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_categoria INT UNSIGNED NOT NULL,
 nombre VARCHAR(120) NOT NULL,
 descripcion TEXT NULL,
 precio DECIMAL(10,2) NOT NULL,
 imagen VARCHAR(255) NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 CONSTRAINT fk_productos_categoria FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria),
 CONSTRAINT chk_precio CHECK (precio >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventario (
 id_inventario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_producto INT UNSIGNED NOT NULL,
 talla VARCHAR(12) NOT NULL,
 stock INT UNSIGNED NOT NULL DEFAULT 0,
 UNIQUE KEY uq_producto_talla (id_producto,talla),
 CONSTRAINT fk_inventario_producto FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos (
 id_pedido INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_usuario INT UNSIGNED NOT NULL,
 fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
 total DECIMAL(10,2) NOT NULL DEFAULT 0,
 CONSTRAINT fk_pedidos_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
 CONSTRAINT chk_total CHECK (total >= 0),
 INDEX idx_pedidos_fecha_estado (fecha,estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_pedidos (
 id_detalle INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_pedido INT UNSIGNED NOT NULL,
 id_inventario INT UNSIGNED NOT NULL,
 cantidad INT UNSIGNED NOT NULL,
 precio_unitario DECIMAL(10,2) NOT NULL,
 UNIQUE KEY uq_pedido_variante (id_pedido,id_inventario),
 CONSTRAINT fk_detalle_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido),
 CONSTRAINT fk_detalle_inventario FOREIGN KEY (id_inventario) REFERENCES inventario(id_inventario),
 CONSTRAINT chk_cantidad CHECK (cantidad > 0),
 CONSTRAINT chk_precio_detalle CHECK (precio_unitario >= 0)
) ENGINE=InnoDB;
