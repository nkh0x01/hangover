CREATE TABLE IF NOT EXISTS gw_service_estimates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service_case_id INT NOT NULL,
  version INT NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'awaiting_customer',
  labor DECIMAL(10,2) NOT NULL DEFAULT 0,
  parts_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  other DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes TEXT,
  approval_token VARCHAR(64) DEFAULT NULL,
  decided_at DATETIME DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_est_token (approval_token),
  KEY idx_case_ver (service_case_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gw_service_parts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service_case_id INT NOT NULL,
  name VARCHAR(190) NOT NULL,
  sku VARCHAR(80) DEFAULT NULL,
  qty INT NOT NULL DEFAULT 1,
  est_unit_cost DECIMAL(10,2) DEFAULT NULL,
  actual_unit_cost DECIMAL(10,2) DEFAULT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'requested',
  is_critical TINYINT NOT NULL DEFAULT 1,
  requested_by INT DEFAULT NULL,
  requested_at DATETIME DEFAULT NULL,
  received_at DATETIME DEFAULT NULL,
  installed_at DATETIME DEFAULT NULL,
  KEY idx_part_case (service_case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gw_service_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service_case_id INT NOT NULL,
  type VARCHAR(16) NOT NULL,
  user_id INT DEFAULT NULL,
  note TEXT,
  meta_json LONGTEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_act_case (service_case_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
