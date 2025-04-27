CREATE TABLE strata_roll (
    id SERIAL PRIMARY KEY,
    owner_name TEXT NOT NULL,
    email TEXT NOT NULL,
    unit_entitlements INTEGER NOT NULL
);
CREATE TABLE levies (
    id SERIAL PRIMARY KEY,
    owner_id INTEGER REFERENCES strata_roll(id),
    amount NUMERIC(10, 2),
    due_date DATE,
    status TEXT DEFAULT 'pending'
);
CREATE TABLE maintenance_requests (
    id SERIAL PRIMARY KEY,
    owner_id INTEGER REFERENCES strata_roll(id),
    description TEXT NOT NULL,
    status TEXT DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);