-- fanoos:rollback-compatible=expand
SET NAMES utf8mb4;

-- Capability 5 (docs/product/01_FRONT_DOOR.md #9: a student whose class does
-- not exist yet raises a creation request to the owners) is out of scope for
-- the join wizard itself -- there is no owner-facing review/notification
-- built here. This table is only the seam: a durable, idempotent record that
-- a verified person asked for a program+entry_year that has no live class
-- yet. No workspace_id exists to thread through, because there is no
-- workspace -- that is exactly the condition this table represents.

CREATE TABLE IF NOT EXISTS class_creation_requests (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    program_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    entry_year SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    requested_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_class_creation_requests_identity (user_id, program_id, entry_year),
    KEY idx_class_creation_requests_program (program_id, entry_year),
    CONSTRAINT fk_class_creation_requests_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_class_creation_requests_program FOREIGN KEY (program_id) REFERENCES directory_programs (id),
    CONSTRAINT chk_class_creation_requests_status CHECK (status IN ('pending', 'created', 'declined'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
