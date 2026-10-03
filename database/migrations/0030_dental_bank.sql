-- fanoos:rollback-compatible=expand
--
-- Expand-only: new tables only. The release before this one never reads or
-- writes them.
--
-- The dental residency question bank (docs/product/05_DENTAL_RESIDENCY_DATA_MODEL.md,
-- exchange format in docs/product/06_QUESTION_FORMAT.md). Every table is
-- workspace-scoped like the rest of the platform; the bank lives in the
-- dental library workspace. Each catalogue row also carries a stable
-- human-readable key (`*_key`) so an import file can be applied again
-- without creating duplicates.
--
-- Rules the schema encodes:
-- * a chapter (reference node) belongs to one edition, never to a subject
--   or a question directly;
-- * a concept is independent of every edition;
-- * which edition counts in which exam year is recorded, not inferred;
-- * machine-made rows carry an origin and a confidence, and a reviewed row
--   records who reviewed it;
-- * the official answer keeps its history.
SET NAMES utf8mb4;

-- ------------------------------------------------------------ exams

CREATE TABLE IF NOT EXISTS bank_exam_types (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    type_key VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(120) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_exam_types_key (workspace_id, type_key),
    CONSTRAINT fk_bank_exam_types_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_subjects (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_key VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    parent_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    name VARCHAR(160) NOT NULL,
    name_en VARCHAR(160) NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_subjects_key (workspace_id, subject_key),
    CONSTRAINT fk_bank_subjects_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_subjects_parent FOREIGN KEY (parent_id) REFERENCES bank_subjects (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_exam_sittings (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exam_type_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exam_year SMALLINT UNSIGNED NOT NULL,
    exam_round TINYINT UNSIGNED NOT NULL DEFAULT 1,
    held_on DATE NULL,
    question_count SMALLINT UNSIGNED NULL,
    answer_key_status VARCHAR(20) NOT NULL DEFAULT 'preliminary',
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_exam_sittings (workspace_id, exam_type_id, exam_year, exam_round),
    CONSTRAINT fk_bank_exam_sittings_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_exam_sittings_type FOREIGN KEY (exam_type_id) REFERENCES bank_exam_types (id),
    CONSTRAINT fk_bank_exam_sittings_assessment FOREIGN KEY (assessment_id) REFERENCES exam_assessments (id),
    CONSTRAINT chk_bank_exam_sittings_key_status CHECK (answer_key_status IN ('preliminary', 'final', 'amended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ references

CREATE TABLE IF NOT EXISTS bank_references (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reference_key VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    title VARCHAR(300) NOT NULL,
    authors VARCHAR(300) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_references_key (workspace_id, reference_key),
    CONSTRAINT fk_bank_references_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_references_subject FOREIGN KEY (subject_id) REFERENCES bank_subjects (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_reference_editions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reference_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    edition_key VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    edition_label VARCHAR(80) NOT NULL,
    published_year SMALLINT UNSIGNED NULL,
    isbn VARCHAR(20) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_reference_editions_key (workspace_id, reference_id, edition_key),
    CONSTRAINT fk_bank_reference_editions_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_reference_editions_reference FOREIGN KEY (reference_id) REFERENCES bank_references (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_reference_nodes (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    edition_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    parent_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    node_key VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    kind VARCHAR(16) NOT NULL,
    number VARCHAR(20) NULL,
    title VARCHAR(300) NOT NULL,
    page_start SMALLINT UNSIGNED NULL,
    page_end SMALLINT UNSIGNED NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_reference_nodes_key (workspace_id, edition_id, node_key),
    CONSTRAINT fk_bank_reference_nodes_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_reference_nodes_edition FOREIGN KEY (edition_id) REFERENCES bank_reference_editions (id),
    CONSTRAINT fk_bank_reference_nodes_parent FOREIGN KEY (parent_id) REFERENCES bank_reference_nodes (id),
    CONSTRAINT chk_bank_reference_nodes_kind CHECK (kind IN ('part', 'chapter', 'section', 'subsection'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which edition is the official reference for a subject in an exam year.
CREATE TABLE IF NOT EXISTS bank_reference_validity (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exam_type_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exam_year SMALLINT UNSIGNED NOT NULL,
    subject_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    edition_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    is_official BOOLEAN NOT NULL DEFAULT TRUE,
    source_document VARCHAR(400) NULL,
    recorded_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_reference_validity (workspace_id, exam_type_id, exam_year, subject_id, edition_id),
    CONSTRAINT fk_bank_reference_validity_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_reference_validity_type FOREIGN KEY (exam_type_id) REFERENCES bank_exam_types (id),
    CONSTRAINT fk_bank_reference_validity_subject FOREIGN KEY (subject_id) REFERENCES bank_subjects (id),
    CONSTRAINT fk_bank_reference_validity_edition FOREIGN KEY (edition_id) REFERENCES bank_reference_editions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_edition_mappings (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    from_node_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    to_node_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    relation VARCHAR(16) NOT NULL,
    note VARCHAR(600) NULL,
    confidence DECIMAL(4,3) NULL,
    origin VARCHAR(8) NOT NULL DEFAULT 'human',
    reviewed_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    reviewed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_edition_mappings (workspace_id, from_node_id, to_node_id),
    CONSTRAINT fk_bank_edition_mappings_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_edition_mappings_from FOREIGN KEY (from_node_id) REFERENCES bank_reference_nodes (id),
    CONSTRAINT fk_bank_edition_mappings_to FOREIGN KEY (to_node_id) REFERENCES bank_reference_nodes (id),
    CONSTRAINT fk_bank_edition_mappings_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_bank_edition_mappings_relation CHECK (relation IN ('equivalent', 'partial', 'removed')),
    CONSTRAINT chk_bank_edition_mappings_origin CHECK (origin IN ('ai', 'human'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ concepts

CREATE TABLE IF NOT EXISTS bank_concepts (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    concept_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    parent_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    level VARCHAR(10) NOT NULL,
    name VARCHAR(200) NOT NULL,
    name_en VARCHAR(200) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_concepts_key (workspace_id, concept_key),
    CONSTRAINT fk_bank_concepts_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_concepts_subject FOREIGN KEY (subject_id) REFERENCES bank_subjects (id),
    CONSTRAINT fk_bank_concepts_parent FOREIGN KEY (parent_id) REFERENCES bank_concepts (id),
    CONSTRAINT chk_bank_concepts_level CHECK (level IN ('topic', 'subtopic', 'concept'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_node_concepts (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    node_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    concept_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (node_id, concept_id),
    CONSTRAINT fk_bank_node_concepts_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_node_concepts_node FOREIGN KEY (node_id) REFERENCES bank_reference_nodes (id),
    CONSTRAINT fk_bank_node_concepts_concept FOREIGN KEY (concept_id) REFERENCES bank_concepts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ questions

CREATE TABLE IF NOT EXISTS bank_questions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sitting_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    number_in_sitting SMALLINT UNSIGNED NULL,
    subject_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    stem TEXT NOT NULL,
    stem_image VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
    question_type VARCHAR(24) NOT NULL DEFAULT 'recall',
    is_negative_stem BOOLEAN NOT NULL DEFAULT FALSE,
    is_multiple_statement BOOLEAN NOT NULL DEFAULT FALSE,
    cognitive_level VARCHAR(16) NULL,
    expert_difficulty TINYINT UNSIGNED NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'draft',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_questions_key (workspace_id, question_key),
    UNIQUE KEY uq_bank_questions_sitting_number (workspace_id, sitting_id, number_in_sitting),
    KEY idx_bank_questions_subject (workspace_id, subject_id),
    CONSTRAINT fk_bank_questions_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_questions_sitting FOREIGN KEY (sitting_id) REFERENCES bank_exam_sittings (id),
    CONSTRAINT fk_bank_questions_subject FOREIGN KEY (subject_id) REFERENCES bank_subjects (id),
    CONSTRAINT chk_bank_questions_type CHECK (question_type IN ('recall', 'conceptual', 'clinical_scenario', 'diagnosis', 'treatment_planning', 'image_based', 'calculation')),
    CONSTRAINT chk_bank_questions_level CHECK (cognitive_level IS NULL OR cognitive_level IN ('recall', 'understanding', 'application', 'analysis')),
    CONSTRAINT chk_bank_questions_difficulty CHECK (expert_difficulty IS NULL OR expert_difficulty BETWEEN 1 AND 5),
    CONSTRAINT chk_bank_questions_status CHECK (status IN ('draft', 'reviewed', 'published', 'withdrawn'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_question_choices (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    text TEXT NOT NULL,
    image VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (question_id, position),
    CONSTRAINT fk_bank_question_choices_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_question_choices_question FOREIGN KEY (question_id) REFERENCES bank_questions (id),
    CONSTRAINT chk_bank_question_choices_position CHECK (position BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The official key, as a history: the newest row is the answer now.
CREATE TABLE IF NOT EXISTS bank_official_answers (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    choice_position TINYINT UNSIGNED NULL,
    status VARCHAR(12) NOT NULL,
    source VARCHAR(400) NULL,
    recorded_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_bank_official_answers_question (question_id, recorded_at),
    CONSTRAINT fk_bank_official_answers_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_official_answers_question FOREIGN KEY (question_id) REFERENCES bank_questions (id),
    CONSTRAINT chk_bank_official_answers_status CHECK (status IN ('preliminary', 'final', 'amended', 'disputed', 'voided')),
    CONSTRAINT chk_bank_official_answers_choice CHECK ((status = 'voided') = (choice_position IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_question_sources (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    edition_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    node_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    page VARCHAR(20) NULL,
    table_ref VARCHAR(40) NULL,
    figure_ref VARCHAR(40) NULL,
    box_ref VARCHAR(40) NULL,
    anchor_text VARCHAR(600) NULL,
    is_primary BOOLEAN NOT NULL DEFAULT TRUE,
    confidence_source DECIMAL(4,3) NULL,
    confidence_node DECIMAL(4,3) NULL,
    confidence_page DECIMAL(4,3) NULL,
    origin VARCHAR(8) NOT NULL DEFAULT 'ai',
    reviewed_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    reviewed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_bank_question_sources_question (question_id),
    KEY idx_bank_question_sources_node (workspace_id, node_id),
    CONSTRAINT fk_bank_question_sources_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_question_sources_question FOREIGN KEY (question_id) REFERENCES bank_questions (id),
    CONSTRAINT fk_bank_question_sources_edition FOREIGN KEY (edition_id) REFERENCES bank_reference_editions (id),
    CONSTRAINT fk_bank_question_sources_node FOREIGN KEY (node_id) REFERENCES bank_reference_nodes (id),
    CONSTRAINT fk_bank_question_sources_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_bank_question_sources_origin CHECK (origin IN ('ai', 'human'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_question_concepts (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    concept_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT TRUE,
    confidence DECIMAL(4,3) NULL,
    origin VARCHAR(8) NOT NULL DEFAULT 'ai',
    reviewed_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    reviewed_at DATETIME(6) NULL,
    PRIMARY KEY (question_id, concept_id),
    KEY idx_bank_question_concepts_concept (workspace_id, concept_id),
    CONSTRAINT fk_bank_question_concepts_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_question_concepts_question FOREIGN KEY (question_id) REFERENCES bank_questions (id),
    CONSTRAINT fk_bank_question_concepts_concept FOREIGN KEY (concept_id) REFERENCES bank_concepts (id),
    CONSTRAINT fk_bank_question_concepts_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_bank_question_concepts_origin CHECK (origin IN ('ai', 'human'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pairs are the facts; clusters are derived from them.
CREATE TABLE IF NOT EXISTS bank_question_similarity (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_a_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_b_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    relation VARCHAR(16) NOT NULL,
    confidence DECIMAL(4,3) NULL,
    origin VARCHAR(8) NOT NULL DEFAULT 'ai',
    reviewed_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    reviewed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_question_similarity (workspace_id, question_a_id, question_b_id),
    CONSTRAINT fk_bank_question_similarity_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_question_similarity_a FOREIGN KEY (question_a_id) REFERENCES bank_questions (id),
    CONSTRAINT fk_bank_question_similarity_b FOREIGN KEY (question_b_id) REFERENCES bank_questions (id),
    CONSTRAINT fk_bank_question_similarity_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_bank_question_similarity_relation CHECK (relation IN ('exact_repeat', 'near_duplicate', 'same_concept')),
    CONSTRAINT chk_bank_question_similarity_origin CHECK (origin IN ('ai', 'human')),
    CONSTRAINT chk_bank_question_similarity_order CHECK (question_a_id < question_b_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Is the official answer still right under a given edition.
CREATE TABLE IF NOT EXISTS bank_question_currency (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    against_edition_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL,
    note VARCHAR(1000) NULL,
    confidence DECIMAL(4,3) NULL,
    origin VARCHAR(8) NOT NULL DEFAULT 'ai',
    reviewed_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    reviewed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_question_currency (workspace_id, question_id, against_edition_id),
    CONSTRAINT fk_bank_question_currency_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_question_currency_question FOREIGN KEY (question_id) REFERENCES bank_questions (id),
    CONSTRAINT fk_bank_question_currency_edition FOREIGN KEY (against_edition_id) REFERENCES bank_reference_editions (id),
    CONSTRAINT fk_bank_question_currency_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_bank_question_currency_status CHECK (status IN ('current', 'valid_old_edition', 'changed_in_newer', 'outdated', 'contradicted', 'removed_from_syllabus')),
    CONSTRAINT chk_bank_question_currency_origin CHECK (origin IN ('ai', 'human'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ explanations

CREATE TABLE IF NOT EXISTS bank_explanations (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL,
    is_current BOOLEAN NOT NULL DEFAULT TRUE,
    short_answer TEXT NULL,
    reference_explanation MEDIUMTEXT NULL,
    source_location VARCHAR(400) NULL,
    exam_tip TEXT NULL,
    common_trap TEXT NULL,
    confidence DECIMAL(4,3) NULL,
    origin VARCHAR(8) NOT NULL DEFAULT 'ai',
    reviewed_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    reviewed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_explanations_version (question_id, version),
    CONSTRAINT fk_bank_explanations_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_explanations_question FOREIGN KEY (question_id) REFERENCES bank_questions (id),
    CONSTRAINT fk_bank_explanations_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_bank_explanations_origin CHECK (origin IN ('ai', 'human'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_explanation_choices (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    explanation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    why_wrong TEXT NOT NULL,
    PRIMARY KEY (explanation_id, position),
    CONSTRAINT fk_bank_explanation_choices_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_bank_explanation_choices_explanation FOREIGN KEY (explanation_id) REFERENCES bank_explanations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
