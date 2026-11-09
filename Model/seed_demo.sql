USE greenside_hoa;

-- Check for approved admin
SELECT IF(
  EXISTS (SELECT 1 FROM users WHERE role = 'admin' AND status = 'approved'),
  'Approved admin found; demo posts, notices, and fees will be added.',
  'No approved admin found; please create and approve an admin account first.'
) AS seed_status;

-- 1. Create Authentic Neighbor Homeowner Accounts
INSERT IGNORE INTO users (full_name, email, password_hash, phone, house_no, role, status) VALUES
('Marites Ramos', 'marites.blk8@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0917-111-2233', 'Blk 8 Lot 14', 'resident', 'approved'),
('Kuya Jun Reyes', 'jun.reyes@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0918-222-3344', 'Phase 2 Blk 4 Lot 9', 'resident', 'approved'),
('Elena Cruz', 'elena.cruz@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0919-333-4455', 'Blk 12 Lot 18', 'resident', 'approved'),
('Bong Santos', 'bong.santos@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0920-444-5566', 'Phase 1 Blk 3 Lot 2', 'resident', 'approved'),
('Arnel Bautista', 'arnel.bautista@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0921-555-6677', 'Blk 15 Lot 5', 'resident', 'approved');

-- 2. Official HOA Announcements (5 Grounded Administrative Notices)
INSERT INTO posts (user_id, title, body, category, status, is_announcement, is_pinned, view_count, created_at)
SELECT admin.id,
       'Scheduled Maynilad Water Service Interruption (Oct 24-25)',
       'NOTICE: Scheduled Maynilad Water Service Interruption (Oct 24, 10:00 PM - Oct 25, 4:00 AM) due to emergency pipe maintenance. Please store enough water.',
       'concern', 'open', TRUE, TRUE, 324, DATE_SUB(NOW(), INTERVAL 3 HOUR)
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE body LIKE '%Scheduled Maynilad Water Service Interruption%')
ORDER BY admin.id LIMIT 1;

INSERT INTO posts (user_id, title, body, category, status, is_announcement, is_pinned, view_count, created_at)
SELECT admin.id,
       'Garbage Collection Update: CENRO / Hakot Basura Schedule',
       'Garbage Collection Update: CENRO / Hakot Basura schedule for Phase 1 & 2 is every Tuesday and Saturday morning. strictly NO segregated trash, NO hakot.',
       'concern', 'open', TRUE, FALSE, 215, DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE body LIKE '%Garbage Collection Update: CENRO%')
ORDER BY admin.id LIMIT 1;

INSERT INTO posts (user_id, title, body, category, status, is_announcement, is_pinned, view_count, created_at)
SELECT admin.id,
       'FINAL NOTICE: Deadline for 2026 Homeowner Gate Stickers',
       'FINAL NOTICE: Deadline for 2026 Homeowner Vehicle & Motorcycle Gate Stickers is on Nov 15. Unregistered vehicles must surrender ID at Main Gate.',
       'concern', 'open', TRUE, TRUE, 480, DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE body LIKE '%FINAL NOTICE: Deadline for 2026 Homeowner Vehicle%')
ORDER BY admin.id LIMIT 1;

INSERT INTO posts (user_id, title, body, category, status, is_announcement, is_pinned, view_count, created_at)
SELECT admin.id,
       'HOA Advisory: Stray Animal & Pet Leash Policy',
       'HOA Advisory: Stray Animal & Pet Leash Policy. All pets outside private property must be on a leash. Unattended dogs will be reported to City Vet.',
       'concern', 'open', TRUE, FALSE, 192, DATE_SUB(NOW(), INTERVAL 3 DAY)
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE body LIKE '%HOA Advisory: Stray Animal & Pet Leash Policy%')
ORDER BY admin.id LIMIT 1;

INSERT INTO posts (user_id, title, body, category, status, is_announcement, is_pinned, view_count, created_at)
SELECT admin.id,
       'Quarterly General Assembly & Financial Report',
       'Quarterly General Assembly & Financial Report: This Sunday, 9:00 AM at the Phase 1 Covered Court. Agenda: Streetlight repairs and clubhouse rental rules.',
       'suggestion', 'open', TRUE, FALSE, 267, DATE_SUB(NOW(), INTERVAL 4 DAY)
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE body LIKE '%Quarterly General Assembly & Financial Report%')
ORDER BY admin.id LIMIT 1;

