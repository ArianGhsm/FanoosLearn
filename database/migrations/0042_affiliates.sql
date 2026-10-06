-- fanoos:rollback-compatible=expand
--
-- Expand-only: four new tables. The release before this one never reads them.
--
-- برنامه همکاری در فروش (docs/product/08_ENGAGEMENT.md):
-- affiliate_programs -- the owner's settings for a workspace: on or off,
--   the commission percentage, and how many days after sign-up a purchase
--   still earns it. Off until the owner turns it on.
-- affiliate_links -- one referral code per person per workspace.
-- affiliate_referrals -- an account that signed up through a referral link,
--   and whose link it was. One per account and workspace.
-- affiliate_commissions -- one per paid order of a referred buyer; pending
--   until the owner marks it paid out.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS affiliate_programs (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT FALSE,
    commission_percent TINYINT UNSIGNED NOT NULL,
    attribution_days SMALLINT UNSIGNED NOT NULL,
    updated_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id),
    CONSTRAINT fk_affiliate_programs_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_affiliate_programs_editor FOREIGN KEY (updated_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_affiliate_programs_percent CHECK (commission_percent BETWEEN 1 AND 90),
    CONSTRAINT chk_affiliate_programs_days CHECK (attribution_days BETWEEN 1 AND 3650)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_links (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, user_id),
    UNIQUE KEY uq_affiliate_links_code (code),
    CONSTRAINT fk_affiliate_links_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_affiliate_links_user FOREIGN KEY (user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_referrals (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    referred_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    affiliate_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, referred_user_id),
    KEY idx_affiliate_referrals_affiliate (workspace_id, affiliate_user_id),
    CONSTRAINT fk_affiliate_referrals_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_affiliate_referrals_referred FOREIGN KEY (referred_user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_affiliate_referrals_affiliate FOREIGN KEY (affiliate_user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_commissions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    affiliate_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    referred_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    order_total_minor BIGINT UNSIGNED NOT NULL,
    commission_minor BIGINT UNSIGNED NOT NULL,
    status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
    created_at DATETIME(6) NOT NULL,
    paid_out_at DATETIME(6) NULL,
    paid_out_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_affiliate_commissions_order (order_id),
    KEY idx_affiliate_commissions_affiliate (workspace_id, affiliate_user_id, status),
    CONSTRAINT fk_affiliate_commissions_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_affiliate_commissions_affiliate FOREIGN KEY (affiliate_user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_affiliate_commissions_referred FOREIGN KEY (referred_user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_affiliate_commissions_order FOREIGN KEY (order_id) REFERENCES commerce_orders (id),
    CONSTRAINT fk_affiliate_commissions_payer FOREIGN KEY (paid_out_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_affiliate_commissions_status CHECK (status IN ('pending', 'paid_out', 'void'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
