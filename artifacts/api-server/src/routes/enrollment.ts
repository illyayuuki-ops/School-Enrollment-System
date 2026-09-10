import { Router, type IRouter, type Request } from "express";
import {
  GetDashboardSummaryResponse,
  GetStudentEnrollmentResponse,
  ListEnrollmentQueueQueryParams,
  ListEnrollmentQueueResponse,
  ListAcademicPoliciesResponse,
  ListFaqEntriesResponse,
  ListStudentGradesQueryParams,
  ListStudentGradesResponse,
  ListStudentPlanOfStudyQueryParams,
  ListStudentPlanOfStudyResponse,
  ListStudentsResponse,
  LoginBody,
  LoginResponse,
  ReviewEnrollmentBody,
  ReviewEnrollmentParams,
} from "@workspace/api-zod";
import {
  academicPolicyDocumentsTable,
  academicPolicyPagesTable,
  db,
  faqEntriesTable,
  planOfStudySubjectsTable,
  studentCoursesTable,
  studentEnrollmentStepsTable,
  studentGradesTable,
  studentsTable,
} from "@workspace/db";
import { createHmac } from "node:crypto";
import { and, asc, eq } from "drizzle-orm";

type Role = "admin" | "admission" | "registrar" | "teacher" | "student";

type SessionUser = {
  id: number;
  email: string;
  fullName: string;
  role: Role;
  status: "active";
};

type Applicant = {
  id: number;
  studentNumber: string;
  fullName: string;
  email: string;
  program: string;
  submittedAt: string;
  documentCount: number;
  status: "pending" | "verified" | "enrolled";
  remarks: string | null;
};

const users: SessionUser[] = [
  { id: 1, email: "alex.morgan@northstar.edu", fullName: "Alex Morgan", role: "admin", status: "active" },
  { id: 2, email: "admissions@northstar.edu", fullName: "Jordan Lee", role: "admission", status: "active" },
  { id: 3, email: "registrar@northstar.edu", fullName: "Mia Chen", role: "registrar", status: "active" },
  { id: 4, email: "faculty@northstar.edu", fullName: "Eli Navarro", role: "teacher", status: "active" },
  { id: 5, email: "student@northstar.edu", fullName: "Ari Santos", role: "student", status: "active" },
];

const applicants: Applicant[] = [
  { id: 1, studentNumber: "2026-1048", fullName: "Maria Santos", email: "maria.santos@email.com", program: "BS Computer Science", submittedAt: "Today, 09:42", documentCount: 5, status: "pending", remarks: null },
  { id: 2, studentNumber: "2026-1047", fullName: "Daniel Tan", email: "daniel.tan@email.com", program: "BA Communication", submittedAt: "Today, 08:18", documentCount: 6, status: "pending", remarks: null },
  { id: 3, studentNumber: "2026-1041", fullName: "Leah Wong", email: "leah.wong@email.com", program: "BS Accountancy", submittedAt: "Yesterday, 16:05", documentCount: 4, status: "pending", remarks: "Please upload a clearer birth certificate." },
  { id: 4, studentNumber: "2026-1036", fullName: "Noah Reyes", email: "noah.reyes@email.com", program: "BS Information Systems", submittedAt: "Yesterday, 13:44", documentCount: 6, status: "verified", remarks: "Verified by Jordan Lee." },
];

const students = [
  { id: 1, studentNumber: "2025-0198", fullName: "Amara Cruz", program: "BS Computer Science", yearLevel: "3rd year", load: "21 units", status: "active" as const },
  { id: 2, studentNumber: "2025-0204", fullName: "Jon Bell", program: "BA Communication", yearLevel: "2nd year", load: "18 units", status: "active" as const },
  { id: 3, studentNumber: "2024-0121", fullName: "Sofia Lim", program: "BS Accountancy", yearLevel: "4th year", load: "15 units", status: "probation" as const },
  { id: 4, studentNumber: "2026-0013", fullName: "Ravi Kumar", program: "BS Information Systems", yearLevel: "1st year", load: "24 units", status: "pending" as const },
];

const secret = process.env.SESSION_SECRET ?? "development-session-secret";

function sign(value: string): string {
  return createHmac("sha256", secret).update(value).digest("base64url");
}

function setSession(res: Parameters<IRouter["post"]>[1] extends never ? never : any, user: SessionUser) {
  const encoded = Buffer.from(JSON.stringify(user)).toString("base64url");
  res.cookie("northstar_session", `${encoded}.${sign(encoded)}`, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    maxAge: 8 * 60 * 60 * 1000,
  });
}

