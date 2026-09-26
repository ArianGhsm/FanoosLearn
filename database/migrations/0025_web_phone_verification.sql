-- fanoos:rollback-compatible=expand
--
-- Expand-only: the release running before this migration only ever writes
-- 'telegram' or 'bale' into these platform columns, both still valid under
-- the widened CHECKs, so it keeps working unchanged against the new schema.
--
-- A phone number added to a website account later. Sign-up asks for none
-- (the owner's decision, 2026-09-26); a student who wants one on the account
-- verifies it with the same OTP engine the bots use, under platform 'web'
-- with their account id as the subject. As in 0021, widening a CHECK means
-- dropping and re-adding the named constraint.

ALTER TABLE onboarding_phone_challenges
    DROP CHECK chk_onboarding_phone_challenges_platform,
    ADD CONSTRAINT chk_onboarding_phone_challenges_platform CHECK (platform IN ('telegram', 'bale', 'web'));

ALTER TABLE onboarding_verified_phones
    DROP CHECK chk_onboarding_verified_phones_platform,
    ADD CONSTRAINT chk_onboarding_verified_phones_platform CHECK (platform IN ('telegram', 'bale', 'web'));
