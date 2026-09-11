-- fanoos:rollback-compatible=expand
SET NAMES utf8mb4;

-- Pre-workspace phone verification for the onboarding identity flow. A row here
-- represents a bot-platform identity (telegram/bale) proving control of a phone
-- number before any tenant_workspaces membership exists. Nothing in this file
-- carries a workspace_id: verifying a phone happens before a student is a member
-- of anything (see docs/product/01_FRONT_DOOR.md section 9).
--
-- Phone numbers and platform subjects are never stored in plaintext: both are
-- protected the same way Messaging/ChannelSubjectProtector already protects bot
-- platform subjects (a keyed digest for equality lookups, AES-256-GCM ciphertext
-- for the rare case the plaintext value must be recovered, e.g. to resend an SMS).

CREATE TABLE IF NOT EXISTS onboarding_phone_challenges (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    platform VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_digest BINARY(32) NOT NULL,
    phone_digest BINARY(32) NOT NULL,
    phone_ciphertext VARBINARY(512) NOT NULL,
    token_digest BINARY(32) NOT NULL,
    code_digest BINARY(32) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    send_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    send_window_started_at DATETIME(6) NOT NULL,
    cooldown_until DATETIME(6) NOT NULL,
    token_expires_at DATETIME(6) NOT NULL,
    code_expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_onboarding_phone_challenge_token (token_digest),
    KEY idx_onboarding_phone_challenges_subject (platform, subject_digest),
    CONSTRAINT chk_onboarding_phone_challenges_platform CHECK (platform IN ('telegram', 'bale')),
    CONSTRAINT chk_onboarding_phone_challenges_token_expiry CHECK (token_expires_at >= created_at),
    CONSTRAINT chk_onboarding_phone_challenges_code_expiry CHECK (code_expires_at <= token_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onboarding_verified_phones (
    platform VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_digest BINARY(32) NOT NULL,
    phone_digest BINARY(32) NOT NULL,
    phone_ciphertext VARBINARY(512) NOT NULL,
    verified_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (platform, subject_digest),
    KEY idx_onboarding_verified_phones_phone (phone_digest),
    CONSTRAINT chk_onboarding_verified_phones_platform CHECK (platform IN ('telegram', 'bale'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
