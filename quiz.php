<?php
session_start();
// ── Parameters ────────────────────────────────────────────────────────────────
include 'config.php';
// ─────────────────────────────────────────────────────────────────────────────

function parse_quiz(string $path): array {
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $meta = []; $questions = []; $i = 0;
    while ($i < count($lines)) {
        $line = trim($lines[$i]);
        if (preg_match('/^\d+\s+\S/', $line)) break;
        if (preg_match('/^(.+?):\s*(.*)$/', $line, $m)) $meta[trim($m[1])] = trim($m[2]);
        $i++;
    }
    $current_q = null;
    while ($i < count($lines)) {
        $line = $lines[$i];
        if (preg_match('/^\s*(\d+)\s+(.+)$/', $line, $m)) {
            if ($current_q !== null) $questions[] = $current_q;
            $current_q = ['text' => trim($m[2]), 'answers' => []];
        } elseif ($current_q !== null && trim($line) !== '') {
            $current_q['answers'][] = trim($line);
        }
        $i++;
    }
    if ($current_q !== null) $questions[] = $current_q;
    return ['meta' => $meta, 'questions' => $questions];
}

function load_results(string $path): array {
    $results = [];
    if (!file_exists($path)) return $results;
    $fh = fopen($path, 'r');
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) < 4) continue;
        [$nick, $quiz, $score, $ts] = array_map('trim', $row);
        $results[$nick][$quiz][] = ['score' => $score, 'ts' => $ts];
    }
    fclose($fh);
    return $results;
}

