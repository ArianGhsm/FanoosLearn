SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS commerce_products (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    product_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(200) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commerce_products_workspace_key (workspace_id, product_key),
    UNIQUE KEY uq_commerce_products_id_workspace (id, workspace_id),
    CONSTRAINT fk_commerce_products_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT chk_commerce_products_status CHECK (status IN ('draft', 'active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_price_versions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    product_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    valid_from DATETIME(6) NOT NULL,
    valid_until DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commerce_price_versions_product_start (product_id, valid_from),
    UNIQUE KEY uq_commerce_price_versions_id_workspace (id, workspace_id),
    UNIQUE KEY uq_commerce_price_versions_id_product_workspace (id, product_id, workspace_id),
    CONSTRAINT fk_commerce_prices_product_workspace FOREIGN KEY (product_id, workspace_id) REFERENCES commerce_products (id, workspace_id),
    CONSTRAINT chk_commerce_prices_window CHECK (valid_until IS NULL OR valid_until > valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_orders (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    buyer_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    total_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idempotency_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commerce_orders_workspace_idempotency (workspace_id, idempotency_key),
    UNIQUE KEY uq_commerce_orders_id_workspace (id, workspace_id),
    KEY idx_commerce_orders_buyer_status (workspace_id, buyer_user_id, status),
    CONSTRAINT fk_commerce_orders_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_commerce_orders_buyer FOREIGN KEY (buyer_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_commerce_orders_status CHECK (status IN ('pending', 'payment_pending', 'paid', 'failed', 'cancelled', 'refunded')),
    CONSTRAINT chk_commerce_orders_version CHECK (version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_order_lines (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    product_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    price_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_amount_minor BIGINT UNSIGNED NOT NULL,
    line_total_minor BIGINT UNSIGNED NOT NULL,
    product_name_snapshot VARCHAR(200) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_commerce_order_lines_order (workspace_id, order_id),
    CONSTRAINT fk_commerce_order_lines_order_workspace FOREIGN KEY (order_id, workspace_id) REFERENCES commerce_orders (id, workspace_id),
    CONSTRAINT fk_commerce_order_lines_product_workspace FOREIGN KEY (product_id, workspace_id) REFERENCES commerce_products (id, workspace_id),
    CONSTRAINT fk_commerce_order_lines_price_product_workspace FOREIGN KEY (price_version_id, product_id, workspace_id) REFERENCES commerce_price_versions (id, product_id, workspace_id),
    CONSTRAINT chk_commerce_order_lines_quantity CHECK (quantity > 0),
    CONSTRAINT chk_commerce_order_lines_total CHECK (line_total_minor = unit_amount_minor * quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_payment_attempts (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    provider_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    provider_authority_digest BINARY(32) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'created',
    requested_amount_minor BIGINT UNSIGNED NOT NULL,
    provider_reference VARCHAR(160) NULL,
    verified_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commerce_payment_authority (provider_key, provider_authority_digest),
    KEY idx_commerce_payment_order_status (workspace_id, order_id, status),
    CONSTRAINT fk_commerce_payment_order_workspace FOREIGN KEY (order_id, workspace_id) REFERENCES commerce_orders (id, workspace_id),
    CONSTRAINT chk_commerce_payment_status CHECK (status IN ('created', 'redirected', 'verifying', 'verified', 'failed', 'cancelled', 'refunded')),
    CONSTRAINT chk_commerce_payment_verified CHECK (status <> 'verified' OR verified_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entitlement_grants (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_scope_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    valid_from DATETIME(6) NOT NULL,
    valid_until DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    revoke_reason VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_entitlement_grants_decision (workspace_id, subject_user_id, target_scope_id, revoked_at, valid_from, valid_until),
    CONSTRAINT fk_entitlement_grants_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_entitlement_grants_subject FOREIGN KEY (subject_user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_entitlement_grants_scope_workspace FOREIGN KEY (target_scope_id, workspace_id) REFERENCES rbac_scopes (id, workspace_id),
    CONSTRAINT chk_entitlement_grants_source CHECK (source_type IN ('order', 'administrator', 'policy', 'migration')),
    CONSTRAINT chk_entitlement_grants_window CHECK (valid_until IS NULL OR valid_until > valid_from),
    CONSTRAINT chk_entitlement_grants_revoke_reason CHECK (revoked_at IS NULL OR revoke_reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_messages (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    message_type VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    data_json JSON NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    created_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    published_at DATETIME(6) NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_messages_id_workspace (id, workspace_id),
    KEY idx_notification_messages_workspace_status (workspace_id, status),
    CONSTRAINT fk_notification_messages_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_notification_messages_creator FOREIGN KEY (created_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_notification_messages_status CHECK (status IN ('draft', 'queued', 'published', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_recipients (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    notification_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    channel VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    read_at DATETIME(6) NULL,
    delivered_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_recipients_delivery (notification_id, user_id, channel),
    KEY idx_notification_recipients_inbox (workspace_id, user_id, status, created_at),
    CONSTRAINT fk_notification_recipients_message_workspace FOREIGN KEY (notification_id, workspace_id) REFERENCES notification_messages (id, workspace_id),
    CONSTRAINT fk_notification_recipients_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_notification_recipients_channel CHECK (channel IN ('web', 'telegram', 'bale', 'push', 'email')),
    CONSTRAINT chk_notification_recipients_status CHECK (status IN ('pending', 'sent', 'delivered', 'failed', 'read'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_assessments (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    offering_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    title VARCHAR(200) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    current_version_no INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exam_assessments_id_workspace (id, workspace_id),
    KEY idx_exam_assessments_workspace_status (workspace_id, status),
    CONSTRAINT fk_exam_assessments_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_exam_assessments_offering_workspace FOREIGN KEY (offering_id, workspace_id) REFERENCES academic_course_offerings (id, workspace_id),
    CONSTRAINT chk_exam_assessments_status CHECK (status IN ('draft', 'review', 'published', 'closed', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_assessment_versions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    definition_json JSON NOT NULL,
    checksum_sha256 BINARY(32) NOT NULL,
    created_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exam_assessment_versions_number (assessment_id, version_no),
    UNIQUE KEY uq_exam_assessment_versions_id_workspace (id, workspace_id),
    UNIQUE KEY uq_exam_assessment_versions_id_assessment_workspace (id, assessment_id, workspace_id),
    CONSTRAINT fk_exam_versions_assessment_workspace FOREIGN KEY (assessment_id, workspace_id) REFERENCES exam_assessments (id, workspace_id),
    CONSTRAINT fk_exam_versions_creator FOREIGN KEY (created_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_exam_versions_number CHECK (version_no > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_attempts (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'in_progress',
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    answers_json JSON NOT NULL,
    started_at DATETIME(6) NOT NULL,
    submitted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exam_attempts_id_workspace (id, workspace_id),
    KEY idx_exam_attempts_user_status (workspace_id, user_id, status),
    CONSTRAINT fk_exam_attempts_assessment_workspace FOREIGN KEY (assessment_id, workspace_id) REFERENCES exam_assessments (id, workspace_id),
    CONSTRAINT fk_exam_attempts_version_assessment_workspace FOREIGN KEY (assessment_version_id, assessment_id, workspace_id) REFERENCES exam_assessment_versions (id, assessment_id, workspace_id),
    CONSTRAINT fk_exam_attempts_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_exam_attempts_status CHECK (status IN ('in_progress', 'submitted', 'scored', 'void')),
    CONSTRAINT chk_exam_attempts_revision CHECK (revision > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grade_gradebooks (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    offering_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(200) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_grade_gradebooks_offering (workspace_id, offering_id),
    UNIQUE KEY uq_grade_gradebooks_id_workspace (id, workspace_id),
    CONSTRAINT fk_grade_gradebooks_offering_workspace FOREIGN KEY (offering_id, workspace_id) REFERENCES academic_course_offerings (id, workspace_id),
    CONSTRAINT chk_grade_gradebooks_status CHECK (status IN ('draft', 'active', 'published', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grade_items (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    gradebook_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    item_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(200) NOT NULL,
    max_score DECIMAL(10,3) NOT NULL,
    weight DECIMAL(8,5) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_grade_items_gradebook_key (gradebook_id, item_key),
    UNIQUE KEY uq_grade_items_id_workspace (id, workspace_id),
    CONSTRAINT fk_grade_items_gradebook_workspace FOREIGN KEY (gradebook_id, workspace_id) REFERENCES grade_gradebooks (id, workspace_id),
    CONSTRAINT chk_grade_items_score CHECK (max_score > 0),
    CONSTRAINT chk_grade_items_weight CHECK (weight IS NULL OR (weight >= 0 AND weight <= 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grade_results (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    grade_item_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    enrollment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    score DECIMAL(10,3) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    updated_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_grade_results_item_enrollment (grade_item_id, enrollment_id),
    KEY idx_grade_results_workspace_enrollment (workspace_id, enrollment_id),
    CONSTRAINT fk_grade_results_item_workspace FOREIGN KEY (grade_item_id, workspace_id) REFERENCES grade_items (id, workspace_id),
    CONSTRAINT fk_grade_results_enrollment_workspace FOREIGN KEY (enrollment_id, workspace_id) REFERENCES academic_enrollments (id, workspace_id),
    CONSTRAINT fk_grade_results_updater FOREIGN KEY (updated_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_grade_results_status CHECK (status IN ('draft', 'published', 'void')),
    CONSTRAINT chk_grade_results_version CHECK (version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_events (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    offering_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    event_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(200) NOT NULL,
    starts_at DATETIME(6) NOT NULL,
    ends_at DATETIME(6) NULL,
    location_text VARCHAR(255) NULL,
    recurrence_json JSON NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'scheduled',
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    cancelled_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_events_id_workspace (id, workspace_id),
    KEY idx_schedule_events_workspace_range (workspace_id, starts_at, ends_at),
    CONSTRAINT fk_schedule_events_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_schedule_events_offering_workspace FOREIGN KEY (offering_id, workspace_id) REFERENCES academic_course_offerings (id, workspace_id),
    CONSTRAINT chk_schedule_events_type CHECK (event_type IN ('class', 'exam', 'deadline', 'event', 'other')),
    CONSTRAINT chk_schedule_events_times CHECK (ends_at IS NULL OR ends_at >= starts_at),
    CONSTRAINT chk_schedule_events_status CHECK (status IN ('scheduled', 'completed', 'cancelled')),
    CONSTRAINT chk_schedule_events_version CHECK (version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
