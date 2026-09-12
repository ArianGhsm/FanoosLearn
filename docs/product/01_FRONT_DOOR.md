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

---

## 9. Product decisions (answered by the owner)

Section 7's open questions are settled. These are product law now; build to them.

**Membership is proven by phone verification plus the representative's act.**
Not phone alone — someone who merely knows a class's details must not be able to walk in.

**A student whose class does not exist yet raises a creation request to the owners.**
Not a silent dead end and not a passive waiting list: Arian and Hossein are told that someone wants a class that does not exist, and can act on it. This adds a capability the original four did not cover — a durable class-creation request, visible to owners. Treat it as capability 5.

**Invite codes are single-use, issued per student.**
The representative generates one code for one person. A leaked code costs one seat, once.

**A person may belong to several classes at the same time.**
Guest students, transfers, dual programs. The schema already permits it, and — importantly — the platform already has the machinery: `selected_workspace_id`, the `/workspaces/select` route, and the bot's workspace switcher all exist and are live. Multi-class was already built for; this decision just confirms it should stay.

### Correction — what the single-use code actually is

An earlier draft of this section read the single-use code as an invite issued by the
representative, and concluded that a separate approval step would be redundant. The owner
corrected it: **the single-use code is the phone OTP**, sent to the student during the
wizard. It proves the phone, and it leaves the platform holding a verified number for that
student. It is not an invitation and it is not the representative's decision.

The representative's approval is a **separate, real step** that happens after verification.

So the join flow is:

```
student runs the wizard and selects their own class
   (province -> institution -> faculty/program -> entry year)
  -> student submits a phone number
  -> single-use code is sent and verified        <- the platform now holds a verified phone
  -> a confirmation request is raised to that class's representative
     (reachable from either the bot or the website)
  -> the representative approves
  -> membership is created and the student enters the class
```

Two consequences to build to:

- The class a student joins is **already determined** by the directory identity they picked;
  the representative is approving a person, not choosing a class.
- Because a representative exists per class, the approval request must route to the
  representative **of that specific class** — not to the owners, and not to every representative.

A student who selects a class that does not exist on FANOOS yet never reaches the approval
step at all; that is the capability-5 path, a creation request raised to the owners.

## 10. Representatives are operators of their own class

Appointing a representative is not a label. Once appointed for a class, the representative
must be able to run it themselves — approving members is the first such power, and the
day-to-day class operations ported from the legacy bot (`class_operations.py`, `classops_*`)
are the rest. The owner's words: when you appoint a representative for a class, "خودش باید
بتونه اینها رو انجام بده".

The owners (Arian, Hossein) stay the only authority for creating classes and appointing
representatives. Everything inside a class belongs to its representative.

This is a scoping rule for every capability from here on: ask whether the action is
*about* a class (owner) or *inside* a class (representative), and put the permission on the
matching side of that line. The RBAC model already separates platform scope from workspace
scope, so this needs no new machinery — only the discipline to use the right one.

## 11. Correction — tiered access replaces the approval gate at join time

§9's flow made membership itself wait on the representative: verify phone, then raise an
approval request, then the representative decides, then membership is created. Shipped
version reverses the order of the last two steps. The owner's instruction: a student who
finishes the wizard and verifies their phone becomes a member **immediately**, at a new
**limited** role — enough to buy things (e.g. a representative selling notes to the whole
cohort) and receive notifications — not the full `student` role. Reaching anything
class-internal (schedule, grades, exams) still requires the representative's approval,
requested from inside the bot; the bot explains this in place rather than refusing silently,
and the underlying permission check is always enforced server-side, never by the bot
deciding not to show a button.

Two reasons drove this, both from the owner directly:

- Representatives are not a buildable capability yet — §10 describes the role but nothing
  appoints one today — so a gate that only a representative can open would be a dead end for
  every student who joins before one exists.
- The commercial case doesn't want the wait: someone selling notes to a cohort needs buyers
  to be members the moment they verify, not after a representative gets around to approving
  them one by one.

So the join flow actually built is:

