-- Hospital Appointment Management System Database Schema
-- This file contains the complete SQL schema for the hospital management system
-- Updated with prescription management, medicine database, and pathology tests

-- Create database
CREATE DATABASE IF NOT EXISTS hospital_management;
USE hospital_management;

-- Users table for all system users (Admin, Doctor, Patient, Manager)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor', 'patient', 'manager') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Doctors table with additional medical information
CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    specialty VARCHAR(100) NOT NULL,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    experience_years INT DEFAULT 0,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    bio TEXT,
    photo_path VARCHAR(500),
    photo_filename VARCHAR(255),
    photo_size INT,
    photo_type VARCHAR(100),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    rejected_by INT NULL,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Patients table with additional health information
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    emergency_contact VARCHAR(20),
    medical_history TEXT,
    nationality ENUM('local', 'international') DEFAULT 'local',
    passport_number VARCHAR(50) NULL,
    passport_expiry DATE NULL,
    passport_path VARCHAR(500) NULL,
    passport_filename VARCHAR(255) NULL,
    photo_path VARCHAR(500) NULL,
    photo_filename VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Doctor schedules table
CREATE TABLE doctor_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration INT DEFAULT 30, -- in minutes
    break_time INT DEFAULT 15, -- in minutes
    is_available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Appointments table
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card', 'debit_card', 'mobile_banking', 'cash', 'bank_transfer') NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_by INT, -- manager who verified
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Booking tokens table
CREATE TABLE booking_tokens (
    id VARCHAR(20) PRIMARY KEY,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    token_type ENUM('qr', 'alphanumeric', 'numeric') DEFAULT 'qr',
    status ENUM('active', 'used', 'expired') DEFAULT 'active',
    issued_by INT NOT NULL, -- manager who issued
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Certificates table for doctor qualifications
CREATE TABLE certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    certificate_name VARCHAR(200) NOT NULL,
    issuing_authority VARCHAR(200) NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NULL,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    file_size INT,
    file_type VARCHAR(100),
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT NULL, -- admin who verified
    verified_at TIMESTAMP NULL,
    rejected_by INT NULL,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Messages table for doctor-patient communication
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    appointment_id INT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'image', 'file') DEFAULT 'text',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Audit logs table for security and compliance
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Activity logs table for user activity tracking
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    activity VARCHAR(200) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Managers table for manager-specific information
CREATE TABLE managers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    department VARCHAR(100),
    employee_id VARCHAR(50),
    position VARCHAR(100),
    hire_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Reports table for generated reports
CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    generated_by INT NOT NULL,
    report_type ENUM('daily', 'weekly', 'monthly', 'custom') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    report_data JSON,
    file_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample data
INSERT INTO users (name, email, phone, password_hash, role, status) VALUES
('Admin User', 'admin@hams.com', '123-456-7890', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin', 'active'),
('Dr. Smith', 'doctor@hospital.com', '123-456-7891', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'doctor', 'active'),
('John Doe', 'patient@hospital.com', '123-456-7892', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'patient', 'active'),
('Manager User', 'manager@hospital.com', '123-456-7893', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'manager', 'active');

INSERT INTO doctors (user_id, specialty, license_number, experience_years, consultation_fee, bio) VALUES
(2, 'Cardiology', 'MD123456', 10, 150.00, 'Experienced cardiologist specializing in heart diseases and treatments.');

INSERT INTO patients (user_id, date_of_birth, gender, address, emergency_contact, medical_history) VALUES
(3, '1990-05-15', 'male', '123 Main St, City, State', '987-654-3210', 'No significant medical history.');

INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, break_time) VALUES
(1, 'monday', '09:00:00', '17:00:00', 30, 15),
(1, 'tuesday', '09:00:00', '17:00:00', 30, 15),
(1, 'wednesday', '09:00:00', '17:00:00', 30, 15),
(1, 'thursday', '09:00:00', '17:00:00', 30, 15),
(1, 'friday', '09:00:00', '17:00:00', 30, 15);

INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, reason) VALUES
(1, 1, '2024-01-15', '10:00:00', 'confirmed', 'Regular checkup'),
(1, 1, '2024-01-20', '14:00:00', 'pending', 'Follow-up consultation');

INSERT INTO payments (appointment_id, patient_id, amount, payment_method, status, transaction_id) VALUES
(1, 1, 150.00, 'credit_card', 'completed', 'TXN123456789'),
(2, 1, 150.00, 'mobile_banking', 'pending', NULL);

INSERT INTO certificates (doctor_id, certificate_name, issuing_authority, issue_date, expiry_date, status) VALUES
(1, 'Medical Degree', 'Medical University', '2015-06-15', NULL, 'verified'),
(1, 'Cardiology Specialization', 'Medical Board', '2018-03-20', '2028-03-20', 'verified');

INSERT INTO messages (sender_id, receiver_id, appointment_id, message) VALUES
(3, 2, 1, 'Thank you for the consultation. When should I schedule my next visit?'),
(2, 3, 1, 'You can schedule your next visit in 6 months. Please take the prescribed medication regularly.');

INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES
(1, 'CREATE', 'users', 2, '192.168.1.1'),
(1, 'UPDATE', 'appointments', 1, '192.168.1.1'),
(4, 'VERIFY', 'payments', 1, '192.168.1.2');

INSERT INTO activity_logs (user_id, activity, details, ip_address) VALUES
(1, 'LOGIN', 'Admin logged in', '192.168.1.1'),
(2, 'SCHEDULE_UPDATE', 'Updated availability schedule', '192.168.1.3'),
(3, 'APPOINTMENT_BOOK', 'Booked appointment with Dr. Smith', '192.168.1.4'),
(4, 'PAYMENT_VERIFY', 'Verified payment for appointment #1', '192.168.1.2');

-- Medicine list table
CREATE TABLE medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    generic_name VARCHAR(255),
    manufacturer VARCHAR(255),
    dosage_form VARCHAR(100), -- tablet, syrup, injection, etc.
    strength VARCHAR(100), -- 500mg, 10ml, etc.
    category VARCHAR(100), -- antibiotic, painkiller, etc.
    description TEXT,
    side_effects TEXT,
    contraindications TEXT,
    price DECIMAL(10,2),
    stock_quantity INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Pathology tests list table
CREATE TABLE pathology_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_name VARCHAR(255) NOT NULL,
    test_code VARCHAR(50) UNIQUE,
    category VARCHAR(100), -- blood, urine, imaging, etc.
    description TEXT,
    preparation_instructions TEXT,
    normal_values TEXT,
    price DECIMAL(10,2),
    duration_hours INT, -- how long test takes
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Prescriptions table
CREATE TABLE prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    appointment_id INT,
    prescription_number VARCHAR(50) UNIQUE NOT NULL,
    diagnosis TEXT,
    symptoms TEXT,
    notes TEXT,
    follow_up_date DATE,
    prescription_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    pdf_path VARCHAR(500),
    pdf_filename VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Prescription medicines table
CREATE TABLE prescription_medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id INT NOT NULL,
    medicine_id INT NOT NULL,
    medicine_name VARCHAR(255) NOT NULL,
    dosage VARCHAR(100), -- 500mg, 1 tablet, etc.
    frequency VARCHAR(100), -- twice daily, once daily, etc.
    duration VARCHAR(100), -- 7 days, 2 weeks, etc.
    instructions TEXT,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- Prescription pathology tests table
CREATE TABLE prescription_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id INT NOT NULL,
    test_id INT NOT NULL,
    test_name VARCHAR(255) NOT NULL,
    instructions TEXT,
    urgency ENUM('routine', 'urgent', 'stat') DEFAULT 'routine',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES pathology_tests(id) ON DELETE CASCADE
);

