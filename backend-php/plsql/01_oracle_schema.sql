-- ============================================================================
-- DENTAL MANAGEMENT SYSTEM - ORACLE DATABASE SCHEMA
-- Converted from Supabase PostgreSQL to Oracle Database
-- ============================================================================
-- Project: Dental Management System
-- Authors: Hardik Jadhav, Prathamesh Patil, Yash Patil
-- Academic Year: 2025-2026
-- Institute: P.V.P.I.T. Budhgaon, Sangli
-- ============================================================================

-- ============================================================================
-- SECTION 1: CLEANUP (DROP EXISTING OBJECTS)
-- ============================================================================
-- Run these only if recreating the schema

/*
DROP TABLE patient_financials CASCADE CONSTRAINTS;
DROP TABLE patient_services CASCADE CONSTRAINTS;
DROP TABLE treatments CASCADE CONSTRAINTS;
DROP TABLE appointments CASCADE CONSTRAINTS;
DROP TABLE feedback CASCADE CONSTRAINTS;
DROP TABLE user_roles CASCADE CONSTRAINTS;
DROP TABLE services CASCADE CONSTRAINTS;
DROP TABLE doctors CASCADE CONSTRAINTS;
DROP TABLE patients CASCADE CONSTRAINTS;
DROP TABLE practice_settings CASCADE CONSTRAINTS;
DROP TABLE users CASCADE CONSTRAINTS;

DROP SEQUENCE seq_patients;
DROP SEQUENCE seq_appointments;
DROP SEQUENCE seq_treatments;
DROP SEQUENCE seq_services;
DROP SEQUENCE seq_doctors;
DROP SEQUENCE seq_feedback;
DROP SEQUENCE seq_patient_services;
DROP SEQUENCE seq_patient_financials;
DROP SEQUENCE seq_user_roles;
DROP SEQUENCE seq_practice_settings;
DROP SEQUENCE seq_users;
DROP SEQUENCE seq_patient_id_counter;
*/

-- ============================================================================
-- SECTION 2: SEQUENCES (Auto-increment IDs)
-- ============================================================================
-- Oracle does not have UUID by default, so we use sequences + triggers

CREATE SEQUENCE seq_patient_id_counter
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_patients
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_appointments
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_treatments
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_services
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_doctors
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_feedback
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_patient_services
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_patient_financials
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_user_roles
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_practice_settings
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE SEQUENCE seq_users
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

-- ============================================================================
-- SECTION 3: TABLES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Table: USERS (Replaces Supabase auth.users)
-- ----------------------------------------------------------------------------
-- Original Supabase: auth.users (managed by Supabase)
-- Oracle Equivalent: Custom users table with password hashing
-- ----------------------------------------------------------------------------

CREATE TABLE users (
    id                  NUMBER PRIMARY KEY,
    email               VARCHAR2(255) NOT NULL UNIQUE,
    password_hash       VARCHAR2(255) NOT NULL,  -- Store bcrypt/hash
    email_verified      NUMBER(1) DEFAULT 0,     -- 0=false, 1=true
    phone               VARCHAR2(20),
    last_sign_in        TIMESTAMP,
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    
    CONSTRAINT chk_users_email_verified CHECK (email_verified IN (0, 1))
);

-- Trigger for auto-increment ID
CREATE OR REPLACE TRIGGER trg_users_id
    BEFORE INSERT ON users
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT seq_users.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
    :NEW.updated_at := SYSTIMESTAMP;
END;
/

-- ----------------------------------------------------------------------------
-- Table: USER_ROLES
-- ----------------------------------------------------------------------------
-- Original Supabase: public.user_roles with app_role enum
-- Oracle: Uses CHECK constraint for role validation
-- ----------------------------------------------------------------------------

CREATE TABLE user_roles (
    id                  NUMBER PRIMARY KEY,
    user_id             NUMBER NOT NULL,
    role                VARCHAR2(20) NOT NULL,
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP,
    
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT uk_user_roles UNIQUE (user_id, role),
    CONSTRAINT chk_user_role CHECK (role IN ('admin', 'doctor', 'staff', 'patient'))
);

CREATE OR REPLACE TRIGGER trg_user_roles_id
    BEFORE INSERT ON user_roles
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT seq_user_roles.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
END;
/

-- ----------------------------------------------------------------------------
-- Table: PATIENTS
-- ----------------------------------------------------------------------------
-- Original Supabase:
--   id: uuid, patient_id: text, name: varchar, email: varchar,
--   phone: varchar, date_of_birth: date, address: text,
--   medical_history: text, insurance_info: varchar, status: varchar
-- 
-- Oracle Conversion:
--   uuid → NUMBER (using sequence)
--   text → CLOB or VARCHAR2(4000)
--   varchar → VARCHAR2
--   timestamp with time zone → TIMESTAMP WITH TIME ZONE
-- ----------------------------------------------------------------------------

CREATE TABLE patients (
    id                  NUMBER PRIMARY KEY,
    patient_id          VARCHAR2(20) UNIQUE,    -- Format: P000001
    name                VARCHAR2(255) NOT NULL,
    email               VARCHAR2(255) NOT NULL,
    phone               VARCHAR2(20) NOT NULL,
    date_of_birth       DATE NOT NULL,
    address             VARCHAR2(4000),
    medical_history     CLOB,
    insurance_info      VARCHAR2(255),
    status              VARCHAR2(20) DEFAULT 'active',
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    
    CONSTRAINT chk_patient_status CHECK (status IN ('active', 'inactive'))
);

-- Auto-generate primary key
CREATE OR REPLACE TRIGGER trg_patients_id
    BEFORE INSERT ON patients
    FOR EACH ROW
DECLARE
    v_counter NUMBER;
    v_patient_id VARCHAR2(20);
