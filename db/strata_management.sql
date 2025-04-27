-- Drop existing tables if they exist to ensure a clean setup
DROP TABLE IF EXISTS work_order_history;
DROP TABLE IF EXISTS budget;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS building_info;
DROP TABLE IF EXISTS maintenance_requests;
DROP TABLE IF EXISTS levies;
DROP TABLE IF EXISTS strata_roll;

-- Create strata_roll table to store owner details
CREATE TABLE strata_roll (
    id SERIAL PRIMARY KEY,
    owner_name TEXT NOT NULL,
    email TEXT NOT NULL,
    unit_entitlements INTEGER NOT NULL
);

-- Create levies table to track quarterly levies
CREATE TABLE levies (
    id SERIAL PRIMARY KEY,
    owner_id INTEGER REFERENCES strata_roll(id),
    quarter TEXT NOT NULL,
    admin NUMERIC(10, 2),
    capital NUMERIC(10, 2),
    due_date DATE,
    status TEXT DEFAULT 'pending'
);

-- Create maintenance_requests table with full-text search
CREATE TABLE maintenance_requests (
    id SERIAL PRIMARY KEY,
    owner_id INTEGER REFERENCES strata_roll(id),
    description TEXT NOT NULL,
    status TEXT DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    search_vector TSVECTOR GENERATED ALWAYS AS (to_tsvector('english', description)) STORED
);

-- Create an index for efficient full-text search
CREATE INDEX maintenance_search_idx ON maintenance_requests USING GIN(search_vector);

-- Enable plpgsql language for triggers
CREATE EXTENSION IF NOT EXISTS plpgsql;

-- Trigger function to mark old maintenance requests as overdue
CREATE OR REPLACE FUNCTION update_maintenance_status()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.created_at < NOW() - INTERVAL '7 days' AND NEW.status = 'open' THEN
        NEW.status = 'overdue';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Attach the trigger to maintenance_requests
CREATE TRIGGER maintenance_status_trigger
BEFORE INSERT OR UPDATE ON maintenance_requests
FOR EACH ROW EXECUTE FUNCTION update_maintenance_status();

-- Create documents table for storing files
CREATE TABLE documents (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INTEGER REFERENCES strata_roll(id)
);

