SET NAMES utf8mb4;

-- The first disciplines, the same three the Dent1402 onboarding offered. A
-- discipline with no library workspace yet is still offered at sign-up: its
-- students get a profile, and they see its exams the moment an owner
-- provisions the library (scripts/ops/provision-discipline-library.php).
-- Re-running keeps names and order in step without touching the library
-- link an owner has already made.
INSERT INTO academic_disciplines (id, code, name, library_workspace_id, status, sort_order, created_at, updated_at)
VALUES
    (UUID(), 'medicine', 'پزشکی', NULL, 'active', 10, NOW(6), NOW(6)),
    (UUID(), 'dentistry', 'دندانپزشکی', NULL, 'active', 20, NOW(6), NOW(6)),
    (UUID(), 'pharmacy', 'داروسازی', NULL, 'active', 30, NOW(6), NOW(6))
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    sort_order = VALUES(sort_order),
    updated_at = VALUES(updated_at);