BEGIN
    -- Auto-increment ID
    IF :NEW.id IS NULL THEN
        SELECT seq_patients.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
    
    -- Generate patient_id (P000001 format)
    IF :NEW.patient_id IS NULL THEN
        SELECT seq_patient_id_counter.NEXTVAL INTO v_counter FROM DUAL;
        :NEW.patient_id := 'P' || LPAD(TO_CHAR(v_counter), 6, '0');
    END IF;
END;
/

-- Indexes
CREATE INDEX idx_patients_email ON patients(email);
CREATE INDEX idx_patients_phone ON patients(phone);
CREATE INDEX idx_patients_status ON patients(status);
CREATE INDEX idx_patients_patient_id ON patients(patient_id);

-- ----------------------------------------------------------------------------
-- Table: DOCTORS
-- ----------------------------------------------------------------------------

CREATE TABLE doctors (
    id                  NUMBER PRIMARY KEY,
    name                VARCHAR2(255) NOT NULL,
    specialty           VARCHAR2(255),
    email               VARCHAR2(255),
    phone               VARCHAR2(20),
    is_active           NUMBER(1) DEFAULT 1,
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    
    CONSTRAINT chk_doctor_active CHECK (is_active IN (0, 1))
);

CREATE OR REPLACE TRIGGER trg_doctors_id
    BEFORE INSERT ON doctors
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT seq_doctors.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
END;
/

-- ----------------------------------------------------------------------------
-- Table: SERVICES
-- ----------------------------------------------------------------------------

CREATE TABLE services (
    id                  NUMBER PRIMARY KEY,
    name                VARCHAR2(255) NOT NULL,
    description         VARCHAR2(4000),
    category            VARCHAR2(100) DEFAULT 'general',
    default_cost        NUMBER(10,2) DEFAULT 0,
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL
);

CREATE OR REPLACE TRIGGER trg_services_id
    BEFORE INSERT ON services
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT seq_services.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
END;
/

-- ----------------------------------------------------------------------------
-- Table: APPOINTMENTS
-- ----------------------------------------------------------------------------
-- Original Supabase: appointments with patient_id FK, date, time, status
-- Oracle: Same structure with NUMBER IDs
-- ----------------------------------------------------------------------------

CREATE TABLE appointments (
    id                  NUMBER PRIMARY KEY,
    patient_id          NUMBER,
    appointment_date    DATE NOT NULL,
    appointment_time    VARCHAR2(10) NOT NULL,  -- HH:MM format
    doctor              VARCHAR2(255) NOT NULL,
    service_type        VARCHAR2(255) NOT NULL,
    status              VARCHAR2(20) DEFAULT 'scheduled',
    notes               VARCHAR2(4000),
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) 
        REFERENCES patients(id) ON DELETE SET NULL,
    CONSTRAINT chk_appt_status CHECK (
        status IN ('scheduled', 'confirmed', 'completed', 'cancelled', 'no-show')
    )
);

CREATE OR REPLACE TRIGGER trg_appointments_id
    BEFORE INSERT ON appointments
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT seq_appointments.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
END;
/

-- Indexes
CREATE INDEX idx_appt_patient ON appointments(patient_id);
CREATE INDEX idx_appt_date ON appointments(appointment_date);
CREATE INDEX idx_appt_status ON appointments(status);
CREATE INDEX idx_appt_date_time ON appointments(appointment_date, appointment_time);

-- ----------------------------------------------------------------------------
-- Table: TREATMENTS
-- ----------------------------------------------------------------------------

CREATE TABLE treatments (
    id                  NUMBER PRIMARY KEY,
    patient_id          NUMBER,
    appointment_id      NUMBER,
    treatment_type      VARCHAR2(255) NOT NULL,
    description         VARCHAR2(4000),
    cost                NUMBER(10,2),
    status              VARCHAR2(20) DEFAULT 'planned',
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    
    CONSTRAINT fk_treat_patient FOREIGN KEY (patient_id) 
        REFERENCES patients(id) ON DELETE SET NULL,
    CONSTRAINT fk_treat_appt FOREIGN KEY (appointment_id) 
        REFERENCES appointments(id) ON DELETE SET NULL,
    CONSTRAINT chk_treat_status CHECK (
        status IN ('planned', 'in-progress', 'completed', 'cancelled')
    )
);

CREATE OR REPLACE TRIGGER trg_treatments_id
    BEFORE INSERT ON treatments
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT seq_treatments.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
END;
/

-- Indexes
CREATE INDEX idx_treat_patient ON treatments(patient_id);
CREATE INDEX idx_treat_appt ON treatments(appointment_id);

-- ----------------------------------------------------------------------------
-- Table: PATIENT_SERVICES
-- ----------------------------------------------------------------------------

CREATE TABLE patient_services (
    id                  NUMBER PRIMARY KEY,
    patient_id          NUMBER NOT NULL,
    service_id          NUMBER NOT NULL,
    assigned_cost       NUMBER(10,2) DEFAULT 0,
    scheduled_date      DATE,
    completed_date      DATE,
    status              VARCHAR2(20) DEFAULT 'pending',
    notes               VARCHAR2(4000),
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    
    CONSTRAINT fk_ps_patient FOREIGN KEY (patient_id) 
        REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_ps_service FOREIGN KEY (service_id) 
        REFERENCES services(id) ON DELETE CASCADE,
    CONSTRAINT chk_ps_status CHECK (
        status IN ('pending', 'scheduled', 'in-progress', 'completed', 'cancelled')
    )
);

CREATE OR REPLACE TRIGGER trg_patient_services_id
    BEFORE INSERT ON patient_services
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT seq_patient_services.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_patient_services_update
    BEFORE UPDATE ON patient_services
    FOR EACH ROW
BEGIN
    :NEW.updated_at := SYSTIMESTAMP;
END;
/

-- ----------------------------------------------------------------------------
-- Table: PATIENT_FINANCIALS
-- ----------------------------------------------------------------------------

