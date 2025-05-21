-- Create database
CREATE DATABASE IF NOT EXISTS cheque_system;
USE cheque_system;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Banks table
CREATE TABLE banks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Invoices table
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cheques table
CREATE TABLE cheques (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cheque_number VARCHAR(50) UNIQUE NOT NULL,
    beneficiary VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    details TEXT,
    bank_id INT,
    invoice_id INT,
    status ENUM('creado', 'devuelto', 'depositado', 'anulado', 'modificado') DEFAULT 'creado',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_id) REFERENCES banks(id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Cheque History table for tracking changes
CREATE TABLE cheque_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cheque_id INT NOT NULL,
    previous_status VARCHAR(50),
    new_status VARCHAR(50),
    changed_by INT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cheque_id) REFERENCES cheques(id),
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
