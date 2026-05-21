-- InterLink Database Schema
-- Import: mysql -u root -p InterLink < sql/schema.sql

CREATE DATABASE IF NOT EXISTS InterLink DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE InterLink;

-- --------------------------------------------------------
-- 1. users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) UNIQUE NOT NULL,
    email         VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100),
    avatar_url    VARCHAR(255) DEFAULT 'default.png',
    bio           TEXT,
    phone         VARCHAR(20),
    role          ENUM('user','admin') DEFAULT 'user',
    status        ENUM('active','banned','deactivated') DEFAULT 'active',
    is_online     TINYINT(1) DEFAULT 0,
    last_seen     DATETIME,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME ON UPDATE CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- 2. conversations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    type            ENUM('private','group') NOT NULL,
    created_by      INT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- 3. conversation_members
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversation_members (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id         INT NOT NULL,
    role            ENUM('member','admin') DEFAULT 'member',
    joined_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    left_at         DATETIME DEFAULT NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_member (conversation_id, user_id)
);

-- --------------------------------------------------------
-- 4. groups
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `groups` (
    group_id        INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    avatar_url      VARCHAR(255) DEFAULT 'group_default.png',
    created_by      INT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- 5. messages
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    message_id      INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id       INT NOT NULL,
    message_type    ENUM('text','image','file','video','audio','system') DEFAULT 'text',
    content         TEXT,
    reply_to        INT DEFAULT NULL,
    is_edited       TINYINT(1) DEFAULT 0,
    is_deleted      TINYINT(1) DEFAULT 0,
    sent_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to) REFERENCES messages(message_id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- 6. message_status
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS message_status (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    message_id  INT NOT NULL,
    user_id     INT NOT NULL,
    delivered   TINYINT(1) DEFAULT 0,
    read_at     DATETIME DEFAULT NULL,
    FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_status (message_id, user_id)
);

-- --------------------------------------------------------
-- 7. files
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS files (
    file_id         INT AUTO_INCREMENT PRIMARY KEY,
    message_id      INT NULL,         -- NULL until linked to a message after send
    uploaded_by     INT NOT NULL,
    original_name   VARCHAR(255),
    stored_name     VARCHAR(255),
    file_type       VARCHAR(50),
    file_size       INT,
    uploaded_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- 8. notifications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    type            VARCHAR(50),
    reference_id    INT,
    message         VARCHAR(255),
    is_read         TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- 9. reports
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
    report_id       INT AUTO_INCREMENT PRIMARY KEY,
    reported_by     INT NOT NULL,
    reported_user   INT,
    message_id      INT,
    reason          TEXT,
    status          ENUM('pending','reviewed','dismissed') DEFAULT 'pending',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- 10. friendships
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS friendships (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    addressee_id INT NOT NULL,
    status       ENUM('pending','accepted','rejected','blocked') DEFAULT 'pending',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (addressee_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_friendship (requester_id, addressee_id),
    INDEX idx_addressee (addressee_id),
    INDEX idx_status (status)
);

-- --------------------------------------------------------
-- Seed: Default admin user (password: Admin@1234)
-- --------------------------------------------------------
INSERT INTO users (username, email, password_hash, full_name, role, status, is_online)
VALUES ('admin', 'admin@InterLink.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 'active', 0);
-- NOTE: Default password hash above is for 'password'. Change immediately in production.

-- --------------------------------------------------------
-- 10. call_signals  (WebRTC signaling relay)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS call_signals (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    from_user       INT NOT NULL,
    to_user         INT NOT NULL,
    conversation_id INT DEFAULT 0,
    type            VARCHAR(30) NOT NULL,
    payload         MEDIUMTEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (to_user)   REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_to_user  (to_user),
    INDEX idx_created  (created_at)
);
