-- fanoos:rollback-compatible=expand
--
-- Expand-only: two new tables and two nullable order columns. The release
-- before this one never reads them, and its orders simply carry no discount.
--
-- کد تخفیف و سکه (docs/product/08_ENGAGEMENT.md):
-- commerce_discount_codes -- a code the owner creates, or a personal
--   single-use code a student bought with coins (owner_user_id set). An
--   amount or a percentage off, optionally for one product only, with a
--   validity window and use limits counted from paid orders.
-- commerce_orders.discount_code_id / discount_minor -- the code an order
--   used and how much it took off; total_minor is what is charged.
-- engagement_coin_offers -- the "boxes": how many coins buy which discount,
--   set by the owner as data.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS commerce_discount_codes (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    label VARCHAR(120) NOT NULL,
    kind VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    amount_minor BIGINT UNSIGNED NULL,
    percent TINYINT UNSIGNED NULL,
    product_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    owner_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    max_uses INT UNSIGNED NULL,
    per_user_limit INT UNSIGNED NOT NULL DEFAULT 1,
    valid_from DATETIME(6) NOT NULL,
    valid_until DATETIME(6) NULL,
    status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
    source VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commerce_discount_code (workspace_id, code),
    KEY idx_commerce_discount_owner (workspace_id, owner_user_id),
    CONSTRAINT fk_commerce_discount_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_commerce_discount_product FOREIGN KEY (product_id) REFERENCES commerce_products (id),
    CONSTRAINT fk_commerce_discount_owner FOREIGN KEY (owner_user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_commerce_discount_creator FOREIGN KEY (created_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_commerce_discount_kind CHECK ((kind = 'amount' AND amount_minor > 0 AND percent IS NULL) OR (kind = 'percent' AND percent BETWEEN 1 AND 100 AND amount_minor IS NULL)),
    CONSTRAINT chk_commerce_discount_status CHECK (status IN ('active', 'disabled')),
    CONSTRAINT chk_commerce_discount_source CHECK (source IN ('admin', 'coins')),
    CONSTRAINT chk_commerce_discount_limit CHECK (per_user_limit > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE commerce_orders
    ADD COLUMN discount_code_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER total_minor,
    ADD COLUMN discount_minor BIGINT UNSIGNED NULL AFTER discount_code_id,
    ADD KEY idx_commerce_orders_discount (discount_code_id, status);

CREATE TABLE IF NOT EXISTS engagement_coin_offers (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(120) NOT NULL,
    coins INT UNSIGNED NOT NULL,
    kind VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    amount_minor BIGINT UNSIGNED NULL,
    percent TINYINT UNSIGNED NULL,
    product_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    code_valid_days TINYINT UNSIGNED NOT NULL DEFAULT 3,
    status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_engagement_coin_offers (workspace_id, status, sort_order),
    CONSTRAINT fk_engagement_offers_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_engagement_offers_product FOREIGN KEY (product_id) REFERENCES commerce_products (id),
    CONSTRAINT chk_engagement_offers_coins CHECK (coins > 0),
    CONSTRAINT chk_engagement_offers_kind CHECK ((kind = 'amount' AND amount_minor > 0 AND percent IS NULL) OR (kind = 'percent' AND percent BETWEEN 1 AND 100 AND amount_minor IS NULL)),
    CONSTRAINT chk_engagement_offers_days CHECK (code_valid_days BETWEEN 1 AND 60),
    CONSTRAINT chk_engagement_offers_status CHECK (status IN ('active', 'disabled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
