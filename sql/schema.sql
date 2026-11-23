-- Portable kos portal database (fresh start)
CREATE DATABASE IF NOT EXISTS kos_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kos_portal;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value TEXT
);

CREATE TABLE IF NOT EXISTS ticker_config (
  id TINYINT PRIMARY KEY DEFAULT 1,
  text TEXT,
  speed VARCHAR(10) DEFAULT '22s',
  color VARCHAR(20) DEFAULT 'amber',
  active TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS tab_configs (
  tab_key VARCHAR(50) PRIMARY KEY,
  nav_label VARCHAR(100) NOT NULL,
  section_title VARCHAR(255),
  section_desc TEXT,
  is_system TINYINT(1) DEFAULT 1,
  is_sub_section TINYINT(1) DEFAULT 0,
  sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS rooms (
  id VARCHAR(10) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  floor VARCHAR(100),
  normal_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_active TINYINT(1) DEFAULT 0,
  promo_price DECIMAL(12,2) DEFAULT 0,
  discount_until DATE NULL,
  promo_label VARCHAR(100),
  status ENUM('available','occupied','renovation') DEFAULT 'available',
  tenant_name VARCHAR(100) NULL,
  description TEXT,
  facilities JSON NULL
);

CREATE TABLE IF NOT EXISTS tenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  pin VARCHAR(20),
  wifi_password VARCHAR(50),
  full_name VARCHAR(100) NOT NULL,
  room_id VARCHAR(10),
  nickname VARCHAR(60),
  phone VARCHAR(30),
  emergency_phone VARCHAR(100),
  origin_address VARCHAR(255),
  profession VARCHAR(100),
  workplace VARCHAR(150),
  social_handle VARCHAR(60),
  ktp_photo LONGTEXT NULL,
  join_date VARCHAR(40),
  billing_status VARCHAR(30),
  billing_amount VARCHAR(40),
  billing_due VARCHAR(40),
  status ENUM('active','moved_out','suspended') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS invite_codes (
  code VARCHAR(50) PRIMARY KEY,
  room_id VARCHAR(10),
  room_name VARCHAR(100),
  nickname VARCHAR(60),
  used TINYINT(1) DEFAULT 0,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS guestbook_entries (
  id VARCHAR(20) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  category VARCHAR(100),
  message TEXT,
  photo LONGTEXT NULL,
  approved TINYINT(1) DEFAULT 0,
  entry_date VARCHAR(40)
);

CREATE TABLE IF NOT EXISTS nearby_locations (
  id VARCHAR(20) PRIMARY KEY,
  icon VARCHAR(20),
  name VARCHAR(150) NOT NULL,
  dist VARCHAR(40),
  time_label VARCHAR(60),
  description TEXT,
  sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS promos (
  id VARCHAR(30) PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  category VARCHAR(30),
  badge VARCHAR(80),
  description TEXT,
  normal_price DECIMAL(12,2) DEFAULT 0,
  unit_label VARCHAR(40),
  discount_active TINYINT(1) DEFAULT 0,
  promo_price DECIMAL(12,2) DEFAULT 0,
  discount_until DATE NULL,
  promo_label VARCHAR(100),
  image LONGTEXT NULL
);

CREATE TABLE IF NOT EXISTS sedhah_menu (
  id VARCHAR(30) PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(80),
  description TEXT,
  normal_price DECIMAL(12,2) DEFAULT 0,
  unit_label VARCHAR(40),
  discount_active TINYINT(1) DEFAULT 0,
  promo_price DECIMAL(12,2) DEFAULT 0,
  discount_until DATE NULL,
  promo_label VARCHAR(100),
  image LONGTEXT NULL
);

CREATE TABLE IF NOT EXISTS radio_stations (
  id VARCHAR(20) PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  genre VARCHAR(100),
  url VARCHAR(255) NOT NULL,
  is_live TINYINT(1) DEFAULT 0,
  live_started_at DATETIME NULL,
  sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS mirc_channels (
  channel VARCHAR(50) PRIMARY KEY,
  topic TEXT
);

CREATE TABLE IF NOT EXISTS mirc_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  channel VARCHAR(50) NOT NULL,
  msg_type ENUM('system','chat') DEFAULT 'chat',
  nick VARCHAR(60),
  role VARCHAR(20),
  text TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (channel) REFERENCES mirc_channels(channel) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS alumni_archive (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100),
  room_id VARCHAR(10),
  note TEXT,
  moved_out_at DATE
);

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','tenant') DEFAULT 'admin',
  tenant_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, role) VALUES ('admin', '$2y$12$2cLaYJeGw7vUx5mh4yUdxugEXQTcLV4uTyU3bQsMdrO4rrvNedIM2', 'admin')
ON DUPLICATE KEY UPDATE username = username;
