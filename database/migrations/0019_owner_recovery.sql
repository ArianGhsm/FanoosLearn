-- fanoos:rollback-compatible=expand
-- Owner sign-in recovery: a single-use, short-lived, workspace-less token
-- an owner requests from the bot (where their messaging link already proves
-- who they are) and redeems in the browser to establish a normal session,
-- landing on a page where they set their own password. No operator, script
-- or agent ever sees or sets that password -- the token is a bearer
-- credential for the redeem step only, stored as a digest exactly the way
-- iam_sessions.token_digest already is.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS owner_recovery_tokens (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    token_digest BINARY(32) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_owner_recovery_tokens_digest (token_digest),
    KEY idx_owner_recovery_tokens_user (user_id, created_at),
    KEY idx_owner_recovery_tokens_expiry (expires_at, consumed_at),
    CONSTRAINT fk_owner_recovery_tokens_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_owner_recovery_tokens_expiry CHECK (expires_at > created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Same locking/upsert idiom as iam_login_attempts (AuthService::recordFailure):
-- one row per identity, read with FOR UPDATE and written unconditionally on
-- every request -- allowed or refused -- so a refusal's own write cannot be
-- lost to a rolled-back transaction (the OTP counter bug this project
-- already shipped and fixed once in OnboardingPhoneVerificationService).
CREATE TABLE IF NOT EXISTS owner_recovery_rate_guards (
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME(6) NOT NULL,
    cooldown_until DATETIME(6) NULL,
    last_request_at DATETIME(6) NOT NULL,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_owner_recovery_rate_guard_user FOREIGN KEY (user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