CREATE TABLE patient_financials (
    id                      NUMBER PRIMARY KEY,
    patient_id              NUMBER NOT NULL,
    total_treatment_cost    NUMBER(10,2) DEFAULT 0,
    amount_paid_by_patient  NUMBER(10,2) DEFAULT 0,
    remaining_from_patient  NUMBER(10,2) DEFAULT 0,
    amount_due_to_doctor    NUMBER(10,2) DEFAULT 0,
    notes                   VARCHAR2(4000),
    created_at              TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at              TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    
    CONSTRAINT fk_pf_patient FOREIGN KEY (patient_id) 
        REFERENCES patients(id) ON DELETE CASCADE
);

CREATE OR REPLACE TRIGGER trg_patient_financials_id
    BEFORE INSERT ON patient_financials
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT seq_patient_financials.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_patient_financials_update
    BEFORE UPDATE ON patient_financials
    FOR EACH ROW
BEGIN
    :NEW.updated_at := SYSTIMESTAMP;
    -- Auto-calculate remaining
    :NEW.remaining_from_patient := NVL(:NEW.total_treatment_cost, 0) - NVL(:NEW.amount_paid_by_patient, 0);
END;
/

-- ----------------------------------------------------------------------------
-- Table: FEEDBACK
-- ----------------------------------------------------------------------------

CREATE TABLE feedback (
    id                  NUMBER PRIMARY KEY,
    patient_id          NUMBER,
    patient_name        VARCHAR2(255) NOT NULL,
    patient_email       VARCHAR2(255) NOT NULL,
    rating              NUMBER(1) NOT NULL,
    message             VARCHAR2(4000) NOT NULL,
    category            VARCHAR2(50) DEFAULT 'general',
    status              VARCHAR2(20) DEFAULT 'new',
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    
    CONSTRAINT fk_feedback_patient FOREIGN KEY (patient_id) 
        REFERENCES patients(id) ON DELETE SET NULL,
    CONSTRAINT chk_feedback_rating CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT chk_feedback_status CHECK (
        status IN ('new', 'reviewed', 'addressed', 'archived')
    )
);

CREATE OR REPLACE TRIGGER trg_feedback_id
    BEFORE INSERT ON feedback
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT seq_feedback.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_feedback_update
    BEFORE UPDATE ON feedback
    FOR EACH ROW
BEGIN
    :NEW.updated_at := SYSTIMESTAMP;
END;
/

-- ----------------------------------------------------------------------------
-- Table: PRACTICE_SETTINGS
-- ----------------------------------------------------------------------------

CREATE TABLE practice_settings (
    id                  NUMBER PRIMARY KEY,
    setting_key         VARCHAR2(255) NOT NULL UNIQUE,
    setting_value       CLOB,  -- Store JSON as CLOB
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL
);

CREATE OR REPLACE TRIGGER trg_practice_settings_id
    BEFORE INSERT ON practice_settings
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT seq_practice_settings.NEXTVAL INTO :NEW.id FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_practice_settings_update
    BEFORE UPDATE ON practice_settings
    FOR EACH ROW
BEGIN
    :NEW.updated_at := SYSTIMESTAMP;
END;
/

-- ----------------------------------------------------------------------------
-- Table: USER_SESSIONS (For PHP session management)
-- ----------------------------------------------------------------------------

