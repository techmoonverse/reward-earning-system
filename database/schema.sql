-- Complete schema for reward-earning-system
-- Drop tables in reverse dependency order
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS withdrawals;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS loyalty_bonus;
DROP TABLE IF EXISTS referrals;
DROP TABLE IF EXISTS daily_checkin;
DROP TABLE IF EXISTS user_tasks;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS ads;
DROP TABLE IF EXISTS rate_limit;
DROP TABLE IF EXISTS site_settings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    referral_code VARCHAR(20) NOT NULL UNIQUE,
    referred_by INT UNSIGNED NULL,
    balance DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    total_earned DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    points INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    email_verify_token VARCHAR(100) NULL,
    password_reset_token VARCHAR(100) NULL,
    password_reset_expires DATETIME NULL,
    login_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
);

-- tasks table
CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    type ENUM('captcha','watch_ads','website_visit','video_watch','daily_checkin','loyalty_bonus','referral') NOT NULL,
    description TEXT,
    reward_points INT UNSIGNED NOT NULL DEFAULT 0,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 30,
    url VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    daily_limit INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- user_tasks table
CREATE TABLE IF NOT EXISTS user_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NOT NULL,
    status ENUM('started','completed','failed','expired') NOT NULL DEFAULT 'started',
    task_token VARCHAR(255) NULL,
    ad_viewed TINYINT(1) NOT NULL DEFAULT 0,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    reward_given TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    device_fingerprint VARCHAR(255) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_user_task (user_id, task_id),
    INDEX idx_status (status)
);

-- daily_checkin table
CREATE TABLE IF NOT EXISTS daily_checkin (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    checkin_date DATE NOT NULL,
    streak_count INT UNSIGNED NOT NULL DEFAULT 1,
    bonus_points INT UNSIGNED NOT NULL DEFAULT 10,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_date (user_id, checkin_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- referrals table
CREATE TABLE IF NOT EXISTS referrals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT UNSIGNED NOT NULL,
    referred_id INT UNSIGNED NOT NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    reward_given TINYINT(1) NOT NULL DEFAULT 0,
    commission_rate DECIMAL(5,4) NOT NULL DEFAULT 0.1000,
    total_earned DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    is_flagged TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_referrer (referrer_id),
    INDEX idx_referred (referred_id)
);

-- loyalty_bonus table
CREATE TABLE IF NOT EXISTS loyalty_bonus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    level ENUM('bronze','silver','gold','platinum','diamond') NOT NULL DEFAULT 'bronze',
    bonus_points INT UNSIGNED NOT NULL DEFAULT 0,
    awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('earn','withdraw','commission','bonus','referral') NOT NULL,
    amount DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    points INT NOT NULL DEFAULT 0,
    status ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'completed',
    reference VARCHAR(100) NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_transactions (user_id),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
);

-- ads table
CREATE TABLE IF NOT EXISTS ads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    ad_code TEXT NOT NULL,
    position ENUM('pre_task','post_task','dashboard','interstitial') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    impressions INT UNSIGNED NOT NULL DEFAULT 0,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- withdrawals table
CREATE TABLE IF NOT EXISTS withdrawals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,4) NOT NULL,
    method ENUM('paypal','bank_transfer','mobile_money','crypto_usdt') NOT NULL,
    account_details TEXT NULL,
    status ENUM('pending','approved','rejected','processing') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_withdrawals (user_id),
    INDEX idx_status (status)
);

-- notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_notif (user_id),
    INDEX idx_read (is_read)
);

-- rate_limit table (IP-based)
CREATE TABLE IF NOT EXISTS rate_limit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL DEFAULT 'task_complete',
    attempts INT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action (ip_address, action)
);

-- site_settings table
CREATE TABLE IF NOT EXISTS site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default settings
INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'RewardHub'),
('min_withdrawal', '1.00'),
('points_per_dollar', '10000'),
('recaptcha_site_key', ''),
('recaptcha_secret_key', ''),
('maintenance_mode', '0');