```
student runs the wizard and selects their own class
   (province -> institution -> faculty -> program -> entry year)
  -> student submits a phone number
  -> single-use code is sent and verified   <- the platform now holds a verified phone
  -> the account is created/found regardless of whether the class resolves
  -> if the class resolves: membership is created now, at the limited role
     if it doesn't: a durable class-creation request is recorded instead (idempotent,
     visible to the owners) and no membership is created
  -> reaching class-internal material later raises an idempotent upgrade request to
     that class's representative, once representatives exist to act on it
```

`tenant_workspace_role_upgrade_requests` and `class_creation_requests` are the two durable
tables this needs; neither existed before this port. §9's approval step is not gone — it
still gates the full `student` role — it has simply moved from *before* membership to
*after* it.

**The wizard itself grew one step relative to legacy.** `onboarding.py`'s twelve steps
assume a single Tehran dental cohort with one fixed faculty; FANOOS's directory is
per-institution, so a **faculty** step was inserted between institution and program,
making it thirteen steps in the general case (twelve for آزاد institutions, which still
skip the course-type step, instead of legacy's eleven). Entry year stayed legacy's static
list (۱۳۹۹–۱۴۰۵) rather than becoming directory-driven — a deliberate choice against the
directory-first spirit of §1, made because the owner asked to keep the proven list as-is.

## 12. Representatives now exist: appointment and approval close the loop

§11 shipped the tier but left the gate itself unbuilt — a limited member could raise an
upgrade request, but "once representatives exist to act on it" was still a future
condition. This closes it.

**Appointment reused what already existed rather than being built from scratch.**
`WorkspacePlatformService::assignRepresentative()` — owner-only, workspace-scoped
`membership.manage` (the platform scope is always an ancestor of a workspace scope, so a
platform owner satisfies this the same way any workspace-scoped check does), idempotent,
restoring a previously-revoked assignment rather than duplicating one — already existed on
the web side (`POST /workspaces/{id}/admin/representatives`), tested in
`tests/Integration/CorePlatformTest.php`. What this added was only a bot-facing path to the
same capability (`POST /representatives/{workspaces,candidates,appoint}`), plus the bot's
own two-step picker (which class, then which of its members) — the person appointed must
already be a member; the join wizard is the only identity path, on purpose, so there is
never a second way to name someone.

**Approval is the genuinely new capability**, and it needed a permission narrower than
`membership.manage`: `membership.approve` (`database/seeds/0009_membership_approve_permission.sql`),
granted to `cohort-representative` and every role that already holds `membership.manage`.
The distinction matters because §10 says everything inside a class belongs to its
representative, but approving a join is not the same power as administering the roster —
`membership.manage` also carries removing classmates and ending memberships outright,
which a representative admitting their own cohort has no business needing.
`ClassMembershipService::approveUpgradeRequest()` closes the request and promotes
`workspace-limited-member` → `student` in one transaction — neither ever applies without
the other — and a request is always looked up scoped to its own `workspace_id`, so a
representative of one class can never see, approve or decline another class's request even
by id.

The bot surface: the owner's management area gets `➕ انتصاب نماینده` beside class
creation; a representative sees `📋 درخواست‌های عضویت` in `➕ بیشتر` — visible only when
the backend actually grants `membership.approve` for their selected workspace, the same
probe-and-hide pattern `⚙️ مدیریت` already used for owners.

---

## 11. Owed cleanup: remove the superseded layers

The bot shell is being replaced by the legacy Dentistry1402TUMS shell, deliberately in two
steps: the legacy shell lands first and the FANOOS ui_v3 shell is left in place, because
deleting a large layer in the same change that introduces its replacement makes a bad
failure impossible to bisect. The owner agreed to that sequencing and then asked, plainly,
that the second step not be forgotten.

**So it is recorded here as owed work, not as an option.**

Once the legacy shell is proven in production, delete what it superseded:

- the FANOOS ui_v3 modules the legacy shell no longer routes through. The shell task is
  required to report that list; start from it rather than guessing.
- the second code path behind the `بیشتر` button. Evidence that one exists: the running
  release contains `more_action()` returning the label with a leading plus emoji, yet a
  production screenshot shows the same button rendered without it. Two code paths produce
  that button and only one was updated.
- any legacy UI generation that came across in duplicate. The legacy carries both
  `classops_ux_v2` and `classops_ux_v3`; only v3 is to be ported, and if any v2 arrives it
  goes out with this pass.
- `legacy/` itself, once nothing remains to translate from it.

This is the same condition that was already removed from the web layer once, where roughly
8400 lines of unreachable v1 and v2 assets were still being linted and tested against a
frozen snapshot of a page that had stopped being served. That cleanup found a live bot test
about to be deleted by accident and a release-artifact contract still asserting the presence
of deleted files. Expect this one to find comparable things, and read the report from the
shell task before starting.

The standing rule that makes this safe: nothing is deleted while it is still reachable, and
nothing is deleted in the same change that replaces it.

---

## 12. The website is migrated the same way, and it is the larger half

The bot is not the whole legacy product. The website carries features FANOOS has no
equivalent for, and the owner has been explicit that it comes across by the same method:
translate from the imported source, edit down, do not reimplement from a summary.

What was imported to `legacy/site/`, by file count:

| area | files | note |
| --- | --- | --- |
| `exams` | 815 | by far the largest single feature in the whole legacy product |
| `api` | 264 | the backend endpoints the bot and the site both spoke to |
| `assets` + `fonts` | 243 | the visual layer, including the Persian faces |
| `buy` | 18 | commerce surface |
| `notes` | 13 | note distribution |
| `chat` | 4 | |
| `grades`, `account`, `forms`, `msg`, `paste`, `dis-request`, `resources`, `payments`, `payment`, `navid`, `html-uploader`, `classops` | 1-3 each | small surfaces, real features |

And it is a **progressive web app**, not just a site: `manifest.webmanifest`, `sw.js`,
`offline.html`, `app-version.json` and installable icons. That means the legacy already
solved installability and offline behaviour for students on poor connections — in Iran, on
mobile data, behind filtering. FANOOS has none of that today and would not have thought to
build it first.

Left upstream deliberately: about 34MB of one cohort's exam content
(`api/exams_term6_reference_data/`, `exams_bank.php`, the `*_mcq_fa.txt` question banks).
That is Dentistry-1402 course material, not product code. It stays available in the upstream
repository if it is ever wanted as seed content for that one class.

### The order that follows from this

The site is deliberately last and done in one pass, not per-capability, for a reason the
owner named himself: the FANOOS site has no visual identity at all, so adding one capability
at a time would produce a handful of unrelated pages. Doing it once means one design system
serving every flow — and by then the contracts are settled, so the site is mostly a
consumer rather than an author of rules.

The `exams` area deserves its own planning pass when the time comes. At 815 files it is not
a task, it is a project, and it is the part of the legacy the owner is proudest of.

---

## 13. Requested feature: exam questions with images

Owner request, to be built when the `exams` area is reached: a question in a site exam must
be able to carry an image, not only text.

**This is new, not a port.** Checked against the imported source: the legacy exam code
contains a single stray `image_url` reference and no question-level image support. The
legacy exam system is effectively text-only, so there is no proven implementation to
translate here — this one gets designed.

Why it matters more than it sounds: the subject is dentistry. Radiographs, clinical
photographs and anatomical diagrams are not decoration in that field, they are frequently
the question itself. A text-only exam engine cannot ask a large share of the questions these
students are actually examined on.

### The part worth deciding early

FANOOS already has a protected-media pipeline, and exam images are exactly the kind of asset
it exists for: `apps/workers/protected-media`, the delivery issue/consume/receipt contract,
watermarking, and the forensic attribution work carried over from the legacy. Exam images
almost certainly want that path rather than a plain public upload — a leaked question bank
is a real cost, and this project already paid to build the machinery that prevents it.

So when this is designed, the first question is not "how do we store an image" but "does an
exam image go through protected delivery like other paid content, and if not, why not".
Decide that before building the upload surface, because it determines the storage model.

Related: section 12 notes that `exams` at 815 files is a project rather than a task, and
needs its own planning pass. This requirement belongs inside that pass.