CREATE TABLE user_sessions (
    id                  VARCHAR2(128) PRIMARY KEY,
    user_id             NUMBER NOT NULL,
    token               VARCHAR2(255) NOT NULL UNIQUE,
    ip_address          VARCHAR2(45),
    user_agent          VARCHAR2(500),
    expires_at          TIMESTAMP NOT NULL,
    created_at          TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
    
    CONSTRAINT fk_session_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_sessions_user ON user_sessions(user_id);
CREATE INDEX idx_sessions_token ON user_sessions(token);
CREATE INDEX idx_sessions_expires ON user_sessions(expires_at);

-- ============================================================================
-- SECTION 4: VIEWS
-- ============================================================================

-- View: Active patients with appointment count
CREATE OR REPLACE VIEW vw_patient_summary AS
SELECT 
    p.id,
    p.patient_id,
    p.name,
    p.email,
    p.phone,
    p.status,
    p.created_at,
    (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS total_appointments,
    (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id AND a.status = 'completed') AS completed_appointments,
    (SELECT COUNT(*) FROM treatments t WHERE t.patient_id = p.id) AS total_treatments
FROM patients p
WHERE p.status = 'active';

-- View: Today's appointments
CREATE OR REPLACE VIEW vw_todays_appointments AS
SELECT 
    a.id,
    a.appointment_date,
    a.appointment_time,
    a.doctor,
    a.service_type,
    a.status,
    p.patient_id,
    p.name AS patient_name,
    p.phone AS patient_phone
FROM appointments a
LEFT JOIN patients p ON a.patient_id = p.id
WHERE a.appointment_date = TRUNC(SYSDATE)
ORDER BY a.appointment_time;

-- View: Dashboard statistics
CREATE OR REPLACE VIEW vw_dashboard_stats AS
SELECT
    (SELECT COUNT(*) FROM patients WHERE status = 'active') AS total_patients,
    (SELECT COUNT(*) FROM appointments WHERE appointment_date = TRUNC(SYSDATE)) AS today_appointments,
    (SELECT COUNT(*) FROM patient_services WHERE status = 'pending') AS pending_treatments,
    (SELECT NVL(SUM(amount_paid_by_patient), 0) FROM patient_financials) AS total_revenue,
    (SELECT NVL(SUM(remaining_from_patient), 0) FROM patient_financials) AS outstanding_balance
FROM DUAL;

-- ============================================================================
-- SECTION 5: STORED PROCEDURES (PL/SQL)
-- ============================================================================
-- These replace Supabase SDK calls and RPC functions
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Package: PKG_AUTH - Authentication procedures
-- ----------------------------------------------------------------------------

CREATE OR REPLACE PACKAGE pkg_auth AS
    -- Check if user has a specific role
    FUNCTION has_role(
        p_user_id IN NUMBER,
        p_role IN VARCHAR2
    ) RETURN NUMBER;  -- Returns 1 (true) or 0 (false)
    
    -- Check if user is healthcare provider
    FUNCTION is_healthcare_provider(
        p_user_id IN NUMBER
    ) RETURN NUMBER;
    
    -- Register new user
    PROCEDURE register_user(
        p_email IN VARCHAR2,
        p_password_hash IN VARCHAR2,
        p_role IN VARCHAR2 DEFAULT 'patient',
        p_user_id OUT NUMBER
    );
    
    -- Authenticate user
    PROCEDURE authenticate_user(
        p_email IN VARCHAR2,
        p_password_hash IN VARCHAR2,
        p_user_id OUT NUMBER,
        p_success OUT NUMBER
    );
    
    -- Create session
    PROCEDURE create_session(
        p_user_id IN NUMBER,
        p_token IN VARCHAR2,
        p_ip_address IN VARCHAR2,
        p_user_agent IN VARCHAR2,
        p_session_id OUT VARCHAR2
    );
    
    -- Validate session
    FUNCTION validate_session(
        p_token IN VARCHAR2
    ) RETURN NUMBER;  -- Returns user_id or NULL
    
    -- Destroy session
    PROCEDURE destroy_session(
        p_token IN VARCHAR2
    );
END pkg_auth;
/

CREATE OR REPLACE PACKAGE BODY pkg_auth AS

    FUNCTION has_role(
        p_user_id IN NUMBER,
        p_role IN VARCHAR2
    ) RETURN NUMBER IS
        v_count NUMBER;
    BEGIN
        SELECT COUNT(*) INTO v_count
        FROM user_roles
        WHERE user_id = p_user_id AND role = p_role;
        
        IF v_count > 0 THEN
            RETURN 1;
        ELSE
            RETURN 0;
        END IF;
    EXCEPTION
        WHEN OTHERS THEN
            RETURN 0;
    END has_role;
    
    FUNCTION is_healthcare_provider(
        p_user_id IN NUMBER
    ) RETURN NUMBER IS
        v_count NUMBER;
    BEGIN
        SELECT COUNT(*) INTO v_count
        FROM user_roles
        WHERE user_id = p_user_id 
        AND role IN ('admin', 'doctor', 'staff');
        
        IF v_count > 0 THEN
            RETURN 1;
        ELSE
            RETURN 0;
        END IF;
    EXCEPTION
        WHEN OTHERS THEN
            RETURN 0;
    END is_healthcare_provider;
    
    PROCEDURE register_user(
        p_email IN VARCHAR2,
        p_password_hash IN VARCHAR2,
        p_role IN VARCHAR2 DEFAULT 'patient',
        p_user_id OUT NUMBER
    ) IS
    BEGIN
        INSERT INTO users (email, password_hash)
        VALUES (p_email, p_password_hash)
        RETURNING id INTO p_user_id;
        
        INSERT INTO user_roles (user_id, role)
        VALUES (p_user_id, p_role);
        
        COMMIT;
    EXCEPTION
        WHEN DUP_VAL_ON_INDEX THEN
            p_user_id := -1;  -- Email already exists
            ROLLBACK;
        WHEN OTHERS THEN
            p_user_id := -2;  -- Other error
            ROLLBACK;
    END register_user;
    
    PROCEDURE authenticate_user(
        p_email IN VARCHAR2,
        p_password_hash IN VARCHAR2,
        p_user_id OUT NUMBER,
        p_success OUT NUMBER
    ) IS
        v_stored_hash VARCHAR2(255);
    BEGIN
        SELECT id, password_hash INTO p_user_id, v_stored_hash
        FROM users
        WHERE email = p_email;
        
        -- Note: In real implementation, compare hashes properly
        IF v_stored_hash = p_password_hash THEN
            p_success := 1;
            UPDATE users SET last_sign_in = SYSTIMESTAMP WHERE id = p_user_id;
            COMMIT;
        ELSE
            p_success := 0;
            p_user_id := NULL;
        END IF;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            p_user_id := NULL;
            p_success := 0;
    END authenticate_user;
    
    PROCEDURE create_session(
        p_user_id IN NUMBER,
        p_token IN VARCHAR2,
        p_ip_address IN VARCHAR2,
        p_user_agent IN VARCHAR2,
        p_session_id OUT VARCHAR2
    ) IS
    BEGIN
        p_session_id := SYS_GUID();
        
        INSERT INTO user_sessions (id, user_id, token, ip_address, user_agent, expires_at)
        VALUES (p_session_id, p_user_id, p_token, p_ip_address, p_user_agent, 
                SYSTIMESTAMP + INTERVAL '24' HOUR);
        
        COMMIT;
    END create_session;
    
    FUNCTION validate_session(
        p_token IN VARCHAR2
    ) RETURN NUMBER IS
        v_user_id NUMBER;
    BEGIN
        SELECT user_id INTO v_user_id
        FROM user_sessions
        WHERE token = p_token AND expires_at > SYSTIMESTAMP;
        
        RETURN v_user_id;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            RETURN NULL;
    END validate_session;
    
    PROCEDURE destroy_session(
        p_token IN VARCHAR2
    ) IS
    BEGIN
        DELETE FROM user_sessions WHERE token = p_token;
        COMMIT;
    END destroy_session;

END pkg_auth;
/

-- ----------------------------------------------------------------------------
-- Package: PKG_PATIENTS - Patient management procedures
-- ----------------------------------------------------------------------------

CREATE OR REPLACE PACKAGE pkg_patients AS
    -- Types for returning multiple rows
    TYPE patient_record IS RECORD (
        id NUMBER,
        patient_id VARCHAR2(20),
        name VARCHAR2(255),
        email VARCHAR2(255),
        phone VARCHAR2(20),
        date_of_birth DATE,
        address VARCHAR2(4000),
        medical_history CLOB,
        insurance_info VARCHAR2(255),
        status VARCHAR2(20),
        created_at TIMESTAMP
    );
    TYPE patient_table IS TABLE OF patient_record;
    
    -- Get all patients
    FUNCTION get_all_patients RETURN SYS_REFCURSOR;
    
    -- Get patient by ID
    FUNCTION get_patient_by_id(p_id IN NUMBER) RETURN SYS_REFCURSOR;
    
    -- Create patient
    PROCEDURE create_patient(
        p_name IN VARCHAR2,
        p_email IN VARCHAR2,
        p_phone IN VARCHAR2,
        p_date_of_birth IN DATE,
        p_address IN VARCHAR2 DEFAULT NULL,
        p_medical_history IN CLOB DEFAULT NULL,
        p_insurance_info IN VARCHAR2 DEFAULT NULL,
        p_patient_id OUT NUMBER,
        p_generated_id OUT VARCHAR2
    );
    
    -- Update patient
    PROCEDURE update_patient(
        p_id IN NUMBER,
        p_name IN VARCHAR2,
        p_email IN VARCHAR2,
        p_phone IN VARCHAR2,
        p_date_of_birth IN DATE,
        p_address IN VARCHAR2,
        p_medical_history IN CLOB,
        p_insurance_info IN VARCHAR2,
        p_status IN VARCHAR2
    );
    
    -- Delete patient
    PROCEDURE delete_patient(p_id IN NUMBER);
    
    -- Search patients
    FUNCTION search_patients(p_search_term IN VARCHAR2) RETURN SYS_REFCURSOR;
    
END pkg_patients;
/

CREATE OR REPLACE PACKAGE BODY pkg_patients AS

    FUNCTION get_all_patients RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT id, patient_id, name, email, phone, date_of_birth,
                   address, medical_history, insurance_info, status, created_at
            FROM patients
            ORDER BY created_at DESC;
        RETURN v_cursor;
    END get_all_patients;
    
    FUNCTION get_patient_by_id(p_id IN NUMBER) RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT id, patient_id, name, email, phone, date_of_birth,
                   address, medical_history, insurance_info, status, created_at
            FROM patients
            WHERE id = p_id;
        RETURN v_cursor;
    END get_patient_by_id;
    
    PROCEDURE create_patient(
        p_name IN VARCHAR2,
        p_email IN VARCHAR2,
        p_phone IN VARCHAR2,
        p_date_of_birth IN DATE,
        p_address IN VARCHAR2 DEFAULT NULL,
        p_medical_history IN CLOB DEFAULT NULL,
        p_insurance_info IN VARCHAR2 DEFAULT NULL,
        p_patient_id OUT NUMBER,
        p_generated_id OUT VARCHAR2
    ) IS
    BEGIN
        INSERT INTO patients (name, email, phone, date_of_birth, address, 
                             medical_history, insurance_info, status)
        VALUES (p_name, p_email, p_phone, p_date_of_birth, p_address,
               p_medical_history, p_insurance_info, 'active')
        RETURNING id, patient_id INTO p_patient_id, p_generated_id;
        
        COMMIT;
    END create_patient;
    
    PROCEDURE update_patient(
        p_id IN NUMBER,
        p_name IN VARCHAR2,
        p_email IN VARCHAR2,
        p_phone IN VARCHAR2,
        p_date_of_birth IN DATE,
        p_address IN VARCHAR2,
        p_medical_history IN CLOB,
        p_insurance_info IN VARCHAR2,
        p_status IN VARCHAR2
    ) IS
    BEGIN
        UPDATE patients
        SET name = p_name,
            email = p_email,
            phone = p_phone,
            date_of_birth = p_date_of_birth,
            address = p_address,
            medical_history = p_medical_history,
            insurance_info = p_insurance_info,
            status = p_status
        WHERE id = p_id;
        
        COMMIT;
    END update_patient;
    
    PROCEDURE delete_patient(p_id IN NUMBER) IS
    BEGIN
        DELETE FROM patients WHERE id = p_id;
        COMMIT;
    END delete_patient;
    
    FUNCTION search_patients(p_search_term IN VARCHAR2) RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
        v_search VARCHAR2(255);
    BEGIN
        v_search := '%' || UPPER(p_search_term) || '%';
        
        OPEN v_cursor FOR
            SELECT id, patient_id, name, email, phone, date_of_birth,
                   address, medical_history, insurance_info, status, created_at
            FROM patients
            WHERE UPPER(name) LIKE v_search
               OR UPPER(email) LIKE v_search
               OR phone LIKE v_search
               OR patient_id LIKE v_search
            ORDER BY name;
        
        RETURN v_cursor;
    END search_patients;

END pkg_patients;
/

-- ----------------------------------------------------------------------------
-- Package: PKG_APPOINTMENTS - Appointment management procedures
-- ----------------------------------------------------------------------------

CREATE OR REPLACE PACKAGE pkg_appointments AS
    
    -- Get all appointments
    FUNCTION get_all_appointments RETURN SYS_REFCURSOR;
    
    -- Get appointments by date
    FUNCTION get_appointments_by_date(p_date IN DATE) RETURN SYS_REFCURSOR;
    
    -- Get appointments for patient
    FUNCTION get_patient_appointments(p_patient_id IN NUMBER) RETURN SYS_REFCURSOR;
    
    -- Check time slot availability
    FUNCTION is_slot_available(
        p_date IN DATE,
        p_time IN VARCHAR2,
        p_doctor IN VARCHAR2
    ) RETURN NUMBER;  -- 1 = available, 0 = booked
    
    -- Get booked slots for date/doctor
    FUNCTION get_booked_slots(
        p_date IN DATE,
        p_doctor IN VARCHAR2
    ) RETURN SYS_REFCURSOR;
    
    -- Create appointment
    PROCEDURE create_appointment(
        p_patient_id IN NUMBER,
        p_date IN DATE,
        p_time IN VARCHAR2,
        p_doctor IN VARCHAR2,
        p_service_type IN VARCHAR2,
        p_notes IN VARCHAR2 DEFAULT NULL,
        p_appointment_id OUT NUMBER
    );
    
    -- Update appointment status
    PROCEDURE update_appointment_status(
        p_id IN NUMBER,
        p_status IN VARCHAR2
    );
    
    -- Cancel appointment
    PROCEDURE cancel_appointment(p_id IN NUMBER);
    
END pkg_appointments;
/

CREATE OR REPLACE PACKAGE BODY pkg_appointments AS

    FUNCTION get_all_appointments RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time,
                   a.doctor, a.service_type, a.status, a.notes, a.created_at,
                   p.name AS patient_name, p.email AS patient_email, p.phone AS patient_phone
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            ORDER BY a.appointment_date DESC, a.appointment_time;
        RETURN v_cursor;
    END get_all_appointments;
    
    FUNCTION get_appointments_by_date(p_date IN DATE) RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time,
                   a.doctor, a.service_type, a.status, a.notes, a.created_at,
                   p.name AS patient_name, p.email AS patient_email, p.phone AS patient_phone
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE a.appointment_date = p_date
            ORDER BY a.appointment_time;
        RETURN v_cursor;
    END get_appointments_by_date;
    
    FUNCTION get_patient_appointments(p_patient_id IN NUMBER) RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT id, patient_id, appointment_date, appointment_time,
                   doctor, service_type, status, notes, created_at
            FROM appointments
            WHERE patient_id = p_patient_id
            ORDER BY appointment_date DESC, appointment_time;
        RETURN v_cursor;
    END get_patient_appointments;
    
    FUNCTION is_slot_available(
        p_date IN DATE,
        p_time IN VARCHAR2,
        p_doctor IN VARCHAR2
    ) RETURN NUMBER IS
        v_count NUMBER;
    BEGIN
        SELECT COUNT(*) INTO v_count
        FROM appointments
        WHERE appointment_date = p_date
          AND appointment_time = p_time
          AND doctor = p_doctor
          AND status NOT IN ('cancelled');
        
        IF v_count = 0 THEN
            RETURN 1;  -- Available
        ELSE
            RETURN 0;  -- Booked
        END IF;
    END is_slot_available;
    
    FUNCTION get_booked_slots(
        p_date IN DATE,
        p_doctor IN VARCHAR2
    ) RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT appointment_time
            FROM appointments
            WHERE appointment_date = p_date
              AND doctor = p_doctor
              AND status NOT IN ('cancelled');
        RETURN v_cursor;
    END get_booked_slots;
    
    PROCEDURE create_appointment(
        p_patient_id IN NUMBER,
        p_date IN DATE,
        p_time IN VARCHAR2,
        p_doctor IN VARCHAR2,
        p_service_type IN VARCHAR2,
        p_notes IN VARCHAR2 DEFAULT NULL,
        p_appointment_id OUT NUMBER
    ) IS
        v_available NUMBER;
    BEGIN
        -- Check availability
        v_available := is_slot_available(p_date, p_time, p_doctor);
        
        IF v_available = 0 THEN
            p_appointment_id := -1;  -- Slot not available
            RETURN;
        END IF;
        
        INSERT INTO appointments (patient_id, appointment_date, appointment_time,
                                  doctor, service_type, notes, status)
        VALUES (p_patient_id, p_date, p_time, p_doctor, p_service_type, 
                p_notes, 'scheduled')
        RETURNING id INTO p_appointment_id;
        
        COMMIT;
    END create_appointment;
    
    PROCEDURE update_appointment_status(
        p_id IN NUMBER,
        p_status IN VARCHAR2
    ) IS
    BEGIN
        UPDATE appointments
        SET status = p_status
        WHERE id = p_id;
        
        COMMIT;
    END update_appointment_status;
    
    PROCEDURE cancel_appointment(p_id IN NUMBER) IS
    BEGIN
        UPDATE appointments
        SET status = 'cancelled'
        WHERE id = p_id;
        
        COMMIT;
    END cancel_appointment;

END pkg_appointments;
/

-- ----------------------------------------------------------------------------
-- Package: PKG_TREATMENTS - Treatment management
-- ----------------------------------------------------------------------------

CREATE OR REPLACE PACKAGE pkg_treatments AS
    
    FUNCTION get_patient_treatments(p_patient_id IN NUMBER) RETURN SYS_REFCURSOR;
    
    PROCEDURE create_treatment(
        p_patient_id IN NUMBER,
        p_appointment_id IN NUMBER,
        p_treatment_type IN VARCHAR2,
        p_description IN VARCHAR2,
        p_cost IN NUMBER,
        p_treatment_id OUT NUMBER
    );
    
    PROCEDURE update_treatment_status(
        p_id IN NUMBER,
        p_status IN VARCHAR2
    );
    
END pkg_treatments;
/

CREATE OR REPLACE PACKAGE BODY pkg_treatments AS

    FUNCTION get_patient_treatments(p_patient_id IN NUMBER) RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT id, patient_id, appointment_id, treatment_type,
                   description, cost, status, created_at
            FROM treatments
            WHERE patient_id = p_patient_id
            ORDER BY created_at DESC;
        RETURN v_cursor;
    END get_patient_treatments;
    
    PROCEDURE create_treatment(
        p_patient_id IN NUMBER,
        p_appointment_id IN NUMBER,
        p_treatment_type IN VARCHAR2,
        p_description IN VARCHAR2,
        p_cost IN NUMBER,
        p_treatment_id OUT NUMBER
    ) IS
    BEGIN
        INSERT INTO treatments (patient_id, appointment_id, treatment_type,
                               description, cost, status)
        VALUES (p_patient_id, p_appointment_id, p_treatment_type,
               p_description, p_cost, 'planned')
        RETURNING id INTO p_treatment_id;
        
        COMMIT;
    END create_treatment;
    
    PROCEDURE update_treatment_status(
        p_id IN NUMBER,
        p_status IN VARCHAR2
    ) IS
    BEGIN
        UPDATE treatments
        SET status = p_status
        WHERE id = p_id;
        
        COMMIT;
    END update_treatment_status;

END pkg_treatments;
/

-- ----------------------------------------------------------------------------
-- Package: PKG_FINANCIALS - Financial management
-- ----------------------------------------------------------------------------

CREATE OR REPLACE PACKAGE pkg_financials AS
    
    FUNCTION get_patient_financials(p_patient_id IN NUMBER) RETURN SYS_REFCURSOR;
    
    PROCEDURE update_patient_financials(
        p_patient_id IN NUMBER,
        p_total_cost IN NUMBER,
        p_amount_paid IN NUMBER,
        p_doctor_amount IN NUMBER,
        p_notes IN VARCHAR2
    );
    
    PROCEDURE add_payment(
        p_patient_id IN NUMBER,
        p_amount IN NUMBER
    );
    
    FUNCTION get_outstanding_balances RETURN SYS_REFCURSOR;
    
END pkg_financials;
/

CREATE OR REPLACE PACKAGE BODY pkg_financials AS

    FUNCTION get_patient_financials(p_patient_id IN NUMBER) RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT pf.*, p.name AS patient_name, p.patient_id AS patient_code
            FROM patient_financials pf
            JOIN patients p ON pf.patient_id = p.id
            WHERE pf.patient_id = p_patient_id;
        RETURN v_cursor;
    END get_patient_financials;
    
    PROCEDURE update_patient_financials(
        p_patient_id IN NUMBER,
        p_total_cost IN NUMBER,
        p_amount_paid IN NUMBER,
        p_doctor_amount IN NUMBER,
        p_notes IN VARCHAR2
    ) IS
        v_count NUMBER;
    BEGIN
        SELECT COUNT(*) INTO v_count
        FROM patient_financials
        WHERE patient_id = p_patient_id;
        
        IF v_count = 0 THEN
            INSERT INTO patient_financials (patient_id, total_treatment_cost,
                                           amount_paid_by_patient, 
                                           remaining_from_patient,
                                           amount_due_to_doctor, notes)
            VALUES (p_patient_id, p_total_cost, p_amount_paid,
                   p_total_cost - p_amount_paid, p_doctor_amount, p_notes);
        ELSE
            UPDATE patient_financials
            SET total_treatment_cost = p_total_cost,
                amount_paid_by_patient = p_amount_paid,
                remaining_from_patient = p_total_cost - p_amount_paid,
                amount_due_to_doctor = p_doctor_amount,
                notes = p_notes
            WHERE patient_id = p_patient_id;
        END IF;
        
        COMMIT;
    END update_patient_financials;
    
    PROCEDURE add_payment(
        p_patient_id IN NUMBER,
        p_amount IN NUMBER
    ) IS
    BEGIN
        UPDATE patient_financials
        SET amount_paid_by_patient = amount_paid_by_patient + p_amount,
            remaining_from_patient = remaining_from_patient - p_amount
        WHERE patient_id = p_patient_id;
        
        COMMIT;
    END add_payment;
    
    FUNCTION get_outstanding_balances RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT pf.*, p.name AS patient_name, p.phone AS patient_phone
            FROM patient_financials pf
            JOIN patients p ON pf.patient_id = p.id
            WHERE pf.remaining_from_patient > 0
            ORDER BY pf.remaining_from_patient DESC;
        RETURN v_cursor;
    END get_outstanding_balances;

END pkg_financials;
/

-- ----------------------------------------------------------------------------
-- Package: PKG_FEEDBACK - Feedback management
-- ----------------------------------------------------------------------------

CREATE OR REPLACE PACKAGE pkg_feedback AS
    
    FUNCTION get_all_feedback RETURN SYS_REFCURSOR;
    
    PROCEDURE create_feedback(
        p_patient_id IN NUMBER,
        p_patient_name IN VARCHAR2,
        p_patient_email IN VARCHAR2,
        p_rating IN NUMBER,
        p_message IN VARCHAR2,
        p_category IN VARCHAR2,
        p_feedback_id OUT NUMBER
    );
    
    PROCEDURE update_feedback_status(
        p_id IN NUMBER,
        p_status IN VARCHAR2
    );
    
END pkg_feedback;
/

CREATE OR REPLACE PACKAGE BODY pkg_feedback AS

    FUNCTION get_all_feedback RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT id, patient_id, patient_name, patient_email,
                   rating, message, category, status, created_at, updated_at
            FROM feedback
            ORDER BY created_at DESC;
        RETURN v_cursor;
    END get_all_feedback;
    
    PROCEDURE create_feedback(
        p_patient_id IN NUMBER,
        p_patient_name IN VARCHAR2,
        p_patient_email IN VARCHAR2,
        p_rating IN NUMBER,
        p_message IN VARCHAR2,
        p_category IN VARCHAR2,
        p_feedback_id OUT NUMBER
    ) IS
    BEGIN
        INSERT INTO feedback (patient_id, patient_name, patient_email,
                             rating, message, category, status)
        VALUES (p_patient_id, p_patient_name, p_patient_email,
               p_rating, p_message, NVL(p_category, 'general'), 'new')
        RETURNING id INTO p_feedback_id;
        
        COMMIT;
    END create_feedback;
    
    PROCEDURE update_feedback_status(
        p_id IN NUMBER,
        p_status IN VARCHAR2
    ) IS
    BEGIN
        UPDATE feedback
        SET status = p_status
        WHERE id = p_id;
        
        COMMIT;
    END update_feedback_status;

END pkg_feedback;
/

-- ----------------------------------------------------------------------------
-- Package: PKG_DASHBOARD - Dashboard statistics
-- ----------------------------------------------------------------------------

CREATE OR REPLACE PACKAGE pkg_dashboard AS
    
    PROCEDURE get_stats(
        p_total_patients OUT NUMBER,
        p_today_appointments OUT NUMBER,
        p_pending_treatments OUT NUMBER,
        p_total_revenue OUT NUMBER,
        p_outstanding_balance OUT NUMBER
    );
    
    FUNCTION get_recent_patients(p_limit IN NUMBER DEFAULT 10) RETURN SYS_REFCURSOR;
    
    FUNCTION get_upcoming_appointments(p_limit IN NUMBER DEFAULT 10) RETURN SYS_REFCURSOR;
    
    FUNCTION get_monthly_revenue(p_months IN NUMBER DEFAULT 12) RETURN SYS_REFCURSOR;
    
END pkg_dashboard;
/

CREATE OR REPLACE PACKAGE BODY pkg_dashboard AS

    PROCEDURE get_stats(
        p_total_patients OUT NUMBER,
        p_today_appointments OUT NUMBER,
        p_pending_treatments OUT NUMBER,
        p_total_revenue OUT NUMBER,
        p_outstanding_balance OUT NUMBER
    ) IS
    BEGIN
        SELECT COUNT(*) INTO p_total_patients
        FROM patients WHERE status = 'active';
        
        SELECT COUNT(*) INTO p_today_appointments
        FROM appointments WHERE appointment_date = TRUNC(SYSDATE);
        
        SELECT COUNT(*) INTO p_pending_treatments
        FROM patient_services WHERE status = 'pending';
        
        SELECT NVL(SUM(amount_paid_by_patient), 0) INTO p_total_revenue
        FROM patient_financials;
        
        SELECT NVL(SUM(remaining_from_patient), 0) INTO p_outstanding_balance
        FROM patient_financials;
    END get_stats;
    
    FUNCTION get_recent_patients(p_limit IN NUMBER DEFAULT 10) RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT * FROM (
                SELECT id, patient_id, name, email, phone, created_at
                FROM patients
                ORDER BY created_at DESC
            ) WHERE ROWNUM <= p_limit;
        RETURN v_cursor;
    END get_recent_patients;
    
    FUNCTION get_upcoming_appointments(p_limit IN NUMBER DEFAULT 10) RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT * FROM (
                SELECT a.id, a.appointment_date, a.appointment_time,
                       a.doctor, a.service_type, a.status,
                       p.name AS patient_name, p.phone AS patient_phone
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.id
                WHERE a.appointment_date >= TRUNC(SYSDATE)
                  AND a.status = 'scheduled'
                ORDER BY a.appointment_date, a.appointment_time
            ) WHERE ROWNUM <= p_limit;
        RETURN v_cursor;
    END get_upcoming_appointments;
    
    FUNCTION get_monthly_revenue(p_months IN NUMBER DEFAULT 12) RETURN SYS_REFCURSOR IS
        v_cursor SYS_REFCURSOR;
    BEGIN
        OPEN v_cursor FOR
            SELECT TO_CHAR(created_at, 'YYYY-MM') AS month,
                   SUM(amount_paid_by_patient) AS revenue
            FROM patient_financials
            WHERE created_at >= ADD_MONTHS(TRUNC(SYSDATE, 'MM'), -p_months)
            GROUP BY TO_CHAR(created_at, 'YYYY-MM')
            ORDER BY month;
        RETURN v_cursor;
    END get_monthly_revenue;

END pkg_dashboard;
/

-- ============================================================================
-- SECTION 6: SAMPLE DATA
-- ============================================================================

-- Insert sample admin user (password should be hashed in production)
INSERT INTO users (email, password_hash, email_verified)
VALUES ('admin@dentalcare.com', '$2y$10$samplehashedpassword', 1);

INSERT INTO user_roles (user_id, role)
VALUES ((SELECT id FROM users WHERE email = 'admin@dentalcare.com'), 'admin');

-- Insert sample doctors
INSERT INTO doctors (name, specialty, email, phone, is_active)
VALUES ('Dr. Sharma', 'General Dentistry', 'sharma@dentalcare.com', '9876543210', 1);

INSERT INTO doctors (name, specialty, email, phone, is_active)
VALUES ('Dr. Patel', 'Orthodontics', 'patel@dentalcare.com', '9876543211', 1);

-- Insert sample services
INSERT INTO services (name, description, category, default_cost)
VALUES ('General Checkup', 'Routine dental examination', 'general', 500);

INSERT INTO services (name, description, category, default_cost)
VALUES ('Teeth Cleaning', 'Professional teeth cleaning and polishing', 'preventive', 1000);

INSERT INTO services (name, description, category, default_cost)
VALUES ('Root Canal', 'Root canal treatment', 'restorative', 8000);

INSERT INTO services (name, description, category, default_cost)
VALUES ('Dental Filling', 'Cavity filling with composite material', 'restorative', 2000);

INSERT INTO services (name, description, category, default_cost)
VALUES ('Teeth Whitening', 'Professional teeth whitening treatment', 'cosmetic', 5000);

COMMIT;

-- ============================================================================
-- SECTION 7: GRANTS (if needed for application user)
-- ============================================================================

-- Create application user (adjust as needed)
-- CREATE USER dental_app IDENTIFIED BY "StrongPassword123";
-- GRANT CONNECT, RESOURCE TO dental_app;
-- GRANT SELECT, INSERT, UPDATE, DELETE ON patients TO dental_app;
-- GRANT SELECT, INSERT, UPDATE, DELETE ON appointments TO dental_app;
-- ... (repeat for all tables)
-- GRANT EXECUTE ON pkg_auth TO dental_app;
-- GRANT EXECUTE ON pkg_patients TO dental_app;
-- ... (repeat for all packages)

-- ============================================================================
-- END OF ORACLE SCHEMA
-- ============================================================================
