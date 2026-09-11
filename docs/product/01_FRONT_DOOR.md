# FANOOS — the front door: product spec and build plan

Written after auditing both FanoosLearn and the legacy `ArianGhsm/Dentistry1402TUMS` bot. This is the specification for the first product milestone, plus the handoff needed to build it.

---

## 1. The diagnosis this comes from

FanoosLearn's backend is genuinely solid: domain modules, two-level RBAC, versioned OpenAPI contracts, migrations, HMAC service auth with replay protection, protected media delivery, and a deployment pipeline with verified backup and automatic rollback that was exercised three times successfully in one session.

What it has never had is a **beginning**. Verified by search: there is no `createWorkspace` anywhere in the codebase and no `POST /workspaces` in `contracts/openapi/core-v1.yaml`. Every route is `/workspaces/{workspaceId}/...` — all of them assume a class that already exists. The bot says so out loud: «این ربات فضای آموزشی تازه‌ای ایجاد نمی‌کند».

So the product is not broken. It has no entrance. Every screen renders a world that was never populated, which is exactly why it reads as empty and hollow.

**The schema, however, is already right.** These tables exist and are migrated:

| table | holds |
| --- | --- |
| `directory_institutions` | universities / faculties |
| `directory_programs` | fields of study |
| `directory_cohorts` | entry years |
| `academic_terms` | semesters, with start and end dates |
| `tenant_workspaces` | the class itself |
| `tenant_workspace_memberships` | who belongs to it |

Nothing needs designing here. It needs filling, and a way to fill it.

---

## 2. The proven flow, extracted from the legacy bot

`bot_runtime/dent_bot/onboarding.py` in the legacy repo implements a twelve-step registration wizard that ran against real students. Its step map:

```
1  first-name        نام
2  last-name         نام خانوادگی
3  major             رشته تحصیلی        (from catalog)
4  province          استان دانشگاه       (paginated, 10 per page)
5  institution       دانشگاه/دانشکده     (filtered by chosen province)
6  entry-year        سال ورود
7  entry-term        نیم‌سال ورود
8  course-type       نوع دوره            (skipped entirely for آزاد)
9  student-number    شماره دانشجویی      (with «شماره دانشجویی ندارم» escape)
10 review            تأیید اطلاعات
11 contact           ارسال شماره موبایل
12 otp               کد یک‌بارمصرف
```

Before the wizard, a gateway offers two paths:

- **«دانشجوی ورودی ۱۴۰۲ دندانپزشکی تهران هستم»** — the known-class fast path, which then links to an existing site account by OTP or by site username/password.
- **«وارد کردن نام و نام خانوادگی»** — the generic path for students of any other university.

### The decisions worth preserving

These were earned against real users and should survive the port:

- back (`↩️ مرحله قبل`) and cancel available at **every** step;
- a live step counter (`مرحله ۴ از ۱۲`) that **adapts** — for آزاد institutions the total drops to 11 and later positions shift down, so the user is never shown a step that will not happen;
- long lists paginate rather than flooding the keyboard;
- each choice narrows the next (institutions are filtered by the chosen province);
- an escape hatch for students who genuinely have no student number, instead of a hard block;
- a review screen before anything is written;
- identity confirmed by phone + OTP, not asserted;
- Persian digits everywhere, via a dedicated `persian_datetime` helper.

### What was hardcoded and must become data

`START_CLASS = "👥 دانشجوی ورودی ۱۴۰۲ دندانپزشکی تهران هستم"` — one cohort, welded into a UI constant. In FANOOS the equivalent must be generated from `directory_*` rows, so the same screen serves any class.

Note what the wizard actually collects: major + province + institution + entry year + entry term. **That is precisely a class identity** — the same tuple `directory_programs` / `directory_institutions` / `directory_cohorts` model. The legacy wizard is, structurally, the missing front door already written down.

---

## 3. What to port, and what not to

The owner's instinct — bring the legacy work over rather than reinvent it — matches `AGENTS.md` §5 (reuse-first). But it must be the **design** that moves, not the source.

The legacy bot is a single `app.py` of 201KB, alongside `bot_home_classops_ux_v2.py` (40KB) and `classops_ux_v3.py` (44KB) — two parallel UI generations coexisting. That is the same condition we spent this session removing from FanoosLearn's web layer (~8400 lines deleted). Copying it in would reimport the disease. The legacy repo's own `PROJECT_LOGIC.md` says it plainly: *"do not copy the old working tree wholesale into this repository."*

