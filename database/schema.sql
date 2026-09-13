DO $$ BEGIN
    CREATE TYPE user_role AS ENUM ('admin', 'admission', 'registrar', 'teacher', 'student');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
    CREATE TYPE user_status AS ENUM ('active', 'suspended');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
    CREATE TYPE enrollment_status AS ENUM ('pending', 'verified', 'enrolled');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS enrollment_levels (
    id SERIAL PRIMARY KEY,
    level_type VARCHAR(20) NOT NULL,
    name VARCHAR(120) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (level_type, name)
);
INSERT INTO enrollment_levels (level_type, name, display_order) VALUES
    ('BED', 'Grade 7', 1), ('BED', 'Grade 8', 2), ('BED', 'Grade 9', 3),
    ('BED', 'Grade 10', 4), ('BED', 'Grade 11', 5), ('BED', 'Grade 12', 6),
    ('College', '1st Year', 1), ('College', '2nd Year', 2),
    ('College', '3rd Year', 3), ('College', '4th Year', 4),
    ('TESDA', 'NC I', 1), ('TESDA', 'NC II', 2), ('TESDA', 'NC III', 3)
ON CONFLICT (level_type, name) DO NOTHING;

CREATE TABLE IF NOT EXISTS enrollment_courses (
    id SERIAL PRIMARY KEY,
    level_type VARCHAR(20) NOT NULL,
    name VARCHAR(160) NOT NULL,
    major_track VARCHAR(120) NULL,
    display_order INT NOT NULL DEFAULT 0,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (level_type, name)
);
INSERT INTO enrollment_courses (level_type, name, major_track, display_order) VALUES
    ('College', 'BS Information Technology', NULL, 1),
    ('College', 'BS Accountancy', NULL, 2),
    ('College', 'BS Education', NULL, 3),
    ('TESDA', 'Cookery', NULL, 1),
    ('TESDA', 'Bookkeeping', NULL, 2),
    ('TESDA', 'Computer Systems Servicing', NULL, 3)
ON CONFLICT (level_type, name) DO NOTHING;

CREATE TABLE IF NOT EXISTS enrollment_semesters (
    id SERIAL PRIMARY KEY,
    name VARCHAR(40) NOT NULL,
    display_order INT NOT NULL,
    UNIQUE (name)
);
INSERT INTO enrollment_semesters (name, display_order) VALUES
    ('1st Semester', 1), ('2nd Semester', 2)
ON CONFLICT (name) DO NOTHING;