-- Insert sample medicines
INSERT INTO medicines (name, generic_name, manufacturer, dosage_form, strength, category, description, price, stock_quantity) VALUES
('Paracetamol', 'Acetaminophen', 'PharmaCorp', 'Tablet', '500mg', 'Painkiller', 'Used for pain relief and fever reduction', 5.50, 1000),
('Amoxicillin', 'Amoxicillin', 'MediPharm', 'Capsule', '250mg', 'Antibiotic', 'Broad-spectrum antibiotic for bacterial infections', 12.00, 500),
('Omeprazole', 'Omeprazole', 'HealthMed', 'Capsule', '20mg', 'Proton Pump Inhibitor', 'Used for treating acid reflux and stomach ulcers', 8.75, 750),
('Metformin', 'Metformin', 'DiabCare', 'Tablet', '500mg', 'Antidiabetic', 'Used for managing type 2 diabetes', 15.25, 300),
('Lisinopril', 'Lisinopril', 'CardioMed', 'Tablet', '10mg', 'ACE Inhibitor', 'Used for treating high blood pressure', 18.50, 400),
('Atorvastatin', 'Atorvastatin', 'LipidCare', 'Tablet', '20mg', 'Statin', 'Used for lowering cholesterol levels', 22.00, 250),
('Ibuprofen', 'Ibuprofen', 'PainRelief Inc', 'Tablet', '400mg', 'NSAID', 'Anti-inflammatory pain reliever', 7.25, 800),
('Cetirizine', 'Cetirizine', 'AllergyMed', 'Tablet', '10mg', 'Antihistamine', 'Used for treating allergies and hay fever', 6.50, 600);

-- Insert sample pathology tests
INSERT INTO pathology_tests (test_name, test_code, category, description, preparation_instructions, normal_values, price, duration_hours) VALUES
('Complete Blood Count', 'CBC', 'Blood', 'Complete blood count including RBC, WBC, platelets', 'Fasting not required', 'RBC: 4.5-5.5M/μL, WBC: 4.5-11K/μL', 25.00, 2),
('Blood Sugar (Fasting)', 'FBS', 'Blood', 'Fasting blood glucose level', '12-hour fasting required', '70-100 mg/dL', 15.00, 1),
('Lipid Profile', 'LIPID', 'Blood', 'Cholesterol and triglyceride levels', '12-hour fasting required', 'Total Chol: <200 mg/dL', 35.00, 3),
('Thyroid Function Test', 'TFT', 'Blood', 'TSH, T3, T4 levels', 'Fasting not required', 'TSH: 0.4-4.0 mIU/L', 45.00, 4),
('Urine Analysis', 'UA', 'Urine', 'Complete urine examination', 'Mid-stream clean catch', 'pH: 4.5-8.0, Protein: Negative', 20.00, 1),
('ECG', 'ECG', 'Cardiology', 'Electrocardiogram', 'No preparation required', 'Normal sinus rhythm', 30.00, 0.5),
('Chest X-Ray', 'CXR', 'Imaging', 'Chest radiograph', 'Remove jewelry, wear gown', 'Clear lung fields', 50.00, 1),
('MRI Brain', 'MRI-BRAIN', 'Imaging', 'Magnetic resonance imaging of brain', 'Remove all metal objects', 'Normal brain structure', 200.00, 2);

-- Medical documents table for patient uploaded documents
CREATE TABLE medical_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    document_type ENUM('lab_report', 'xray', 'prescription', 'medical_history', 'insurance', 'other') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    document_date DATE,
    notes TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Prescription templates table for common prescriptions
