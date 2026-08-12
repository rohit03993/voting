-- HCS Voting System — MySQL schema
-- Run once via install/index.php or phpMyAdmin

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS elections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  status ENUM('draft','live','closed') NOT NULL DEFAULT 'draft',
  principal_token VARCHAR(64) NOT NULL,
  director_token VARCHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  published_at DATETIME NULL,
  closed_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS positions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_positions_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
  INDEX idx_positions_election (election_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NOT NULL,
  position_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  class_name VARCHAR(100) NOT NULL DEFAULT '',
  photo VARCHAR(255) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_candidates_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
  CONSTRAINT fk_candidates_position FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
  INDEX idx_candidates_position (position_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ballots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NOT NULL,
  voter_type ENUM('student','staff','principal','director') NOT NULL,
  voter_token VARCHAR(64) NOT NULL,
  ip_hash VARCHAR(64) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ballots_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
  UNIQUE KEY uq_ballot_token (election_id, voter_type, voter_token),
  INDEX idx_ballots_election_type (election_id, voter_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS votes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ballot_id INT UNSIGNED NOT NULL,
  election_id INT UNSIGNED NOT NULL,
  position_id INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  voter_type ENUM('student','staff','principal','director') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_votes_ballot FOREIGN KEY (ballot_id) REFERENCES ballots(id) ON DELETE CASCADE,
  CONSTRAINT fk_votes_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
  CONSTRAINT fk_votes_position FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
  CONSTRAINT fk_votes_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  UNIQUE KEY uq_ballot_position (ballot_id, position_id),
  INDEX idx_votes_tally (election_id, position_id, candidate_id, voter_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(64) PRIMARY KEY,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
