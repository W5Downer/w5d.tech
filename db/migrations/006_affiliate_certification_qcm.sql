-- Affiliate programme, certificates, and the QCM (multiple-choice) engine.
-- Every table that the portal edits through src/Crud.php carries country_id (Crud writes 'tn').
ALTER TABLE users ADD COLUMN referred_by INTEGER;

CREATE TABLE affiliates (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn',
  user_id INTEGER NOT NULL UNIQUE, code TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL DEFAULT 'pending',            -- pending | active | suspended
  pitch TEXT, payout_gsm TEXT, note TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, approved_at TEXT
);
CREATE INDEX ix_affiliates_status ON affiliates(status);

CREATE TABLE affiliate_clicks (
  id INTEGER PRIMARY KEY AUTOINCREMENT, affiliate_id INTEGER NOT NULL, day TEXT NOT NULL, visitor TEXT NOT NULL,
  landing TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(affiliate_id, day, visitor)
);

CREATE TABLE affiliate_referrals (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn',
  affiliate_id INTEGER NOT NULL, kind TEXT NOT NULL,   -- signup | payment | certificate
  user_id INTEGER NOT NULL, ref_key TEXT NOT NULL,
  base_amount REAL NOT NULL DEFAULT 0, commission REAL NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'pending',               -- pending | approved | paid | rejected
  note TEXT, paid_in INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, approved_at TEXT,
  UNIQUE(kind, ref_key)
);
CREATE INDEX ix_aff_ref_aff ON affiliate_referrals(affiliate_id, status);

CREATE TABLE affiliate_payouts (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn',
  affiliate_id INTEGER NOT NULL, amount REAL NOT NULL, gsm TEXT,
  status TEXT NOT NULL DEFAULT 'requested',             -- requested | paid | rejected
  txn_ref TEXT, note TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, paid_at TEXT
);
CREATE INDEX ix_aff_pay_status ON affiliate_payouts(status);

CREATE TABLE certificates (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn',
  code TEXT NOT NULL UNIQUE, user_id INTEGER NOT NULL, service_slug TEXT NOT NULL, level INTEGER NOT NULL DEFAULT 1,
  score REAL, attempt_id INTEGER, status TEXT NOT NULL DEFAULT 'valid',   -- valid | revoked (expired is computed)
  issued_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at TEXT, revoked_at TEXT, revoke_reason TEXT, issued_by TEXT
);
CREATE INDEX ix_cert_user ON certificates(user_id, status);

CREATE TABLE qcm_rules (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn',
  name TEXT NOT NULL, purpose TEXT NOT NULL DEFAULT 'practice',       -- practice | certification | upgrade
  level INTEGER NOT NULL DEFAULT 1, service_slug TEXT NOT NULL DEFAULT '',   -- '' = a random service
  question_count INTEGER NOT NULL DEFAULT 10, minutes INTEGER NOT NULL DEFAULT 10, pass_pct INTEGER NOT NULL DEFAULT 70,
  fee REAL NOT NULL DEFAULT 0, min_level INTEGER NOT NULL DEFAULT 0, min_affiliates INTEGER NOT NULL DEFAULT 0,
  min_tasks INTEGER NOT NULL DEFAULT 0, min_practice_pct INTEGER NOT NULL DEFAULT 0,
  cooldown_hours INTEGER NOT NULL DEFAULT 0, max_per_day INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE qcm_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn',
  user_id INTEGER NOT NULL, rule_id INTEGER NOT NULL, purpose TEXT NOT NULL, level INTEGER NOT NULL, service_slug TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'open',                                  -- open | submitted
  questions TEXT NOT NULL, answers TEXT NOT NULL DEFAULT '{}',
  started_at TEXT NOT NULL, deadline_at TEXT NOT NULL, submitted_at TEXT,
  correct INTEGER, total INTEGER, score_pct REAL, passed INTEGER NOT NULL DEFAULT 0, timed_out INTEGER NOT NULL DEFAULT 0,
  pass_id INTEGER, cert_code TEXT
);
CREATE INDEX ix_qcm_att_user ON qcm_attempts(user_id, status, started_at);

CREATE TABLE qcm_passes (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn',
  ref TEXT NOT NULL UNIQUE, user_id INTEGER NOT NULL, rule_id INTEGER NOT NULL,
  amount REAL NOT NULL, currency TEXT NOT NULL DEFAULT 'TND', sender_gsm TEXT, txn_ref TEXT,
  status TEXT NOT NULL DEFAULT 'awaiting',                              -- awaiting | pending | verified | rejected | used
  note TEXT, verified_by TEXT, verified_at TEXT, attempt_id INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_qcm_pass_user ON qcm_passes(user_id, status);

CREATE TABLE qcm_tasks (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn',
  user_id INTEGER NOT NULL, service_slug TEXT NOT NULL DEFAULT '', title TEXT NOT NULL, evidence TEXT,
  status TEXT NOT NULL DEFAULT 'submitted',                             -- submitted | approved | rejected
  reviewer TEXT, note TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_qcm_task_user ON qcm_tasks(user_id, status);

-- Demo rules (owner edits or replaces them in the portal: QCM rules). Level 1 certification is free; upgrades add requirements.
INSERT INTO qcm_rules (name, purpose, level, question_count, minutes, pass_pct, fee, min_level, min_affiliates, min_tasks, min_practice_pct, cooldown_hours, max_per_day, sort) VALUES
 ('Practice L1', 'practice', 1, 10, 10, 60, 0, 0, 0, 0, 0, 0, 20, 10),
 ('Practice L2', 'practice', 2, 10, 10, 60, 0, 0, 0, 0, 0, 0, 20, 20),
 ('Practice L3', 'practice', 3, 10, 10, 60, 0, 0, 0, 0, 0, 0, 20, 30),
 ('Practice L4', 'practice', 4, 10, 10, 60, 0, 0, 0, 0, 0, 0, 20, 40),
 ('Certification L1', 'certification', 1, 10, 10, 70, 0, 0, 0, 0, 60, 1, 3, 110),
 ('Upgrade to L2', 'upgrade', 2, 10, 10, 75, 0, 1, 0, 1, 60, 6, 3, 120),
 ('Upgrade to L3', 'upgrade', 3, 10, 10, 80, 15, 2, 0, 1, 60, 24, 2, 130),
 ('Upgrade to L4', 'upgrade', 4, 10, 10, 85, 30, 3, 1, 2, 70, 48, 1, 140);
