import {
  ArrowUpRight,
  BookOpen,
  CalendarDays,
  Check,
  CheckCircle2,
  ChevronDown,
  ChevronRight,
  Clock3,
  Download,
  FileText,
  GraduationCap,
  HelpCircle,
  Info,
  MapPin,
  Printer,
  Search,
  ShieldCheck,
  UserRound,
  UsersRound,
} from "lucide-react";
import { Link } from "wouter";
import { useMemo, useState, type ReactNode } from "react";
import {
  getGetDashboardSummaryQueryKey,
  getGetSessionQueryKey,
  getGetStudentEnrollmentQueryKey,
  getListAcademicPoliciesQueryKey,
  getListFaqEntriesQueryKey,
  getListStudentGradesQueryKey,
  getListStudentPlanOfStudyQueryKey,
  useGetDashboardSummary,
  useGetSession,
  useGetStudentEnrollment,
  useListAcademicPolicies,
  useListFaqEntries,
  useListStudentGrades,
  useListStudentPlanOfStudy,
} from "@workspace/api-client-react";

export function TeacherClasses() {
  const summary = useGetDashboardSummary({
    query: { retry: 1, queryKey: getGetDashboardSummaryQueryKey() },
  });
  const cards = [
    {
      code: "BIO 214",
      title: "Cellular Systems",
      time: "Mon / Wed · 09:00–10:30",
      room: "Science Hall 204",
      enrolled: 28,
      capacity: 32,
    },
    {
      code: "BIO 318",
      title: "Research Methods",
      time: "Tue / Thu · 13:00–14:30",
      room: "Northstar Lab 2",
      enrolled: 19,
      capacity: 24,
    },
    {
      code: "SEM 401",
      title: "Senior Seminar",
      time: "Friday · 10:00–12:00",
      room: "Library Forum",
      enrolled: 12,
      capacity: 16,
    },
  ];
  return (
    <RoleFrame
      eyebrow="Faculty workspace"
      title="Your active classes"
      subtitle="A focused view of today’s teaching load and roster health."
    >
      <div className="role-stat-strip">
        <div>
          <span>Active classes</span>
          <strong>{summary.data?.activeClasses ?? "—"}</strong>
        </div>
        <div>
          <span>Total roster</span>
          <strong>59</strong>
        </div>
        <div>
          <span>Attendance today</span>
          <strong>94.8%</strong>
        </div>
      </div>
      <div className="class-grid">
        {cards.map((card) => (
          <article
            className="class-card"
            key={card.code}
            data-testid={`card-class-${card.code.replace(" ", "-")}`}
          >
            <div className="class-card-top">
              <span className="class-code">{card.code}</span>
              <span className="class-dot" />
            </div>
            <h2>{card.title}</h2>
            <div className="class-meta">
              <span>
                <CalendarDays size={15} />
                {card.time}
              </span>
              <span>
                <MapPin size={15} />
                {card.room}
              </span>
            </div>
            <div className="roster-line">
              <div>
                <strong>{card.enrolled}</strong>
                <span> / {card.capacity} students</span>
              </div>
              <div className="roster-bar">
                <span style={{ width: `${(card.enrolled / card.capacity) * 100}%` }} />
              </div>
            </div>
            <button
              className="class-action"
              onClick={() => window.alert(`Roster for ${card.code}`)}
              data-testid={`button-roster-${card.code.replace(" ", "-")}`}
            >
              Open roster <ArrowUpRight size={15} />
            </button>
          </article>
        ))}
      </div>
    </RoleFrame>
  );
}

