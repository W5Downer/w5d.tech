-- Transaction fee snapshot per payment: what the method charged (as configured when the order was placed),
-- who bears it, and the amount the buyer is asked to pay. "amount" stays the published price of the order.
ALTER TABLE payments ADD COLUMN fee REAL NOT NULL DEFAULT 0;
ALTER TABLE payments ADD COLUMN fee_payer TEXT NOT NULL DEFAULT 'seller';
ALTER TABLE payments ADD COLUMN charge REAL;
