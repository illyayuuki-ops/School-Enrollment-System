import { createInsertSchema } from "drizzle-zod";
import {
  boolean,
  integer,
  pgEnum,
  pgTable,
  serial,
  text,
  timestamp,
  uniqueIndex,
  varchar,
} from "drizzle-orm/pg-core";
import { z } from "zod/v4";

export const userRole = pgEnum("user_role", [
  "admin",
  "admission",
  "registrar",
  "teacher",
  "student",
]);
export const userStatus = pgEnum("user_status", ["active", "suspended"]);
export const enrollmentStatus = pgEnum("enrollment_status", [
  "pending",
  "verified",
  "enrolled",
]);
export const enrollmentStepStatus = pgEnum("enrollment_step_status", [
  "not_started",
  "in_progress",
  "completed",
]);

export const usersTable = pgTable(
  "users",
  {
    id: serial("id").primaryKey(),
    email: varchar("email", { length: 255 }).notNull(),
    passwordHash: text("password_hash").notNull(),
    fullName: varchar("full_name", { length: 160 }).notNull(),
    role: userRole("role").notNull(),
    status: userStatus("status").notNull().default("active"),
    createdAt: timestamp("created_at", { withTimezone: true })
      .notNull()
      .defaultNow(),
  },
  (table) => ({
    emailIdx: uniqueIndex("users_email_idx").on(table.email),
  }),
);

export const studentsTable = pgTable(
  "students",
  {
    id: serial("id").primaryKey(),
    userId: integer("user_id").references(() => usersTable.id),
    studentNumber: varchar("student_number", { length: 32 }).notNull(),
    enrollmentStatus: enrollmentStatus("enrollment_status")
      .notNull()
      .default("pending"),
    academicYear: varchar("academic_year", { length: 20 }).notNull(),
    currentLoadId: integer("current_load_id"),
    program: varchar("program", { length: 160 }).notNull(),
    yearLevel: varchar("year_level", { length: 40 }).notNull(),
  },
  (table) => ({
    studentNumberIdx: uniqueIndex("students_student_number_idx").on(
      table.studentNumber,
    ),
  }),
);

export const enrollmentQueueTable = pgTable("enrollment_queue", {
  id: serial("id").primaryKey(),
  studentId: integer("student_id")
    .notNull()
    .references(() => studentsTable.id),
  documentUrl: text("document_url"),
  verificationRemarks: text("verification_remarks"),
  reviewedBy: integer("reviewed_by").references(() => usersTable.id),
  updatedAt: timestamp("updated_at", { withTimezone: true })
    .notNull()
    .defaultNow(),
});

export const classesAndSchedulesTable = pgTable("classes_and_schedules", {
  id: serial("id").primaryKey(),
  subjectCode: varchar("subject_code", { length: 32 }).notNull(),
  subjectName: varchar("subject_name", { length: 180 }).notNull(),
  teacherId: integer("teacher_id").references(() => usersTable.id),
  scheduleGrid: text("schedule_grid").notNull(),
  room: varchar("room", { length: 80 }).notNull(),
  capacity: integer("capacity").notNull(),
  enrolledCount: integer("enrolled_count").notNull().default(0),
});

export const studentEnrollmentStepsTable = pgTable(
  "student_enrollment_steps",
  {
    id: serial("id").primaryKey(),
    studentId: integer("student_id")
      .notNull()
      .references(() => studentsTable.id),
    stepKey: varchar("step_key", { length: 40 }).notNull(),
    label: varchar("label", { length: 120 }).notNull(),
    status: enrollmentStepStatus("status").notNull().default("not_started"),
    displayOrder: integer("display_order").notNull(),
    updatedAt: timestamp("updated_at", { withTimezone: true })
      .notNull()
      .defaultNow(),
  },
  (table) => ({
    studentStepIdx: uniqueIndex("student_enrollment_steps_student_step_idx").on(
      table.studentId,
      table.stepKey,
    ),
  }),
);

export const studentCoursesTable = pgTable("student_courses", {
  id: serial("id").primaryKey(),
  studentId: integer("student_id")
    .notNull()
    .references(() => studentsTable.id),
  academicYear: varchar("academic_year", { length: 20 }).notNull(),
  semester: varchar("semester", { length: 40 }).notNull(),
  subjectCode: varchar("subject_code", { length: 32 }).notNull(),
  subjectName: varchar("subject_name", { length: 180 }).notNull(),
  units: integer("units").notNull(),
  status: varchar("status", { length: 30 }).notNull().default("enrolled"),
});

