-- Drop the database if it already exists
DROP DATABASE IF EXISTS ashesi_club_hub;

-- Create the database
CREATE DATABASE ashesi_club_hub;

-- Use the newly created database
USE ashesi_club_hub;

-- Club Users Table (Updated with Role)
CREATE TABLE club_users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role TINYINT NOT NULL DEFAULT 3 COMMENT '1: Super Admin, 2: Club Admin, 3: Regular User',
    created_by INT, -- To track who created this user account
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES club_users(user_id)
);

-- Clubs Table (Updated to include more management details)
CREATE TABLE clubs (
    club_id INT PRIMARY KEY AUTO_INCREMENT,
    club_name VARCHAR(100) NOT NULL,
    description TEXT,
    logo_path VARCHAR(255),
    club_head_id INT,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT, -- Super admin who created the club
    FOREIGN KEY (club_head_id) REFERENCES club_users(user_id),
    FOREIGN KEY (created_by) REFERENCES club_users(user_id)
);

-- Club Memberships Table
CREATE TABLE club_memberships (
    membership_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    club_id INT,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, club_id),
    FOREIGN KEY (user_id) REFERENCES club_users(user_id),
    FOREIGN KEY (club_id) REFERENCES clubs(club_id)
);

-- Events Table
CREATE TABLE events (
    event_id INT PRIMARY KEY AUTO_INCREMENT,
    club_id INT,
    event_title VARCHAR(100) NOT NULL,
    description TEXT,
    event_date DATETIME NOT NULL,
    total_slots INT NOT NULL,
    current_slots INT NOT NULL,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_by INT, -- User who created the event
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(club_id),
    FOREIGN KEY (created_by) REFERENCES club_users(user_id)
);

-- Event Registrations Table
CREATE TABLE event_registrations (
    registration_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    event_id INT,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, event_id),
    FOREIGN KEY (user_id) REFERENCES club_users(user_id),
    FOREIGN KEY (event_id) REFERENCES events(event_id)
);

-- Posts Table
CREATE TABLE posts (
    post_id INT PRIMARY KEY AUTO_INCREMENT,
    club_id INT,
    user_id INT, -- Who created the post
    content TEXT,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(club_id),
    FOREIGN KEY (user_id) REFERENCES club_users(user_id)
);

-- Likes Table
CREATE TABLE likes (
    like_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    post_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES club_users(user_id),
    FOREIGN KEY (post_id) REFERENCES posts(post_id)
);

-- Comments Table
CREATE TABLE comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    post_id INT,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES club_users(user_id),
    FOREIGN KEY (post_id) REFERENCES posts(post_id)
);

-- Admin Actions Log Table
CREATE TABLE admin_actions_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT, -- Who performed the action
    action_type ENUM('create', 'update', 'delete') NOT NULL,
    target_table VARCHAR(50) NOT NULL, -- Which table was modified
    target_id INT NOT NULL, -- ID of the record modified
    details TEXT, -- Additional details about the action
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES club_users(user_id)
);

-- Insert initial super admin
INSERT INTO club_users (full_name, email, password, role) VALUES 
('Super', 'Admin', 'superadmin@ashesi.edu.gh', 
-- Use a secure password hash (this is a placeholder - use real bcrypt/password_hash in actual implementation)
'$2y$10$ZVQ7Qy5nZV5nZVQyZVQ3Qe.zk/PfFhD5qR3S1PfFhD5qR3S1PfFhD', 
1);