export function StudentEnrollment() {
  const session = useGetSession({
    query: { retry: false, queryKey: getGetSessionQueryKey() },
  });
  const enrollment = useGetStudentEnrollment({
    query: { retry: false, queryKey: getGetStudentEnrollmentQueryKey() },
  });
  const record = enrollment.data;
  const firstName = session.data?.fullName?.split(" ")[0] || "student";
  const year = record?.academicYear || "—";
  const steps = record?.steps ?? [];
  const completedSteps = steps.filter((step) => step.status === "completed").length;
  const isComplete = steps.length > 0 && completedSteps === steps.length;
  const statusLabel = record?.enrollmentStatus || "pending";

  return (
    <RoleFrame
      eyebrow="Student portal"
      title={`You're on track, ${firstName}.`}
      subtitle="Your enrollment record for the current academic year."
    >
      <div className="student-welcome-strip">
        <div className="student-welcome-copy">
          <span className="eyebrow">Welcome back</span>
          <h2>One clear view of your academic record.</h2>
          <p>
            Check your enrollment status, review your subjects, and keep your
            next steps close.
          </p>
        </div>
        <div className="student-id-card">
          <div className="avatar avatar-large">
            {session.data?.fullName
              ? session.data.fullName
                  .split(" ")
                  .map((part) => part[0])
                  .slice(0, 2)
                  .join("")
              : "ST"}
          </div>
          <div>
            <span>Student account</span>
            <strong>{record?.fullName || session.data?.fullName || "Your account"}</strong>
            <small>AY {year}</small>
          </div>
        </div>
      </div>
      {enrollment.isLoading ? (
        <DataState message="Loading your enrollment record…" />
      ) : enrollment.isError || !record ? (
        <DataState message="We couldn't load your enrollment record. Please refresh and try again." error />
      ) : (
        <>
          <div className="enrollment-hero">
            <div className="enrollment-check">
              <CheckCircle2 size={25} />
            </div>
            <div>
              <span className="eyebrow">Enrollment status</span>
              <h2>{formatStatus(statusLabel)}</h2>
              <p>
                {isComplete
                  ? `Your registration is complete for AY ${year}.`
                  : `Your enrollment record is being processed for AY ${year}.`}
              </p>
            </div>
            <span className={`status-badge ${statusLabel === "enrolled" ? "verified" : statusLabel}`}>
              {statusLabel}
            </span>
          </div>
          <div className="student-progress-card surface-card">
            <div className="card-heading">
              <div>
                <div className="eyebrow">Enrollment progress</div>
                <h2>{isComplete ? "Your path is complete" : "Your next steps"}</h2>
              </div>
              <span className="progress-date">
                {completedSteps} of {steps.length} complete
              </span>
            </div>
            <div className="student-stepper">
              {steps.map((step, index) => (
                <div className="student-step" key={step.key}>
                  <span className={`student-step-number ${step.status !== "completed" ? "incomplete" : ""}`}>
                    {step.status === "completed" ? <Check size={13} strokeWidth={3} /> : index + 1}
                  </span>
                  <span>{step.label}</span>
                  {index < steps.length - 1 && <i />}
                </div>
              ))}
            </div>
          </div>
          <div className="student-load-grid">
            <section className="surface-card">
              <div className="card-heading">
                <div>
                  <div className="eyebrow">Study load</div>
                  <h2>Current courses</h2>
                </div>
                <span className="load-total">
                  {record.courses.reduce((total, course) => total + course.units, 0)} units
                </span>
              </div>
              {record.courses.length === 0 ? (
                <div className="table-state">No courses are attached to this enrollment record.</div>
              ) : (
                record.courses.map((course, index) => (
                  <div className="course-row" key={`${course.code}-${course.semester}`}>
                    <span className="course-index">{String(index + 1).padStart(2, "0")}</span>
                    <strong>{course.code} · {course.title}</strong>
                    <span className="course-unit">{course.units} units</span>
                  </div>
                ))
              )}
            </section>
            <section className="surface-card next-card">
              <div className="eyebrow">Next on your path</div>
              <h2>Keep your record current</h2>
              <p>Update your contact information before the next registration window opens.</p>
              <Link className="primary-button" href="/student/faq">
                Find an answer <ArrowUpRight size={15} />
              </Link>
              <div className="next-note">
                <Clock3 size={15} /> Your academic record is saved in the portal
              </div>
            </section>
          </div>
        </>
      )}
    </RoleFrame>
  );
}

