-- Unsubscribe list for non-essential e-mail (updates / newsletter). Service mail (verification, reset, receipts, payment status) is never suppressed.
CREATE TABLE mail_suppressions (
  email TEXT PRIMARY KEY,
  scope TEXT NOT NULL DEFAULT 'updates',
  source TEXT NOT NULL DEFAULT 'link',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE newsletter_subscribers ADD COLUMN unsubscribed_at TEXT;
