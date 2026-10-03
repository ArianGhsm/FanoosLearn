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

When the library workspace does not exist yet, create it under an institution
already in the directory; it is then marked as the library the same way
(dry run without `--execute`):

```
php scripts/ops/create-library-workspace.php --discipline=dentistry --institution=<slug> \
    --faculty="دانشکده دندانپزشکی" --program="دندانپزشکی عمومی" \
    --name="کتابخانه‌ی دندانپزشکی" --actor=<platform owner uuid> --execute
```

## API

All public except `GET /profile`:

- `GET /signup/options`
- `GET /directory/provinces`
- `GET /directory/provinces/{id}/institutions`
- `POST /auth/register` (throttled to 5 sign-ups per source per hour)
- `GET /profile` (signed in)

After a successful password login, an account with exactly one workspace is
placed in it directly instead of being shown a chooser.

## Adding a phone later

The account page can attach a phone to an account after sign-up
(`AccountPhoneService`):

- The code is sent through the bots' OTP engine and SMS gateway, under
  platform `web` with the account id as the subject (migration 0025 widened
  the platform CHECKs).
- The verified number becomes the account's single `phone` identifier; any
  earlier number is retired.
- A number another account already holds is refused before any SMS is sent.
  That account may be the same student's bot account, and merging the two is
  a deliberate, separate step.

## Question images

Image questions from the imported banks live in `ExamImageStore` under
`FANOOS_STORAGE_ROOT/exam-images/`:

- The files are content-addressed.
- The directory must keep the `fanoosrt` group (setgid), so the backup, which
  runs as `fanoosupd`, can read them.
