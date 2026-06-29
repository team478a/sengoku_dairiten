-- AIアート教室 LINE画像生成システム 完全スキーマ v1.2.0
-- MySQL 5.7+ / MariaDB 10.3+

SET NAMES utf8mb4;
SET time_zone = '+09:00';

-- ユーザー
CREATE TABLE IF NOT EXISTS users (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    line_user_id   VARCHAR(255) NOT NULL UNIQUE,
    display_name   VARCHAR(255),
    picture_url    TEXT,
    memo           TEXT,
    registered_at  DATETIME,
    status         VARCHAR(50)  NOT NULL DEFAULT 'active',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 開催スケジュール
CREATE TABLE IF NOT EXISTS class_schedules (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(255)  NOT NULL DEFAULT '教室',
    class_date     DATE          NOT NULL,
    start_time     TIME          NOT NULL DEFAULT '10:00:00',
    end_time       TIME          NOT NULL DEFAULT '12:00:00',
    checkin_open   TIME          NOT NULL DEFAULT '09:30:00',
    checkin_close  TIME          NOT NULL DEFAULT '11:00:00',
    capacity       INT           NOT NULL DEFAULT 20,
    max_requests   INT           NOT NULL DEFAULT 2,
    description    TEXT,
    status         VARCHAR(20)   NOT NULL DEFAULT 'scheduled',
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_class_date (class_date),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 参加申請
CREATE TABLE IF NOT EXISTS class_attendances (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id     BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    line_user_id    VARCHAR(255)    NOT NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'pending',
    approved_by     BIGINT UNSIGNED,
    approved_at     DATETIME,
    rejected_reason TEXT,
    notified_at     DATETIME,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_schedule_user (schedule_id, user_id),
    INDEX idx_schedule_id  (schedule_id),
    INDEX idx_user_id      (user_id),
    INDEX idx_line_user_id (line_user_id),
    INDEX idx_status       (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ユーザーセッション（アンケート進行状態）
CREATE TABLE IF NOT EXISTS user_sessions (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    line_user_id VARCHAR(255) NOT NULL UNIQUE,
    step         VARCHAR(50)  NOT NULL DEFAULT 'idle',
    survey_data  JSON,
    expires_at   DATETIME     NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_line_user_id (line_user_id),
    INDEX idx_expires_at   (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 画像生成依頼
CREATE TABLE IF NOT EXISTS image_requests (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        BIGINT UNSIGNED NOT NULL,
    line_user_id   VARCHAR(255)    NOT NULL,
    input_type     VARCHAR(50)     NOT NULL DEFAULT 'survey',
    survey_style   VARCHAR(50),
    survey_mood    VARCHAR(50),
    input_text     TEXT            NOT NULL,
    requested_size VARCHAR(50)     NOT NULL DEFAULT 'square',
    status         VARCHAR(50)     NOT NULL DEFAULT 'received',
    error_message  TEXT,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id    (user_id),
    INDEX idx_status     (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 生成プロンプト
CREATE TABLE IF NOT EXISTS prompts (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id       BIGINT UNSIGNED NOT NULL,
    prompt_type      VARCHAR(10)     NOT NULL,
    title_ja         VARCHAR(255),
    input_summary_ja TEXT,
    prompt_en        TEXT            NOT NULL,
    safety_notes     TEXT,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 生成画像
CREATE TABLE IF NOT EXISTS generated_images (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id   BIGINT UNSIGNED NOT NULL,
    prompt_id    BIGINT UNSIGNED NOT NULL,
    prompt_type  VARCHAR(10)     NOT NULL,
    image_no     INT             NOT NULL,
    image_url    TEXT,
    preview_url  TEXT,
    storage_path TEXT,
    status       VARCHAR(50)     NOT NULL DEFAULT 'generated',
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ジョブキュー
CREATE TABLE IF NOT EXISTS job_queue (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id    BIGINT UNSIGNED NOT NULL,
    job_type      VARCHAR(50)     NOT NULL DEFAULT 'generate_images',
    status        VARCHAR(50)     NOT NULL DEFAULT 'pending',
    retry_count   INT             NOT NULL DEFAULT 0,
    error_message TEXT,
    available_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_available (status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- システムログ
CREATE TABLE IF NOT EXISTS system_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED,
    log_level  VARCHAR(20)  NOT NULL DEFAULT 'info',
    log_type   VARCHAR(50)  NOT NULL DEFAULT 'system',
    message    TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request_id (request_id),
    INDEX idx_level_type (log_level, log_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- システム設定
CREATE TABLE IF NOT EXISTS system_settings (
    `key`      VARCHAR(100) NOT NULL PRIMARY KEY,
    `value`    TEXT,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理者
CREATE TABLE IF NOT EXISTS admin_users (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- デフォルト設定
INSERT IGNORE INTO system_settings (`key`, `value`) VALUES
('max_daily_requests_per_user',  '2'),
('max_images_per_request',       '8'),
('storage_driver',               'local'),
('image_size',                   '1024x1024'),
('class_mode_enabled',           '1'),
('checkin_required',             '1'),
('next_class_message',           '次回の教室開催日をお待ちください。'),
('survey_enabled',               '1'),
('survey_session_ttl_minutes',   '30');

-- v3.6.0 追加カラム（ALTER TABLE / IF NOT EXISTS で冪等）
-- class_schedules 拡張
ALTER TABLE class_schedules
    ADD COLUMN IF NOT EXISTS organizer        VARCHAR(255)  NOT NULL DEFAULT '' AFTER description,
    ADD COLUMN IF NOT EXISTS public_message   TEXT                               AFTER organizer,
    ADD COLUMN IF NOT EXISTS event_format     VARCHAR(20)   NOT NULL DEFAULT 'realtime' AFTER public_message,
    ADD COLUMN IF NOT EXISTS location         VARCHAR(255)  NOT NULL DEFAULT '' AFTER event_format,
    ADD COLUMN IF NOT EXISTS zoom_url         TEXT                               AFTER location,
    ADD COLUMN IF NOT EXISTS auto_approve     TINYINT(1)    NOT NULL DEFAULT 0   AFTER zoom_url,
    ADD COLUMN IF NOT EXISTS fee              INT           NOT NULL DEFAULT 0   AFTER auto_approve,
    ADD COLUMN IF NOT EXISTS reminder_at      DATETIME                           AFTER fee,
    ADD COLUMN IF NOT EXISTS reminder_message TEXT                               AFTER reminder_at,
    ADD COLUMN IF NOT EXISTS reminder_sent_at DATETIME                           AFTER reminder_message;

-- class_attendances 拡張
ALTER TABLE class_attendances
    ADD COLUMN IF NOT EXISTS attended_at      DATETIME                           AFTER notified_at,
    ADD COLUMN IF NOT EXISTS payment_status   VARCHAR(30)   NOT NULL DEFAULT 'free' AFTER attended_at,
    ADD COLUMN IF NOT EXISTS payment_amount   INT           NOT NULL DEFAULT 0   AFTER payment_status,
    ADD COLUMN IF NOT EXISTS paid_at          DATETIME                           AFTER payment_amount,
    ADD COLUMN IF NOT EXISTS stripe_session_id VARCHAR(255)                      AFTER paid_at;

-- users 拡張
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS member_type      VARCHAR(30)   NOT NULL DEFAULT 'standard' AFTER memo,
    ADD COLUMN IF NOT EXISTS ticket_balance   INT           NOT NULL DEFAULT 0   AFTER member_type,
    ADD COLUMN IF NOT EXISTS ticket_expires_at DATETIME                          AFTER ticket_balance,
    ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255)                     AFTER ticket_expires_at,
    ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(255)                 AFTER stripe_customer_id,
    ADD COLUMN IF NOT EXISTS subscription_until DATETIME                         AFTER stripe_subscription_id;

-- payment_transactions（PaymentLog::ensureTable で作成されるが、初期スキーマにも定義）
CREATE TABLE IF NOT EXISTS payment_transactions (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    user_id                INT NULL,
    line_user_id           VARCHAR(255) NULL,
    kind                   VARCHAR(30)  NOT NULL,
    amount                 INT          NOT NULL DEFAULT 0,
    status                 VARCHAR(30)  NOT NULL DEFAULT 'paid',
    description            VARCHAR(255) NULL,
    stripe_session_id      VARCHAR(255) NULL,
    stripe_payment_intent  VARCHAR(255) NULL,
    refunded_at            DATETIME NULL,
    created_at             DATETIME     NOT NULL,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    UNIQUE KEY uq_stripe_session_id (stripe_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- audit_logs（AuditLog サービス用）
CREATE TABLE IF NOT EXISTS audit_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action     VARCHAR(100) NOT NULL,
    target     VARCHAR(255),
    detail     TEXT,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action     (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- admin_roles（migration_admin_roles.sql 統合）
ALTER TABLE admin_users
    ADD COLUMN IF NOT EXISTS name  VARCHAR(255)  NOT NULL DEFAULT '' AFTER email,
    ADD COLUMN IF NOT EXISTS role  VARCHAR(50)   NOT NULL DEFAULT 'manager' AFTER name;
