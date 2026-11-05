# Greenside Homes HOA Portal

The portal is a PHP MVC application backed by MySQL. `Views/index.html` now
redirects to the PHP app when served by Apache; opening it directly as a file
only shows a note because browsers do not execute PHP from `file://`.

## Run locally without Apache

1. Start **MySQL** in the XAMPP Control Panel. Apache is not needed.
2. Import `Model/schema.sql` into MySQL if you have not already imported it.
   The script creates the `greenside_hoa` database and its tables.
3. Double-click `start_portal.bat`. It starts PHP's built-in development
   server and opens the portal at `http://127.0.0.1:8080/`. Keep its console
   window open for as long as you want the portal available; press Ctrl+C in
   that window to stop the server. If PHP fails to start, the console remains
   open and displays the error instead of closing immediately.
4. If this is a fresh database, create the first administrator in a separate
   terminal from the project folder:

   ```powershell
   C:\xampp\php\php.exe tools\create_admin.php
   ```

   The administrator must be created before residents can be approved. The
   command-line tool only creates an administrator when none exists.

To display example community posts and bills from MySQL, import
`Model/seed_demo.sql` into the `greenside_hoa` database **after** creating the
administrator. The script is safe to run again and uses the administrator as
the sample author/fee creator. It does not create fake residents or payments.
Real payment history appears when a resident submits a receipt and an HOA
reviewer verifies it.

The local XAMPP defaults are `127.0.0.1:3306`, database `greenside_hoa`, user
`root`, and a blank password. Configure `HOA_DB_HOST`, `HOA_DB_PORT`,
`HOA_DB_NAME`, `HOA_DB_USER`, and `HOA_DB_PASSWORD` in the PHP
environment to override them. Use a dedicated MySQL account with a password
outside local development.

The built-in PHP server is for local development only, not public/production
hosting. `router.php` exposes the app and front-end assets while blocking
direct web access to application source and private upload directories.

## Features

- Resident registration, administrator approval, and password-hash sign-in.
- Community posts, comments, one reaction per user/post, and administrator
  announcements with resident notifications.
- Fee creation, private receipt submission, payment verification, expense
  recording, payment history, and notifications.
- Role checks, CSRF validation, PDO prepared statements, file type and size
  validation, and authenticated receipt access.

Resident registrations remain pending until an administrator approves them.
Treasurers can manage fees, expenses, and payment reviews; only administrators
can approve residents and publish announcements.
