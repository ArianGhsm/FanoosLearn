# Disciplines and website sign-up

Owner decisions, 2026-09-26:

- A field's exams (رشته) are open to every student of that field, from any
  university and any entry year. Exams specific to one university or cohort
  stay in that class's workspace.
- The website has its own sign-up with a username and password. A phone number
  is not required at sign-up and can be added to the account later.

## Model

- `academic_disciplines` is a platform-wide list: medicine, dentistry, pharmacy
  to start, seeded by `database/seeds/0012_academic_disciplines.sql`.
- Each discipline can own a **library workspace**
  (`academic_disciplines.library_workspace_id`). This is an ordinary workspace,
  so the catalogue, attempts, pacing, review and RBAC all work unchanged.
- Signing up under a discipline makes the student a member of its library
  and gives them the `student` role there, which carries `exam.take`.
- A library is never offered as a class. `ClassMembershipService::join()` and
  `DirectoryReadService::joinableCohortsByProgram()` both exclude it.
- `iam_student_profiles` holds the sign-up answers:
  - name and discipline;
  - optional university, entry year, term (`first`/`second`), course type
    (`daily`/`tuition`/`international`) and student number.
- The username is an `iam_user_identifiers` row of type `external`, the same
  type `AuthService::login()` gives any plain identifier.

## Operating

To make an existing workspace a discipline's library, and enrol everyone who
signed up while it had none:

```
php scripts/ops/provision-discipline-library.php --discipline=medicine \
    --workspace=<uuid> --name="بانک سؤالات پزشکی"
```

The script refuses to move a discipline that already has a different library.

## API

All public except `GET /profile`:

- `GET /signup/options`
- `GET /directory/provinces`
- `GET /directory/provinces/{id}/institutions`
- `POST /auth/register` (throttled to 5 sign-ups per source per hour)
- `GET /profile` (signed in)

After a successful password login, an account with exactly one workspace is
placed in it directly instead of being shown a chooser.
