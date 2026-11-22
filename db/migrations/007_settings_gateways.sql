-- Owner-managed settings (payment gateways, third-party integrations). Secrets are stored encrypted (see src/Settings.php).
CREATE TABLE app_settings (
  skey TEXT PRIMARY KEY,
  value TEXT NOT NULL DEFAULT '',
  is_secret INTEGER NOT NULL DEFAULT 0,
  updated_by TEXT NOT NULL DEFAULT '',
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- Append-only change log. Values of secrets are NEVER written here, only the fact that they changed.
CREATE TABLE settings_audit (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  skey TEXT NOT NULL,
  action TEXT NOT NULL,                       -- set | clear | secret_set | secret_clear
  old_value TEXT, new_value TEXT,
  changed_by TEXT NOT NULL, note TEXT,
  changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_settings_audit ON settings_audit(skey, changed_at);

-- Online gateways: the provider's own payment id, the hosted-page URL and the last raw status we saw.
ALTER TABLE payments ADD COLUMN provider_ref TEXT;
ALTER TABLE payments ADD COLUMN gateway_url TEXT;
ALTER TABLE payments ADD COLUMN gateway_status TEXT;
ALTER TABLE payments ADD COLUMN started_at TEXT;
ALTER TABLE payments ADD COLUMN checked_at TEXT;
CREATE INDEX ix_payments_provider ON payments(method, provider_ref);

-- Webhook deliveries: idempotency + forensic trail (no secrets, no card data).
CREATE TABLE payment_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  provider TEXT NOT NULL, event_id TEXT NOT NULL, payment_ref TEXT, outcome TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(provider, event_id)
);