function sessionFrom(req: Request): SessionUser | null {
  const raw = req.headers.cookie?.match(/(?:^|;\s*)northstar_session=([^;]+)/)?.[1];
  if (!raw) return null;
  const [encoded, signature] = raw.split(".");
  if (!encoded || !signature || sign(encoded) !== signature) return null;
  try {
    return JSON.parse(Buffer.from(encoded, "base64url").toString("utf8")) as SessionUser;
  } catch {
    return null;
  }
}

const router: IRouter = Router();

router.post("/auth/login", (req, res) => {
  const input = LoginBody.parse(req.body);
  const user = users.find((candidate) => candidate.role === input.role) ?? users[4];
  setSession(res, user);
  res.json(LoginResponse.parse(user));
});

router.get("/auth/session", (req, res) => {
  const user = sessionFrom(req);
  if (!user) return res.status(401).json({ error: "Not authenticated" });
  return res.json(user);
});

router.post("/auth/logout", (_req, res) => {
  res.clearCookie("northstar_session");
  res.status(204).send();
});

router.get("/dashboard/summary", (_req, res) => {
  res.json(GetDashboardSummaryResponse.parse({
    activeAcademicYear: "A.Y. 2026–2027",
    totalApplicants: 1284,
    pendingReview: applicants.filter((item) => item.status === "pending").length + 83,
    enrolledStudents: 4692,
    activeClasses: 186,
    completionRate: 78.6,
    recentActivity: [
      { id: 1, label: "Application verified", detail: "Maria Santos · 8 min ago", timestamp: "8 min ago", tone: "green" },
      { id: 2, label: "New application received", detail: "Daniel Tan · 24 min ago", timestamp: "24 min ago", tone: "blue" },
      { id: 3, label: "Document returned", detail: "Leah Wong · 1 hr ago", timestamp: "1 hr ago", tone: "amber" },
      { id: 4, label: "Section updated", detail: "BSCS 2A · 2 hrs ago", timestamp: "2 hrs ago", tone: "slate" },
    ],
  }));
});

router.get("/enrollment/queue", (req, res) => {
  const params = ListEnrollmentQueueQueryParams.parse(req.query);
  const rows = params.status === "all" ? applicants : applicants.filter((item) => item.status === params.status);
  res.json(ListEnrollmentQueueResponse.parse(rows));
});

router.post("/enrollment/queue/:id/review", (req, res) => {
  const params = ReviewEnrollmentParams.parse(req.params);
  const input = ReviewEnrollmentBody.parse(req.body);
  const applicant = applicants.find((item) => item.id === params.id);
  if (!applicant) return res.status(404).json({ error: "Applicant not found" });
  applicant.status = input.decision;
  applicant.remarks = input.remarks ?? null;
  return res.json(applicant);
});

router.get("/students", (_req, res) => {
  res.json(ListStudentsResponse.parse(students));
});

function requireStudent(req: Request, res: Parameters<IRouter["get"]>[1] extends never ? never : any) {
  const user = sessionFrom(req);
  if (!user) {
    res.status(401).json({ error: "Not authenticated" });
    return null;
  }
  if (user.role !== "student") {
    res.status(403).json({ error: "Student access required" });
    return null;
  }
  return user;
}

async function findStudentForUser(userId: number) {
  const [student] = await db
    .select()
    .from(studentsTable)
    .where(eq(studentsTable.userId, userId))
    .limit(1);
  return student;
}

router.get("/students/me/enrollment", async (req, res) => {
  const user = requireStudent(req, res);
  if (!user) return;

  const student = await findStudentForUser(user.id);
  if (!student) return res.status(404).json({ error: "Student record not found" });

  const [courses, steps] = await Promise.all([
    db
      .select()
      .from(studentCoursesTable)
      .where(
        and(
          eq(studentCoursesTable.studentId, student.id),
          eq(studentCoursesTable.academicYear, student.academicYear),
        ),
      )
      .orderBy(asc(studentCoursesTable.id)),
    db
      .select()
      .from(studentEnrollmentStepsTable)
      .where(eq(studentEnrollmentStepsTable.studentId, student.id))
      .orderBy(asc(studentEnrollmentStepsTable.displayOrder)),
  ]);

  return res.json(
    GetStudentEnrollmentResponse.parse({
      id: student.id,
      studentNumber: student.studentNumber,
      fullName: user.fullName,
      program: student.program,
      yearLevel: student.yearLevel,
      academicYear: student.academicYear,
      enrollmentStatus: student.enrollmentStatus,
      courses: courses.map((course) => ({
        code: course.subjectCode,
        title: course.subjectName,
        units: course.units,
        semester: course.semester,
        academicYear: course.academicYear,
        status: course.status,
      })),
      steps: steps.map((step) => ({
        key: step.stepKey,
        label: step.label,
        status: step.status,
        displayOrder: step.displayOrder,
      })),
    }),
  );
});

