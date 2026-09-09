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