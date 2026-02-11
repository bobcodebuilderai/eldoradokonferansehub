-- Eldorado Konferansehub - Database Schema Extensions
-- Role-based access, file sharing, and run of show functionality

-- ============================================
-- 1. ROLES SYSTEM
-- ============================================

-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default roles
INSERT INTO roles (name, description) VALUES 
    ('admin', 'System administrator with full access'),
    ('venue_admin', 'Venue administrator - access to all conferences'),
    ('customer', 'Customer/Conference organizer - access to own conferences only')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Add role_id to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS role_id INT DEFAULT NULL;
ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_venue_admin BOOLEAN DEFAULT FALSE;

-- ============================================
-- 2. FILE SHARING SYSTEM
-- ============================================

CREATE TABLE IF NOT EXISTS conference_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conference_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type ENUM('pdf', 'image', 'video', 'audio', 'other') NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    description TEXT,
    is_venue_visible BOOLEAN DEFAULT TRUE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_files_conference ON conference_files(conference_id);
CREATE INDEX idx_files_type ON conference_files(file_type);

-- ============================================
-- 3. RUN OF SHOW (KJØREPLAN) SYSTEM
-- ============================================

CREATE TABLE IF NOT EXISTS run_of_show_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conference_id INT NOT NULL,
    
    -- Basic info
    title VARCHAR(255) NOT NULL,
    description TEXT,
    block_type ENUM('presentation', 'break', 'video', 'audio', 'other') DEFAULT 'presentation',
    
    -- Timing
    start_time TIME NOT NULL,
    duration_minutes INT NOT NULL,
    end_time TIME GENERATED ALWAYS AS (SEC_TO_TIME(TIME_TO_SEC(start_time) + duration_minutes * 60)) STORED,
    day_number INT DEFAULT 1,
    
    -- Location & Responsible
    location VARCHAR(100),
    responsible_person VARCHAR(100),
    
    -- Technical requirements (JSON for flexibility)
    tech_requirements JSON,
    -- Example: {"microphone": true, "presentation": true, "video": false, "lighting": true, "audience_interaction": false}
    
    -- Display settings
    color_code VARCHAR(7) DEFAULT '#3b82f6',
    display_order INT DEFAULT 0,
    
    -- Status for live management
    status ENUM('pending', 'active', 'completed', 'skipped') DEFAULT 'pending',
    actual_start_time TIMESTAMP NULL,
    actual_end_time TIMESTAMP NULL,
    
    -- Comments
    venue_notes TEXT,
    presenter_notes TEXT,
    
    -- Attachments
    attachment_file_id INT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE,
    FOREIGN KEY (attachment_file_id) REFERENCES conference_files(id) ON DELETE SET NULL
);

CREATE INDEX idx_ros_conference ON run_of_show_blocks(conference_id);
CREATE INDEX idx_ros_day ON run_of_show_blocks(day_number);
CREATE INDEX idx_ros_order ON run_of_show_blocks(display_order);
CREATE INDEX idx_ros_status ON run_of_show_blocks(status);

-- ============================================
-- 4. CONFERENCE DAYS (for multi-day events)
-- ============================================

CREATE TABLE IF NOT EXISTS conference_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conference_id INT NOT NULL,
    day_number INT NOT NULL,
    date DATE NOT NULL,
    start_time TIME DEFAULT '08:00:00',
    end_time TIME DEFAULT '17:00:00',
    description VARCHAR(255),
    FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE,
    UNIQUE KEY unique_conference_day (conference_id, day_number)
);

-- ============================================
-- 5. CONFERENCE SETTINGS EXTENSIONS
-- ============================================

ALTER TABLE conferences ADD COLUMN IF NOT EXISTS venue_id INT DEFAULT NULL;
ALTER TABLE conferences ADD COLUMN IF NOT EXISTS is_public BOOLEAN DEFAULT FALSE;
ALTER TABLE conferences ADD COLUMN IF NOT EXISTS event_start_date DATE DEFAULT NULL;
ALTER TABLE conferences ADD COLUMN IF NOT EXISTS event_end_date DATE DEFAULT NULL;
ALTER TABLE conferences ADD COLUMN IF NOT EXISTS venue_notes TEXT;

-- ============================================
-- 6. ACTIVITY LOG (for audit trail)
-- ============================================

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    conference_id INT,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE
);

CREATE INDEX idx_activity_conference ON activity_log(conference_id);
CREATE INDEX idx_activity_user ON activity_log(user_id);
CREATE INDEX idx_activity_created ON activity_log(created_at);

-- ============================================
-- 7. RUN OF SHOW COMMENTS
-- ============================================

CREATE TABLE IF NOT EXISTS ros_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    block_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    comment_type ENUM('general', 'technical', 'urgent', 'presenter') DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (block_id) REFERENCES run_of_show_blocks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_ros_comments_block ON ros_comments(block_id);
CREATE INDEX idx_ros_comments_user ON ros_comments(user_id);
CREATE INDEX idx_ros_comments_created ON ros_comments(created_at);

-- Track which comments have been read by which users
CREATE TABLE IF NOT EXISTS ros_comment_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES ros_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_read (comment_id, user_id)
);

-- ============================================
-- 8. USER PHONE NUMBERS (for SMS)
-- ============================================

ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL;
ALTER TABLE users ADD INDEX idx_users_phone (phone);

-- ============================================
-- 9. SMS NOTIFICATION LOG
-- ============================================

CREATE TABLE IF NOT EXISTS sms_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    conference_id INT,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    gateway_response VARCHAR(100),
    status ENUM('pending', 'sent', 'failed', 'delivered') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE
);

CREATE INDEX idx_sms_log_user ON sms_log(user_id);
CREATE INDEX idx_sms_log_conference ON sms_log(conference_id);
CREATE INDEX idx_sms_log_status ON sms_log(status);

-- ============================================
-- 10. NOTIFICATION PREFERENCES
-- ============================================

ALTER TABLE users ADD COLUMN IF NOT EXISTS notification_method ENUM('email', 'sms', 'both', 'none') DEFAULT 'email';
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications BOOLEAN DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS sms_notifications BOOLEAN DEFAULT FALSE;

-- ============================================
-- 11. NOTIFICATION LOG
-- ============================================

CREATE TABLE IF NOT EXISTS notification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    conference_id INT,
    type ENUM('sms', 'email') NOT NULL,
    subject VARCHAR(255),
    message TEXT,
    status ENUM('pending', 'sent', 'failed', 'delivered') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE
);

CREATE INDEX idx_notification_log_user ON notification_log(user_id);
CREATE INDEX idx_notification_log_conference ON notification_log(conference_id);
