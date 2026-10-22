# SBA 2012 — cPanel Install Guide

This package is the complete site: public pages, admin, member audit portal,
and the installable mobile app (PWA), all served by PHP on MySQL. No Node,
Render, or Supabase is needed.

## 1. What you need on cPanel

- PHP **8.1 or newer** (cPanel → MultiPHP Manager; select 8.1+ for the domain)
- PHP extensions: **pdo_mysql, zip, gd, mbstring, curl** (usually on by default)
- MySQL / MariaDB (cPanel → MySQL® Databases)
- AutoSSL or a free SSL certificate on the domain (the PWA needs HTTPS)

## 2. Folder layout

Upload so that `public_html` and `private` sit side by side in your home folder:

```
/home/CPANELUSER/
├── public_html/          ← web root (everything a visitor can reach)
│   ├── .htaccess         ← Apache rules, security headers, 404 page
│   ├── .user.ini         ← PHP upload limits (20 MB files)
│   ├── index.php, about.php, executives.php, news.php, … contact.php, audit.php, file.php
│   ├── admin/            ← admin area (login at /admin/)
│   ├── api/chat.php      ← chatbot endpoint
│   ├── assets/           ← CSS, JS, images, icons
│   ├── uploads/          ← public images (news, blogs, gallery, executives)
│   ├── sw.js, manifest.webmanifest, offline.html   ← PWA files
│   └── 404.php
└── private/              ← NEVER web-visible (denied by its own .htaccess)
    ├── config.php        ← the one file you edit: database + options
    ├── bootstrap.php, layout.php, chat.php, reports.php
    ├── schema.sql        ← database schema (applied automatically)
    ├── constitution.txt, letterhead.png
    ├── lib/fpdf/         ← PDF library (no Composer needed)
    └── documents/        ← private member documents (served only via /file.php)
```

Upload the zip with File Manager → Upload → then Extract. Make sure the
hidden files (`.htaccess`, `.user.ini`) came through; File Manager → Settings →
"Show Hidden Files" lets you check.

## 3. Create the database

1. cPanel → **MySQL® Databases**
2. Create a database (e.g. `CPANELUSER_sba`)
3. Create a database user with a strong password
4. Add the user to the database with **ALL PRIVILEGES**

## 4. Configure

Open `private/config.php` in File Manager and set:

- `db_name`, `db_user`, `db_pass` — from step 3 (cPanel prefixes names with your account name)
- `db_host` — leave `localhost` (cPanel default)
- `groq_api_key` — optional. Free key from console.groq.com. Leave empty and the chatbot gives a friendly "not switched on" answer.
- `default_admin_password` — used **only** on the first visit, when the database has no admin yet. Change it straight after the first login.

You may set these as environment variables instead (`SBA_DB_NAME`, `SBA_DB_USER`,
`SBA_DB_PASS`, `SBA_DB_HOST`, `GROQ_API_KEY`) if your host supports it.

## 5. First run

1. Visit `https://yourdomain.org/` — the database tables are created automatically from `private/schema.sql`.
2. Visit `https://yourdomain.org/admin/` and sign in with username **admin** and the `default_admin_password`.
3. Go to **Password** and change it immediately (minimum 8 characters).
4. Go to **Settings** and confirm the contact email, phone, and address shown on the Contact page.
5. Go to **Executives / Tenures**: create the tenure (start and end year) first, then add executives to it.

## 6. Go-live checklist

- [ ] Site loads over HTTPS with no mixed-content warnings
- [ ] Admin password changed from the default
- [ ] Register a test member, approve, open `/audit.php` with the SBA12 ID
- [ ] Upload one news item with a picture; check it shows on the home page
- [ ] Upload one document as a member-only file; check it is **not** reachable at `/file.php?...` without logging in
- [ ] Account page: export Excel and PDF; check the note and "Prepared and submitted by" block
- [ ] On an Android phone (Chrome) and an iPhone (Safari), open the site and use "Add to Home Screen"
- [ ] Delete the test member and test records from the admin before going live

## 7. Moving existing data from Supabase

The old site stored data in Supabase Postgres and files in Supabase Storage.

1. **Tables:** in the Supabase Table Editor export each table to CSV (members, payments, news, blogs, gallery, documents, messages, settings, executive_tenures, executives, account_years, account_entries, admins).
2. **Import:** in phpMyAdmin, import the CSVs in that order (parents before children: `executive_tenures` before `executives`, `account_years` before `account_entries`). Check column names match `private/schema.sql`; rename any that differ.
3. **Admin passwords carry over.** Existing bcrypt hashes from the Node app verify correctly in PHP. If you prefer, reset the password instead from the admin Password page.
4. **Files:** download news, blog, gallery and executive images from Supabase Storage into `public_html/uploads/` (keep the file names, since the database stores them). Download member documents into `private/documents/`.
5. Check the public pages and the audit portal against the old site before pointing the domain.

Keep the Netlify and Render sites running until the cPanel site is verified.

## 8. Maintenance

- **Backups:** cPanel → Backup (weekly full backup), plus phpMyAdmin → Export for the database.
- **Errors:** PHP errors are written to the server log; the site shows a friendly message to visitors.
- **Keepalive pinger:** not needed on cPanel. No cron job is required for the site itself.
- **Uploads:** `public_html/uploads/` blocks PHP execution (its own `.htaccess`). Member documents live outside the web root.

## 9. Known limits

- Member ID format is `SBA12` + 3 characters (32,768 combinations). The audit portal is ID-gated but has no rate limit. Consider adding one before large-scale use.
- iPhone PWA: no push notifications. Use the News page for notices.
- The chatbot needs a Groq key; without one it answers with a fixed message.
