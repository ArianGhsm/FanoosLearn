<?php

declare(strict_types=1);

namespace Fanoos\Platform\Identity;

use Fanoos\Platform\Support\PlatformException;
use PDO;

/**
 * What joining one account into another would do, row by row -- read-only.
 *
 * docs/product/04_ACCOUNT_MERGE.md is the design; PLAN is its table in code.
 * inventory() is the data behind the "this is what will be combined" screen
 * a student confirms, and the checklist the merge itself will follow. It
 * writes nothing.
 *
 * Every column in the schema that points at an account is listed in PLAN,
 * including the ones that deliberately stay where they are (history), so a
 * new table that owns user rows cannot be silently left behind by a merge:
 * AccountMergePlanTest compares PLAN with the migrations.
 */
final class AccountMergePlanner
{
    /** The row becomes the target's. */
    public const MOVE = 'move';
    /** Moves, except where the target already has a row with the same key; that one is kept and the source's dropped. */
    public const MOVE_DEDUPE = 'move_dedupe';
    /** The target's rows are kept; the source's only fill what the target lacks. */
    public const KEEP_TARGET = 'keep_target';
    /** The source's rows are ended (sessions, sign-in methods). */
    public const REVOKE = 'revoke';
    /** Transient counters for the source, simply dropped. */
    public const DROP = 'drop';
    /** Stays attributed to the source: a record of what that account did. */
    public const HISTORY = 'history';

