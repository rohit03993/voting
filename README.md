# HCS Voting System

Standalone school voting app. **No WordPress. No Google Sheets.**

Built with PHP + MySQL for shared hosting (cPanel / Plesk / etc.).

## Features

- Staff admin: add positions, candidates, photos
- Draft → **Publish** → Live voting → Close
- Public URL for **Student** / **Staff** voting
- Separate private URLs for **Principal** and **Director** (token + passcode)
- Live results with student/staff/principal/director breakdown
- CSV import from last year’s Google Sheet
- Duplicate-vote protection (cookie token; principal/director once each)

## Deploy on shared hosting

1. Upload this whole folder to your host, e.g.  
   `public_html/vote/`  
   so the site is `https://yourdomain.com/vote/`

2. Create a MySQL database + user in cPanel.

3. Open `https://yourdomain.com/vote/install/` and fill the form.

4. Login at `https://yourdomain.com/vote/admin/`

5. Add candidates (or **Import CSV**), then click **Publish (Go Live)**.

6. Delete or password-protect the `/install` folder after install.

7. Share links from the admin Dashboard:
   - Students & Staff: `/vote/`
   - Principal: `/vote/p/?t=...`
   - Director: `/vote/d/?t=...`
   - Results: `/vote/results/`

## Local folder map

```
vote/
  index.php          Student/Staff voting
  p/                 Principal voting
  d/                 Director voting
  results/           Live results
  admin/             Staff panel
  api/               JSON API
  install/           One-time installer
  assets/            CSS, JS, uploads
  config/            config.php (created by installer)
  includes/          PHP helpers
```

## CSV import format

Same as last year’s sheet:

| Name | Class | Position | ImageURL |
|------|-------|----------|----------|
| Rohit Yadav | Class 12 | Head Boy | https://... |

## Requirements

- PHP 8.0+ (7.4 may work but 8+ recommended)
- MySQL 5.7+ / MariaDB 10+
- `pdo_mysql`, `fileinfo` extensions
- Write permission on `config/` and `assets/uploads/`

## Security notes

- Passcodes are checked on the server (not in browser JS)
- Admin password is hashed in the database
- Principal/Director links use secret tokens — do not post them publicly
- After install, remove `/install` from the server
