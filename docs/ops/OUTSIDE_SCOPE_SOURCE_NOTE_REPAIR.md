# Correcting an outside-scope source note

Use `scripts/references/repair_verified_outside_scope_source_note.php` when an
already verified outside-scope residency citation has a wrong or misleading
`official_scope_note`. This operator changes only that note. It does not alter
the citation, official answer, question, choices, review state, frozen
assessment, or attempts.

The private plan and SHA-pinned audit must identify the exact live source row,
chapter, printed and physical PDF pages, current approved PDF hash, existing
note hash, question and choice fingerprints, and latest official answer.
Recheck the cited page, its printed-page neighbors, and the official scope.
Record the corrected note and any unresolved answer alignment without
changing the official answer.

Store the plan and audit with mode `0600` in
`/srv/fanoos/shared/research/classification/reports/`. Run the rollback-only
preview as the updater service:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_outside_scope_source_note.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json
```

After a successful preview, take and verify a fresh full backup. Apply with
`--apply --backup=<verified-backup> --receipt=<new-private-receipt>`. Then
independently read back the note, source metadata, question/choice/key hashes,
frozen assessment and attempt/review counts. The year-wide publication gate
must still pass before any year-level publication.
