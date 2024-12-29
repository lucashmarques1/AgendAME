-- Create patient_schedule database if it doesn't exist and use it
CREATE DATABASE IF NOT EXISTS patient_schedule;
USE patient_schedule;


-- User table
CREATE TABLE IF NOT EXISTS user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('administrador', 'agendamento', 'telefonista', 'supervisor') NOT NULL DEFAULT 'telefonista',
    active TINYINT NOT NULL DEFAULT 1
);

-- Medical Specialty table
CREATE TABLE IF NOT EXISTS medical_specialty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    specialty_name VARCHAR(100) NOT NULL,
    active TINYINT NOT NULL DEFAULT 1
);

-- Professional table
CREATE TABLE IF NOT EXISTS professional (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL, -- Nome do especialista
    license_number VARCHAR(50) NOT NULL UNIQUE, -- Número do CRM ou outra licença
    license_type VARCHAR(50) NOT NULL, -- Tipo de licença
    active TINYINT NOT NULL DEFAULT 1
);

-- Professional-Specialty table
CREATE TABLE IF NOT EXISTS professional_specialty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professional_id INT NOT NULL, -- Referência ao especialista
    specialty_id INT NOT NULL, -- Referência à especialidade
    active TINYINT NOT NULL DEFAULT 1,
    CONSTRAINT fk_professional FOREIGN KEY (professional_id) REFERENCES professional(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_specialty FOREIGN KEY (specialty_id) REFERENCES medical_specialty(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE (professional_id, specialty_id) -- Garante que não existam associações duplicadas
);

-- Patient table
CREATE TABLE IF NOT EXISTS patient (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL, -- Nome do paciente
    registration_number VARCHAR(50) NOT NULL, -- Número de registro do paciente (CROSS)
    medical_specialty INT, -- Liga ao id da tabela medical_specialty
    professional_id INT, -- Liga ao id da tabela profissional
    exam_date DATETIME, -- Data do exame
    contact_datetime DATETIME, -- Data e hora do contato do paciente
    cancel_reason VARCHAR(500), -- Motivo do cancelamento
    cancellation_datetime DATETIME, -- Data e hora do cancelamento
    exchange_date DATETIME, -- Data de reagendamento
    registering_user_id INT NOT NULL, -- Usuário que registrou o paciente
    situation ENUM('agendado', 'agendamento em andamento', 'cancelado', 'cancelamento em andamento', 'pendente', 'revisao', 'sem demanda') NOT NULL DEFAULT 'pendente',
    comment VARCHAR(500), -- Comentários adicionais
    CONSTRAINT fk_medical_specialty FOREIGN KEY (medical_specialty) REFERENCES medical_specialty(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_patient_professional FOREIGN KEY (professional_id) REFERENCES professional(id) ON DELETE SET NULL ON UPDATE CASCADE
);





-- Add user as administrator
INSERT INTO user (name, username, password, user_type) VALUES ('Lucas Henrique Marques','lmarques', '049d228d1fbcbb11c894573dc19ce843', 'administrador');