export const studentGradesTable = pgTable("student_grades", {
  id: serial("id").primaryKey(),
  studentId: integer("student_id")
    .notNull()
    .references(() => studentsTable.id),
  academicYear: varchar("academic_year", { length: 20 }).notNull(),
  semester: varchar("semester", { length: 40 }).notNull(),
  yearLevel: varchar("year_level", { length: 40 }).notNull(),
  section: varchar("section", { length: 40 }),
  subjectCode: varchar("subject_code", { length: 32 }).notNull(),
  subjectName: varchar("subject_name", { length: 180 }).notNull(),
  units: integer("units").notNull(),
  finalGrade: varchar("final_grade", { length: 20 }).notNull(),
  remark: varchar("remark", { length: 80 }).notNull(),
});

export const planOfStudySubjectsTable = pgTable("plan_of_study_subjects", {
  id: serial("id").primaryKey(),
  studentId: integer("student_id")
    .notNull()
    .references(() => studentsTable.id),
  yearLevel: varchar("year_level", { length: 40 }).notNull(),
  semester: varchar("semester", { length: 40 }).notNull(),
  subjectCode: varchar("subject_code", { length: 32 }).notNull(),
  description: varchar("description", { length: 180 }).notNull(),
  units: integer("units").notNull(),
  prerequisite: varchar("prerequisite", { length: 120 }).notNull().default("—"),
  curriculumYear: varchar("curriculum_year", { length: 20 }).notNull(),
  status: varchar("status", { length: 30 }).notNull().default("planned"),
  displayOrder: integer("display_order").notNull(),
});

export const academicPolicyDocumentsTable = pgTable(
  "academic_policy_documents",
  {
    id: serial("id").primaryKey(),
    title: varchar("title", { length: 180 }).notNull(),
    fileName: varchar("file_name", { length: 180 }).notNull(),
    academicYear: varchar("academic_year", { length: 20 }).notNull(),
    pageCount: integer("page_count").notNull(),
    summary: text("summary").notNull(),
    updatedAt: timestamp("updated_at", { withTimezone: true })
      .notNull()
      .defaultNow(),
  },
);

export const academicPolicyPagesTable = pgTable("academic_policy_pages", {
  id: serial("id").primaryKey(),
  documentId: integer("document_id")
    .notNull()
    .references(() => academicPolicyDocumentsTable.id),
  pageNumber: integer("page_number").notNull(),
  heading: varchar("heading", { length: 180 }).notNull(),
  body: text("body").notNull(),
});

export const faqEntriesTable = pgTable("faq_entries", {
  id: serial("id").primaryKey(),
  question: varchar("question", { length: 240 }).notNull(),
  answer: text("answer").notNull(),
  displayOrder: integer("display_order").notNull(),
  active: boolean("active").notNull().default(true),
  updatedAt: timestamp("updated_at", { withTimezone: true })
    .notNull()
    .defaultNow(),
});

export const insertUserSchema = createInsertSchema(usersTable);
export const insertStudentSchema = createInsertSchema(studentsTable);
export const insertEnrollmentQueueSchema =
  createInsertSchema(enrollmentQueueTable);
export const insertClassAndScheduleSchema = createInsertSchema(
  classesAndSchedulesTable,
);
export const insertStudentEnrollmentStepSchema = createInsertSchema(
  studentEnrollmentStepsTable,
);
export const insertStudentCourseSchema = createInsertSchema(studentCoursesTable);
export const insertStudentGradeSchema = createInsertSchema(studentGradesTable);
export const insertPlanOfStudySubjectSchema = createInsertSchema(
  planOfStudySubjectsTable,
);
export const insertAcademicPolicyDocumentSchema = createInsertSchema(
  academicPolicyDocumentsTable,
);
export const insertAcademicPolicyPageSchema = createInsertSchema(
  academicPolicyPagesTable,
);
export const insertFaqEntrySchema = createInsertSchema(faqEntriesTable);

export type User = typeof usersTable.$inferSelect;
export type Student = typeof studentsTable.$inferSelect;
export type EnrollmentQueue = typeof enrollmentQueueTable.$inferSelect;
export type ClassAndSchedule = typeof classesAndSchedulesTable.$inferSelect;
export type StudentEnrollmentStep =
  typeof studentEnrollmentStepsTable.$inferSelect;
export type StudentCourse = typeof studentCoursesTable.$inferSelect;
export type StudentGrade = typeof studentGradesTable.$inferSelect;
export type PlanOfStudySubject = typeof planOfStudySubjectsTable.$inferSelect;
export type AcademicPolicyDocument =
  typeof academicPolicyDocumentsTable.$inferSelect;
export type AcademicPolicyPage = typeof academicPolicyPagesTable.$inferSelect;
export type FaqEntry = typeof faqEntriesTable.$inferSelect;
export type UserRole = z.infer<typeof insertUserSchema>["role"];