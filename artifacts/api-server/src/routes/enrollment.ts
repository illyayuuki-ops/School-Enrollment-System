import { Router, type IRouter, type Request } from "express";
import {
  GetDashboardSummaryResponse,
  ListEnrollmentQueueQueryParams,
  ListEnrollmentQueueResponse,
  ListStudentsResponse,
  LoginBody,
  LoginResponse,
  ReviewEnrollmentBody,
  ReviewEnrollmentParams,
} from "@workspace/api-zod";
import { createHmac } from "node:crypto";

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

export default router;