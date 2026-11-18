-- Owner-editable variables: overrides on top of config/variables.php, with an append-only change log.
CREATE TABLE variable_overrides (
  path TEXT PRIMARY KEY,
  value TEXT NOT NULL,
  changed_by TEXT NOT NULL,
  changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note TEXT
);
CREATE TABLE variable_audit (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  path TEXT NOT NULL,
  old_value TEXT,
  new_value TEXT,
  changed_by TEXT NOT NULL,
  changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note TEXT
);
CREATE INDEX ix_variable_audit ON variable_audit(path, changed_at);
