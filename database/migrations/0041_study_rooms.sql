-- fanoos:rollback-compatible=expand
--
-- Expand-only: two new tables. The release before this one never reads them.
--
-- اتاق مطالعه گروهی (docs/product/08_ENGAGEMENT.md):
-- engagement_study_rooms -- a private room in a workspace, joined by its
--   invite code; archived when its last member leaves.
-- engagement_study_room_members -- who is in a room; leaving sets left_at,
--   joining again clears it. At most 10 present members a room, and a person
--   is present in at most 10 rooms (enforced by StudyRoomService).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS engagement_study_rooms (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(60) NOT NULL,
    invite_code CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_engagement_room_invite (invite_code),
    KEY idx_engagement_rooms_workspace (workspace_id, archived_at),
    CONSTRAINT fk_engagement_rooms_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_engagement_rooms_creator FOREIGN KEY (created_by_user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS engagement_study_room_members (
    room_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    joined_at DATETIME(6) NOT NULL,
    left_at DATETIME(6) NULL,
    PRIMARY KEY (room_id, user_id),
    KEY idx_engagement_room_members_user (workspace_id, user_id, left_at),
    CONSTRAINT fk_engagement_room_members_room FOREIGN KEY (room_id) REFERENCES engagement_study_rooms (id),
    CONSTRAINT fk_engagement_room_members_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_engagement_room_members_user FOREIGN KEY (user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