    /**
     * table => [column, treatment, dedupe key column|null]
     *
     * @var array<string, list<array{0:string,1:string,2:?string}>>
     */
    public const PLAN = [
        'tenant_workspace_memberships' => [['user_id', self::MOVE_DEDUPE, 'workspace_id']],
        'rbac_role_assignments' => [['user_id', self::MOVE_DEDUPE, 'role_template_id,scope_id'], ['granted_by_user_id', self::HISTORY, null]],
        'exam_attempts' => [['user_id', self::MOVE, null]],
        'exam_assessments' => [['created_for_user_id', self::MOVE, null]],
        'exam_question_read_rate_guards' => [['user_id', self::DROP, null]],
        'exam_question_daily_reads' => [['user_id', self::DROP, null]],
        // Who reviewed a bank classification is a record of who did it.
        'bank_edition_mappings' => [['reviewed_by_user_id', self::HISTORY, null]],
        'bank_question_sources' => [['reviewed_by_user_id', self::HISTORY, null]],
        'bank_question_concepts' => [['reviewed_by_user_id', self::HISTORY, null]],
        'bank_question_similarity' => [['reviewed_by_user_id', self::HISTORY, null]],
        'bank_question_currency' => [['reviewed_by_user_id', self::HISTORY, null]],
        'bank_explanations' => [['reviewed_by_user_id', self::HISTORY, null]],
        // Derived counters: dropped, then rebuilt for the target from the
        // attempts that moved (QuestionStatsRecorder::rebuild).
        'exam_question_user_stats' => [['user_id', self::DROP, null]],
        'exam_question_user_blanks' => [['user_id', self::DROP, null]],
        // The student's own bookmarks and notes go with them; where both
        // accounts kept the same question, the target's is kept.
        'exam_question_bookmarks' => [['user_id', self::MOVE_DEDUPE, 'workspace_id,question_key']],
        'exam_question_notes' => [['user_id', self::MOVE_DEDUPE, 'workspace_id,question_key']],
        'exam_question_highlights' => [['user_id', self::MOVE_DEDUPE, 'workspace_id,question_key']],
        'exam_question_reports' => [['user_id', self::MOVE, null], ['resolved_by_user_id', self::HISTORY, null]],
        'study_sessions' => [['user_id', self::MOVE, null]],
        // امتیاز روزانه and سکه go with the student; where both accounts have
        // the same day (or the same coin event), the target's row is kept.
        'engagement_point_awards' => [['user_id', self::MOVE_DEDUPE, 'workspace_id,day,question_key']],
        'engagement_daily_points' => [['user_id', self::MOVE_DEDUPE, 'workspace_id,day']],
        'engagement_coin_ledger' => [['user_id', self::MOVE_DEDUPE, 'workspace_id,reason,reference']],
        // Study rooms: a membership goes with the student (one per room); who made a room is history.
        'engagement_study_room_members' => [['user_id', self::MOVE_DEDUPE, 'room_id']],
        'engagement_study_rooms' => [['created_by_user_id', self::HISTORY, null]],
        // Both accounts' plans move; the newest active one is the one shown.
        'study_plans' => [['user_id', self::MOVE, null]],
        'exam_schedules' => [['created_by_user_id', self::HISTORY, null]],
        'entitlement_grants' => [['subject_user_id', self::MOVE, null]],
        'commerce_orders' => [['buyer_user_id', self::MOVE, null]],
        // A personal code bought with coins goes with its owner; who created a code is history.
        'commerce_discount_codes' => [['owner_user_id', self::MOVE, null], ['created_by_user_id', self::HISTORY, null]],
        'content_delivery_issuances' => [['user_id', self::MOVE, null]],
        'protected_media_artifacts' => [['user_id', self::MOVE, null]],
        'notification_recipients' => [['user_id', self::MOVE, null]],
        'notification_preferences' => [['user_id', self::KEEP_TARGET, null]],
        'notification_channel_preferences' => [['user_id', self::KEEP_TARGET, null]],
        'messaging_links' => [['user_id', self::MOVE_DEDUPE, 'platform']],
        'messaging_link_challenges' => [['user_id', self::DROP, null]],
        'messaging_link_rate_guards' => [['user_id', self::DROP, null]],
        'iam_user_identifiers' => [['user_id', self::MOVE_DEDUPE, 'identifier_type']],
        'iam_student_profiles' => [['user_id', self::KEEP_TARGET, null]],
        'class_creation_requests' => [['user_id', self::MOVE, null], ['resolved_by_user_id', self::HISTORY, null]],
        'tenant_workspace_role_upgrade_requests' => [['user_id', self::MOVE, null], ['resolved_by_user_id', self::HISTORY, null]],
        'form_submissions' => [['submitter_user_id', self::MOVE, null]],
        'iam_sessions' => [['user_id', self::REVOKE, null]],
        'iam_authenticators' => [['user_id', self::REVOKE, null]],
        'owner_recovery_tokens' => [['user_id', self::REVOKE, null]],
        'owner_recovery_rate_guards' => [['user_id', self::DROP, null]],
        'commerce_reconciliation_runs' => [['requested_by_user_id', self::HISTORY, null]],
        'content_derivations' => [['created_by_user_id', self::HISTORY, null]],
        'content_import_batches' => [['imported_by_user_id', self::HISTORY, null]],
        'content_resource_publications' => [['published_by_user_id', self::HISTORY, null]],
        'content_resource_versions' => [['created_by_user_id', self::HISTORY, null]],
        'content_resources' => [['owner_user_id', self::HISTORY, null]],
        'content_version_reviews' => [['reviewer_user_id', self::HISTORY, null]],
        'exam_assessment_versions' => [['created_by_user_id', self::HISTORY, null]],
        'exam_version_reviews' => [['reviewer_user_id', self::HISTORY, null]],
        'form_definitions' => [['owner_user_id', self::HISTORY, null]],
        'form_versions' => [['created_by_user_id', self::HISTORY, null]],
        'grade_import_batches' => [['imported_by_user_id', self::HISTORY, null]],
        'grade_results' => [['updated_by_user_id', self::HISTORY, null]],
        'migration_batches' => [['requested_by_user_id', self::HISTORY, null]],
        'notification_messages' => [['created_by_user_id', self::HISTORY, null]],
        'release_update_requests' => [['requested_by_user_id', self::HISTORY, null]],
        'iam_account_merges' => [['source_user_id', self::HISTORY, null], ['target_user_id', self::HISTORY, null]],
        'iam_users' => [['merged_into_user_id', self::HISTORY, null]],
    ];

    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @return array{source_user_id:string,target_user_id:string,rows:list<array{table:string,column:string,treatment:string,count:int,duplicates:int}>}
     */
    public function inventory(string $sourceUserId, string $targetUserId): array
    {
        if ($sourceUserId === $targetUserId) {
            throw new PlatformException('account_merge_same_account', 'An account cannot be merged into itself.', 422);
        }
        $users = $this->database->prepare("SELECT id FROM iam_users WHERE id IN (:source, :target) AND status = 'active' AND deleted_at IS NULL");
        $users->execute(['source' => $sourceUserId, 'target' => $targetUserId]);
        if (count($users->fetchAll(PDO::FETCH_COLUMN)) !== 2) {
            throw new PlatformException('account_merge_unavailable', 'Both accounts must be active.', 409);
        }

        $rows = [];
        foreach (self::PLAN as $table => $columns) {
            foreach ($columns as [$column, $treatment, $dedupeKey]) {
                if ($treatment === self::HISTORY) {
                    continue;
                }
                $count = $this->database->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :source");
                $count->execute(['source' => $sourceUserId]);
                $total = (int) $count->fetchColumn();

                $duplicates = 0;
                if ($total > 0 && $treatment === self::MOVE_DEDUPE && $dedupeKey !== null) {
                    $join = implode(' AND ', array_map(
                        static fn (string $key): string => "mine.`{$key}` <=> theirs.`{$key}`",
                        explode(',', $dedupeKey),
                    ));
                    $dupe = $this->database->prepare(
                        "SELECT COUNT(*) FROM `{$table}` mine JOIN `{$table}` theirs ON {$join} AND theirs.`{$column}` = :target WHERE mine.`{$column}` = :source",
                    );
                    $dupe->execute(['source' => $sourceUserId, 'target' => $targetUserId]);
                    $duplicates = (int) $dupe->fetchColumn();
                }
                $rows[] = ['table' => $table, 'column' => $column, 'treatment' => $treatment, 'count' => $total, 'duplicates' => $duplicates];
            }
        }

        return ['source_user_id' => $sourceUserId, 'target_user_id' => $targetUserId, 'rows' => $rows];
    }
}
