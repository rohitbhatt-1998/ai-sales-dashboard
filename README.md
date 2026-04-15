# 🤖 AI Sales Calling Assistant Dashboard

A production-ready PHP web application for managing leads, triggering AI-based calls, tracking call activity, and viewing transcripts — designed to run on **shared web hosting** with PHP + MySQL.

---

## 📋 Requirements

| Item | Version |
|------|---------|
| PHP  | 7.4+ (8.x recommended) |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Apache | 2.4+ with `mod_rewrite` enabled |
| cURL | PHP extension (for Twilio / AI provider APIs) |
| PDO | PHP extension (for database) |

---

## 🚀 Installation (Shared Hosting)

### Step 1 — Upload Files

Upload the entire `ai-sales-dashboard/` folder to your public web root, for example:
```
/public_html/ai-sales-dashboard/
```
or as the root of your domain:
```
/public_html/
```

### Step 2 — Create Database

1. Log into your **cPanel → MySQL Databases**
2. Create a new database: `ai_sales_db`
3. Create a new user with a strong password
4. Assign the user **ALL PRIVILEGES** to the database
5. Go to **phpMyAdmin**, select your database, and run the contents of `db/schema.sql`

### Step 3 — Configure the App

Open `includes/config.php` and update:

```php
define('DB_HOST', 'localhost');       // Usually localhost
define('DB_NAME', 'ai_sales_db');    // Your database name
define('DB_USER', 'your_db_user');   // Your DB username
define('DB_PASS', 'your_password');  // Your DB password

define('BASE_URL', 'https://yourdomain.com/ai-sales-dashboard');
```

> ⚠️ For HTTPS, set `'secure' => true` in the session cookie params.

### Step 4 — Set Folder Permissions

```bash
chmod 755 uploads/
chmod 644 .htaccess
```

Or via cPanel File Manager → right-click → Change Permissions.

### Step 5 — First Login

Visit: `https://yourdomain.com/ai-sales-dashboard/`

Default credentials:
- **Email:** `admin@example.com`
- **Password:** `password`

> 🔒 Change the password immediately after first login via phpMyAdmin:
> ```sql
> UPDATE users SET password = '$2y$10$...' WHERE email = 'admin@example.com';
> ```
> Generate hash with PHP: `echo password_hash('YourNewPass', PASSWORD_DEFAULT);`

---

## 🎯 Features

### Dashboard
- Real-time metrics: Total Leads, Calls Today, Connected Calls, Hot/Warm/Cold Leads, Conversion Rate
- 7-day call activity bar chart
- Lead score distribution progress bars
- Recent calls & Hot leads quick view

### Leads Management
- Add leads manually via modal form
- **Import CSV** (drag & drop or click): supports name, phone, email, city, company columns
- Search by name/phone/email
- Filter by status and city
- Inline status badges with color coding
- Per-lead call history count

### AI Calling System
- **Single call**: One-click per lead row
- **Bulk calling**: Select multiple leads via checkboxes → "Start AI Calling"
- Sequential queue processing (avoids duplicate calls)
- Real-time status bar showing call outcome
- **Mock mode** (no Twilio configured): Simulates calls with realistic transcripts & scoring

### Call Logs
- Full log with status, duration, AI-generated summary
- Lead score per call (Hot / Warm / Cold)
- Full transcript viewer in modal
- Filter by status, date, score

### AI Configuration Panel
- Language style: English / Hinglish
- Tone: Friendly / Formal / Persuasive / Casual
- Opening script (personalized with `{{lead_name}}`)
- Multi-question flow (one per line)
- Closing statement
- Script preview modal
- Call behavior: max retries, delay between calls

### Lead Scoring
Automatic classification based on transcript keyword analysis:
- **Hot** 🔥: "interested", "buy", "demo", "price", "schedule" etc.
- **Warm** 🌤: "maybe", "consider", "let me check" etc.
- **Cold** ❄️: "not interested", "remove", "do not call" etc.

### Queue Management
- Live queue stats (pending / processing / done / failed)
- Manual "Process Next Call" trigger
- Retry failed calls button
- Auto-refresh every 10 seconds

---