-- 3. Community Forum Mock Posts (Authentic Pinoy Subdivision GC / FB Group style)
INSERT INTO posts (user_id, title, body, category, status, is_announcement, is_pinned, view_count, created_at)
SELECT u.id,
       'puting aso sa blk 8 nagkakalkal ng basura',
       'pakiusap naman po sa may ari nung puting aso sa blk 8 wag nyo po pakawalan. kanina pa tumatahol sa mga nagdadaan at nagkakalkal ng basura sa tapat namin',
       'concern', 'open', FALSE, FALSE, 142, DATE_SUB(NOW(), INTERVAL 2 HOUR)
FROM users u
WHERE u.email = 'marites.blk8@greenside.local'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE body LIKE '%puting aso sa blk 8 wag nyo po pakawalan%')
LIMIT 1;

INSERT INTO posts (user_id, title, body, category, status, is_announcement, is_pinned, view_count, created_at)
SELECT u.id,
       'ano oras po babalik kuryente sa phase 2?',
       'ano oras po babalik kuryente? nawalan bigla kaninang 2pm phase 2 area. may nagwe-welding ba dyan sa kanto?',
       'question', 'open', FALSE, FALSE, 89, DATE_SUB(NOW(), INTERVAL 4 HOUR)
FROM users u
WHERE u.email = 'jun.reyes@greenside.local'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE body LIKE '%ano oras po babalik kuryente%')
LIMIT 1;

INSERT INTO posts (user_id, title, body, category, status, is_announcement, is_pinned, view_count, created_at)
SELECT u.id,
       'lf honest na nag-aayos ng automatic washing machine',
       'lf honest na nag-aayos ng automatic washing machine (panasonic). yung marunong po sana mag home service dito sa phase 1',
       'question', 'open', FALSE, FALSE, 63, DATE_SUB(NOW(), INTERVAL 6 HOUR)
FROM users u
WHERE u.email = 'elena.cruz@greenside.local'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE body LIKE '%lf honest na nag-aayos ng automatic washing machine%')
LIMIT 1;

INSERT INTO posts (user_id, title, body, category, status, is_announcement, is_pinned, view_count, created_at)
SELECT u.id,
       'pahiram mineral water baka may extra kayo',
       'good pm kapitbahay baka may extra kayong 5 gallon mineral water pahiram muna? sarado na kase water refilling station sa may guard house',
       'suggestion', 'resolved', FALSE, FALSE, 51, DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM users u
WHERE u.email = 'bong.santos@greenside.local'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE body LIKE '%pahiram muna? sarado na kase water refilling station%')
LIMIT 1;

INSERT INTO posts (user_id, title, body, category, status, is_announcement, is_pinned, view_count, created_at)
SELECT u.id,
       'contact number ng tricycle terminal sa labas',
       'sinong may contact number ng tricycle terminal sa labas? wala kasing pumapasok na trike dito sa dulo',
       'question', 'open', FALSE, FALSE, 77, DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM users u
WHERE u.email = 'arnel.bautista@greenside.local'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE body LIKE '%contact number ng tricycle terminal sa labas%')
LIMIT 1;

-- 4. Sample Assessment Fees
INSERT INTO fees (title, description, amount, due_date, type, created_by)
SELECT 'Monthly dues - November', 'Community maintenance, streetlights, and 24/7 security guardhouse', 1500.00, '2026-11-15', 'monthly', admin.id
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM fees WHERE title = 'Monthly dues - November')
ORDER BY admin.id LIMIT 1;

INSERT INTO fees (title, description, amount, due_date, type, created_by)
SELECT 'Garbage collection fee - Q4', 'CENRO subdivision waste management and segregation monitoring', 350.00, '2026-11-20', 'special', admin.id
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM fees WHERE title = 'Garbage collection fee - Q4')
ORDER BY admin.id LIMIT 1;

INSERT INTO fees (title, description, amount, due_date, type, created_by)
SELECT 'Water maintenance - October', 'Phase 1 & 2 booster pump electrical check & emergency valve overhaul', 600.00, '2026-10-25', 'special', admin.id
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM fees WHERE title = 'Water maintenance - October')
ORDER BY admin.id LIMIT 1;
