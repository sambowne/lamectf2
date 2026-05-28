# lamectf2

A CTF scoring engine with quiz support, discussions, extra credit, and multi-class management. Designed for college courses where students earn points by solving cybersecurity challenges and completing quizzes.

---

## Requirements

- A **Linux** server: shared hosting or VPS running PHP 7.2+
- SSH access (for the initial home-directory permission step)
- A **Google reCAPTCHA Enterprise** account — get free keys at [Google reCAPTCHA Console](https://www.google.com/recaptcha/admin/create). Both admin login and student login are protected by reCAPTCHA.

---

## Deployment

### Step 1: Lock down your home directory (recommended)

On shared hosting, other users on the same system can browse world-readable home directories. SSH in and run:

```bash
chmod 700 ~
```

This is a best-effort hardening step — not all shared hosts allow it. The critical protection is that `~/.lamectf2/` (created next) has mode 700, which prevents other users from entering it even if they can see its name.

### Step 2: Upload the files

Upload the entire `lamectf2/` folder to your web server's public directory (e.g. `public_html/lamectf2/`). All files in the ZIP go there. No build step required.

### Step 3: Run init.php in your browser

Open `https://yoursite.example.com/lamectf2/init.php`.

**Step 3a — Create your first class:**

Fill in:

| Field | Example | Notes |
|---|---|---|
| Course number | `CNIT_126_F26` | Letters, numbers, underscores only. Used as a prefix on all data files. |
| Course name | `Network Security, Fall 2026` | Shown on the scoreboard header. |
| Admin password | *(8+ chars)* | Hashed with bcrypt cost-14. Shared across all classes you add later. |
| reCAPTCHA site key | `6Lx...` | Public key from Google Console. |
| reCAPTCHA secret key | `6Lx...` | Private key — never exposed to students. |
| Ask for section/CRN | *(checkbox)* | Uncheck to hide the section field on the student registration form. |

`init.php` will:
1. Create `~/.lamectf2/` with mode 700 (outside the web root — students cannot access it)
2. Hash the password with bcrypt (cost 14)
3. Write `~/.lamectf2/CNIT_126_F26_secret.php` with mode 600

**Step 3b — Upload your challenge answers:**

After class creation, go to **Manage → Master Answers File** and upload your `CTF_answers.php`. Then go to **Challenge List** and upload the per-class challenge list (also a PHP file — see File Formats below).

**Step 3c — Upload quiz files:**

Go to **Manage → Quiz Files** and upload your `.txt` quiz files. See the Quiz File Format section below.

**Step 3d — Add students:**

Either:
- Share the student URL (shown in the "Ready to go?" box) and let students self-register, or
- Upload a bulk import CSV under **Manage → Students**

**Step 3e — Send students to the login page:**

The student entry point is:

```
https://yoursite.example.com/lamectf2/login.php?c=CNIT_126_F26
```

This URL is shown in the "Ready to go?" box in init.php after class creation. Students can register, log in, submit flags, and take quizzes from this page.

### Step 4: Managing an existing class

Return to `init.php` any time to:
- Upload updated quiz or answer files
- Enable/disable discussions
- Enable/disable the section/CRN field on registration
- Trigger a score backup
- Add additional classes

`init.php` is protected by the admin password and can remain on the server permanently.

### Adding a second class

Visit `init.php` and click **Add Another Class**. The admin password and reCAPTCHA keys are reused automatically — you only need to enter the new course number and name. Each class gets its own isolated set of data files in `~/.lamectf2/`.

### Resetting the admin password

Delete `~/.lamectf2/{course}_secret.php` via SSH or your host's file manager, then visit `init.php` to re-create the class with a new password.

---

## File Formats

### Master Answers File — `CTF_answers.php`

A PHP file mapping challenge IDs to correct flag values and point scores. This file is **shared across all classes** — it contains the authoritative answers for every challenge in every class. Upload it via **Manage → Master Answers File**.

Challenge IDs must start and end with an underscore.

```php
<?php
$correct_answers = array(
//  Challenge ID       Correct answer   Points
    ["_Web 1_",        "FLAG{xss}",     10],
    ["_Web 2_",        "FLAG{sqli}",    20],
    ["_Crypto 1_",     "FLAG{base64}",  10],
);
```

### Per-Class Challenge List — `{course}_CTF_answers.php`

A PHP file listing which challenges appear for **this class only**. This controls what students see on the flag submission page. Upload it via **Manage → Challenge List**.

```php
<?php
// Challenge display list for this class
// LABEL_SeriesName   — section header (not clickable)
// _Challenge ID_     — must match an entry in CTF_answers.php
// BREAK              — visual separator

$poss_chals = [
    "LABEL_Web",
    "_Web 1_", "_Web 2_",
    "BREAK",
    "LABEL_Cryptography",
    "_Crypto 1_",
];
```

### Bulk Student Import CSV

Upload via **Manage → Students**. Accepted formats:

**6-column** (full):
```
nickname, last_name, first_name, student_id, section, access_code
1234,Smith,Jordan,W00001234,CNIT126,open_apple
```

**5-column** (combined name):
```
nickname, "Last, First", student_id, section, access_code
1234,"Smith, Jordan",W00001234,CNIT126,open_apple
```

**2-column** (minimal):
```
nickname, access_code
1234,open_apple
```

The nickname and access code are what students use to log in. Access codes should be memorable passphrases (e.g. `open_apple`).

### Quiz File (`.txt`)

Plain text. The **first answer listed under each question is correct**; distractors follow. Answers are displayed in random order to each student, and the shuffle is stored server-side (students cannot manipulate it).

```
Title: Web Security Quiz
Select: 5
Points per question: 4
Number of Attempts: 2

1 Which HTTP method is used to submit a form?

POST
GET
PUT
DELETE

2 What does XSS stand for?

Cross-Site Scripting
Cross-Site Request Forgery
Extended Style Sheets
External Script Source
```

| Header | Meaning |
|---|---|
| `Title` | Shown to students and used as the quiz identifier in score records |
| `Select` | Number of questions drawn at random per attempt |
| `Points per question` | Points awarded per correct answer |
| `Number of Attempts` | How many times each student may submit (omit for unlimited) |

> **Auto-discovery:** All `.txt` files uploaded to the secret directory are automatically included in the quiz list. No `config.php` edits are required — upload a file via **Manage → Quiz Files** and it appears immediately.

---

## Page Reference

| Page | Who | Purpose |
|---|---|---|
| `login.php` | Students | Entry point — log in or link to register |
| `register_form.php` | Students | Self-registration form |
| `index.php` | Students | Flag submission |
| `quiz.php` | Students | Quiz list and quiz taking |
| `scoreboard_overall.php` | Public | Overall class scoreboard |
| `scoreboard_with_quizzes.php` | Students | Personal score breakdown |
| `admin.php` | Admin | Admin console — links, backup trigger |
| `init.php` | Admin | Class setup and management |
| `grid.php` | Admin | Score grid (all students × all challenges) |
| `scoreboard_with_names.php` | Admin | Scoreboard with real names |
| `extra.php` | Admin | Extra credit entry |
| `discussions.php` | Admin | Discussion participation scores |
| `quiz_upload.php` | Admin | Quiz file upload |
| `backup_cron.php` | Cron | Automated nightly score backup |
| `backup_log.php` | Admin | View backup history |

---

## Multi-Class Setup

One installation can host multiple independent classes. Each class has its own:
- Secret file: `~/.lamectf2/{course}_secret.php`
- Score log: `~/.lamectf2/{course}_scores.csv`
- Student list: `~/.lamectf2/{course}_students.csv`
- Access codes: `~/.lamectf2/{course}_nick_access.csv`
- Quiz results: `~/.lamectf2/{course}_quiz_results.csv`
- Challenge list: `~/.lamectf2/{course}_CTF_answers.php`
- Feature flags: `~/.lamectf2/{course}_discussions_enabled`, `~/.lamectf2/{course}_no_section`

The master answers file (`CTF_answers.php`) and backup configuration (`backup.php`) are **shared** across all classes.

When multiple classes are configured, visiting `init.php` or `index.php` without a `?c=` parameter shows a class selector. Students should always use their class-specific URL (`?c=CNIT_126_F26`).

---

## Score Backup

Configure backup under **Manage → Score Backup**. Backups are sent as a password-encrypted ZIP to an email address via [AgentMail](https://agentmail.to).

To set up **nightly automated backups**, SSH in, `cd` to the lamectf2 directory, and run the cron install command shown in `init.php` (it installs a midnight cron job automatically).

Backup ZIP contents: all score CSVs, student lists, access code files, quiz results, and discussion scores for all configured classes.

---

## Disabling Features on an Existing Deployment

These toggles are available in **init.php → Manage** for any class. You can also flip them via SSH:

| Feature | Enable | Disable |
|---|---|---|
| Discussion scores | `touch ~/.lamectf2/{course}_discussions_enabled` | `rm ~/.lamectf2/{course}_discussions_enabled` |
| Section/CRN field on registration | `rm ~/.lamectf2/{course}_no_section` | `touch ~/.lamectf2/{course}_no_section` |

---

## Security Features

lamectf2 is designed for a shared-hosting environment where the web root is world-readable. The following protections are in place:

### Secret storage outside the web root

All sensitive data — the hashed admin password, reCAPTCHA keys, student names, access codes, quiz files, challenge answers, and score logs — is stored in `~/.lamectf2/` with mode 700/600. These files are never served by the web server and cannot be accessed via a browser, even if a student knows the path.

### Bcrypt password hashing

The admin password is hashed with `password_hash()` using bcrypt at cost 14 (approximately 1 second to verify). The plaintext password is never stored anywhere.

### CSRF protection on all state-changing requests

Every POST form includes a CSRF token generated with `random_bytes(32)` and stored in the session. `csrf_verify()` is called on every state-changing handler. Forms that do not mutate state (scoreboards, quiz display) do not require tokens.

### reCAPTCHA on admin and student login

Both `admin.php` and `login.php` require a Google reCAPTCHA Enterprise challenge before accepting credentials. This rate-limits brute-force attempts against both the admin password and student access codes. reCAPTCHA is bypassed gracefully if keys are not configured (useful for local testing).

### Session fixation prevention

`session_regenerate_id(true)` is called immediately after every successful login (student, admin, and init.php admin). This invalidates the pre-login session ID and prevents session fixation attacks.

### Student identity tied to session

Once a student logs in, their nickname is stored in the session and cannot be overridden by any POST parameter. `grade.php` and `quiz.php` always read the nickname from `$_SESSION` — a logged-in student cannot submit flags or quiz answers on behalf of another student.

### Quiz shuffle stored server-side

When a quiz is rendered, the question selection and per-question answer shuffle order are stored in `$_SESSION` and immediately consumed on submit. No shuffle state is sent to the client. Students cannot manipulate hidden fields to guarantee correct answers.

### Duplicate nickname prevention

`register.php` checks the existing student list before accepting a new registration. A student cannot register an already-taken nickname, preventing score poisoning via duplicate accounts.

### Input sanitization

All user-supplied input displayed in HTML passes through `htmlspecialchars()` with `ENT_QUOTES`. Challenge IDs and student names written to CSV files pass through a whitelist sanitizer that strips characters outside `[a-zA-Z0-9 ._-]`, preventing CSV injection in score logs.

### Per-class admin session isolation

Admin authentication is scoped to a specific class. Authenticating as admin for CNIT 126 does not grant access to CNIT 127 management pages. Switching classes in the URL invalidates the prior class's admin session.
