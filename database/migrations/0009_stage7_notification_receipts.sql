-- fanoos:rollback-compatible=expand
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notification_delivery_receipts (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    delivery_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idempotency_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    outcome VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    provider_message_ref VARCHAR(255) NULL,
    error_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_delivery_receipt_key (delivery_id, idempotency_key),
    CONSTRAINT fk_notification_delivery_receipt_delivery FOREIGN KEY (delivery_id) REFERENCES notification_channel_deliveries (id),
    CONSTRAINT chk_notification_delivery_receipt_outcome CHECK (outcome IN ('delivered', 'retry', 'failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
