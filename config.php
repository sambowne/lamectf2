<?php

$_secret_dir = ($_SERVER['HOME'] ?? posix_getpwuid(posix_geteuid())['dir']) . '/.lamectf2';

// ── Discover all configured classes ─────────────────────────────────────────
$_known_classes = [];
foreach (glob($_secret_dir . '/*_secret.php') ?: [] as $_f) {
    if (preg_match('/\/([A-Za-z0-9_-]+)_secret\.php$/', $_f, $_m))
        $_known_classes[] = $_m[1];
}

// ── Legacy migration: auto-convert old secret.php to {course}_secret.php ────
if (empty($_known_classes) && file_exists($_secret_dir . '/secret.php')) {
    // Peek at old file to get course_number
    $_peek = [];
    preg_match('/\$course_number\s*=\s*([\'"])([a-zA-Z0-9_-]+)\1/', file_get_contents($_secret_dir . '/secret.php'), $_peek);
    $_legacy_course = $_peek[2] ?? 'COURSE1';
    $_new_secret = $_secret_dir . '/' . $_legacy_course . '_secret.php';
    if (!file_exists($_new_secret)) {
        copy($_secret_dir . '/secret.php', $_new_secret);
        chmod($_new_secret, 0600);
        // Migrate CTF_answers.php if it exists
        if (file_exists($_secret_dir . '/CTF_answers.php') && !file_exists($_secret_dir . '/' . $_legacy_course . '_CTF_answers.php')) {
            copy($_secret_dir . '/CTF_answers.php', $_secret_dir . '/' . $_legacy_course . '_CTF_answers.php');
            chmod($_secret_dir . '/' . $_legacy_course . '_CTF_answers.php', 0600);
        }
    }
    $_known_classes[] = $_legacy_course;
}

// ── Select active class ──────────────────────────────────────────────────────
$_c = '';

// ?c= param overrides everything; validate strictly against whitelist
if (!empty($_GET['c']) && in_array($_GET['c'], $_known_classes, true)) {
    $_c = $_GET['c'];
    // Switching class invalidates admin auth for the previous class
    if (isset($_SESSION['admin_authed_class']) && $_SESSION['admin_authed_class'] !== $_c) {
        $_SESSION['admin_authed']       = false;
        $_SESSION['admin_authed_class'] = '';
    }
    $_SESSION['current_course'] = $_c;
} elseif (!empty($_SESSION['current_course']) && in_array($_SESSION['current_course'], $_known_classes, true)) {
    $_c = $_SESSION['current_course'];
} elseif (count($_known_classes) === 1) {
    $_c = $_known_classes[0];
    $_SESSION['current_course'] = $_c;
}

// No class selected and multiple exist → send to class selector
// (classes.php and init.php handle themselves without config.php)
if ($_c === '' && !empty($_known_classes)) {
    header('Location: classes.php');
    exit;
}

// ── Load secrets for selected class ─────────────────────────────────────────
if ($_c !== '') {
    include $_secret_dir . '/' . $_c . '_secret.php';
}

// ── Fallbacks ────────────────────────────────────────────────────────────────
if (empty($logfile))          $logfile          = $_secret_dir . '/scores.csv';
if (empty($xfile))            $xfile            = $_secret_dir . '/scores_extra.csv';
if (empty($results_csv))      $results_csv      = $_secret_dir . '/quiz_results.csv';
if (empty($access_codes_csv)) $access_codes_csv = $_secret_dir . '/nick_access.csv';
if (empty($description))      $description      = 'CTF Course';
if (empty($namefile))         $namefile         = $_secret_dir . '/students.csv';
if (empty($quiz_files))       $quiz_files       = [];
// Auto-discover uploaded quiz files (.txt) from the secret directory
foreach (glob($_secret_dir . '/*.txt') ?: [] as $_qf) {
    if (!in_array($_qf, $quiz_files, true)) $quiz_files[] = $_qf;
}

// ── Per-class derived paths ──────────────────────────────────────────────────
$_prefix             = !empty($course_number) ? $course_number . '_' : '';
$discussions_csv     = $_secret_dir . '/' . $_prefix . 'discussions.csv';
$discussions_enabled = file_exists($_secret_dir . '/' . $_prefix . 'discussions_enabled');
$ask_section         = !file_exists($_secret_dir . '/' . $_prefix . 'no_section');
$grades_csv          = $_secret_dir . '/' . $_prefix . 'grades.csv';

// Backup settings — global (shared across all classes)
$backup_enabled       = false;
$backup_email         = '';
$backup_agentmail_key = '';
$backup_zip_password  = '';
$_backup_cfg = $_secret_dir . '/backup.php';
if (file_exists($_backup_cfg)) include $_backup_cfg;

// ── Navigation helper ────────────────────────────────────────────────────────
// $nav_c  = '?c=CNIT126'  — use as first query param on all links
// $nav_qs = '' or '&name=...&code=...'  — appended after $nav_c by nav.php
$nav_c = !empty($course_number) ? '?c=' . urlencode($course_number) : '?';
if (!isset($projects_url)) $projects_url = '';

// ── Challenges ───────────────────────────────────────────────────────────────
$removes = [];

// Default challenge list — can be overridden by {course}_CTF_answers.php
$poss_chals = [
    "LABEL_Chal",  "_Chal 1_", "_Chal 2_",  "BREAK",
];

// Load master answers file (all classes) — defines $correct_answers
if (file_exists($_secret_dir . '/CTF_answers.php')) {
    include $_secret_dir . '/CTF_answers.php';
}

// Load per-class challenge list — overrides $poss_chals for this class
if ($_c !== '' && file_exists($_secret_dir . '/' . $_c . '_CTF_answers.php')) {
    include $_secret_dir . '/' . $_c . '_CTF_answers.php';
}

$junk = [];

?>
