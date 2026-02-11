-- Conference Interactive System - Database Setup
-- Run this SQL to create the database and tables

CREATE DATABASE IF NOT EXISTS conference_interactive 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE conference_interactive;

-- Users table (admin accounts)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reset_token VARCHAR(100) NULL,
    reset_expires DATETIME NULL,
    INDEX idx_reset_token (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conferences table
CREATE TABLE conferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    unique_code VARCHAR(20) UNIQUE NOT NULL,
    require_email BOOLEAN DEFAULT FALSE,
    require_job_title BOOLEAN DEFAULT FALSE,
    overlay_background VARCHAR(20) DEFAULT 'graphic',
    screen_width INT DEFAULT 1920,
    screen_height INT DEFAULT 1080,
    language VARCHAR(5) DEFAULT 'no',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_unique_code (unique_code),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Participants table (guests)
CREATE TABLE participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conference_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    job_title VARCHAR(100) NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE,
    INDEX idx_conference_id (conference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions table (FROM conference to guests)
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conference_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('single', 'multiple', 'wordcloud', 'rating') NOT NULL,
    options JSON NULL,
    chart_type ENUM('pie', 'bar_horizontal', 'bar_vertical', 'wordcloud') NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    show_results BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE,
    INDEX idx_conference_id (conference_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Answers table
CREATE TABLE answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    participant_id INT NOT NULL,
    answer_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    INDEX idx_question_id (question_id),
    INDEX idx_participant_id (participant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guest questions table (questions FROM guests TO conference)
CREATE TABLE guest_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conference_id INT NOT NULL,
    participant_id INT NOT NULL,
    question_text TEXT NOT NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'approved', 'rejected', 'displayed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    INDEX idx_conference_id (conference_id),
    INDEX idx_status (status),
    INDEX idx_participant_id (participant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: Add overlay_background column if upgrading from older version
-- ALTER TABLE conferences ADD COLUMN IF NOT EXISTS overlay_background VARCHAR(20) DEFAULT 'graphic';

-- Migration: Add screen resolution and language columns if upgrading from older version
-- ALTER TABLE conferences ADD COLUMN IF NOT EXISTS screen_width INT DEFAULT 1920;
-- ALTER TABLE conferences ADD COLUMN IF NOT EXISTS screen_height INT DEFAULT 1080;
-- ALTER TABLE conferences ADD COLUMN IF NOT EXISTS language VARCHAR(5) DEFAULT 'no';
