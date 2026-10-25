<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

/** Attendance below this percentage is flagged as low. */
const LOW_ATTENDANCE_PERCENT = 75;

/** Teachers can mark or correct attendance for today and this many days back. */
const ATTENDANCE_EDIT_DAYS = 14;

/** Escape a value for safe output inside HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Read a string from $_POST ('' when missing or not a string). Passwords must not be trimmed. */
function post(string $key, bool $trim = true): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return $trim ? trim($value) : $value;
}

/** Positive integer from $_GET, or null. */
function get_int(string $key): ?int
{
    $value = $_GET[$key] ?? null;
    return is_string($value) && ctype_digit($value) ? (int) $value : null;
}

/** Positive integer from $_POST, or null. */
function post_int(string $key): ?int
{
    $value = $_POST[$key] ?? null;
    return is_string($value) && ctype_digit($value) ? (int) $value : null;
}

/** List of positive integers from a $_POST array field (e.g. st_id[]). */
function post_int_list(string $key): array
{
    $out = [];
    $values = $_POST[$key] ?? [];
    foreach (is_array($values) ? $values : [] as $v) {
        if (is_string($v) && ctype_digit($v)) {
            $out[(int) $v] = (int) $v;
        }
    }
    return array_values($out);
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Only allow redirects back to a simple relative page like "student.php?id=3". */
function safe_back(string $back, string $default): string
{
    return preg_match('/^[a-z_]+\.php(\?[a-z_]+=[0-9]+(&[a-z_]+=[0-9]+)*)?$/', $back) === 1 ? $back : $default;
}

/** Stop with a "not allowed" page. */
function deny(string $message = 'You are not allowed to access this page.', int $status = 403): never
{
    http_response_code($status);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Access denied</title></head>'
        . '<body style="font-family:Arial,sans-serif;text-align:center;margin-top:80px">'
        . '<h2>' . e($message) . '</h2><p><a href="index.php">Back to dashboard</a></p></body></html>';
    exit;
}

// ---- CSRF ---------------------------------------------------------------

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Hidden input to put inside every logged-in POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of POST handling. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Invalid or expired form. Go back, refresh the page and try again.');
    }
}

// ---- Flash messages (shown once on the next page) -----------------------

function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][] = [$type, $message];
}

/** Show a generated login to the admin once. */
function flash_credentials(string $title, string $code, string $username, string $password): void
{
    start_session();
    $_SESSION['creds'] = ['title' => $title, 'code' => $code, 'username' => $username, 'password' => $password];
}

function take_flash(): array
{
    start_session();
    $out = ['messages' => $_SESSION['flash'] ?? [], 'creds' => $_SESSION['creds'] ?? null];
    unset($_SESSION['flash'], $_SESSION['creds']);
    return $out;
}

// ---- Attendance maths ---------------------------------------------------

function pct_value(int $present, int $total): float
{
    return $total > 0 ? round($present * 100 / $total, 1) : 0.0;
}

/** "90%", "88.9%" or "n/a" when there were no classes. */
function pct_text(int $present, int $total): string
{
    if ($total === 0) {
        return 'n/a';
    }
    return rtrim(rtrim(number_format(pct_value($present, $total), 1, '.', ''), '0'), '.') . '%';
}

function is_low(int $present, int $total): bool
{
    return $total > 0 && pct_value($present, $total) < LOW_ATTENDANCE_PERCENT;
}

function valid_date(string $date): bool
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $dt !== false && $dt->format('Y-m-d') === $date;
}

function display_date(string $isoDate): string
{
    return date('d/m/Y', strtotime($isoDate));
}

// ---- Passwords ----------------------------------------------------------

/** True when the stored value is an old plaintext password rather than a password_hash() hash. */
function password_is_legacy(string $stored): bool
{
    return password_get_info($stored)['algo'] === null;
}

function password_matches(string $plain, string $stored): bool
{
    if (password_is_legacy($stored)) {
        return hash_equals($stored, $plain);
    }
    return password_verify($plain, $stored);
}

/** Random temporary password (no look-alike characters). */
function generate_password(int $length = 10): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}
