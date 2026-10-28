# Permanent QA / test account (owner decision 2026-10-28)

Every agent (Claude, Codex, …) and human tester uses **one** permanent account
to test the live site end to end. **Never delete or disable it.**

- Username: `fanoos_ai_qa` (display name «حساب آزمایشی هوش مصنوعی»), user id
  `01a180f2-e4f2-7bb4-877b-3cc17f45042d`.
- Roles: `platform-super-admin` (platform scope, same as the owner) **and**
  `student` in the dentistry workspace, so both the student experience and every
  admin/owner page can be tested with it.
- Password: only on the server, in `/etc/fanoos/qa-account.env`
  (`root:fanoosrt`, mode 0640; `sudo grep PASSWORD /etc/fanoos/qa-account.env`).
  Never put it in Git, a PR, a chat transcript or a log.
- Login: `POST /api/v1/auth/login` with `{"identifier","password"}` or the
  normal `/login` page. From the server itself, reach the site with
  `curl --noproxy '*' --resolve fanooslearn.ir:443:127.0.0.1 https://fanooslearn.ir/...`
  (the public address does not hairpin).
- Data it creates (attempts, notes, bookmarks, reports) is test data; keep it out
  of owner statistics reviews and never use it as evidence of real usage.
- If the password must change, update `/etc/fanoos/qa-account.env` in the same step.
