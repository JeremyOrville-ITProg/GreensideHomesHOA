USE greenside_hoa;

-- Run after creating the first approved admin. These demo records use that
-- admin as their author; they do not create fake resident accounts/payments.
SELECT IF(
  EXISTS (SELECT 1 FROM users WHERE role = 'admin' AND status = 'approved'),
  'Approved admin found; demo posts and fees will be added.',
  'No approved admin found; no demo posts or fees can be added yet. Create the first admin, then import this file again.'
) AS seed_status;

INSERT INTO posts
  (user_id, title, body, category, status, is_announcement, is_pinned, view_count)
SELECT admin.id,
       'Water supply interruption this Saturday',
       'Maintenance on the main line from 8 AM to 2 PM. Please store water ahead of time. We appreciate your patience while our team completes this important work.',
       'concern', 'open', TRUE, TRUE, 248
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (
    SELECT 1 FROM posts existing
    WHERE existing.title = 'Water supply interruption this Saturday'
      AND existing.is_announcement = TRUE
  )
ORDER BY admin.id
LIMIT 1;

INSERT INTO posts
  (user_id, title, body, category, status, view_count)
SELECT admin.id,
       'Streetlight not working near the Block 4 entrance',
       'It has been dark for a week and people walk home late. Can the board schedule a repair?',
       'concern', 'open', 112
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (
    SELECT 1 FROM posts existing
    WHERE existing.title = 'Streetlight not working near the Block 4 entrance'
  )
ORDER BY admin.id
LIMIT 1;

INSERT INTO posts
  (user_id, title, body, category, status, view_count)
SELECT admin.id,
       'Proposal: monthly clean-up drive',
       'Suggesting a volunteer clean-up every first Saturday of the month. Who is interested?',
       'suggestion', 'in_progress', 203
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (
    SELECT 1 FROM posts existing
    WHERE existing.title = 'Proposal: monthly clean-up drive'
  )
ORDER BY admin.id
LIMIT 1;

INSERT INTO posts
  (user_id, title, body, category, status, view_count)
SELECT admin.id,
       'Water pressure issue on our street',
       'Thanks to the maintenance team for looking into it so quickly. Pressure is back to normal.',
       'concern', 'resolved', 86
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (
    SELECT 1 FROM posts existing
    WHERE existing.title = 'Water pressure issue on our street'
  )
ORDER BY admin.id
LIMIT 1;

INSERT INTO fees (title, description, amount, due_date, type, created_by)
SELECT 'Monthly dues - November', 'Community maintenance', 1500.00, '2026-11-15', 'monthly', admin.id
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM fees WHERE title = 'Monthly dues - November')
ORDER BY admin.id
LIMIT 1;

INSERT INTO fees (title, description, amount, due_date, type, created_by)
SELECT 'Garbage collection fee', 'November collection', 350.00, '2026-11-20', 'special', admin.id
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM fees WHERE title = 'Garbage collection fee')
ORDER BY admin.id
LIMIT 1;

INSERT INTO fees (title, description, amount, due_date, type, created_by)
SELECT 'Water maintenance - October', 'Water system maintenance', 600.00, '2026-10-25', 'special', admin.id
FROM users admin
WHERE admin.role = 'admin' AND admin.status = 'approved'
  AND NOT EXISTS (SELECT 1 FROM fees WHERE title = 'Water maintenance - October')
ORDER BY admin.id
LIMIT 1;
