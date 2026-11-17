-- Accounts (login, verification, reset, remember-me, social), mail outbox, D17 payments, notifications.
ALTER TABLE users ADD COLUMN email_verified_at TEXT;
ALTER TABLE users ADD COLUMN phone TEXT;
ALTER TABLE users ADD COLUMN lang TEXT NOT NULL DEFAULT 'en';
ALTER TABLE users ADD COLUMN last_login_at TEXT;
ALTER TABLE users ADD COLUMN avatar_url TEXT;
ALTER TABLE users ADD COLUMN password_changed_at TEXT;

CREATE TABLE user_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL,
  selector TEXT NOT NULL UNIQUE, token_hash TEXT NOT NULL, expires_at TEXT NOT NULL, used_at TEXT,
  user_agent TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_user_tokens_user ON user_tokens(user_id, type);

CREATE TABLE social_accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, provider TEXT NOT NULL, provider_uid TEXT NOT NULL,
  email TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(provider, provider_uid)
);
CREATE INDEX ix_social_user ON social_accounts(user_id);

CREATE TABLE mail_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT, to_email TEXT NOT NULL, subject TEXT NOT NULL, template TEXT NOT NULL, lang TEXT NOT NULL DEFAULT 'en',
  driver TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'queued', error TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, sent_at TEXT
);
CREATE INDEX ix_mail_status ON mail_outbox(status, created_at);

CREATE TABLE payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn', ref TEXT NOT NULL UNIQUE, order_ref TEXT NOT NULL,
  investor_id INTEGER, method TEXT NOT NULL DEFAULT 'd17', amount REAL NOT NULL, currency TEXT NOT NULL DEFAULT 'TND',
  sender_gsm TEXT, txn_ref TEXT, status TEXT NOT NULL DEFAULT 'pending', note TEXT, verified_by TEXT, verified_at TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_payments_status ON payments(status, created_at);
CREATE INDEX ix_payments_order ON payments(order_ref);

CREATE TABLE notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL, body TEXT, href TEXT, read_at TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_notif_user ON notifications(user_id, read_at);
