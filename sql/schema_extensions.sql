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