CREATE TABLE prescription_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    template_name VARCHAR(255) NOT NULL,
    diagnosis TEXT NOT NULL,
    symptoms TEXT,
    notes TEXT,
    medicines JSON, -- Store medicine details as JSON
    tests JSON, -- Store test details as JSON
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Medicine categories table for better organization
CREATE TABLE medicine_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Test categories table for better organization
CREATE TABLE test_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert medicine categories
INSERT INTO medicine_categories (category_name, description) VALUES
('Painkiller', 'Medicines for pain relief'),
('Antibiotic', 'Medicines to treat bacterial infections'),
('Antidiabetic', 'Medicines for diabetes management'),
('Cardiovascular', 'Medicines for heart and blood vessel conditions'),
('Gastrointestinal', 'Medicines for digestive system'),
('Respiratory', 'Medicines for breathing and lung conditions'),
('Neurological', 'Medicines for nervous system conditions'),
('Dermatological', 'Medicines for skin conditions'),
('Antihistamine', 'Medicines for allergies'),
('NSAID', 'Non-steroidal anti-inflammatory drugs');

-- Insert test categories
INSERT INTO test_categories (category_name, description) VALUES
('Blood', 'Blood tests and analysis'),
('Urine', 'Urine tests and analysis'),
('Imaging', 'X-ray, MRI, CT scans'),
('Cardiology', 'Heart-related tests'),
('Pathology', 'Tissue and cell analysis'),
('Microbiology', 'Infection and bacteria tests'),
('Biochemistry', 'Chemical analysis of body fluids'),
('Hematology', 'Blood cell analysis'),
('Immunology', 'Immune system tests'),
('Endocrinology', 'Hormone tests');

-- Add indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_doctors_license ON doctors(license_number);
CREATE INDEX idx_doctors_status ON doctors(status);
CREATE INDEX idx_patients_user_id ON patients(user_id);
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_prescriptions_number ON prescriptions(prescription_number);
CREATE INDEX idx_prescriptions_date ON prescriptions(prescription_date);
CREATE INDEX idx_medicines_name ON medicines(name);
CREATE INDEX idx_medicines_category ON medicines(category);
CREATE INDEX idx_tests_name ON pathology_tests(test_name);
CREATE INDEX idx_tests_category ON pathology_tests(category);

-- Add triggers for automatic updates
DELIMITER //

-- Trigger to update medicine stock when prescribed
CREATE TRIGGER update_medicine_stock 
AFTER INSERT ON prescription_medicines
FOR EACH ROW
BEGIN
    UPDATE medicines 
    SET stock_quantity = stock_quantity - NEW.quantity 
    WHERE id = NEW.medicine_id;
END//

-- Trigger to log prescription creation
CREATE TRIGGER log_prescription_creation
AFTER INSERT ON prescriptions
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values)
    VALUES (NEW.doctor_id, 'CREATE', 'prescriptions', NEW.id, 
            JSON_OBJECT('prescription_number', NEW.prescription_number, 'patient_id', NEW.patient_id));
END//

DELIMITER ;

-- Create views for common queries
CREATE VIEW active_prescriptions AS
SELECT 
    p.id,
    p.prescription_number,
    u1.name as doctor_name,
    u2.name as patient_name,
    p.diagnosis,
    p.prescription_date,
    p.status
FROM prescriptions p
JOIN users u1 ON p.doctor_id = u1.id
JOIN users u2 ON p.patient_id = u2.id
WHERE p.status = 'active';

CREATE VIEW medicine_stock_low AS
SELECT 
    id,
    name,
    generic_name,
    category,
    stock_quantity,
    price
FROM medicines 
WHERE stock_quantity < 50 AND is_active = TRUE
ORDER BY stock_quantity ASC;

CREATE VIEW doctor_prescription_stats AS
SELECT 
    d.id as doctor_id,
    u.name as doctor_name,
    COUNT(p.id) as total_prescriptions,
    COUNT(CASE WHEN p.status = 'active' THEN 1 END) as active_prescriptions,
    COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_prescriptions
FROM doctors d
JOIN users u ON d.user_id = u.id
LEFT JOIN prescriptions p ON d.user_id = p.doctor_id
GROUP BY d.id, u.name;