function append_result(string $path, string $nick, string $quiz, int $score, string $ts): void {
    $fh = fopen($path, 'a');
    fputcsv($fh, [$nick, $quiz, $score, $ts]);
    fclose($fh);
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

function validate_access_code(string $nickname, string $code, string $csv_path): bool {
    if ($nickname === '' || $code === '') return false;
    if (!file_exists($csv_path)) return false;
    $fh = fopen($csv_path, 'r');
    fgetcsv($fh); // skip header
    while (($row = fgetcsv($fh)) !== false) {
        if (trim($row[0]) === $nickname) {
            fclose($fh);
            return strtolower(trim($row[1] ?? '')) === strtolower($code);
        }
    }
    fclose($fh);
    return false;
}

function nickname_dropdown(string $namefile, string $selected = ''): void {
    echo '<select name="name" id="name">';
    echo '<option value="">-- choose --</option>';
    if (file_exists($namefile)) {
        $fh = fopen($namefile, 'r');
        while (($row = fgetcsv($fh)) !== false) {
            $n = h($row[0]);
            $sel = ($row[0] === $selected) ? ' selected' : '';
            echo "<option value=\"$n\"$sel>$n</option>";
        }
        fclose($fh);
    }
    echo '</select>';
}

// ── State ─────────────────────────────────────────────────────────────────────
$_k_nick = 'student_nick_' . ($course_number ?? 'default');
$_k_code = 'student_code_' . ($course_number ?? 'default');

if (empty($_SESSION[$_k_nick])) {
    header('Location: login.php' . $nav_c);
    exit;
}

$nickname    = $_SESSION[$_k_nick];
$code        = $_SESSION[$_k_code] ?? '';
$selected_qi = trim($_POST['quiz'] ?? $_GET['quiz'] ?? '');
$selected_q  = (is_numeric($selected_qi) && isset($quiz_files[(int)$selected_qi]))
               ? $quiz_files[(int)$selected_qi] : '';
$submitting  = isset($_POST['submit_quiz']);
$authenticated = true;
$nav_qs      = '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quiz</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 900px; margin: 30px auto; padding: 0 20px; }
  h1 { color: #333; }
  table { border-collapse: collapse; width: 100%; margin: 16px 0; }
  th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
  th { background: #f0f0f0; }
  .question { margin: 12px 0; padding: 12px; border: 1px solid #ddd; border-radius: 4px; }
  .question p { font-weight: bold; margin: 0 0 8px; }
  label { display: block; margin: 4px 0; }
  .answer-correct { outline: 3px solid green; border-radius: 4px; padding: 2px 6px; }
  .answer-wrong   { outline: 3px solid red;   border-radius: 4px; padding: 2px 6px; }
  .group-correct   { border: 6px solid green; border-radius: 12px; padding: 16px; margin: 24px 0; }
  .group-incorrect { border: 6px solid red;   border-radius: 12px; padding: 16px; margin: 24px 0; }
  .group-correct h3   { color: green; margin: 0 0 12px; }
  .group-incorrect h3 { color: red;   margin: 0 0 12px; }
  .color-legend { font-size: 0.88em; margin: 6px 0 10px; color: #555; }
  .score-box { background: #e8f4e8; border: 1px solid #4a4; padding: 16px; border-radius: 6px; margin: 16px 0; }
  .quiz-instructions { background: #f0f4ff; border: 1px solid #99b; padding: 12px 16px; border-radius: 6px; margin: 10px 0 18px; }
  .error { background: #fdd; border: 1px solid #c00; padding: 10px; border-radius: 4px; margin: 12px 0; }
  input[type=submit] { padding: 10px 24px; font-size: 1em; cursor: pointer; }
  input[type=text] { padding: 6px; font-size: 1em; }
  select { padding: 6px; font-size: 1em; }
</style>
</head>
<body>
<?php echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description ?? ''), ENT_QUOTES) . "</h2>"; ?>
<?php include 'nav.php'; ?>
<?php

// ── Load all quiz metadata and results ────────────────────────────────────────
$all_quizzes = [];
foreach ($quiz_files as $qf) {
    if (!file_exists($qf)) continue;
    $parsed = parse_quiz($qf);
    $parsed['filename'] = $qf;
    $all_quizzes[$qf] = $parsed;
}

$all_results = load_results($results_csv);
$my_results  = $all_results[$nickname] ?? [];

$auth_fields = ''; // session handles auth — no hidden fields needed

// ── Step 2: Handle quiz submission ────────────────────────────────────────────
if ($submitting && $selected_q !== '') {
    $quiz = $all_quizzes[$selected_q] ?? null;
    if ($quiz) {
        $meta        = $quiz['meta'];
        $title       = $meta['Title'] ?? $selected_q;
        $pts_each    = (int)($meta['Points per question'] ?? 1);
        $max_attempts = (int)($meta['Number of Attempts'] ?? PHP_INT_MAX);

        // Load quiz state from session — never from POST (prevents shuffle manipulation).
        $_qsk        = 'quiz_state_' . ($course_number ?? 'default');
        $_quiz_state = $_SESSION[$_qsk] ?? null;
        if (!$_quiz_state || $_quiz_state['file'] !== $selected_q) {
            echo "<div class='error'>Quiz session expired or invalid. <a href='quiz.php" . h($nav_c) . "'>Go back and reload the quiz.</a></div>";
            include __DIR__ . '/nav_bottom.php';
            echo '</body></html>';
            exit;
        }
        unset($_SESSION[$_qsk]); // one-time use

        $shown_questions = [];
        foreach ($_quiz_state['questions'] as $qi => $qs) {
            $idx = $qs['orig_idx'];
            if (isset($quiz['questions'][$idx]))
                $shown_questions[$qi] = ['orig_idx' => $idx, 'q' => $quiz['questions'][$idx], 'order' => $qs['order']];
        }

        $score = 0;
        $total_pts = count($shown_questions) * $pts_each;
        $graded = [];

        foreach ($shown_questions as $qi => $sq) {
            $shuffled_order       = $sq['order'];
            $correct_shuffled_pos = array_search(0, $shuffled_order);
            $user_choice          = (int)($_POST["q$qi"] ?? -1);
            $is_correct           = ($user_choice === (int)$correct_shuffled_pos);
            if ($is_correct) $score += $pts_each;
            $graded[] = [
                'text'           => $sq['q']['text'],
                'answers'        => $sq['q']['answers'],
                'shuffled_order' => $shuffled_order,
                'correct_pos'    => $correct_shuffled_pos,
                'user_choice'    => $user_choice,
                'is_correct'     => $is_correct,
            ];
        }

        $ts = date('Y-m-d H:i:s');
        append_result($results_csv, $nickname, $title, $score, $ts);

        echo '<h2>' . h($title) . ' — Results</h2>';
        echo '<p>Student: <strong>' . h($nickname) . '</strong></p>';
        echo "<div class='score-box'><strong>Score: $score / $total_pts</strong> &nbsp; ($ts)</div>";

        $correct_qs   = array_filter($graded, function($g) { return  $g['is_correct']; });
        $incorrect_qs = array_filter($graded, function($g) { return !$g['is_correct']; });

        foreach ([
            ['group-correct',   'Correct',   $correct_qs,   '<span style="color:green">Green outline</span> = correct answer.'],
            ['group-incorrect', 'Incorrect', $incorrect_qs, '<span style="color:green">Green outline</span> = correct answer &nbsp; <span style="color:red">Red outline</span> = your answer.'],
        ] as [$cls, $label, $questions, $legend]) {
            if (empty($questions)) continue;
            echo "<div class='$cls'>";
            echo "<h3>$label (" . count($questions) . ")</h3>";
            echo "<p class='color-legend'>$legend</p>";
            foreach ($questions as $gi => $g) {
                echo "<div class='question'>";
                echo "<p>" . ($gi+1) . ". " . h($g['text']) . "</p>";
                foreach ($g['shuffled_order'] as $pos => $orig_idx) {
                    $ans_text       = h($g['answers'][$orig_idx]);
                    $is_user        = ($pos === $g['user_choice']);
                    $is_correct_ans = ($pos === (int)$g['correct_pos']);
                    if ($is_correct_ans)      $acls = 'answer-correct';
                    elseif ($is_user)         $acls = 'answer-wrong';
                    else                      $acls = '';
                    $checked = $is_user ? 'checked' : '';
                    echo "<label class='$acls'><input type='radio' disabled $checked> $ans_text</label>";
                }
                echo "</div>";
            }
            echo "</div>";
        }

        include __DIR__ . '/nav_bottom.php';
        echo '</body></html>';
        exit;
    }
}

// ── Step 3: No quiz selected — show quiz list ─────────────────────────────────
if ($selected_q === '' || !isset($all_quizzes[$selected_q])) {
    echo '<p>Student: <strong>' . h($nickname) . '</strong></p>';
    echo '<h2>Available Quizzes</h2>';
    echo '<table>';
    echo '<tr><th>Quiz</th><th>Total Points</th><th>Attempts Used</th><th>Attempts Remaining</th><th>Previous Scores</th><th>Action</th></tr>';

    foreach ($all_quizzes as $qf => $quiz) {
        $meta         = $quiz['meta'];
        $title        = $meta['Title'] ?? $qf;
        $num_q        = (int)($meta['Select'] ?? count($quiz['questions']));
        $pts_each     = (int)($meta['Points per question'] ?? 1);
        $total_pts    = $num_q * $pts_each;
        $max_attempts = isset($meta['Number of Attempts']) ? (int)$meta['Number of Attempts'] : '∞';

        $my_attempts = $my_results[$title] ?? [];
        $used        = count($my_attempts);
        $remaining   = is_int($max_attempts) ? max(0, $max_attempts - $used) : '∞';
        $scores_str  = implode(', ', array_map(function($r) { return $r['score']; }, $my_attempts)) ?: '—';
        $can_take    = ($remaining === '∞' || $remaining > 0);
        $qi_val      = array_search($qf, $quiz_files);
        $action      = $can_take
            ? '<a href="quiz.php' . htmlspecialchars($nav_c, ENT_QUOTES) . '&quiz=' . $qi_val . '">Take Quiz</a>'
            : '<em>No attempts remaining</em>';

        echo "<tr><td>" . h($title) . "</td><td>$total_pts</td><td>$used</td><td>$remaining</td><td>$scores_str</td><td>$action</td></tr>";
    }
    echo '</table>';
    include __DIR__ . '/nav_bottom.php';
    echo '</body></html>';
    exit;
}

// ── Step 4: Show the quiz ─────────────────────────────────────────────────────
$quiz         = $all_quizzes[$selected_q];
$meta         = $quiz['meta'];
$title        = $meta['Title'] ?? $selected_q;
$num_q        = (int)($meta['Select'] ?? count($quiz['questions']));
$pts_each     = (int)($meta['Points per question'] ?? 1);
$max_attempts = isset($meta['Number of Attempts']) ? (int)$meta['Number of Attempts'] : PHP_INT_MAX;

$my_attempts = $my_results[$title] ?? [];
$used        = count($my_attempts);

if ($used >= $max_attempts) {
    echo '<p>You have used all ' . $max_attempts . ' attempt(s) for this quiz.</p>';
    echo '<a href="quiz.php' . htmlspecialchars($nav_c, ENT_QUOTES) . '">&#8592; Back</a>';
    include __DIR__ . '/nav_bottom.php';
    echo '</body></html>';
    exit;
}

$all_q    = $quiz['questions'];
$indices  = array_keys($all_q);
shuffle($indices);
$selected_indices = array_slice($indices, 0, min($num_q, count($indices)));

// Build per-question answer shuffles and store entirely in session — never trust client.
$_qsk = 'quiz_state_' . ($course_number ?? 'default');
$_quiz_state = ['file' => $selected_q, 'questions' => []];
foreach ($selected_indices as $qi => $orig_idx) {
    $answer_order = range(0, count($all_q[$orig_idx]['answers']) - 1);
    shuffle($answer_order);
    $_quiz_state['questions'][$qi] = ['orig_idx' => $orig_idx, 'order' => $answer_order];
}
$_SESSION[$_qsk] = $_quiz_state;

echo '<h2>' . h($title) . '</h2>';
echo '<p>Student: <strong>' . h($nickname) . '</strong></p>';
if (!empty($meta['Instructions'])) {
    echo '<div class="quiz-instructions">' . $meta['Instructions'] . '</div>';
}
echo '<p>Answer all questions and click Submit.</p>';
echo '<form method="post">';
echo '<input type="hidden" name="c" value="' . htmlspecialchars($course_number ?? '', ENT_QUOTES) . '">';
echo $auth_fields;
echo '<input type="hidden" name="quiz" value="' . (int)$selected_qi . '">';

foreach ($_quiz_state['questions'] as $qi => $qs) {
    $orig_idx     = $qs['orig_idx'];
    $answer_order = $qs['order'];
    $q            = $all_q[$orig_idx];

    echo "<div class='question'>";
    echo "<p>" . ($qi+1) . ". " . h($q['text']) . "</p>";

    foreach ($answer_order as $pos => $orig_ans_idx) {
        $ans = h($q['answers'][$orig_ans_idx]);
        echo "<label><input type='radio' name='q$qi' value='$pos'> $ans</label>";
    }
    echo "</div>";
}

$total_shown = count($_quiz_state['questions']);
?>
<br>
<input type="submit" name="submit_quiz" value="Submit Quiz" onclick="
  var total = <?php echo (int)$total_shown; ?>;
  var answered = 0;
  for (var i = 0; i < total; i++) {
    if (document.querySelector('input[name=&quot;q'+i+'&quot;]:checked')) answered++;
  }
  if (answered < total) {
    return confirm((total - answered) + ' question(s) unanswered. Submit anyway?');
  }
">
</form>
<?php include __DIR__ . '/nav_bottom.php'; ?>
</body></html>
