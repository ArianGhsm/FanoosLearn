from __future__ import annotations
import time

class ProtectedMediaLocalState:
    """Disposable transport correlation only; backend remains authorization truth."""
    LIMIT = 500
    MAX_AGE = 24 * 3600

    def __init__(self, local_state):
        self.state = local_state
        self.db = local_state.db
        self.db.execute(
            """
            CREATE TABLE IF NOT EXISTS protected_media_correlations(
              job_id TEXT PRIMARY KEY,
              subject TEXT NOT NULL,
              workspace_id TEXT NOT NULL,
              issuance_id TEXT NOT NULL,
              created_at INTEGER NOT NULL
            )
            """
        )
        self.db.commit()
        self.prune()

    def remember(self, job_id: str, subject: str, workspace_id: str, issuance_id: str) -> None:
        now = int(time.time())
        with self.db:
            self.db.execute(
                "INSERT OR REPLACE INTO protected_media_correlations(job_id,subject,workspace_id,issuance_id,created_at) VALUES(?,?,?,?,?)",
                (str(job_id), str(subject), str(workspace_id), str(issuance_id), now),
            )
            self._bound()

    def get(self, job_id: str, subject: str):
        row = self.db.execute(
            "SELECT job_id,subject,workspace_id,issuance_id,created_at FROM protected_media_correlations WHERE job_id=? AND subject=?",
            (str(job_id), str(subject)),
        ).fetchone()
        return dict(row) if row else None

    def forget(self, job_id: str, subject: str) -> None:
        self.db.execute(
            "DELETE FROM protected_media_correlations WHERE job_id=? AND subject=?",
            (str(job_id), str(subject)),
        )
        self.db.commit()

    def prune(self) -> None:
        cutoff = int(time.time()) - self.MAX_AGE
        with self.db:
            self.db.execute("DELETE FROM protected_media_correlations WHERE created_at<?", (cutoff,))
            self._bound()

    def _bound(self) -> None:
        row = self.db.execute("SELECT COUNT(*) AS n FROM protected_media_correlations").fetchone()
        excess = max(0, int(row['n']) - self.LIMIT)
        if excess:
            self.db.execute(
                "DELETE FROM protected_media_correlations WHERE rowid IN (SELECT rowid FROM protected_media_correlations ORDER BY created_at ASC LIMIT ?)",
                (excess,),
            )
