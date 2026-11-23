-- One-time e-mail codes for owner / admin / leader / manager / member sign-in (@w5d.tech only).
-- Only a keyed hash of the code is stored; a code is single-use, expires, and dies after a fixed number of wrong guesses.
CREATE TABLE staff_codes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL,
  code_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  used_at TEXT,
  ip TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_staff_codes_email ON staff_codes(email, id);
