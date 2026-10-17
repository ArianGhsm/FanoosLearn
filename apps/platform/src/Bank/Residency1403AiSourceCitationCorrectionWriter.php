<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use PDO;
use RuntimeException;

/** Performs the compare-and-swap source-row write after all 1403 evidence gates pass. */
final class Residency1403AiSourceCitationCorrectionWriter
{
    /**
     * @param array<string,mixed> $old Locked source row previously checked against the protected plan.
     * @param array{edition_id:string,node_id:string} $target Unique official 1403 edition and chapter node.
     * @return array<string,mixed> Exact source-only readback for the apply receipt.
     */
    public static function write(
        PDO $database,
        array $old,
        array $target,
        int $pdfPage,
        string $pdfSha256,
        string $evidence,
    ): array {
        if (!$database->inTransaction()) {
            throw new RuntimeException('1403 source correction must run inside a transaction.');
        }
        foreach (['source_id', 'workspace_id', 'question_id', 'edition_id', 'node_id'] as $field) {
            if (!is_string($old[$field] ?? null) || $old[$field] === '') {
                throw new RuntimeException("1403 source correction lacks locked {$field}.");
            }
        }
        if (!is_string($target['edition_id'] ?? null) || $target['edition_id'] === ''
            || !is_string($target['node_id'] ?? null) || $target['node_id'] === ''
            || $pdfPage < 1 || $pdfPage > 6000
            || !preg_match('/^[a-f0-9]{64}$/D', $pdfSha256)
            || !is_string($evidence) || mb_strlen(trim($evidence)) < 24 || mb_strlen($evidence) > 190
            || ($old['origin'] ?? null) !== 'ai' || (int) ($old['is_primary'] ?? 0) !== 1
            || ($old['reviewed_at'] ?? null) !== null || ($old['reviewed_by_user_id'] ?? null) !== null
            || ($old['pdf_page'] ?? null) !== null || ($old['pdf_sha256'] ?? null) !== null
            || ($old['table_ref'] ?? null) !== null || ($old['figure_ref'] ?? null) !== null
            || ($old['box_ref'] ?? null) !== null) {
            throw new RuntimeException('1403 writer rejected a non-primary, reviewed, bound or malformed source.');
        }

        $label = 'pdf ' . $pdfPage;
        $update = $database->prepare(<<<'SQL'
UPDATE bank_question_sources
SET edition_id=:new_edition,node_id=:new_node,
    page=:new_page,printed_page=NULL,pdf_page=:new_pdf_page,
    pdf_sha256=:new_pdf_sha256,anchor_text=:new_anchor
WHERE id=:source_id AND workspace_id=:workspace_id AND question_id=:question_id
  AND edition_id=:old_edition AND node_id=:old_node
  AND page <=> :old_page AND printed_page <=> :old_printed_page
  AND anchor_text <=> :old_anchor AND pdf_page IS NULL AND pdf_sha256 IS NULL
  AND origin='ai' AND is_primary=1 AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
  AND table_ref IS NULL AND figure_ref IS NULL AND box_ref IS NULL
SQL);
        $update->execute([
            'new_edition' => $target['edition_id'],
            'new_node' => $target['node_id'],
            'new_page' => $label,
            'new_pdf_page' => $pdfPage,
            'new_pdf_sha256' => $pdfSha256,
            'new_anchor' => $evidence,
            'source_id' => $old['source_id'],
            'workspace_id' => $old['workspace_id'],
            'question_id' => $old['question_id'],
            'old_edition' => $old['edition_id'],
            'old_node' => $old['node_id'],
            'old_page' => $old['page'],
            'old_printed_page' => $old['printed_page'],
            'old_anchor' => $old['anchor_text'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('1403 source correction compare-and-swap failed.');
        }

        $readback = $database->prepare(<<<'SQL'
SELECT edition_id,node_id,page,printed_page,pdf_page,pdf_sha256,anchor_text
FROM bank_question_sources
WHERE id=:source_id AND workspace_id=:workspace_id AND question_id=:question_id
FOR UPDATE
SQL);
        $readback->execute([
            'source_id' => $old['source_id'],
            'workspace_id' => $old['workspace_id'],
            'question_id' => $old['question_id'],
        ]);
        $after = $readback->fetch(PDO::FETCH_ASSOC);
        if (!is_array($after) || $after['edition_id'] !== $target['edition_id']
            || $after['node_id'] !== $target['node_id'] || $after['page'] !== $label
            || $after['printed_page'] !== null || (int) $after['pdf_page'] !== $pdfPage
            || strtolower((string) $after['pdf_sha256']) !== $pdfSha256
            || $after['anchor_text'] !== $evidence) {
            throw new RuntimeException('1403 source correction readback mismatch.');
        }

        return [
            'source_id' => $old['source_id'],
            'edition_id' => $after['edition_id'],
            'node_id' => $after['node_id'],
            'page' => $after['page'],
            'printed_page' => $after['printed_page'],
            'pdf_page' => (int) $after['pdf_page'],
            'pdf_sha256' => strtolower((string) $after['pdf_sha256']),
            'anchor_sha256' => hash('sha256', (string) $after['anchor_text']),
        ];
    }
}