router.get("/students/me/grades", async (req, res) => {
  const user = requireStudent(req, res);
  if (!user) return;

  const params = ListStudentGradesQueryParams.parse(req.query);
  const student = await findStudentForUser(user.id);
  if (!student) return res.status(404).json({ error: "Student record not found" });

  const filters = [eq(studentGradesTable.studentId, student.id)];
  if (params.academicYear) {
    filters.push(eq(studentGradesTable.academicYear, params.academicYear));
  }
  if (params.semester) {
    filters.push(eq(studentGradesTable.semester, params.semester));
  }

  const rows = await db
    .select()
    .from(studentGradesTable)
    .where(and(...filters))
    .orderBy(asc(studentGradesTable.id));

  return res.json(
    ListStudentGradesResponse.parse(
      rows.map((row) => ({
        id: row.id,
        academicYear: row.academicYear,
        semester: row.semester,
        yearLevel: row.yearLevel,
        section: row.section,
        code: row.subjectCode,
        title: row.subjectName,
        units: row.units,
        finalGrade: row.finalGrade,
        remark: row.remark,
      })),
    ),
  );
});

router.get("/students/me/plan-of-study", async (req, res) => {
  const user = requireStudent(req, res);
  if (!user) return;

  const params = ListStudentPlanOfStudyQueryParams.parse(req.query);
  const student = await findStudentForUser(user.id);
  if (!student) return res.status(404).json({ error: "Student record not found" });

  const filters = [eq(planOfStudySubjectsTable.studentId, student.id)];
  if (params.yearLevel) {
    filters.push(eq(planOfStudySubjectsTable.yearLevel, params.yearLevel));
  }
  if (params.semester) {
    filters.push(eq(planOfStudySubjectsTable.semester, params.semester));
  }

  const rows = await db
    .select()
    .from(planOfStudySubjectsTable)
    .where(and(...filters))
    .orderBy(
      asc(planOfStudySubjectsTable.yearLevel),
      asc(planOfStudySubjectsTable.displayOrder),
    );

  return res.json(
    ListStudentPlanOfStudyResponse.parse(
      rows.map((row) => ({
        id: row.id,
        yearLevel: row.yearLevel,
        semester: row.semester,
        code: row.subjectCode,
        description: row.description,
        units: row.units,
        prerequisite: row.prerequisite,
        curriculumYear: row.curriculumYear,
        status: row.status,
        displayOrder: row.displayOrder,
      })),
    ),
  );
});

router.get("/academic-policies", async (_req, res) => {
  const [documents, pages] = await Promise.all([
    db
      .select()
      .from(academicPolicyDocumentsTable)
      .orderBy(asc(academicPolicyDocumentsTable.academicYear)),
    db
      .select()
      .from(academicPolicyPagesTable)
      .orderBy(
        asc(academicPolicyPagesTable.documentId),
        asc(academicPolicyPagesTable.pageNumber),
      ),
  ]);

  return res.json(
    ListAcademicPoliciesResponse.parse(
      documents.map((document) => ({
        id: document.id,
        title: document.title,
        fileName: document.fileName,
        academicYear: document.academicYear,
        pageCount: document.pageCount,
        summary: document.summary,
        updatedAt: document.updatedAt.toISOString(),
        pages: pages
          .filter((page) => page.documentId === document.id)
          .map((page) => ({
            pageNumber: page.pageNumber,
            heading: page.heading,
            body: page.body,
          })),
      })),
    ),
  );
});

router.get("/faq", async (_req, res) => {
  const rows = await db
    .select()
    .from(faqEntriesTable)
    .where(eq(faqEntriesTable.active, true))
    .orderBy(asc(faqEntriesTable.displayOrder));

  return res.json(
    ListFaqEntriesResponse.parse(
      rows.map((row) => ({
        id: row.id,
        question: row.question,
        answer: row.answer,
        displayOrder: row.displayOrder,
        updatedAt: row.updatedAt.toISOString(),
      })),
    ),
  );
});

export default router;