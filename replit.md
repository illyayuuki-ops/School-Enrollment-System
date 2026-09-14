# Northstar School Enrollment System

Northstar is a role-aware school enrollment operations portal for admissions, registrars, faculty, students, and administrators.

## Run & Operate

- `pnpm --filter @workspace/api-server run dev` — run the API server (port 5000)
- `pnpm run typecheck` — full typecheck across all packages
- `pnpm run build` — typecheck + build all packages
- `pnpm --filter @workspace/api-spec run codegen` — regenerate API hooks and Zod schemas from the OpenAPI spec
- `pnpm --filter @workspace/db run push` — push DB schema changes (dev only)
- Required env: `DATABASE_URL` — Postgres connection string
- Optional env: `ALLOW_TEST_LOGIN=1` — enables the TEST12345KL passwordless test bypass in `php/process_login.php`. **Must not be set in production.**

## Stack

- pnpm workspaces, Node.js 24, TypeScript 5.9
- API: Express 5
- DB: PostgreSQL + Drizzle ORM
- Validation: Zod (`zod/v4`), `drizzle-zod`
- API codegen: Orval (from OpenAPI spec)
- Build: esbuild (CJS bundle)

## Where things live

- `artifacts/school-enrollment` — responsive enrollment portal UI and role-specific views.
- `artifacts/api-server/src/routes/enrollment.ts` — authentication, dashboard, applicant queue, and student endpoints.
- `lib/api-spec/openapi.yaml` — API contract source of truth.
- `lib/db/src/schema/index.ts` — Drizzle schema for users, students, enrollment queue, and class schedules.
- `database/schema.sql` — portable PostgreSQL schema reference.
- `php/` — native PHP connection, session auth, reusable layout fragments, and dashboard reference implementation.

## Architecture decisions

- The API contract is generated from OpenAPI so the dashboard and server share the same response shapes.
- Role-aware navigation is centralized in the portal shell so each user sees only the workflows relevant to their role.
- The enrollment queue uses query-key invalidation after a review decision so the visible list stays in sync.
- Native PHP layout and authentication fragments are kept separately under `php/` for deployments that run the requested PHP application directly.

## Product

- Split-screen role-aware login experience.
- Overview dashboard with academic-year context, enrollment metrics, activity feed, and action center.
- Admission verification queue with status filters, search, document counts, and review decisions.
- Registrar masterlist with search and status filtering.
- Teacher, student, and administrator role-specific dashboard surfaces.

## User preferences

_Populate as you build — explicit user instructions worth remembering across sessions._

## Gotchas

_Populate as you build — sharp edges, "always run X before Y" rules._

## Pointers

- See the `pnpm-workspace` skill for workspace structure, TypeScript setup, and package details
