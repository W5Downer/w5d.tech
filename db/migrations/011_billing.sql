-- Billing core: every payable thing is an invoice; every payment (any method) points at an invoice or an order.
-- Four revenue streams hang off it: service fees (milestones), subscription plans, certification fees, placement commissions.
-- No price is seeded: plans and fees start at 0 and switched off; the owner sets the numbers in the portal.

ALTER TABLE invoices ADD COLUMN number TEXT;            -- INV-YYYY-NNNNN, also the "order_ref" of its payments
ALTER TABLE invoices ADD COLUMN user_id INTEGER;
ALTER TABLE invoices ADD COLUMN email TEXT;
ALTER TABLE invoices ADD COLUMN name TEXT;
ALTER TABLE invoices ADD COLUMN lang TEXT;
ALTER TABLE invoices ADD COLUMN kind TEXT NOT NULL DEFAULT 'manual';   -- order | qcm_pass | plan | cert | service | placement | manual
ALTER TABLE invoices ADD COLUMN source_ref TEXT;        -- ORD-… | QP-… | PLN-… | CF-… | MS-… | PLC-…
ALTER TABLE invoices ADD COLUMN currency TEXT NOT NULL DEFAULT 'TND';
ALTER TABLE invoices ADD COLUMN due_on TEXT;
ALTER TABLE invoices ADD COLUMN issued_at TEXT;
ALTER TABLE invoices ADD COLUMN paid_at TEXT;
ALTER TABLE invoices ADD COLUMN payment_ref TEXT;
ALTER TABLE invoices ADD COLUMN method TEXT;
ALTER TABLE invoices ADD COLUMN note TEXT;
CREATE UNIQUE INDEX ux_invoices_number ON invoices(number) WHERE number IS NOT NULL;
CREATE INDEX ix_invoices_user ON invoices(user_id);
CREATE INDEX ix_invoices_email ON invoices(email);
CREATE INDEX ix_invoices_source ON invoices(kind, source_ref);

CREATE TABLE invoice_lines (
  id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL, position INTEGER NOT NULL DEFAULT 0,
  description TEXT NOT NULL,                           -- plain text or a JSON {"en","fr","ar"}
  qty REAL NOT NULL DEFAULT 1, unit_price REAL NOT NULL DEFAULT 0, amount REAL NOT NULL DEFAULT 0
);
CREATE INDEX ix_invoice_lines ON invoice_lines(invoice_id, position);

ALTER TABLE payments ADD COLUMN invoice_id INTEGER;
CREATE INDEX ix_payments_invoice ON payments(invoice_id);

-- 2. Subscriptions ---------------------------------------------------------------------------------------------
CREATE TABLE plans (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn', code TEXT NOT NULL UNIQUE,
  name_en TEXT NOT NULL, name_fr TEXT NOT NULL, name_ar TEXT NOT NULL,
  price REAL NOT NULL DEFAULT 0,                       -- per month; 0 = not for sale online
  price_year REAL NOT NULL DEFAULT 0,                  -- per year; 0 = yearly billing not offered
  seats_included INTEGER NOT NULL DEFAULT 1, seat_price REAL NOT NULL DEFAULT 0,   -- price of each extra seat per month
  pipeline_access INTEGER NOT NULL DEFAULT 0, weekly_reports INTEGER NOT NULL DEFAULT 0,
  quote_only INTEGER NOT NULL DEFAULT 0,               -- 1 = "talk to the partners desk" instead of a buy button
  active INTEGER NOT NULL DEFAULT 0, sort INTEGER NOT NULL DEFAULT 0, note TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO plans (code, name_en, name_fr, name_ar, seats_included, pipeline_access, weekly_reports, quote_only, sort) VALUES
  ('individual', 'Individual', 'Individuel', 'فردي', 1, 1, 0, 0, 1),
  ('team', 'Team', 'Équipe', 'فريق', 5, 1, 1, 0, 2),
  ('enterprise', 'Enterprise', 'Entreprise', 'مؤسسة', 25, 1, 1, 1, 3);

CREATE TABLE plan_subscriptions (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn', ref TEXT NOT NULL UNIQUE,
  user_id INTEGER NOT NULL, plan_id INTEGER NOT NULL, seats INTEGER NOT NULL DEFAULT 1,
  period TEXT NOT NULL DEFAULT 'month',                -- month | year
  amount REAL NOT NULL DEFAULT 0,                      -- per period, snapshot when it was bought
  status TEXT NOT NULL DEFAULT 'pending',              -- pending | active | cancelled | expired
  started_on TEXT, current_period_end TEXT, cancel_at_period_end INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_plan_subs_user ON plan_subscriptions(user_id, status);
CREATE TABLE plan_seats (
  id INTEGER PRIMARY KEY AUTOINCREMENT, subscription_id INTEGER NOT NULL, email TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE (subscription_id, email)
);

-- 3. Certification & assessment fees -----------------------------------------------------------------------------
CREATE TABLE cert_fees (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn',
  name TEXT NOT NULL, kind TEXT NOT NULL DEFAULT 'certification',   -- assessment | certification | recertification
  level INTEGER NOT NULL DEFAULT 0,                    -- 0 = any level
  amount REAL NOT NULL DEFAULT 0, rule_id INTEGER,     -- paying grants one pass for this QCM rule (optional)
  active INTEGER NOT NULL DEFAULT 0, sort INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO cert_fees (name, kind, sort) VALUES ('Assessment fee', 'assessment', 1), ('Certification fee', 'certification', 2), ('Re-certification fee', 'recertification', 3);

-- 1. Service fees: scoped, project-based delivery, paid by milestone, released after review -------------------------
CREATE TABLE service_engagements (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn', ref TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL, scope TEXT, client_name TEXT, client_email TEXT NOT NULL, user_id INTEGER,
  status TEXT NOT NULL DEFAULT 'draft',                -- draft | active | completed | cancelled
  created_by INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE milestones (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn', engagement_id INTEGER NOT NULL,
  title TEXT NOT NULL, description TEXT, amount REAL NOT NULL DEFAULT 0, due_on TEXT,
  status TEXT NOT NULL DEFAULT 'planned',              -- planned | invoiced | funded | delivered | released | disputed | cancelled
  invoice_id INTEGER, delivered_by TEXT, delivered_at TEXT, delivery_note TEXT,
  reviewed_by TEXT, reviewed_at TEXT, review_note TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_milestones_eng ON milestones(engagement_id, status);

-- 4. Placement / marketplace commission ------------------------------------------------------------------------------
CREATE TABLE placements (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL DEFAULT 'tn', ref TEXT NOT NULL UNIQUE,
  kind TEXT NOT NULL DEFAULT 'supervised_work',        -- supervised_work | enterprise_talent
  client_id INTEGER NOT NULL, contract_id INTEGER, title TEXT NOT NULL, talent_name TEXT,
  contract_value REAL NOT NULL DEFAULT 0,
  rate_pct REAL,                                       -- NULL = the owner's default rate (variable billing.placement_rate_pct)
  rate_snapshot REAL, commission REAL,                 -- fixed when the invoice is made
  status TEXT NOT NULL DEFAULT 'proposed',             -- proposed | invoiced | paid | cancelled
  invoice_id INTEGER, created_by INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_placements_client ON placements(client_id, status);
