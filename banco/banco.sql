-- Create patient_schedule database if it doesn't exist and use it
CREATE DATABASE IF NOT EXISTS patient_schedule;
USE patient_schedule;


-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('administrador', 'agendamento', 'telefonista', 'supervisor') NOT NULL DEFAULT 'telefonista',
    active TINYINT NOT NULL DEFAULT 1
);

-- Medical Specialties table
CREATE TABLE IF NOT EXISTS medical_specialties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    specialty_name VARCHAR(100) NOT NULL,
    active TINYINT NOT NULL DEFAULT 1
);

-- Professionals table
CREATE TABLE IF NOT EXISTS professionals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    license_number INT(30) NOT NULL, -- Número da licença do profissional
    license_type VARCHAR(30) NOT NULL, -- CRM, COREN...
    active TINYINT NOT NULL DEFAULT 1,
    UNIQUE (license_number, license_type) -- Garante que não existam tipo de licença com o mesmo número
);

-- Professional-Specialties table
CREATE TABLE IF NOT EXISTS professional_specialties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professional_id INT NOT NULL, -- Referência ao especialista
    specialty_id INT NOT NULL, -- Referência à especialidade
    active TINYINT NOT NULL DEFAULT 1,
    CONSTRAINT fk_professional FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_specialty FOREIGN KEY (specialty_id) REFERENCES medical_specialties(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE (professional_id, specialty_id) -- Garante que não existam associações duplicadas
);

-- Patients table
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    registration_number INT(50) NOT NULL, -- Número de registro do paciente (SIRESP)
    medical_specialty_id INT, -- id da tabela medical_specialties (FK)
    professional_id INT, -- id da tabela professionals (FK)
    exam_date DATETIME, -- Data do exame
    contact_datetime DATETIME, -- Data e hora do contato do paciente
    cancel_reason VARCHAR(500), -- Motivo do cancelamento
    cancellation_datetime DATETIME, -- Data e hora do cancelamento
    exchange_date DATETIME, -- Data do agendamento
    registering_user_id INT NOT NULL, -- Usuário que registrou o paciente
    situation ENUM('agendado', 'agendamento em andamento', 'cancelado', 'cancelamento em andamento', 'pendente', 'revisao', 'sem demanda') NOT NULL DEFAULT 'pendente',
    comment VARCHAR(500), -- Comentários adicionais
    CONSTRAINT fk_medical_specialty_id FOREIGN KEY (medical_specialty_id) REFERENCES medical_specialties(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_patient_professional FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE SET NULL ON UPDATE CASCADE
);





-- Add user as administrator
INSERT INTO users (name, username, password, user_type) VALUES ('Lucas Henrique Marques','lmarques', '049d228d1fbcbb11c894573dc19ce843', 'administrador');
