-- Pre-registry funding stack and AdSense fail-closed ledger.
-- Amounts here are DATA (what was actually received); the policy numbers live in .env tokens.

-- Dated facts the owner records (e.g. auto-entrepreneur registration). Append-only: the latest row per key wins, history stays.
CREATE TABLE funding_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  rkey TEXT NOT NULL,                       -- auto_entrepreneur_registration | consent_mechanism | ...
  rvalue TEXT NOT NULL DEFAULT '',          -- the reference as issued by the authority (never invented)
  recorded_on TEXT NOT NULL,                -- YYYY-MM-DD, the date of the fact
  recorded_by TEXT NOT NULL DEFAULT '',
  note TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_funding_records ON funding_records(rkey, id);
CREATE TRIGGER funding_records_no_update BEFORE UPDATE ON funding_records BEGIN SELECT RAISE(ABORT, 'funding_records is append-only'); END;
CREATE TRIGGER funding_records_no_delete BEFORE DELETE ON funding_records BEGIN SELECT RAISE(ABORT, 'funding_records is append-only'); END;
INSERT INTO funding_records(rkey, rvalue, recorded_on, recorded_by, note)
  VALUES ('consent_mechanism', 'cookie-notice', date('now'), 'system', 'Always-on cookie and consent notice (views/templates/w5d/partials/cookies.blade.php, htdocs/assets/_base/js/cookies.js).');

-- F2: restricted sponsorship. Money is restricted to the registry activation fund and refundable if activation fails. It is not a loan and returns nothing.
CREATE TABLE sponsorships (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ref TEXT NOT NULL UNIQUE,
  sponsor_name TEXT NOT NULL,
  email TEXT NOT NULL,
  organisation TEXT NOT NULL DEFAULT '',
  amount_tnd REAL NOT NULL,
  status TEXT NOT NULL DEFAULT 'pledged',   -- pledged | received | refunded | cancelled
  agreement_ref TEXT NOT NULL DEFAULT '',   -- reference of the signed agreement
  received_on TEXT, refunded_on TEXT,
  note TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_sponsorships_status ON sponsorships(status);

-- F4: pre-sold service credits. Not a completed sale until the condition precedent is met; money sits in escrow until then.
CREATE TABLE presales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ref TEXT NOT NULL UNIQUE,
  client_name TEXT NOT NULL,
  email TEXT NOT NULL,
  amount_tnd REAL NOT NULL,
  credits INTEGER NOT NULL DEFAULT 0,       -- service credits, non-transferable
  status TEXT NOT NULL DEFAULT 'draft',     -- draft | escrowed | completed | refunded
  contract_ref TEXT NOT NULL DEFAULT '',
  escrow_ref TEXT NOT NULL DEFAULT '',
  escrowed_on TEXT, completed_on TEXT, refunded_on TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_presales_status ON presales(status);

-- AdSense rejections: append-only. The only permitted change to a row is setting reapplied_at once.
CREATE TABLE ads_rejection_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  date TEXT NOT NULL,
  rejection_type TEXT NOT NULL,
  corrective_action TEXT NOT NULL,
  reapplied_at TEXT,
  recorded_by TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TRIGGER ads_rejection_log_no_delete BEFORE DELETE ON ads_rejection_log BEGIN SELECT RAISE(ABORT, 'ads_rejection_log is append-only'); END;
CREATE TRIGGER ads_rejection_log_update_guard BEFORE UPDATE ON ads_rejection_log
WHEN OLD.id IS NOT NEW.id OR OLD.date IS NOT NEW.date OR OLD.rejection_type IS NOT NEW.rejection_type
  OR OLD.corrective_action IS NOT NEW.corrective_action OR OLD.recorded_by IS NOT NEW.recorded_by
  OR OLD.created_at IS NOT NEW.created_at OR OLD.reapplied_at IS NOT NULL OR NEW.reapplied_at IS NULL
BEGIN SELECT RAISE(ABORT, 'ads_rejection_log is append-only (only reapplied_at may be set, once)'); END;
