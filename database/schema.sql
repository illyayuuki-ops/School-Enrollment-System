CREATE TYPE user_role AS ENUM ('admin', 'admission', 'registrar', 'teacher', 'student');
CREATE TYPE user_status AS ENUM ('active', 'suspended');
CREATE TYPE enrollment_status AS ENUM ('pending', 'verified', 'enrolled');

CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  role user_role NOT NULL,
  status user_status NOT NULL DEFAULT 'active',
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
  year_level VARCHAR(40) NOT NULL
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
  enrolled_count INTEGER NOT NULL DEFAULT 0
);

CREATE TYPE enrollment_step_status AS ENUM ('not_started', 'in_progress', 'completed');

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