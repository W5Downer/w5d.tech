-- 001_initial: platform tables (README §Data model). SQLite-compatible SQL only.
-- Every table carries country_id (schema_migrations is the only exemption).

CREATE TABLE countries (
  country_id TEXT PRIMARY KEY, iso TEXT NOT NULL, name_en TEXT NOT NULL, currency TEXT NOT NULL,
  timezone TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE regions (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, slug TEXT NOT NULL,
  name_en TEXT NOT NULL, name_fr TEXT, name_ar TEXT, quota INTEGER NOT NULL DEFAULT 0,
  UNIQUE(country_id, slug)
);
CREATE TABLE roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, slug TEXT NOT NULL, label TEXT NOT NULL,
  UNIQUE(country_id, slug)
);
CREATE TABLE permissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, role_slug TEXT NOT NULL, perm TEXT NOT NULL,
  UNIQUE(country_id, role_slug, perm)
);
CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, email TEXT NOT NULL, name TEXT,
  role_slug TEXT NOT NULL DEFAULT 'user', password_hash TEXT, region_id INTEGER, active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(country_id, email)
);
CREATE TABLE applicants (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, cin TEXT NOT NULL, full_name TEXT NOT NULL,
  email TEXT, phone TEXT, region_id INTEGER, macro_slug TEXT, service_slug TEXT, diploma TEXT,
  status TEXT NOT NULL DEFAULT 'registered', lang TEXT NOT NULL DEFAULT 'en', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX ux_applicants_cin ON applicants(country_id, cin);
CREATE INDEX ix_applicants_status ON applicants(country_id, status);
CREATE INDEX ix_applicants_macro ON applicants(country_id, macro_slug, status);
CREATE INDEX ix_applicants_region ON applicants(country_id, region_id, status);
CREATE TABLE documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, applicant_id INTEGER NOT NULL, kind TEXT NOT NULL,
  storage_ref TEXT NOT NULL, bytes INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE verifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, applicant_id INTEGER NOT NULL, kind TEXT NOT NULL,
  result TEXT NOT NULL DEFAULT 'pending', reviewer_id INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE services_country (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, service_slug TEXT NOT NULL,
  active INTEGER NOT NULL DEFAULT 0, seats INTEGER NOT NULL DEFAULT 0, leader_id INTEGER,
  UNIQUE(country_id, service_slug)
);
CREATE TABLE cohorts (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, service_slug TEXT NOT NULL, level TEXT NOT NULL DEFAULT 'L1',
  starts_on TEXT, seats INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE cert_submissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, applicant_id INTEGER NOT NULL, service_slug TEXT NOT NULL,
  task TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'submitted', reviewer_id INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE teams (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, service_slug TEXT NOT NULL, manager_id INTEGER,
  name TEXT, cap INTEGER NOT NULL DEFAULT 12
);
CREATE TABLE team_members (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, team_id INTEGER NOT NULL, applicant_id INTEGER NOT NULL,
  UNIQUE(country_id, team_id, applicant_id)
);
CREATE TABLE directives (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, week_id TEXT NOT NULL, service_slug TEXT,
  body TEXT NOT NULL, created_by INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE weekly_reports (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, week_id TEXT NOT NULL, applicant_id INTEGER NOT NULL,
  manager_id INTEGER, service_slug TEXT NOT NULL, evidence TEXT, units INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'submitted', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_wr_week ON weekly_reports(country_id, week_id, status);
CREATE INDEX ix_wr_mgr ON weekly_reports(country_id, manager_id, week_id);
CREATE TABLE approvals (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, report_id INTEGER NOT NULL, approver_id INTEGER NOT NULL,
  decision TEXT NOT NULL, note TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE clients (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, name TEXT NOT NULL, email TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE contracts (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, client_id INTEGER NOT NULL, scope TEXT, hours INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'draft', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE invoices (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, contract_id INTEGER, amount REAL NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'draft', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE payroll_runs (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, period TEXT NOT NULL, posts INTEGER NOT NULL DEFAULT 0,
  amount REAL NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'simulated'
);
CREATE TABLE campaigns (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, name TEXT NOT NULL, channel TEXT, utm TEXT,
  daily_quota INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, channel TEXT NOT NULL, template TEXT, recipient TEXT,
  status TEXT NOT NULL DEFAULT 'queued', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE tickets (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, subject TEXT NOT NULL, body TEXT, requester TEXT,
  status TEXT NOT NULL DEFAULT 'open', due_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, actor TEXT NOT NULL, action TEXT NOT NULL, subject TEXT,
  detail TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE consents (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, subject_ref TEXT NOT NULL, purpose TEXT NOT NULL,
  granted INTEGER NOT NULL DEFAULT 1, ip_hash TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE rollups_daily (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, day TEXT NOT NULL, macro_slug TEXT NOT NULL DEFAULT '*',
  registered INTEGER NOT NULL DEFAULT 0, verified INTEGER NOT NULL DEFAULT 0, certified INTEGER NOT NULL DEFAULT 0,
  reports_submitted INTEGER NOT NULL DEFAULT 0, reports_approved INTEGER NOT NULL DEFAULT 0,
  UNIQUE(country_id, day, macro_slug)
);
CREATE INDEX ix_rollups_day ON rollups_daily(country_id, day);
CREATE INDEX ix_rollups_macro ON rollups_daily(country_id, macro_slug, day);
CREATE TABLE pricing_audit (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, changed_by TEXT NOT NULL,
  changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, alpha REAL NOT NULL, beta REAL NOT NULL,
  p_prog REAL NOT NULL, p_time REAL NOT NULL, cap REAL NOT NULL, note TEXT
);
CREATE INDEX ix_pricing_audit ON pricing_audit(country_id, changed_at);
