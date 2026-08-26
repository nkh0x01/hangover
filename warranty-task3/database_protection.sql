CREATE TABLE IF NOT EXISTS gw_protection_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  plan_type VARCHAR(30) NOT NULL DEFAULT 'general',
  price_type VARCHAR(10) NOT NULL DEFAULT 'fixed',
  price_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  duration_months SMALLINT NOT NULL DEFAULT 12,
  coverage_json LONGTEXT,
  exclusions_json LONGTEXT,
  terms TEXT,
  terms_version INT NOT NULL DEFAULT 1,
  min_price DECIMAL(10,2) DEFAULT NULL,
  max_price DECIMAL(10,2) DEFAULT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_plan_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gw_customer_protections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  registration_id INT NOT NULL,
  customer_phone VARCHAR(20) DEFAULT NULL,
  plan_id INT DEFAULT NULL,
  plan_name_snapshot VARCHAR(190) DEFAULT NULL,
  plan_type_snapshot VARCHAR(30) DEFAULT NULL,
  coverage_snapshot LONGTEXT,
  terms_snapshot TEXT,
  terms_version_snapshot INT DEFAULT NULL,
  price_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
  starts_at DATE DEFAULT NULL,
  ends_at DATE DEFAULT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'active',
  payment_id INT DEFAULT NULL,
  public_token VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_prot_token (public_token),
  UNIQUE KEY uniq_prot_payment (payment_id),
  KEY idx_prot_phone (customer_phone),
  KEY idx_prot_reg (registration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gw_protection_claims (
  id INT AUTO_INCREMENT PRIMARY KEY,
  protection_id INT NOT NULL,
  incident_type VARCHAR(30) NOT NULL,
  incident_at DATE DEFAULT NULL,
  description TEXT,
  status VARCHAR(14) NOT NULL DEFAULT 'submitted',
  service_case_id INT DEFAULT NULL,
  recorded_cost DECIMAL(10,2) DEFAULT NULL,
  review_flags_json LONGTEXT,
  decided_by INT DEFAULT NULL,
  decided_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_claim_prot (protection_id),
  KEY idx_claim_status (status),
  KEY idx_claim_case (service_case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
