INSERT INTO strata_roll (owner_name, email, unit_entitlements) VALUES
('Michael Myer', 'micmyer10@gmail.com', 10),
('Lexi Li', 'lexili2006@gmail.com', 15);
INSERT INTO levies (owner_id, amount, due_date) VALUES
(1, 500.00, '2025-05-01'),
(2, 750.00, '2025-05-01');
INSERT INTO maintenance_requests (owner_id, description, status) VALUES
(1, 'Fix plumbing in unit 101', 'open'),
(2, 'Repair elevator', 'open');