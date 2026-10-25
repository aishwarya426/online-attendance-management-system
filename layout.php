<?php

declare(strict_types=1);

/** Navigation links per role (file => label). */
function nav_items(string $role): array
{
    return match ($role) {
        'admin' => [
            'index.php' => 'Overview', 'students.php' => 'Students', 'teachers.php' => 'Teachers',
            'courses.php' => 'Courses', 'assign_teacher.php' => 'Assign Teacher', 'enroll.php' => 'Enroll Students',
            'attendance.php' => 'Attendance', 'semesters.php' => 'Semesters', 'account.php' => 'Account',
            '../logout.php' => 'Logout',
        ],
        'teacher' => [
            'index.php' => 'My Courses', 'report.php' => 'Attendance Reports', 'students.php' => 'My Students',
            'account.php' => 'My Profile', '../logout.php' => 'Logout',
        ],
        default => [
            'index.php' => 'Home', 'report.php' => 'My Attendance', 'account.php' => 'My Profile',
            '../logout.php' => 'Logout',
        ],
    };
}

/**
 * UI only: which nav entry a page belongs to, so add/detail pages keep their
 * parent section highlighted in the navigation bar.
 */
function nav_current_file(string $role): string
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $map = $role === 'admin'
        ? [
            'student.php' => 'students.php', 'student_add.php' => 'students.php',
            'teacher.php' => 'teachers.php', 'teacher_add.php' => 'teachers.php',
            'course.php'  => 'courses.php',  'course_add.php'  => 'courses.php',
            'signup.php'  => 'account.php',
        ]
        : ['course.php' => 'index.php'];
    return $map[$script] ?? $script;
}

/** Print the page header, navigation, flash messages and open the content area. */
function page_start(string $role, string $heading = ''): void
{
    $flash   = take_flash();
    $current = nav_current_file($role);
    $cssVer  = (int) @filemtime(__DIR__ . '/css/main.css');
    echo '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Online Attendance Management System 1.0</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap-theme.min.css">
  <link rel="stylesheet" href="../css/main.css?v=' . $cssVer . '">
</head>
<body class="role-' . e($role) . '">
<header class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg></span>
      <h1>Online Attendance Management System <span class="ver">1.0</span></h1>
    </div>
    <span class="role-pill">' . e(ucfirst($role)) . '</span>
  </div>
  <nav class="navbar compact"><div class="navbar-inner">';
    foreach (nav_items($role) as $file => $label) {
        $classes = [];
        if (basename($file) === $current) {
            $classes[] = 'active';
        }
        if (basename($file) === 'logout.php') {
            $classes[] = 'nav-logout';
        }
        echo '<a href="' . e($file) . '"' . ($classes !== [] ? ' class="' . implode(' ', $classes) . '"' : '') . '>' . e($label) . '</a>';
    }
    echo '</div></nav>
</header>

<main class="page">
  <div class="content">';

    foreach ($flash['messages'] as [$type, $message]) {
        echo '<p class="' . ($type === 'error' ? 'msg-err' : 'msg-ok') . '">' . e($message) . '</p>';
    }
    if ($flash['creds'] !== null) {
        $c = $flash['creds'];
        echo '<div class="cred-box"><strong>' . e($c['title']) . '</strong><br>'
            . 'ID: <strong>' . e($c['code']) . '</strong><br>'
            . 'Username: <strong>' . e($c['username']) . '</strong><br>'
            . 'Temporary password: <strong>' . e($c['password']) . '</strong><br>'
            . '<small>Shown only once. Give these to the user; they can change the password under Account / My Profile.</small></div>';
    }
    if ($heading !== '') {
        echo '<h3>' . e($heading) . '</h3>';
    }
}

function page_end(): void
{
    echo "\n  </div>\n</main>\n</body>\n</html>\n";
}

/** Bootstrap horizontal-form text input row. */
function form_field(string $label, string $name, string $type = 'text', string $value = '', string $extra = ''): void
{
    echo '<div class="form-group"><label for="' . e($name) . '" class="col-sm-3 control-label">' . e($label) . '</label>'
        . '<div class="col-sm-7"><input type="' . e($type) . '" name="' . e($name) . '" id="' . e($name)
        . '" class="form-control" value="' . e($value) . '" ' . $extra . '></div></div>';
}

