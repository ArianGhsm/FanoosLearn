-- fanoos:rollback-compatible=expand
--
-- Expand-only: two new tables. The release before this one never reads them.
--
-- Visual study material in MedoFast's manner (owner, 2026-10-11): mind maps
-- (tree summaries), flowcharts, diagrams, tables and short "capsule"
-- summaries, each tied to the book chapters, concepts and questions it
-- explains and to the book pages it was made from.
--
-- bank_visuals -- one visual, versioned.
--   kind: mindmap | flowchart | diagram | table | capsule
--   body_format: tree (a JSON outline the site draws as a mind map),
--     markdown (capsules and tables, a small safe subset), image (a PNG/WebP/
--     JPEG made elsewhere, stored content-addressed like exam images).
--   source_*: the book pages it was made from, by value (reference edition,
--     PDF page range, the PDF file's SHA-256).
--   status draft | published; only published visuals reach students.
--   origin/confidence/reviewed_*: as for every machine-made bank value.
-- bank_visual_links -- what a visual explains: one chapter node, concept or
--   question per row.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS bank_visuals (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    visual_key VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    kind VARCHAR(12) NOT NULL,
    title VARCHAR(200) NOT NULL,
    subject_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    body_format VARCHAR(10) NOT NULL,
    body MEDIUMTEXT NULL,
    image VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
    source_edition_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    source_pdf_page_from INT UNSIGNED NULL,
    source_pdf_page_to INT UNSIGNED NULL,
    source_pdf_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'draft',
    origin VARCHAR(8) NOT NULL DEFAULT 'ai',
    confidence DECIMAL(4,3) NULL,
    reviewed_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    reviewed_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_visuals_key (workspace_id, visual_key),
    KEY idx_bank_visuals_subject (workspace_id, subject_id, status),
    CONSTRAINT fk_bank_visuals_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_visuals_subject FOREIGN KEY (subject_id) REFERENCES bank_subjects (id),
    CONSTRAINT fk_bank_visuals_edition FOREIGN KEY (source_edition_id) REFERENCES bank_reference_editions (id),
    CONSTRAINT fk_bank_visuals_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_bank_visuals_kind CHECK (kind IN ('mindmap', 'flowchart', 'diagram', 'table', 'capsule')),
    CONSTRAINT chk_bank_visuals_format CHECK (body_format IN ('tree', 'markdown', 'image')),
    CONSTRAINT chk_bank_visuals_body CHECK ((body_format = 'image') = (image IS NOT NULL) AND (body_format = 'image' OR body IS NOT NULL)),
    CONSTRAINT chk_bank_visuals_status CHECK (status IN ('draft', 'published')),
    CONSTRAINT chk_bank_visuals_origin CHECK (origin IN ('ai', 'human'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_visual_links (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    visual_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    node_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    concept_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    question_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (id),
    KEY idx_bank_visual_links_visual (visual_id),
    KEY idx_bank_visual_links_node (workspace_id, node_id),
    KEY idx_bank_visual_links_concept (workspace_id, concept_id),
    KEY idx_bank_visual_links_question (workspace_id, question_id),
    CONSTRAINT fk_bank_visual_links_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_visual_links_visual FOREIGN KEY (visual_id) REFERENCES bank_visuals (id),
    CONSTRAINT fk_bank_visual_links_node FOREIGN KEY (node_id) REFERENCES bank_reference_nodes (id),
    CONSTRAINT fk_bank_visual_links_concept FOREIGN KEY (concept_id) REFERENCES bank_concepts (id),
    CONSTRAINT fk_bank_visual_links_question FOREIGN KEY (question_id) REFERENCES bank_questions (id),
    CONSTRAINT chk_bank_visual_links_one CHECK ((node_id IS NOT NULL) + (concept_id IS NOT NULL) + (question_id IS NOT NULL) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