-- Create building_info table for general building details
CREATE TABLE building_info (
    id SERIAL PRIMARY KEY,
    address TEXT NOT NULL,
    description TEXT,
    amenities TEXT,
    committee_details TEXT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create budget table for quarterly budget targets
CREATE TABLE budget (
    id SERIAL PRIMARY KEY,
    quarter TEXT NOT NULL,
    admin_target NUMERIC(10, 2),
    capital_target NUMERIC(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create work_order_history table to log maintenance status changes
CREATE TABLE work_order_history (
    id SERIAL PRIMARY KEY,
    request_id INTEGER REFERENCES maintenance_requests(id),
    status TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER REFERENCES strata_roll(id),
    notes TEXT
);

-- Insert data into strata_roll (50 owners with Australian names)
INSERT INTO strata_roll (owner_name, email, unit_entitlements) VALUES
('James Wilson', 'james.wilson@example.com', 10),
('Emma Thompson', 'emma.thompson@example.com', 15),
('Liam Harris', 'liam.harris@example.com', 12),
('Olivia Brown', 'olivia.brown@example.com', 8),
('Noah Davis', 'noah.davis@example.com', 20),
('Sophia Clark', 'sophia.clark@example.com', 10),
('Ethan Lewis', 'ethan.lewis@example.com', 14),
('Isabella Walker', 'isabella.walker@example.com', 16),
('Mason Young', 'mason.young@example.com', 9),
('Ava King', 'ava.king@example.com', 11),
('Jack Mitchell', 'jack.mitchell@example.com', 13),
('Charlotte Roberts', 'charlotte.roberts@example.com', 17),
('William Turner', 'william.turner@example.com', 10),
('Amelia Parker', 'amelia.parker@example.com', 12),
('Henry Phillips', 'henry.phillips@example.com', 15),
('Mia Scott', 'mia.scott@example.com', 9),
('Alexander Green', 'alexander.green@example.com', 11),
('Harper Adams', 'harper.adams@example.com', 14),
('Benjamin Hall', 'benjamin.hall@example.com', 8),
('Evelyn Campbell', 'evelyn.campbell@example.com', 16),
('Lucas Wright', 'lucas.wright@example.com', 12),
('Grace Baker', 'grace.baker@example.com', 18),
('Thomas Evans', 'thomas.evans@example.com', 10),
('Chloe Edwards', 'chloe.edwards@example.com', 13),
('Daniel Cooper', 'daniel.cooper@example.com', 15),
('Ruby Hill', 'ruby.hill@example.com', 9),
('Samuel Kelly', 'samuel.kelly@example.com', 11),
('Lily Stewart', 'lily.stewart@example.com', 14),
('Matthew Watson', 'matthew.watson@example.com', 8),
('Ella Hughes', 'ella.hughes@example.com', 16),
('Joshua Morgan', 'joshua.morgan@example.com', 12),
('Scarlett Bennett', 'scarlett.bennett@example.com', 17),
('David Gray', 'david.gray@example.com', 10),
('Zoe Foster', 'zoe.foster@example.com', 13),
('Ryan Murphy', 'ryan.murphy@example.com', 15),
('Hannah Price', 'hannah.price@example.com', 9),
('Nathan Reed', 'nathan.reed@example.com', 11),
('Abigail Cole', 'abigail.cole@example.com', 14),
('Jacob Fisher', 'jacob.fisher@example.com', 8),
('Sienna Hart', 'sienna.hart@example.com', 16),
('Lachlan Webb', 'lachlan.webb@example.com', 12),
('Imogen Perry', 'imogen.perry@example.com', 18),
('Finn Russell', 'finn.russell@example.com', 10),
('Holly Dunn', 'holly.dunn@example.com', 13),
('Angus Reid', 'angus.reid@example.com', 15),
('Matilda Fox', 'matilda.fox@example.com', 9),
('Declan Walsh', 'declan.walsh@example.com', 11),
('Jasmine Gordon', 'jasmine.gordon@example.com', 14),
('Toby Simpson', 'toby.simpson@example.com', 8),
('Freya Lane', 'freya.lane@example.com', 16);

-- Insert data into levies (800 records: 50 owners, 4 quarters for 2022-2025)
DO $$
BEGIN
    FOR owner_id IN 1..50 LOOP
        FOR year IN 2022..2025 LOOP
            FOR quarter IN 1..4 LOOP
                INSERT INTO levies (owner_id, quarter, admin, capital, due_date, status) VALUES
                (owner_id, year || '-Q' || quarter, 
                 ROUND((RANDOM() * 1500 + 500)::NUMERIC, 2), -- Admin levy between 500 and 2000
                 ROUND((RANDOM() * 1500 + 500)::NUMERIC, 2), -- Capital levy between 500 and 2000
                 (year || '-' || (quarter * 3) || '-01')::DATE,
                 CASE 
                     WHEN RANDOM() < 0.7 THEN 'paid' 
                     WHEN RANDOM() < 0.9 THEN 'pending' 
                     ELSE 'overdue' 
                 END);
            END LOOP;
        END LOOP;
    END LOOP;
END $$;

-- Insert data into maintenance_requests (200 requests)
DO $$
BEGIN
    FOR i IN 1..200 LOOP
        INSERT INTO maintenance_requests (owner_id, description, status, created_at) VALUES
        ((RANDOM() * 49 + 1)::INTEGER, 
         CASE (RANDOM() * 7)::INTEGER
             WHEN 0 THEN 'Leaking pipe in unit ' || i
             WHEN 1 THEN 'Broken window in common area'
             WHEN 2 THEN 'Elevator maintenance required'
             WHEN 3 THEN 'Air conditioning unit failure'
             WHEN 4 THEN 'Parking gate not working'
             WHEN 5 THEN 'Electrical fault in hallway'
             ELSE 'Pool pump malfunction'
         END,
         CASE 
             WHEN RANDOM() < 0.5 THEN 'open' 
             WHEN RANDOM() < 0.8 THEN 'in_progress' 
             WHEN RANDOM() < 0.95 THEN 'closed' 
             ELSE 'overdue' 
         END,
         NOW() - INTERVAL '1 day' * (RANDOM() * 1095)); -- Random date within 3 years (2022-2025)
    END LOOP;
END $$;

-- Insert data into documents (30 documents)
INSERT INTO documents (name, file_path, upload_date, uploaded_by) VALUES
('Insurance Certificate 2022', '/public/docs/insurance_2022.pdf', '2022-01-10 10:00:00', 1),
('Financial Report Q1 2022', '/public/docs/financial_q1_2022.pdf', '2022-04-15 12:00:00', 1),
('Strata Committee Minutes Feb 2022', '/public/docs/minutes_feb_2022.pdf', '2022-02-20 09:00:00', 2),
('Insurance Certificate 2023', '/public/docs/insurance_2023.pdf', '2023-01-12 11:00:00', 1),
('Financial Report Q2 2023', '/public/docs/financial_q2_2023.pdf', '2023-07-10 14:00:00', 3),
('Building Maintenance Plan 2023', '/public/docs/maintenance_plan_2023.pdf', '2023-03-05 13:00:00', 4),
('Fire Safety Certificate 2023', '/public/docs/fire_safety_2023.pdf', '2023-04-01 10:00:00', 5),
('Annual General Meeting Agenda 2023', '/public/docs/agm_agenda_2023.pdf', '2023-05-15 16:00:00', 6),
('Insurance Certificate 2024', '/public/docs/insurance_2024.pdf', '2024-01-15 10:00:00', 1),
('Financial Report Q4 2024', '/public/docs/financial_q4_2024.pdf', '2024-12-01 12:00:00', 1),
('Strata Committee Minutes Jan 2024', '/public/docs/minutes_jan_2024.pdf', '2024-01-20 09:00:00', 2),
('Building Maintenance Plan 2024', '/public/docs/maintenance_plan_2024.pdf', '2024-02-10 14:00:00', 3),
('Fire Safety Certificate 2024', '/public/docs/fire_safety_2024.pdf', '2024-03-05 11:00:00', 4),
('Annual General Meeting Agenda 2024', '/public/docs/agm_agenda_2024.pdf', '2024-04-15 16:00:00', 5),
('Budget Forecast 2024-2025', '/public/docs/budget_forecast_2024_2025.pdf', '2024-05-01 13:00:00', 6),
('Insurance Certificate 2025', '/public/docs/insurance_2025.pdf', '2025-01-10 10:00:00', 1),
('Financial Report Q1 2025', '/public/docs/financial_q1_2025.pdf', '2025-04-25 18:00:00', 1),
('Strata Committee Minutes Jan 2025', '/public/docs/minutes_jan_2025.pdf', '2025-01-20 09:00:00', 2),
('Building Maintenance Plan 2025', '/public/docs/maintenance_plan_2025.pdf', '2025-02-10 14:00:00', 3),
('Fire Safety Certificate 2025', '/public/docs/fire_safety_2025.pdf', '2025-03-05 11:00:00', 4),
('Annual General Meeting Agenda 2025', '/public/docs/agm_agenda_2025.pdf', '2025-03-15 16:00:00', 5),
('Budget Forecast 2025-2026', '/public/docs/budget_forecast_2025_2026.pdf', '2025-04-01 13:00:00', 6),
('Strata Rules and By-Laws 2022', '/public/docs/strata_rules_2022.pdf', '2022-06-10 10:00:00', 7),
('Strata Rules and By-Laws 2023', '/public/docs/strata_rules_2023.pdf', '2023-06-12 10:00:00', 7),
('Strata Rules and By-Laws 2024', '/public/docs/strata_rules_2024.pdf', '2024-06-15 10:00:00', 7),
('Strata Rules and By-Laws 2025', '/public/docs/strata_rules_2025.pdf', '2025-04-10 10:00:00', 7),
('Emergency Evacuation Plan 2023', '/public/docs/evacuation_plan_2023.pdf', '2023-08-01 15:00:00', 8),
('Sustainability Report 2024', '/public/docs/sustainability_2024.pdf', '2024-09-10 12:00:00', 9),
('Lift Upgrade Proposal 2025', '/public/docs/lift_upgrade_2025.pdf', '2025-02-15 11:00:00', 10),
('Security System Review 2025', '/public/docs/security_review_2025.pdf', '2025-03-20 14:00:00', 11);

-- Insert data into building_info (3 buildings)
INSERT INTO building_info (address, description, amenities, committee_details) VALUES
('123 Strata St, Sydney NSW 2000', 'A modern 20-unit apartment building in the heart of Sydney with scenic views.', 
 'Pool, gym, parking, BBQ area', 
 'Treasurer: James Wilson, Secretary: Emma Thompson, Chairperson: Liam Harris'),
('456 Ocean Rd, Bondi NSW 2026', 'A beachside 15-unit complex with modern facilities.', 
 'Rooftop terrace, parking, bike storage', 
 'Treasurer: Noah Davis, Secretary: Sophia Clark, Chairperson: Ethan Lewis'),
('789 Harbour Ave, Pyrmont NSW 2009', 'A luxury 25-unit tower with waterfront views.', 
 'Spa, concierge, gym, parking', 
 'Treasurer: Isabella Walker, Secretary: Mason Young, Chairperson: Ava King');

-- Insert data into budget (16 quarters: 2022-2025)
INSERT INTO budget (quarter, admin_target, capital_target) VALUES
('2022-Q1', 55000.00, 35000.00),
('2022-Q2', 57000.00, 37000.00),
('2022-Q3', 59000.00, 39000.00),
('2022-Q4', 61000.00, 41000.00),
('2023-Q1', 63000.00, 43000.00),
('2023-Q2', 65000.00, 45000.00),
('2023-Q3', 67000.00, 47000.00),
('2023-Q4', 69000.00, 49000.00),
('2024-Q1', 71000.00, 51000.00),
('2024-Q2', 73000.00, 53000.00),
('2024-Q3', 75000.00, 55000.00),
('2024-Q4', 77000.00, 57000.00),
('2025-Q1', 79000.00, 59000.00),
('2025-Q2', 81000.00, 61000.00),
('2025-Q3', 83000.00, 63000.00),
('2025-Q4', 85000.00, 65000.00);

-- Insert data into work_order_history (500 updates)
DO $$
BEGIN
    FOR i IN 1..500 LOOP
        INSERT INTO work_order_history (request_id, status, updated_at, updated_by, notes) VALUES
        ((RANDOM() * 199 + 1)::INTEGER,
         CASE (RANDOM() * 4)::INTEGER
             WHEN 0 THEN 'open'
             WHEN 1 THEN 'in_progress'
             WHEN 2 THEN 'closed'
             WHEN 3 THEN 'overdue'
             ELSE 'on_hold'
         END,
         NOW() - INTERVAL '1 hour' * (RANDOM() * 26280), -- Random time within 3 years
         (RANDOM() * 49 + 1)::INTEGER,
         CASE (RANDOM() * 5)::INTEGER
             WHEN 0 THEN 'Request created'
             WHEN 1 THEN 'Assigned to contractor'
             WHEN 2 THEN 'Work completed'
             WHEN 3 THEN 'Awaiting parts'
             ELSE 'Scheduled for inspection'
         END);
    END LOOP;
END $$;

-- Reporting Queries for Beautiful Reports

-- 1. Levy Payment Status Report (Total levies owed and paid per owner)
SELECT 
    sr.owner_name,
    COUNT(l.id) AS total_levies,
    SUM(l.admin + l.capital) AS total_owed,
    SUM(CASE WHEN l.status = 'paid' THEN l.admin + l.capital ELSE 0 END) AS total_paid,
    SUM(CASE WHEN l.status = 'pending' THEN l.admin + l.capital ELSE 0 END) AS total_pending,
    SUM(CASE WHEN l.status = 'overdue' THEN l.admin + l.capital ELSE 0 END) AS total_overdue
FROM strata_roll sr
LEFT JOIN levies l ON sr.id = l.owner_id
GROUP BY sr.owner_name
ORDER BY total_pending DESC;

-- 2. Maintenance Request Summary (Status distribution and overdue requests)
SELECT 
    status,
    COUNT(*) AS request_count,
    COUNT(*) FILTER (WHERE status = 'overdue') AS overdue_count,
    STRING_AGG(description, '; ') AS sample_descriptions
FROM maintenance_requests
GROUP BY status
ORDER BY request_count DESC;

-- 3. Budget vs. Actual Levy Collection (Compares budget targets to collected levies)
SELECT 
    b.quarter,
    b.admin_target,
    b.capital_target,
    COALESCE(SUM(l.admin) FILTER (WHERE l.status = 'paid'), 0) AS admin_collected,
    COALESCE(SUM(l.capital) FILTER (WHERE l.status = 'paid'), 0) AS capital_collected
FROM budget b
LEFT JOIN levies l ON b.quarter = l.quarter
GROUP BY b.quarter, b.admin_target, b.capital_target
ORDER BY b.quarter;

-- 4. Full-Text Search for Maintenance Requests (Search for specific issues, e.g., 'leak' or 'pipe')
SELECT 
    id,
    description,
    status,
    created_at,
    ts_rank(search_vector, to_tsquery('leak | pipe')) AS relevance
FROM maintenance_requests
WHERE search_vector @@ to_tsquery('leak | pipe')
ORDER BY relevance DESC
LIMIT 10;

-- 5. Document Upload History (Recent document uploads)
SELECT 
    d.name,
    d.upload_date,
    sr.owner_name AS uploaded_by
FROM documents d
JOIN strata_roll sr ON d.uploaded_by = sr.id
ORDER BY d.upload_date DESC
LIMIT 10;