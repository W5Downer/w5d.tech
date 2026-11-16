-- 002: investor checkout / daily subscriptions, manager hiring, contact inbox.
CREATE TABLE investors (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, email TEXT NOT NULL, full_name TEXT NOT NULL,
  phone TEXT, organisation TEXT, kind TEXT NOT NULL DEFAULT 'individual', lang TEXT NOT NULL DEFAULT 'en',
  accepted_limits INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(country_id, email)
);
CREATE TABLE investor_orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, ref TEXT NOT NULL UNIQUE, investor_id INTEGER NOT NULL,
  cadence TEXT NOT NULL, parts_per_day INTEGER NOT NULL, days INTEGER NOT NULL, macro_slug TEXT, start_on TEXT NOT NULL,
  unit_price REAL NOT NULL, total REAL NOT NULL, p REAL NOT NULL, t REAL NOT NULL,
  payment_mode TEXT NOT NULL DEFAULT 'demo', status TEXT NOT NULL DEFAULT 'pledged', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_orders_investor ON investor_orders(country_id, investor_id);
CREATE TABLE investor_subscriptions (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, token TEXT NOT NULL UNIQUE, investor_id INTEGER NOT NULL,
  order_ref TEXT, parts_per_day INTEGER NOT NULL, macro_slug TEXT, status TEXT NOT NULL DEFAULT 'active',
  next_charge_on TEXT, paused_until TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_subs_status ON investor_subscriptions(country_id, status);
CREATE TABLE manager_applications (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, ref TEXT NOT NULL UNIQUE, full_name TEXT NOT NULL,
  email TEXT NOT NULL, phone TEXT, macro_slug TEXT NOT NULL, service_slug TEXT, years INTEGER NOT NULL DEFAULT 0,
  team_size_led INTEGER NOT NULL DEFAULT 0, region_slug TEXT, profile_url TEXT, motivation TEXT, scenario_answer TEXT,
  score INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'received', lang TEXT NOT NULL DEFAULT 'en',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_mgr_status ON manager_applications(country_id, status);
CREATE TABLE contact_messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, desk TEXT NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL,
  subject TEXT, body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'new', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE newsletter_subscribers (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, email TEXT NOT NULL, lang TEXT NOT NULL DEFAULT 'en',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(country_id, email)
);
CREATE TABLE eligibility_checks (
  id INTEGER PRIMARY KEY AUTOINCREMENT, country_id TEXT NOT NULL, diploma TEXT, field TEXT, age_band TEXT,
  region_slug TEXT, suggested_macro TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