/** Bootstrap horizontal-form select row. $options is value => label. */
function form_select(string $label, string $name, array $options, string $selected = '', string $extra = ''): void
{
    echo '<div class="form-group"><label for="' . e($name) . '" class="col-sm-3 control-label">' . e($label) . '</label>'
        . '<div class="col-sm-7"><select name="' . e($name) . '" id="' . e($name) . '" class="form-control" ' . $extra . '>';
    foreach ($options as $value => $text) {
        echo '<option value="' . e($value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . e($text) . '</option>';
    }
    echo '</select></div></div>';
}

function form_submit(string $label, string $name = 'save'): void
{
    echo '<div class="form-group"><div class="col-sm-offset-3 col-sm-7">'
        . '<button type="submit" name="' . e($name) . '" value="1" class="btn btn-primary">' . e($label) . '</button></div></div>';
}

/** A one-button POST form (used for Remove / Activate / Reset links). */
function action_button(string $action, array $fields, string $label, string $class = 'btn-default', string $confirm = ''): string
{
    $html = '<form method="post" class="inline-form"' . ($confirm !== '' ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>'
        . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
    return $html . '<button type="submit" class="btn btn-xs ' . e($class) . '">' . e($label) . '</button></form>';
}

function status_badge(bool $active): string
{
    return $active ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>';
}

/** Password-change form used on every role's account page. */
function password_form(): void
{
    echo '<h4>Change password</h4><form method="post" class="form-horizontal" style="max-width:600px;margin:0 auto">'
        . csrf_field() . '<input type="hidden" name="action" value="password">';
    form_field('Current password', 'current_password', 'password', '', 'autocomplete="current-password"');
    form_field('New password', 'new_password', 'password', '', 'autocomplete="new-password"');
    form_field('Confirm new password', 'confirm_password', 'password', '', 'autocomplete="new-password"');
    form_submit('Change Password', 'change');
    echo '</form>';
}

/**
 * Course attendance report (shared by admin and teacher pages).
 *
 * @param string|null $dateLink if set, each date links to "$dateLink&date=YYYY-MM-DD"
 */
function render_course_report(array $course, array $students, array $sessions, ?string $dateLink = null): void
{
    $sumPresent = 0;
    $sumTotal   = 0;
    $low        = 0;
    foreach ($students as $s) {
        $sumPresent += (int) $s['present'];
        $sumTotal   += (int) $s['total'];
        $low        += is_low((int) $s['present'], (int) $s['total']) ? 1 : 0;
    }

    echo '<p><strong>' . e($course['course_code']) . ' - ' . e($course['course_name']) . '</strong><br>'
        . e(semester_label($course)) . '</p>';
    echo '<p>Students: <strong>' . count($students) . '</strong> &middot; Classes held: <strong>' . count($sessions)
        . '</strong> &middot; Overall attendance: <strong>' . e(pct_text($sumPresent, $sumTotal))
        . '</strong> &middot; Below ' . LOW_ATTENDANCE_PERCENT . '%: <strong class="' . ($low > 0 ? 'low' : '') . '">' . $low . '</strong></p>';

    echo '<h4>Student-wise</h4><table class="table table-striped"><thead><tr>
            <th>Student ID</th><th>Student</th><th>Present</th><th>Absent</th><th>Percentage</th></tr></thead><tbody>';
    foreach ($students as $s) {
        $p = (int) $s['present'];
        $t = (int) $s['total'];
        echo '<tr><td>' . e($s['student_code']) . '</td><td>' . e($s['st_name']) . '</td><td>' . $p . '</td><td>' . ($t - $p)
            . '</td><td class="' . (is_low($p, $t) ? 'low' : '') . '">' . e(pct_text($p, $t)) . (is_low($p, $t) ? ' (low)' : '') . '</td></tr>';
    }
    if ($students === []) {
        echo '<tr><td colspan="5">No students are enrolled in this course.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h4>Date-wise</h4><table class="table table-striped"><thead><tr>
            <th>Date</th><th>Present</th><th>Absent</th><th>Percentage</th></tr></thead><tbody>';
    foreach ($sessions as $d) {
        $p = (int) $d['present'];
        $t = (int) $d['total'];
        $date = display_date((string) $d['stat_date']);
        $cell = $dateLink !== null ? '<a href="' . e($dateLink . '&date=' . $d['stat_date']) . '">' . e($date) . '</a>' : e($date);
        echo '<tr><td>' . $cell . '</td><td>' . $p . '</td><td>' . ($t - $p) . '</td><td>' . e(pct_text($p, $t)) . '</td></tr>';
    }
    if ($sessions === []) {
        echo '<tr><td colspan="4">No attendance has been recorded yet.</td></tr>';
    }
    echo '</tbody></table>';
}

/** Compact select for inline forms. $options is value => label. */
function form_select_inline(string $name, array $options): void
{
    echo '<select name="' . e($name) . '" class="form-control">';
    foreach ($options as $value => $text) {
        echo '<option value="' . e($value) . '">' . e($text) . '</option>';
    }
    echo '</select> ';
}