## 🔗 Integrations

### Twilio (Real Phone Calls)

1. Create a [Twilio account](https://www.twilio.com/)
2. Buy a phone number with Voice capability
3. In the app: **AI Config → Integrations**
   - Paste Account SID, Auth Token, and Phone Number
4. Set your `BASE_URL` to a **public HTTPS URL** so Twilio can reach callbacks

Twilio Webhook URLs (configured automatically):
- TwiML: `https://yourdomain.com/ai-sales-dashboard/api/twiml.php?lead_id=X`
- Status Callback: `https://yourdomain.com/ai-sales-dashboard/api/call_callback.php`
- Transcription: `https://yourdomain.com/ai-sales-dashboard/api/transcribe_callback.php?lead_id=X`

### AI Summaries (OpenRouter or Cohere)

1. Get an [OpenRouter API key](https://openrouter.ai/) or [Cohere API key](https://dashboard.cohere.com/api-keys).
2. In the app: **AI Config → Integrations** choose provider and paste the key.
3. Summaries are generated from the call transcript after callbacks are received.

---

## 📂 Project Structure

```
ai-sales-dashboard/
├── index.php                  # Entry point / redirect
├── login.php                  # Login page
├── logout.php                 # Logout handler
├── .htaccess                  # Security + rewrite rules
│
├── includes/
│   ├── config.php             # App configuration
│   ├── db.php                 # PDO database singleton
│   ├── auth.php               # Authentication helper
│   ├── helpers.php            # Utility functions
│   ├── layout.php             # HTML layout (sidebar, topbar)
│   ├── CallService.php        # Call initiation + queue logic
│   └── LeadService.php        # Lead CRUD + CSV import
│
├── api/
│   ├── leads.php              # REST API: leads CRUD + CSV import
│   ├── calls.php              # REST API: call triggers + logs
│   ├── config.php             # REST API: AI configuration
│   ├── queue_items.php        # REST API: queue listing
│   ├── twiml.php              # Twilio TwiML script generator
│   ├── call_callback.php      # Twilio status callback
│   └── transcribe_callback.php # Twilio transcription + AI summary
│
├── pages/
│   ├── dashboard.php          # Main dashboard
│   ├── leads.php              # Leads management
│   ├── call_logs.php          # Call history
│   ├── ai_config.php          # AI configuration panel
│   └── queue.php              # Queue management
│
├── assets/
│   ├── css/app.css            # Full UI stylesheet
│   └── js/app.js              # Frontend JS (no framework)
│
├── db/
│   └── schema.sql             # Database schema + seed data
│
└── uploads/
    ├── .htaccess              # Block PHP in uploads
    └── sample_leads.csv       # Sample CSV for testing
```

---

## 🔒 Security Notes

- All inputs sanitized with `htmlspecialchars` and `strip_tags`
- SQL injection prevented via PDO prepared statements
- CSRF tokens on all state-changing forms
- Session regeneration on login
- Direct access to `includes/`, `db/` blocked by `.htaccess`
- PHP execution blocked in `uploads/`
- Sensitive API keys masked in the config panel

---

## ⚙️ Cron Job (Optional — Auto Queue Processing)

If you want calls to process automatically every minute, add this cron job in your hosting panel:

```bash
*/1 * * * * curl -s "https://yourdomain.com/ai-sales-dashboard/api/calls.php?action=process_queue" > /dev/null 2>&1
```

Or via a PHP cron:
```
*/1 * * * * php /home/youraccount/public_html/ai-sales-dashboard/api/calls.php?action=process_queue
```

---

## 🛠 Troubleshooting

| Problem | Solution |
|---------|----------|
| White screen | Enable errors: set `display_errors = 1` in config temporarily |
| Database error | Check DB credentials in `includes/config.php` |
| CSS not loading | Verify `BASE_URL` is correct with no trailing slash |
| Twilio not calling | Ensure BASE_URL is public HTTPS; check Twilio dashboard logs |
| CSV import failing | Check PHP `upload_max_filesize` and `post_max_size` settings |
| Session issues | Check `session.save_path` is writable on your host |

---

## 📄 License

MIT — Free to use, modify, and deploy.