CREATE TABLE IF NOT EXISTS pre_admission_codes (
    id SERIAL PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    used BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
INSERT INTO pre_admission_codes (code) VALUES
    ('SMA-PRE-2024-001'), ('SMA-PRE-2024-002'), ('SMA-PRE-2024-003')
ON CONFLICT (code) DO NOTHING;

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    role user_role NOT NULL,
    status user_status NOT NULL DEFAULT 'active',
    activated BOOLEAN NOT NULL DEFAULT TRUE,
    school_email VARCHAR(255) NULL,
    level_type VARCHAR(20) NULL CHECK (level_type IN ('BED', 'College', 'TESDA')),
    active_this_year BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS system_logs (
    id SERIAL PRIMARY KEY,
    actor_user_id INTEGER REFERENCES users(id),
    action VARCHAR NOT NULL,
    detail TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS sections (
    id SERIAL PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    level_type VARCHAR(20) NOT NULL CHECK (level_type IN ('BED', 'College', 'TESDA')),
    program VARCHAR(160) NOT NULL,
    year_level VARCHAR(40) NOT NULL,
    adviser_id INTEGER REFERENCES users(id),
    capacity INTEGER NOT NULL DEFAULT 40,
    academic_year VARCHAR(20) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS students (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    student_number VARCHAR(32) NOT NULL UNIQUE,
    enrollment_status enrollment_status NOT NULL DEFAULT 'pending',
    academic_year VARCHAR(20) NOT NULL,
    current_load_id INTEGER,
    program VARCHAR(160) NOT NULL,
    year_level VARCHAR(40) NOT NULL,
    section_id INTEGER REFERENCES sections(id)
);

CREATE TABLE IF NOT EXISTS enrollment_queue (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES students(id),
    document_url TEXT,
    verification_remarks TEXT,
    reviewed_by INTEGER REFERENCES users(id),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS classes_and_schedules (
    id SERIAL PRIMARY KEY,
    subject_code VARCHAR(32) NOT NULL,
    subject_name VARCHAR(180) NOT NULL,
    teacher_id INTEGER REFERENCES users(id),
    schedule_grid TEXT NOT NULL,
    room VARCHAR(80) NOT NULL,
    capacity INTEGER NOT NULL,
    enrolled_count INTEGER NOT NULL DEFAULT 0,
    adviser_section VARCHAR(40) NULL,
    units INTEGER NULL
);

DO $$ BEGIN
    CREATE TYPE enrollment_step_status AS ENUM ('not_started', 'in_progress', 'completed');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS student_enrollment_steps (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES students(id),
    step_key VARCHAR(40) NOT NULL,
    label VARCHAR(120) NOT NULL,
    status enrollment_step_status NOT NULL DEFAULT 'not_started',
    display_order INTEGER NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (student_id, step_key)
);

CREATE TABLE IF NOT EXISTS student_courses (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES students(id),
    academic_year VARCHAR(20) NOT NULL,
    semester VARCHAR(40) NOT NULL,
    subject_code VARCHAR(32) NOT NULL,
    subject_name VARCHAR(180) NOT NULL,
    units INTEGER NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enrolled'
);

CREATE TABLE IF NOT EXISTS student_grades (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES students(id),
    academic_year VARCHAR(20) NOT NULL,
    semester VARCHAR(40) NOT NULL,
    year_level VARCHAR(40) NOT NULL,
    section VARCHAR(40),
    subject_code VARCHAR(32) NOT NULL,
    subject_name VARCHAR(180) NOT NULL,
    units INTEGER NOT NULL,
    final_grade VARCHAR(20) NOT NULL,
    remark VARCHAR(80) NOT NULL
);

CREATE TABLE IF NOT EXISTS plan_of_study_subjects (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES students(id),
    year_level VARCHAR(40) NOT NULL,
    semester VARCHAR(40) NOT NULL,
    subject_code VARCHAR(32) NOT NULL,
    description VARCHAR(180) NOT NULL,
    units INTEGER NOT NULL,
    prerequisite VARCHAR(120) NOT NULL DEFAULT '—',
    curriculum_year VARCHAR(20) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'planned',
    display_order INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS academic_policy_documents (
    id SERIAL PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    file_name VARCHAR(180) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    page_count INTEGER NOT NULL,
    summary TEXT NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS academic_policy_pages (
    id SERIAL PRIMARY KEY,
    document_id INTEGER NOT NULL REFERENCES academic_policy_documents(id),
    page_number INTEGER NOT NULL,
    heading VARCHAR(180) NOT NULL,
    body TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS faq_entries (
    id SERIAL PRIMARY KEY,
    question VARCHAR(240) NOT NULL,
    answer TEXT NOT NULL,
    display_order INTEGER NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS pre_registrations (
    id SERIAL PRIMARY KEY,
    application_number VARCHAR(40) UNIQUE,
    enrollee_type VARCHAR(20) NOT NULL DEFAULT 'new',
    level_type VARCHAR(20) NOT NULL,
    level VARCHAR(120) NOT NULL DEFAULT '-',
    semester VARCHAR(40) NOT NULL DEFAULT '-',
    modality VARCHAR(40) NOT NULL DEFAULT 'FACE-TO-FACE',
    first_choice_course VARCHAR(160) NOT NULL DEFAULT '-',
    first_major_track VARCHAR(120) NOT NULL DEFAULT '-',
    second_choice_course VARCHAR(160) NOT NULL DEFAULT '-',
    second_major_track VARCHAR(120) NOT NULL DEFAULT '-',
    nc_level VARCHAR(20) NOT NULL DEFAULT '-',
    email VARCHAR(255) NOT NULL,
    contact_number VARCHAR(40) NOT NULL DEFAULT '-',
    lrn VARCHAR(20) NOT NULL DEFAULT '-',
    last_name VARCHAR(80) NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NOT NULL DEFAULT '-',
    date_of_birth DATE,
    place_of_birth VARCHAR(160) NOT NULL DEFAULT '-',
    gender VARCHAR(20) NOT NULL DEFAULT '-',
    nationality VARCHAR(60) NOT NULL DEFAULT 'FILIPINO',
    religion VARCHAR(60) NOT NULL DEFAULT '-',
    street_no VARCHAR(40) NOT NULL DEFAULT '-',
    street_name VARCHAR(120) NOT NULL DEFAULT '-',
    subdivision VARCHAR(120) NOT NULL DEFAULT '-',
    province VARCHAR(80) NOT NULL DEFAULT '-',
    town_city VARCHAR(80) NOT NULL DEFAULT '-',
    barangay VARCHAR(80) NOT NULL DEFAULT '-',
    father_name VARCHAR(160) NOT NULL DEFAULT '-',
    father_occupation VARCHAR(120) NOT NULL DEFAULT '-',
    father_mobile VARCHAR(40) NOT NULL DEFAULT '-',
    mother_name VARCHAR(160) NOT NULL DEFAULT '-',
    mother_occupation VARCHAR(120) NOT NULL DEFAULT '-',
    mother_mobile VARCHAR(40) NOT NULL DEFAULT '-',
    guardian_name VARCHAR(160) NOT NULL DEFAULT '-',
    guardian_occupation VARCHAR(120) NOT NULL DEFAULT '-',
    guardian_mobile VARCHAR(40) NOT NULL DEFAULT '-',
    guardian_email VARCHAR(255) NOT NULL DEFAULT '-',
    school_last_attended VARCHAR(180) NOT NULL DEFAULT '-',
    school_address VARCHAR(200) NOT NULL DEFAULT '-',
    school_year VARCHAR(20) NOT NULL DEFAULT '-',
    comorbidity VARCHAR(120) NOT NULL DEFAULT '-',
    parental_consent VARCHAR(20) NOT NULL DEFAULT '-',
    mode_of_transportation VARCHAR(40) NOT NULL DEFAULT '-',
    pre_admission_code VARCHAR(60) NOT NULL DEFAULT '-',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS student_ledgers (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES students(id),
    academic_year VARCHAR(20) NOT NULL,
    description VARCHAR(180) NOT NULL,
    amount_due NUMERIC(10,2) NOT NULL DEFAULT 0,
    amount_paid NUMERIC(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (student_id, academic_year, description)
);

CREATE TABLE IF NOT EXISTS app_settings (
    key VARCHAR PRIMARY KEY,
    value TEXT
);
INSERT INTO app_settings (key, value) VALUES
    ('academic_year', '2026-2027'),
    ('school_name', 'Steiner Maris Academy'),
    ('registration_open', '1')
ON CONFLICT (key) DO NOTHING;

-- Seed test accounts (one per role) with TEST12345KL bypass
-- First delete any old test accounts
DELETE FROM users WHERE email LIKE 'test-%@sma.test';

INSERT INTO users (email, password_hash, full_name, role, status, activated, created_at) VALUES
    ('test-admin@sma.test', '$2y$10$placeholder', 'Test Admin', 'admin', 'active', TRUE, NOW()),
    ('test-admission@sma.test', '$2y$10$placeholder', 'Test Admission', 'admission', 'active', TRUE, NOW()),
    ('test-registrar@sma.test', '$2y$10$placeholder', 'Test Registrar', 'registrar', 'active', TRUE, NOW()),
    ('test-teacher@sma.test', '$2y$10$placeholder', 'Test Teacher', 'teacher', 'active', TRUE, NOW()),
    ('test-student@sma.test', '$2y$10$placeholder', 'Test Student', 'student', 'active', TRUE, NOW())
ON CONFLICT (email) DO NOTHING;

-- Seed student record for test student
INSERT INTO students (user_id, student_number, enrollment_status, academic_year, program, year_level)
SELECT u.id, 'TEST-STU-001', 'enrolled', '2026-2027', 'General', '1st Year'
FROM users u WHERE u.email = 'test-student@sma.test'
ON CONFLICT (student_number) DO NOTHING;

-- Seed student ledger entries for test student (referencing test student by student_number)
INSERT INTO student_ledgers (student_id, academic_year, description, amount_due, amount_paid, status)
SELECT s.id, '2026-2027', 'Tuition Fee - 1st Semester', 50000.00, 25000.00, 'partial'
FROM students s WHERE s.student_number = 'TEST-STU-001'
ON CONFLICT (student_id, academic_year, description) DO NOTHING;

INSERT INTO student_ledgers (student_id, academic_year, description, amount_due, amount_paid, status)
SELECT s.id, '2026-2027', 'Laboratory Fee', 5000.00, 0.00, 'unpaid'
FROM students s WHERE s.student_number = 'TEST-STU-001'
ON CONFLICT (student_id, academic_year, description) DO NOTHING;

INSERT INTO student_ledgers (student_id, academic_year, description, amount_due, amount_paid, status)
SELECT s.id, '2026-2027', 'Library Fee', 2000.00, 2000.00, 'paid'
FROM students s WHERE s.student_number = 'TEST-STU-001'
ON CONFLICT (student_id, academic_year, description) DO NOTHING;

INSERT INTO student_ledgers (student_id, academic_year, description, amount_due, amount_paid, status)
SELECT s.id, '2026-2027', 'Miscellaneous Fee', 3000.00, 3000.00, 'paid'
FROM students s WHERE s.student_number = 'TEST-STU-001'
ON CONFLICT (student_id, academic_year, description) DO NOTHING;

-- Seed student courses (using correct integer FK students.id) - simplified seed for test student
-- MUST RUN BEFORE student_grades and plan_of_study_subjects which JOIN student_courses
INSERT INTO student_courses (student_id, academic_year, semester, subject_code, subject_name, units, status) VALUES
    ((SELECT id FROM students WHERE student_number = 'TEST-STU-001'), '2026-2027', '1st Semester', 'CS101', 'Introduction to Computer Science', 3, 'enrolled'),
    ((SELECT id FROM students WHERE student_number = 'TEST-STU-001'), '2026-2027', '1st Semester', 'MATH101', 'College Algebra', 3, 'enrolled'),
    ((SELECT id FROM students WHERE student_number = 'TEST-STU-001'), '2026-2027', '1st Semester', 'ENG101', 'English Composition', 3, 'enrolled'),
    ((SELECT id FROM students WHERE student_number = 'TEST-STU-001'), '2026-2027', '1st Semester', 'PHYS101', 'General Physics', 4, 'enrolled')
ON CONFLICT DO NOTHING;

-- Seed student grades (passed and failed for testing red/green indicators)
INSERT INTO student_grades (student_id, academic_year, semester, year_level, section, subject_code, subject_name, units, final_grade, remark) 
SELECT s.id, '2026-2027', '1st Semester', '1st Year', 'A', sc.subject_code, sc.subject_name, sc.units, 
       CASE WHEN sc.subject_code LIKE 'CS%' THEN '1.5' ELSE '3.5' END,
       CASE WHEN sc.subject_code LIKE 'CS%' THEN 'Passed' ELSE 'Failed' END
FROM students s
JOIN student_courses sc ON sc.student_id = s.id
WHERE s.student_number = 'TEST-STU-001'
ON CONFLICT DO NOTHING;

-- Seed plan of study subjects
INSERT INTO plan_of_study_subjects (student_id, year_level, semester, subject_code, description, units, prerequisite, curriculum_year, status, display_order) 
SELECT s.id, '1st Year', '1st Semester', sc.subject_code, sc.subject_name, sc.units, 'None', '2026', 'planned', 1
FROM students s
JOIN student_courses sc ON sc.student_id = s.id
WHERE s.student_number = 'TEST-STU-001'
ON CONFLICT DO NOTHING;

-- Seed academic policy documents
INSERT INTO academic_policy_documents (title, file_name, academic_year, page_count, summary, updated_at) VALUES
    ('Student Handbook 2026-2027', 'student_handbook_2026.pdf', '2026-2027', 45, 'Complete student handbook covering all academic policies, student rights, and campus regulations.', NOW()),
    ('Academic Calendar 2026-2027', 'academic_calendar_2026.pdf', '2026-2027', 12, 'Official academic calendar including enrollment periods, examination schedules, and holiday breaks.', NOW()),
    ('Code of Conduct', 'code_of_conduct.pdf', '2026-2027', 28, 'Student code of conduct including disciplinary policies, academic integrity, and campus behavior standards.', NOW())
ON CONFLICT DO NOTHING;

-- Seed academic policy pages
INSERT INTO academic_policy_pages (document_id, page_number, heading, body) 
SELECT d.id, 1, 'Introduction', 'Welcome to the Student Handbook. This document outlines the policies and procedures that govern student life at Steiner Maris Academy.'
FROM academic_policy_documents d WHERE d.title = 'Student Handbook 2026-2027'
ON CONFLICT DO NOTHING;

INSERT INTO academic_policy_pages (document_id, page_number, heading, body) 
SELECT d.id, 2, 'Academic Policies', 'All students are expected to maintain satisfactory academic progress. The minimum GPA requirement is 2.0 for undergraduate programs.'
FROM academic_policy_documents d WHERE d.title = 'Student Handbook 2026-2027'
ON CONFLICT DO NOTHING;

-- Seed FAQ entries
INSERT INTO faq_entries (question, answer, display_order, active, updated_at) VALUES
    ('How do I enroll for the next semester?', 'Log in to the student portal, go to the Enrollment page, and follow the 6-step enrollment process. Contact your academic advisor for assistance.', 1, TRUE, NOW()),
    ('How do I check my grades?', 'Go to the Grades page in the student portal. Grades are organized by academic year and semester. You can view your final grades and remarks for each subject.', 2, TRUE, NOW()),
    ('How do I view my Statement of Account?', 'Go to the Statement of Account page to view your complete payment history, outstanding balance, and payment status for all academic years.', 3, TRUE, NOW()),
    ('How do I pay my tuition?', 'You can pay your tuition through the Payment page or visit the cashier office. Online payment options include bank transfer and credit card.', 4, TRUE, NOW()),
    ('What is the passing grade?', 'For College programs, grades 1.0 to 3.0 are passing. For BED and TESDA programs, a numeric grade of 75 or above is passing. Grades of 3.5 and above are considered failing.', 5, TRUE, NOW()),
    ('How do I check my class schedule?', 'Go to the Subjects / Plan of Study page or the Schedule page in the student portal to view your class schedule.', 6, TRUE, NOW()),
    ('Who do I contact for academic advising?', 'Your assigned academic adviser is listed on your Classes page. You can also visit the Registrar''s office for assistance.', 7, TRUE, NOW()),
    ('What if I lose my student ID?', 'Visit the Registrar''s office to request a replacement ID. A replacement fee may apply.', 8, TRUE, NOW())
ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS teacher_attendance (
    id SERIAL PRIMARY KEY,
    teacher_id INTEGER NOT NULL REFERENCES users(id),
    work_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'present' CHECK (status IN ('present', 'absent', 'late', 'leave')),
    remarks VARCHAR(200) NULL,
    recorded_by INTEGER REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (teacher_id, work_date)
);

CREATE TABLE IF NOT EXISTS email_otps (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    email VARCHAR(255) NOT NULL,
    otp_hash TEXT NOT NULL,
    purpose VARCHAR(20) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    consumed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);