export function StudentGrades() {
  const [year, setYear] = useState("2026");
  const [semester, setSemester] = useState("First semester");
  const enrollment = useGetStudentEnrollment({
    query: { retry: false, queryKey: getGetStudentEnrollmentQueryKey() },
  });
  const gradeParams = {
    academicYear: year,
    semester: semester === "Summary" ? undefined : semester,
  };
  const grades = useListStudentGrades(gradeParams, {
    query: {
      retry: 1,
      queryKey: getListStudentGradesQueryKey(gradeParams),
    },
  });
  const average = useMemo(() => {
    const numeric = (grades.data ?? [])
      .map((row) => Number(row.finalGrade))
      .filter((value) => Number.isFinite(value));
    return numeric.length ? (numeric.reduce((total, value) => total + value, 0) / numeric.length).toFixed(2) : "—";
  }, [grades.data]);
  const totalUnits = (grades.data ?? []).reduce((total, row) => total + row.units, 0);

  return (
    <RoleFrame eyebrow="Academic record" title="Grades" subtitle="Review your subjects and final marks by academic year.">
      <div className="record-toolbar">
        <label>
          <span>Academic year</span>
          <select value={year} onChange={(event) => setYear(event.target.value)}>
            <option>2026</option>
            <option>2025</option>
            <option>2024</option>
          </select>
        </label>
        <button className="secondary-button" onClick={() => window.print()}>
          <Printer size={15} /> Print record
        </button>
      </div>
      <section className="academic-record surface-card">
        <div className="record-year-bar">
          <span><CheckCircle2 size={14} /> Academic Year {year}</span>
          <span className="record-meta">{enrollment.data?.program || "Student academic record"}</span>
        </div>
        <div className="record-section-heading">
          <div>
            <span className="eyebrow">Section: {grades.data?.[0]?.section || "—"}</span>
            <h2>{enrollment.data?.yearLevel || "Academic"} · {semester}</h2>
          </div>
          <div className="record-tabs">
            {["First semester", "Second semester", "Summary"].map((item) => (
              <button className={semester === item ? "active" : ""} onClick={() => setSemester(item)} key={item}>
                {item}
              </button>
            ))}
          </div>
        </div>
        {grades.isLoading ? (
          <DataState message="Loading grades…" />
        ) : grades.isError ? (
          <DataState message="We couldn't load your grades. Please try again." error />
        ) : grades.data?.length ? (
          <>
            <div className="table-wrap">
              <table className="data-table student-table">
                <thead><tr><th>#</th><th>Subject</th><th>Units</th><th>Final grade</th><th>Remarks</th></tr></thead>
                <tbody>
                  {grades.data.map((row, index) => (
                    <tr key={row.id}>
                      <td className="record-index">{index + 1}</td>
                      <td><strong>{row.code}</strong><span>{row.title}</span></td>
                      <td>{row.units}</td>
                      <td className="grade-value">{row.finalGrade}</td>
                      <td><span className="status-badge active">{row.remark}</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="record-summary">
              <span>Semester average</span><strong>{average}</strong>
              <span className="record-summary-note">Good standing · {totalUnits} units completed</span>
            </div>
          </>
        ) : (
          <DataState message={`No grades are available for ${year} ${semester.toLowerCase()}.`} />
        )}
      </section>
      <div className="collapsed-years">
        <button><ChevronRight size={15} /> Academic Year 2025</button>
        <button><ChevronRight size={15} /> Academic Year 2024</button>
      </div>
    </RoleFrame>
  );
}

export function StudentPlanOfStudy() {
  const [openYear, setOpenYear] = useState("First year");
  const plan = useListStudentPlanOfStudy(undefined, {
    query: {
      retry: 1,
      queryKey: getListStudentPlanOfStudyQueryKey(),
    },
  });
  const subjectsByYear = useMemo(() => {
    const groups = new Map<string, typeof plan.data>();
    for (const subject of plan.data ?? []) {
      groups.set(subject.yearLevel, [...(groups.get(subject.yearLevel) ?? []), subject]);
    }
    return groups;
  }, [plan.data]);
  const yearLevels = Array.from(new Set([
    "First year",
    "Second year",
    "Third year",
    "Fourth year",
    ...Array.from(subjectsByYear.keys()),
  ]));

  return (
    <RoleFrame eyebrow="Academic record" title="Plan of study" subtitle="Follow the subjects that make up your degree, year by year.">
      <div className="plan-note">
        <Info size={16} />
        <span>Subjects marked with a check are in your current curriculum. Your registrar can help with substitutions or changes.</span>
      </div>
      {plan.isLoading ? <DataState message="Loading your plan of study…" /> : plan.isError ? <DataState message="We couldn't load your plan of study. Please try again." error /> : (
        <div className="study-accordion">
          {yearLevels.map((yearLevel) => {
            const rows = subjectsByYear.get(yearLevel) ?? [];
            const units = rows.reduce((total, row) => total + row.units, 0);
            return (
              <section className={`study-year surface-card ${openYear === yearLevel ? "open" : ""}`} key={yearLevel}>
                <button className="study-year-toggle" onClick={() => setOpenYear(openYear === yearLevel ? "" : yearLevel)}>
                  <span><ChevronDown size={16} className="year-chevron" /> {yearLevel}</span>
                  <small>{rows.length ? `${rows.length} subjects · ${units} units` : "No subjects listed"}</small>
                </button>
                {openYear === yearLevel && rows.length > 0 && (
                  <div className="study-year-content">
                    <div className="table-wrap">
                      <table className="data-table student-table plan-table">
                        <thead><tr><th>#</th><th>Subject code</th><th>Description</th><th>Units</th><th>Pre-requisite</th><th>Status</th><th>Sem</th><th>Curriculum</th></tr></thead>
                        <tbody>{rows.map((row, index) => (
                          <tr key={row.id}>
                            <td>{index + 1}</td><td><strong>{row.code}</strong></td><td>{row.description}</td><td>{row.units}</td><td>{row.prerequisite}</td>
                            <td>{row.status === "completed" || row.status === "current" ? <Check size={14} className="table-check" /> : "Planned"}</td>
                            <td>{row.semester}</td><td>{row.curriculumYear}</td>
                          </tr>
                        ))}</tbody>
                      </table>
                    </div>
                  </div>
                )}
              </section>
            );
          })}
        </div>
      )}
    </RoleFrame>
  );
}

export function StudentAcademicPolicies() {
  const [page, setPage] = useState(1);
  const policies = useListAcademicPolicies({
    query: { retry: 1, queryKey: getListAcademicPoliciesQueryKey() },
  });
  const document = policies.data?.[0];
  const selectedPage = document?.pages.find((item) => item.pageNumber === page) ?? document?.pages[0];
  const currentPage = selectedPage?.pageNumber ?? page;

  return (
    <RoleFrame eyebrow="Student resources" title="Academic policies" subtitle="The handbook and policies that guide your academic journey.">
      {policies.isLoading ? <DataState message="Loading academic policies…" /> : policies.isError ? <DataState message="We couldn't load the academic policies. Please try again." error /> : !document ? <DataState message="No policy documents have been published yet." /> : (
        <>
          <div className="policy-alert">
            <ShieldCheck size={17} />
            <div><strong>Keep this reference close.</strong><span>{document.summary}</span></div>
            <button onClick={() => window.print()}><Download size={14} /> Save copy</button>
          </div>
          <section className="policy-viewer surface-card">
            <div className="viewer-toolbar">
              <div className="viewer-file"><FileText size={18} /><strong>{document.title} · {document.fileName}</strong></div>
              <div className="viewer-controls">
                <button onClick={() => setPage(Math.max(1, currentPage - 1))} aria-label="Previous page">−</button>
                <span>{currentPage} <i>/</i> {document.pageCount}</span>
                <button onClick={() => setPage(Math.min(document.pageCount, currentPage + 1))} aria-label="Next page">+</button>
                <span className="zoom-label">100%</span>
                <button onClick={() => window.print()} aria-label="Print policy"><Printer size={15} /></button>
              </div>
            </div>
            <div className="viewer-body">
              <aside className="viewer-thumbs">
                {document.pages.slice(0, 3).map((thumb) => (
                  <button className={currentPage === thumb.pageNumber ? "active" : ""} onClick={() => setPage(thumb.pageNumber)} key={thumb.pageNumber}>
                    <div><FileText size={18} /></div><span>{thumb.pageNumber}</span>
                  </button>
                ))}
              </aside>
              <article className="policy-paper">
                <div className="paper-header"><div className="paper-seal">N</div><div><strong>NORTHSTAR UNIVERSITY</strong><span>Office of the Registrar</span></div><span>Academic year {document.academicYear}</span></div>
                <h2>{document.title}</h2><div className="paper-rule" />
                <h3>{selectedPage?.heading || "Policy page"}</h3>
                <p>{selectedPage?.body || "This policy page has no published content yet."}</p>
                <span className="paper-page">{currentPage}</span>
              </article>
            </div>
          </section>
        </>
      )}
    </RoleFrame>
  );
}

export function StudentFaq() {
  const [open, setOpen] = useState<number | null>(0);
  const [search, setSearch] = useState("");
  const faq = useListFaqEntries({
    query: { retry: 1, queryKey: getListFaqEntriesQueryKey() },
  });
  const filtered = (faq.data ?? []).filter((entry) =>
    entry.question.toLowerCase().includes(search.toLowerCase()),
  );
  return (
    <RoleFrame eyebrow="Student resources" title="Frequently asked questions" subtitle="Quick guidance for the questions students ask most often.">
      <div className="faq-search"><Search size={16} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search questions" aria-label="Search frequently asked questions" /></div>
      {faq.isLoading ? <DataState message="Loading frequently asked questions…" /> : faq.isError ? <DataState message="We couldn't load the frequently asked questions. Please try again." error /> : (
        <section className="faq-list">
          {filtered.map((entry, index) => (
            <article className={`faq-item ${open === index ? "open" : ""}`} key={entry.id}>
              <button onClick={() => setOpen(open === index ? null : index)}><span>{entry.question}</span><ChevronDown size={16} /></button>
              {open === index && <div className="faq-answer"><CheckCircle2 size={15} /><p>{entry.answer}</p></div>}
            </article>
          ))}
          {filtered.length === 0 && <div className="table-state empty-state"><HelpCircle size={28} /><strong>No matching questions</strong><p>Try a shorter search or email the registrar’s office.</p></div>}
        </section>
      )}
      <div className="faq-contact"><div className="settings-icon"><UserRound size={17} /></div><div><strong>Still need a hand?</strong><span>The registrar’s office usually replies within one business day.</span></div><a className="secondary-button" href="mailto:registrar@northstar.edu">Contact registrar <ArrowUpRight size={14} /></a></div>
    </RoleFrame>
  );
}

export function AdminSettings() {
  return <RoleFrame eyebrow="Administration" title="System controls" subtitle="Shape the workspace around how your institution operates."><div className="settings-grid"><section className="surface-card settings-section"><div className="settings-icon"><UsersRound size={18} /></div><div><h2>Account management</h2><p>Manage role access and staff invitations.</p></div><button className="secondary-button" onClick={() => window.alert("Account management is available to administrators.")} data-testid="button-manage-accounts">Manage accounts <ArrowUpRight size={15} /></button></section><section className="surface-card settings-section"><div className="settings-icon"><BookOpen size={18} /></div><div><h2>Academic year</h2><p>Current cycle · <strong>2025–26</strong></p></div><button className="secondary-button" onClick={() => window.alert("Academic year configuration")} data-testid="button-configure-year">Configure <ArrowUpRight size={15} /></button></section><section className="surface-card settings-section"><div className="settings-icon"><GraduationCap size={18} /></div><div><h2>Brand placeholders</h2><p>Logo, favicon, and sign-in background.</p></div><button className="secondary-button" onClick={() => window.alert("Brand customization")} data-testid="button-configure-brand">Customize <ArrowUpRight size={15} /></button></section></div><div className="settings-note"><div className="signal"><span className="signal-dot" /> All systems operational</div><span>Last configuration review · Today, 08:42</span></div></RoleFrame>;
}

function formatStatus(status: string) {
  return status === "enrolled" ? "Enrolled and active" : status === "verified" ? "Verified and active" : "Enrollment pending";
}

function DataState({ message, error = false }: { message: string; error?: boolean }) {
  return <div className={`table-state ${error ? "form-error" : ""}`} role={error ? "alert" : undefined}>{message}</div>;
}

function RoleFrame({ eyebrow, title, subtitle, children }: { eyebrow: string; title: string; subtitle: string; children: ReactNode }) {
  return <div className="content-frame"><div className="page-intro"><div><div className="eyebrow">{eyebrow}</div><h1>{title}</h1><p className="page-subtitle">{subtitle}</p></div></div>{children}</div>;
}