-- ============================================================
-- Integrar Salud - Database Schema (FIXED)
-- ============================================================

CREATE DATABASE IF NOT EXISTS integrar_salud CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE integrar_salud;

-- ─── Users ───
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('medico', 'recepcion', 'admin') NOT NULL DEFAULT 'recepcion',
    must_change_password TINYINT(1) DEFAULT 1,
    doctor_id INT UNSIGNED NULL,
    staff_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Doctors ───
CREATE TABLE IF NOT EXISTS doctors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    specialty VARCHAR(100) NOT NULL,
    license VARCHAR(50) DEFAULT NULL,
    color VARCHAR(20) NOT NULL DEFAULT 'indigo',
    phone VARCHAR(30) DEFAULT NULL,
    remuneration DECIMAL(12,2) DEFAULT NULL,
    remuneration_type ENUM('fijo', 'porcentaje') DEFAULT 'fijo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Doctor Schedules ───
CREATE TABLE IF NOT EXISTS doctor_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_hour TINYINT NOT NULL,
    end_hour TINYINT NOT NULL,
    UNIQUE KEY uk_doctor_day (doctor_id, day_of_week),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Patients ───
CREATE TABLE IF NOT EXISTS patients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nhc VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    dni VARCHAR(15) DEFAULT NULL,
    birth_date DATE DEFAULT NULL,
    gender ENUM('femenino', 'masculino', 'otro', 'prefiero_no_decir') DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    emergency_contact VARCHAR(100) DEFAULT NULL,
    coverage VARCHAR(100) DEFAULT 'Particular',
    coverage_number VARCHAR(50) DEFAULT NULL,
    plan VARCHAR(50) DEFAULT NULL,
    allergies TEXT,
    diagnosis TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_dni (dni),
    INDEX idx_nhc (nhc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Appointments ───
CREATE TABLE IF NOT EXISTS appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(100) NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration DECIMAL(3,1) NOT NULL DEFAULT 1.0,
    type VARCHAR(50) DEFAULT NULL,
    attendance ENUM('agendado', 'confirmado', 'en_espera', 'en_curso', 'finalizado', 'ausente') DEFAULT 'agendado',
    payment_status ENUM('pendiente', 'senado', 'pagado') DEFAULT 'pendiente',
    is_paid TINYINT(1) DEFAULT 0,
    payment_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_method VARCHAR(30) DEFAULT NULL,
    is_block TINYINT(1) DEFAULT 0,
    notes TEXT,
    wait_ticket VARCHAR(10) DEFAULT NULL,
    referrer VARCHAR(100) DEFAULT NULL,
    color_class VARCHAR(50) DEFAULT NULL,
    afip_cae VARCHAR(20) DEFAULT NULL,
    afip_cae_vence DATE DEFAULT NULL,
    afip_nro INT UNSIGNED DEFAULT NULL,
    afip_punto_venta INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (appointment_date),
    INDEX idx_doctor_date (doctor_id, appointment_date),
    INDEX idx_attendance (attendance),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── SOAP History ───
CREATE TABLE IF NOT EXISTS soap_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    linked_to_id INT UNSIGNED DEFAULT NULL,
    is_aclaracion TINYINT(1) DEFAULT 0,
    subjective TEXT,
    objective TEXT,
    analysis TEXT,
    plan TEXT,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient (patient_id),
    INDEX idx_linked (linked_to_id),
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Medications ───
CREATE TABLE IF NOT EXISTS medications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    drug VARCHAR(100) NOT NULL,
    dose VARCHAR(50) NOT NULL,
    frequency VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient (patient_id),
    INDEX idx_active (patient_id, active),
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Admin Staff ───
CREATE TABLE IF NOT EXISTS admin_staff (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL,
    shift ENUM('Mañana', 'Tarde', 'Doble Turno') DEFAULT 'Mañana',
    phone VARCHAR(30) DEFAULT NULL,
    remuneration DECIMAL(12,2) DEFAULT NULL,
    remuneration_type ENUM('fijo', 'porcentaje') DEFAULT 'fijo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Transactions ───
CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('Ingreso', 'Egreso') NOT NULL,
    concept VARCHAR(255) NOT NULL,
    method VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    receipt_number VARCHAR(50) DEFAULT NULL,
    notes TEXT,
    transaction_date DATETIME NOT NULL,
    afip_cae VARCHAR(20) DEFAULT NULL,
    afip_cae_vence DATE DEFAULT NULL,
    afip_nro INT UNSIGNED DEFAULT NULL,
    afip_punto_venta INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_date (transaction_date),
    INDEX idx_type_date (type, transaction_date),
    
    doctor_id INT UNSIGNED DEFAULT NULL,
    staff_id INT UNSIGNED DEFAULT NULL,
    patient_id INT UNSIGNED DEFAULT NULL,
    
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES admin_staff(id) ON DELETE SET NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Refresh Tokens ───
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(500) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token(191)),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Dashboard Notes ───
CREATE TABLE IF NOT EXISTS dashboard_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL, -- NULL si es compartida
    content TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Patient Files ───
CREATE TABLE IF NOT EXISTS patient_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INT UNSIGNED,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient (patient_id),
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── AFIP Config ───
CREATE TABLE IF NOT EXISTS afip_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cuit VARCHAR(20) DEFAULT NULL,
    punto_venta INT UNSIGNED DEFAULT 1,
    environment ENUM('test', 'prod') DEFAULT 'test',
    cert_file VARCHAR(255) DEFAULT NULL,
    key_file VARCHAR(255) DEFAULT NULL,
    tax_condition ENUM('monotributo', 'ri') DEFAULT 'monotributo',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar fila inicial de config si no existe
INSERT IGNORE INTO afip_config (id) VALUES (1);

-- ═══════════════════════════════════════════════════════════
-- DATOS SEMILLA
-- ═══════════════════════════════════════════════════════════

-- ─── Usuario Admin (password: admin123) ───
INSERT INTO users (name, email, password_hash, role) VALUES
('Administrador', 'admin@integrarsalud.com', '$2y$10$fvFA/4bMLrvdi5cwCxGKD.yKwCFG2g.sDV1H3XUG9Rpvpi9iOJlFy', 'admin');