| legacy file | size | verdict |
| --- | --- | --- |
| `onboarding.py` | 13KB | **port first** — the flow above |
| `class_operations.py`, `classops_*` | ~110KB | port next — daily class operations, the product core |
| `persian_datetime.py` | 4.5KB | port — proven Jalali handling |
| `reminder_policy.py` | 2.7KB | port — reminder logic |
| `payments.py`, `booklets.py`, `protected_media.py` | ~37KB | later — FANOOS already has equivalents; compare behavior, do not replace |
| `forensic_detector.py`, `pdf_fingerprint.py` | 150KB | **not now** — impressive, but irrelevant to making the product exist |

---

## 4. Milestone 1 — the front door

Four capabilities. All are missing; all are small next to what already exists.

**1. An owner creates a class.**
Owners (Arian, Hossein) pick institution / program / cohort / term from the directory — creating directory rows where they do not yet exist — and a `tenant_workspaces` row is created. No self-service class creation for anyone else; the owner is the authority, as decided.

**2. An owner appoints a representative.**
A membership with the representative role, granted by the owner. The RBAC model already supports it; there is no path to set it.

**3. A student joins.**
The twelve-step wizard above, generalized. The gateway's two paths become:
- *join the class you were invited to* (an invite link or class code the representative shares), and
- *register and find your class* (the full wizard, resolving to an existing `tenant_workspaces` row — or telling the student honestly that their class is not on FANOOS yet, rather than inventing one).

**4. Term dates are configurable.**
`academic_terms` has the columns; start and end dates must be settable per institution, from the bot, by the owner.

When these four exist, a real class lives in the system and every room already built starts to mean something.

---

## 5. Web parity is part of the milestone, not a follow-up

The legacy project implemented this only in the bot; the website never had it. FANOOS must not repeat that split — the owner's requirement is that bot and site stay in sync.

The architecture already answers how: **the backend owns the flow, the channels present it.** Concretely, each capability above is a backend service plus a contract entry in `contracts/openapi/core-v1.yaml`, consumed by the Telegram bot, the Bale bot, and the website alike. No channel gets its own copy of the rules, no channel becomes a second source of truth. This is what `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md` already mandates.

Practically: build the backend capability and its contract first, then the bot surface (where the proven UX lives), then the web surface. Do not build any of them as the source of truth.

---

## 6. Where to start, concretely

Build capability **1** end to end, alone, before anything else: owner creates a class.

It is the smallest of the four, it unblocks the other three (a representative needs a class; a student needs a class to join), and it converts the project from empty to real in a single step. Once one workspace exists, walking the bot as a student will surface the genuine gaps in priority order — instead of the speculative list we would otherwise argue about.

Suggested sequence after that: representative appointment → term dates → student join wizard → web parity pass.

---

## 7. Open questions for the owner

These need answers before the student-join wizard is built — not before capability 1:

- How does a student prove they belong to a class? The legacy used phone + OTP, plus optional site credentials. Is phone verification enough on its own for FANOOS, or must the representative approve each member?
- When a student's class does not exist on FANOOS yet, what should happen? Waiting list, a request to the owners, or a plain "not available yet"?
- Is the class code / invite link per class, per term, or single-use per student?
- Should one person be able to belong to more than one class at once? The schema permits it; the product has not decided.

---

## 8. The directory chain a class hangs from

Confirmed against the migrations. A class is the leaf of a nine-level chain, all of it already modelled:

```
directory_countries
  └── directory_provinces
        └── directory_cities
              └── directory_institutions   (city_id)
                    ├── directory_campuses
                    └── directory_faculties
                          ├── directory_departments      (optional)
                          └── directory_programs         (faculty_id, department_id?, code, degree_level)
                                └── directory_cohorts    (program_id, entry_year, label, starts_on, ends_on)
                                      └── tenant_workspaces   (cohort_id, slug, name, settings_json)
                                            └── academic_terms (workspace_id, term_key, starts_on, ends_on, status)
```

Two things follow.

**The legacy wizard and this chain are the same shape.** province → institution → major → entry year, exactly the path a student walks in `onboarding.py`. That is strong evidence the model is right and the flow is proven; they were designed from the same reality and have simply never been connected.

**Creating a class means walking this chain, not inserting one row.** The owner must not be made to create nine rows by hand. The service should find-or-create along the chain from the identity the owner supplies (province, institution, faculty, program, entry year), and the bot should present it as the same narrowing wizard the legacy bot already proved — each choice filtering the next, back and cancel at every step.

Seeding the directory with real Iranian geography and universities is a separate, later concern. For milestone 1, rows are created as the owner goes.
