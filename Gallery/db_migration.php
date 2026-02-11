<?php
/**
 * 数据库迁移和升级脚本
 * 在应用启动时检查并添加缺失的表和列
 */

function ensure_database_schema($pdo) {
    try {
        // 添加 images 表的缺失列
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM images LIKE 'is_deleted'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE images ADD COLUMN is_deleted BOOLEAN NOT NULL DEFAULT 0");
            }
        } catch (Exception $e) {
            error_log("添加 is_deleted 列失败: " . $e->getMessage());
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM images LIKE 'deleted_at'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE images ADD COLUMN deleted_at DATETIME");
            }
        } catch (Exception $e) {
            error_log("添加 deleted_at 列失败: " . $e->getMessage());
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM images LIKE 'deleted_by'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE images ADD COLUMN deleted_by VARCHAR(50)");
            }
        } catch (Exception $e) {
            error_log("添加 deleted_by 列失败: " . $e->getMessage());
        }

        // 创建 user_action_logs 表（如果不存在）
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_action_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    username VARCHAR(50),
                    action_type VARCHAR(50),
                    details TEXT,
                    ip_address VARCHAR(45),
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_username (username),
                    INDEX idx_action_type (action_type)
                )
            ");
        } catch (Exception $e) {
            error_log("创建 user_action_logs 表失败: " . $e->getMessage());
        }

        return true;
    } catch (Exception $e) {
        error_log("数据库迁移失败: " . $e->getMessage());
        return false;
    }
}
?>
