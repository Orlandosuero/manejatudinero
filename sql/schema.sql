-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS maneja_tu_dinero;
USE maneja_tu_dinero;

-- Tabla para definir la naturaleza de las cuentas
CREATE TABLE clasificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL, -- Ej: Activo, Pasivo, Ingreso, Gasto
    naturaleza ENUM('Deudora', 'Acreedora') NOT NULL
);

-- Tabla de Cuentas (Tu catálogo financiero)
CREATE TABLE cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clasificacion_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL, -- Ej: Efectivo, Tarjeta de Crédito, Salario
    balance DECIMAL(15,2) DEFAULT 0.00,
    FOREIGN KEY (clasificacion_id) REFERENCES clasificaciones(id)
);

-- Tabla de Transacciones (El libro diario)
CREATE TABLE transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_origen_id INT NOT NULL,
    cuenta_destino_id INT NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    fecha DATE NOT NULL,
    descripcion TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cuenta_origen_id) REFERENCES cuentas(id),
    FOREIGN KEY (cuenta_destino_id) REFERENCES cuentas(id)
);