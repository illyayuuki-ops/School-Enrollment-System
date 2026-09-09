import { createInsertSchema } from "drizzle-zod";
import {
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

export const insertUserSchema = createInsertSchema(usersTable);
export const insertStudentSchema = createInsertSchema(studentsTable);
export const insertEnrollmentQueueSchema =
  createInsertSchema(enrollmentQueueTable);
export const insertClassAndScheduleSchema = createInsertSchema(
  classesAndSchedulesTable,
);

export type User = typeof usersTable.$inferSelect;
export type Student = typeof studentsTable.$inferSelect;
export type EnrollmentQueue = typeof enrollmentQueueTable.$inferSelect;
export type ClassAndSchedule = typeof classesAndSchedulesTable.$inferSelect;
export type UserRole = z.infer<typeof insertUserSchema>["role"];