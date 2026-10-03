# Working loop

How a change goes from the laptop to students, keeping the laptop, GitHub
and the server identical (`docs/PROJECT_PRINCIPLES.md` §2).

## 1. Start

```sh
git checkout main
git pull --ff-only
git checkout -b <kind>/<short-name>      # feat/, fix/, design/, ops/, docs/, chore/
```

## 2. Change and check

One coherent change: code, its tests, and the documents that describe it.

```sh
php tests/run.php                        # static, schema and rendering checks
node --test tests/web/*.mjs              # web unit tests
php scripts/ci/check-text.php            # encoding guard
```

The MySQL integration suite runs in CI (it needs a disposable database).
To run it locally, point `FANOOS_DB_DSN` at a database whose name ends in
`_test`, set `FANOOS_ALLOW_TEST_DB=1` and a test-only
`FANOOS_LEGACY_ID_HMAC_KEY`, then `php tests/run.php`.

Pages can be previewed without a database through a small router that
renders the real page classes against mocked API answers; screenshots are
taken with headless Chrome.

## 3. Push and merge

```sh
git push -u origin <branch>
gh pr create --base main
gh pr checks --watch
gh pr merge --merge --delete-branch     # only when every check is green
git checkout main && git pull --ff-only
```

## 4. Deploy (same session)

Wait for `main`'s own CI run to be green, then on the server
(`docs/ops/SERVER.md`; access details in `.local/SERVER_ACCESS.md`):

1. fast-forward the updater checkout to `origin/main`;
2. queue the deploy with `scripts/ops/request-deployment.php`;
3. wait for `/srv/fanoos/current` to point at the new commit;
4. check the site, the API and the bots, and that the other workload on the
   host is still running.

## 5. Confirm

```sh
FANOOS_SSH_TARGET=... FANOOS_SSH_KEY=... FANOOS_KNOWN_HOSTS=... \
  sh scripts/dev/check-sync.sh
```

It must end with `in sync`. Unfinished work is pushed on its branch before
the session ends.
