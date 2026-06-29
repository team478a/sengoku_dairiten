-- 管理者ロール追加マイグレーション
-- phpMyAdminで実行、またはアップデート時に自動適用

ALTER TABLE admin_users
    ADD COLUMN name VARCHAR(255) AFTER email,
    ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'staff' AFTER name,
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER role,
    ADD COLUMN last_login_at DATETIME AFTER status;

-- 既存の最初の管理者をオーナーに
UPDATE admin_users SET role = 'owner' WHERE id = (SELECT t.mid FROM (SELECT MIN(id) AS mid FROM admin_users) AS t);
