-- TermTrail AI schema (MySQL 5.7+/MariaDB 10.4+ via XAMPP)
-- Safe to re-run: uses IF NOT EXISTS and never drops data.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','viewer') NOT NULL DEFAULT 'viewer',
  theme ENUM('dark','light') NOT NULL DEFAULT 'dark',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_la_email (email, attempted_at),
  KEY idx_la_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pr_token (token_hash),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS counterparties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  contact_person VARCHAR(150) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  address TEXT NULL,
  notes TEXT NULL,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cp_owner (owner_id),
  KEY idx_cp_name (name),
  CONSTRAINT fk_cp_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contracts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  counterparty_id INT UNSIGNED NULL,
  reviewer_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  contract_type VARCHAR(80) NULL,
  effective_date DATE NULL,
  start_date DATE NULL,
  expiry_date DATE NULL,
  renewal_date DATE NULL,
  notice_period_days INT UNSIGNED NULL,
  renewal_decision ENUM('pending','renew','not_renew','renegotiate') NOT NULL DEFAULT 'pending',
  contract_value DECIMAL(15,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  status ENUM('draft','under_review','active','expired','terminated','archived') NOT NULL DEFAULT 'draft',
  tags VARCHAR(255) NULL,
  description TEXT NULL,
  original_filename VARCHAR(255) NULL,
  stored_filename VARCHAR(80) NULL,
  mime_type VARCHAR(100) NULL,
  file_size INT UNSIGNED NULL,
  extracted_text_path VARCHAR(255) NULL,
  extraction_error VARCHAR(255) NULL,
  analysis_status ENUM('none','uploaded','extracting','analyzing','saving_results','completed','failed') NOT NULL DEFAULT 'none',
  analysis_error VARCHAR(255) NULL,
  risk_score TINYINT UNSIGNED NULL,
  risk_level ENUM('low','medium','high') NULL,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ct_owner (owner_id),
  KEY idx_ct_cp (counterparty_id),
  KEY idx_ct_status (status),
  KEY idx_ct_expiry (expiry_date),
  KEY idx_ct_renewal (renewal_date),
  KEY idx_ct_risk (risk_level),
  KEY idx_ct_analysis (analysis_status),
  FULLTEXT KEY ft_ct_title (title, description, tags),
  CONSTRAINT fk_ct_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ct_cp FOREIGN KEY (counterparty_id) REFERENCES counterparties(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ct_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename VARCHAR(80) NOT NULL,
  mime_type VARCHAR(100) NULL,
  extracted_text_path VARCHAR(255) NULL,
  notes TEXT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cv (contract_id, version_number),
  CONSTRAINT fk_cv_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_cv_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processing_jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  stage ENUM('uploaded','extracting','analyzing','saving_results','completed','failed') NOT NULL DEFAULT 'uploaded',
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_chunk INT UNSIGNED NOT NULL DEFAULT 0,
  total_chunks INT UNSIGNED NOT NULL DEFAULT 0,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  error_message VARCHAR(255) NULL,
  partial_json LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pj_contract (contract_id),
  CONSTRAINT fk_pj_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analysis_results (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  mode ENUM('ai','fallback') NOT NULL DEFAULT 'ai',
  executive_summary TEXT NULL,
  detailed_explanation MEDIUMTEXT NULL,
  result_json LONGTEXT NULL,
  risk_score TINYINT UNSIGNED NULL,
  model_name VARCHAR(80) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ar_contract (contract_id),
  CONSTRAINT fk_ar_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_chunks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  chunk_index INT UNSIGNED NOT NULL,
  content MEDIUMTEXT NOT NULL,
  word_count INT UNSIGNED NOT NULL DEFAULT 0,
  embedding LONGTEXT NULL,
  UNIQUE KEY uq_dc (contract_id, chunk_index),
  FULLTEXT KEY ft_dc_content (content),
  CONSTRAINT fk_dc_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clauses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  clause_type VARCHAR(60) NOT NULL,
  summary TEXT NOT NULL,
  source_quote TEXT NULL,
  quote_verified TINYINT(1) NOT NULL DEFAULT 0,
  chunk_index INT UNSIGNED NULL,
  confidence DECIMAL(4,3) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cl_contract (contract_id, clause_type),
  CONSTRAINT fk_cl_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS risk_findings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  clause_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  severity ENUM('low','medium','high') NOT NULL,
  reason TEXT NULL,
  source_quote TEXT NULL,
  quote_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_inferred TINYINT(1) NOT NULL DEFAULT 0,
  suggested_action TEXT NULL,
  confidence DECIMAL(4,3) NULL,
  review_status ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  reviewer_comment TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rf_contract (contract_id, severity),
  CONSTRAINT fk_rf_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_rf_clause FOREIGN KEY (clause_id) REFERENCES clauses(id) ON DELETE SET NULL,
  CONSTRAINT fk_rf_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obligation_suggestions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  kind ENUM('obligation','payment') NOT NULL DEFAULT 'obligation',
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  responsible_party VARCHAR(190) NULL,
  due_date DATE NULL,
  amount DECIMAL(15,2) NULL,
  currency CHAR(3) NULL,
  recurrence VARCHAR(60) NULL,
  source_quote TEXT NULL,
  quote_verified TINYINT(1) NOT NULL DEFAULT 0,
  confidence DECIMAL(4,3) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  decided_by INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  reject_reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_os_contract (contract_id, status),
  CONSTRAINT fk_os_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_os_user FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obligations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  suggestion_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  responsible_party VARCHAR(190) NULL,
  owner_id INT UNSIGNED NULL,
  due_date DATE NULL,
  recurrence VARCHAR(60) NULL,
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  status ENUM('open','completed','overdue','cancelled') NOT NULL DEFAULT 'open',
  completion_notes TEXT NULL,
  source_quote TEXT NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ob_contract (contract_id),
  KEY idx_ob_due (due_date, status),
  KEY idx_ob_owner (owner_id),
  CONSTRAINT fk_ob_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ob_sugg FOREIGN KEY (suggestion_id) REFERENCES obligation_suggestions(id) ON DELETE SET NULL,
  CONSTRAINT fk_ob_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ob_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  suggestion_id INT UNSIGNED NULL,
  amount DECIMAL(15,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  category VARCHAR(80) NULL,
  due_date DATE NOT NULL,
  status ENUM('planned','due','paid','overdue','cancelled') NOT NULL DEFAULT 'planned',
  paid_date DATE NULL,
  reference_number VARCHAR(100) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pay_contract (contract_id),
  KEY idx_pay_due (due_date, status),
  CONSTRAINT fk_pay_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_sugg FOREIGN KEY (suggestion_id) REFERENCES obligation_suggestions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reminders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  entity_type ENUM('contract_expiry','contract_renewal','payment','obligation') NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  window_days SMALLINT UNSIGNED NOT NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  email_sent TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_rem (user_id, entity_type, entity_id, window_days),
  CONSTRAINT fk_rem_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  message VARCHAR(500) NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_nt_user (user_id, is_read, created_at),
  CONSTRAINT fk_nt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rn_contract (contract_id),
  CONSTRAINT fk_rn_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_rn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('user','assistant') NOT NULL,
  message MEDIUMTEXT NOT NULL,
  sources_json TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_chat (contract_id, user_id, created_at),
  CONSTRAINT fk_chat_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  metadata TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_al_user (user_id),
  KEY idx_al_action (action, created_at),
  KEY idx_al_entity (entity_type, entity_id),
  CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS version_comparisons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  from_version_id INT UNSIGNED NOT NULL,
  to_version_id INT UNSIGNED NOT NULL,
  added_count INT UNSIGNED NOT NULL DEFAULT 0,
  removed_count INT UNSIGNED NOT NULL DEFAULT 0,
  summary_json LONGTEXT NULL,
  compared_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_vc_contract (contract_id),
  CONSTRAINT fk_vc_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_vc_from FOREIGN KEY (from_version_id) REFERENCES contract_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_vc_to FOREIGN KEY (to_version_id) REFERENCES contract_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_vc_user FOREIGN KEY (compared_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clause_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  category VARCHAR(60) NOT NULL,
  clause_text MEDIUMTEXT NOT NULL,
  plain_explanation TEXT NULL,
  tags VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tpl_cat (category),
  FULLTEXT KEY ft_tpl (name, clause_text, tags),
  CONSTRAINT fk_tpl_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
