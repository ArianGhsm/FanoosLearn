<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

/**
 * Moves the legacy `page` text of question sources into the separate
 * pdf_page / printed_page columns (migration 0043), so every source says
 * which page of the PDF file and which printed page it means.
 *
 *   "pdf 257"  -> pdf_page = 257       (a PDF page proven by the classification work)
 *   "523"      -> printed_page = "523" (a printed page label)
 *   "xii"      -> printed_page = "xii"
 *   anything else is left alone and listed.
 *
 * Only rows whose pdf_page and printed_page are both still empty are touched;
 * `page`, the source itself, questions, answers and published exams are never
 * changed. With --bind-pdf, rows that get a pdf_page also get pdf_sha256 =
 * the edition's one verified private reference PDF (the file the
 * classification read), when there is exactly one.
 *
 * Dry run (default) prints the counts. Apply needs a verified full backup and
 * a receipt path that does not exist yet:
 *
 *   php scripts/references/backfill_source_pages.php --workspace=<uuid> [--bind-pdf]
 *   php scripts/references/backfill_source_pages.php --workspace=<uuid> [--bind-pdf] --apply
 *       --backup=/var/backups/fanoos/<verified-backup> --receipt=<new receipt.json>
 */

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

/** What a legacy page value means: ['pdf_page', 257], ['printed_page', '523'] or null. */
function classify_page(string $page): ?array
{
    $page = trim($page);
    if (preg_match('/^pdf\s*(\d{1,5})$/i', $page, $m) === 1 && (int) $m[1] > 0) {
        return ['pdf_page', (int) $m[1]];
    }
    if (preg_match('/^\d{1,5}$/', $page) === 1 && (int) $page > 0) {
        return ['printed_page', $page];
    }
    if (preg_match('/^[ivxlcdm]{1,8}$/i', $page) === 1) {
        return ['printed_page', strtolower($page)];
    }

    return null;
}

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return; // included by a test for classify_page()
}

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(workspace|apply|backup|receipt|bind-pdf)(?:=(.*))?$/D', $arg, $m) !== 1) {
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        exit(2);
    }
    $args[$m[1]] = $m[2] ?? '1';
}

try {
    $workspace = (string) ($args['workspace'] ?? '');
    if (preg_match('/^[0-9a-f-]{36}$/', $workspace) !== 1) {
        throw new RuntimeException('--workspace=<uuid> is required');
    }
    $apply = isset($args['apply']);
    if ($apply && (!isset($args['backup'], $args['receipt']))) {
        throw new RuntimeException('--apply needs --backup=<verified full backup> and --receipt=<new path>');
    }
    if ($apply) {
        BackupManifest::verify((string) $args['backup']);
        if (file_exists((string) $args['receipt'])) {
            throw new RuntimeException('The receipt path already exists; choose a new one.');
        }
    }

    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();

    $pdfOf = [];
    if (isset($args['bind-pdf'])) {
        $query = $db->prepare(<<<'SQL'
SELECT e.id AS edition_id, LOWER(HEX(o.checksum_sha256)) AS sha
FROM bank_reference_editions e
JOIN bank_references r ON r.id = e.reference_id
JOIN content_resource_metadata m ON m.topic = CONCAT(r.reference_key, '@', e.edition_key) AND m.format_key = 'reference_pdf' AND m.access_level = 'private'
JOIN content_resources cr ON cr.id = m.resource_id AND cr.workspace_id = m.workspace_id AND cr.lifecycle_status = 'published' AND cr.deleted_at IS NULL AND cr.archived_at IS NULL
JOIN content_resource_versions v ON v.resource_id = cr.id AND v.workspace_id = cr.workspace_id AND v.version_no = cr.current_version_no AND v.status = 'approved'
JOIN content_objects o ON o.id = v.object_id AND o.workspace_id = v.workspace_id AND o.status = 'verified' AND o.deleted_at IS NULL AND o.detected_mime = 'application/pdf'
WHERE e.workspace_id = :workspace
SQL);
        $query->execute(['workspace' => $workspace]);
        $seen = [];
        foreach ($query->fetchAll() as $row) {
            $seen[(string) $row['edition_id']][(string) $row['sha']] = true;
        }
        foreach ($seen as $editionId => $hashes) {
            if (count($hashes) === 1) {
                $pdfOf[$editionId] = array_key_first($hashes);
            }
        }
    }

    $rows = $db->prepare(<<<'SQL'
SELECT id, edition_id, page FROM bank_question_sources
WHERE workspace_id = :workspace AND page IS NOT NULL AND page <> '' AND pdf_page IS NULL AND printed_page IS NULL
SQL);
    $rows->execute(['workspace' => $workspace]);
    $plan = [];
    $counts = ['pdf_page' => 0, 'printed_page' => 0, 'pdf_sha256' => 0, 'left' => 0];
    $left = [];
    foreach ($rows->fetchAll() as $row) {
        $kind = classify_page((string) $row['page']);
        if ($kind === null) {
            ++$counts['left'];
            $left[(string) $row['page']] = ($left[(string) $row['page']] ?? 0) + 1;
            continue;
        }
        [$column, $value] = $kind;
        $sha = $column === 'pdf_page' ? ($pdfOf[(string) $row['edition_id']] ?? null) : null;
        $plan[] = ['id' => (string) $row['id'], 'column' => $column, 'value' => $value, 'sha' => $sha];
        ++$counts[$column];
        $counts['pdf_sha256'] += $sha === null ? 0 : 1;
    }

    $report = ['mode' => $apply ? 'apply' : 'dry-run', 'counts' => $counts, 'left_values' => $left];
    if ($apply) {
        $db->beginTransaction();
        try {
            $pdf = $db->prepare('UPDATE bank_question_sources SET pdf_page = :value, pdf_sha256 = :sha WHERE id = :id AND pdf_page IS NULL AND printed_page IS NULL');
            $printed = $db->prepare('UPDATE bank_question_sources SET printed_page = :value WHERE id = :id AND pdf_page IS NULL AND printed_page IS NULL');
            $changed = 0;
            foreach ($plan as $step) {
                if ($step['column'] === 'pdf_page') {
                    $pdf->execute(['value' => $step['value'], 'sha' => $step['sha'], 'id' => $step['id']]);
                    $changed += $pdf->rowCount();
                } else {
                    $printed->execute(['value' => $step['value'], 'id' => $step['id']]);
                    $changed += $printed->rowCount();
                }
            }
            if ($changed !== count($plan)) {
                throw new RuntimeException("Expected {$changed} = " . count($plan) . ' rows; nothing was changed.');
            }
            $report += ['backup' => (string) $args['backup'], 'rows_changed' => $changed, 'at' => gmdate('c'), 'row_ids' => array_column($plan, 'id')];
            $handle = fopen((string) $args['receipt'], 'x');
            if ($handle === false || fwrite($handle, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n") === false) {
                throw new RuntimeException('Could not write the receipt.');
            }
            fclose($handle);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
        unset($report['row_ids']);
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
