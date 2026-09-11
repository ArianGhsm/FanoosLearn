# Local runtime state

This directory contains examples only. Operational scripts require an ignored
`.codex-local/` state directory containing host metadata and protected local
credentials. During the repository migration the existing unversioned sibling
remains a compatibility location for that state; it is not a code source.

When the state is relocated, copy/migrate it through the verified secret/backup
procedure or pass an absolute `-ServerConfig` path. Never commit it and never
replace production state from a repository copy.
