-- fanoos:rollback-compatible=expand
--
-- Expand-only: a new table, a new nullable column, and a CHECK widened to
-- admit one more status the running release never writes -- so the release
-- before this one keeps working unchanged against the new schema.
--
-- Joining a student's bot-made account into their website account
-- (docs/product/04_ACCOUNT_MERGE.md). The merged-away account is kept as a
-- tombstone -- status 'merged', pointing at the account it became -- so every
-- historical foreign key and audit row still resolves; this table is the
-- record of each merge: who into whom, on what proof, and what moved.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS iam_account_merges (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    proof VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    summary_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_iam_account_merges_source (source_user_id),
    KEY idx_iam_account_merges_target (target_user_id, created_at),
    CONSTRAINT fk_iam_account_merges_source FOREIGN KEY (source_user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_iam_account_merges_target FOREIGN KEY (target_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_iam_account_merges_distinct CHECK (source_user_id <> target_user_id),
    CONSTRAINT chk_iam_account_merges_proof CHECK (proof IN ('messaging_link', 'phone_otp'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE iam_users
    ADD COLUMN merged_into_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER status,
    ADD CONSTRAINT fk_iam_users_merged_into FOREIGN KEY (merged_into_user_id) REFERENCES iam_users (id);

ALTER TABLE iam_users
    DROP CHECK chk_iam_users_status,
    ADD CONSTRAINT chk_iam_users_status CHECK (status IN ('invited', 'active', 'suspended', 'deleted', 'merged'));
