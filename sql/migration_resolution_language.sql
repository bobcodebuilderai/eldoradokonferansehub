-- Migration: Add screen resolution and language columns to existing conferences table
-- Run this SQL to upgrade from older versions

-- Add screen width column with default 1920 (Full HD)
ALTER TABLE conferences ADD COLUMN IF NOT EXISTS screen_width INT DEFAULT 1920;

-- Add screen height column with default 1080 (Full HD)
ALTER TABLE conferences ADD COLUMN IF NOT EXISTS screen_height INT DEFAULT 1080;

-- Add language column with default 'no' (Norwegian)
ALTER TABLE conferences ADD COLUMN IF NOT EXISTS language VARCHAR(5) DEFAULT 'no';
