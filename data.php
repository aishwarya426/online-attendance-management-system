<?php

declare(strict_types=1);

// ============================================================================
// Authentication / authorization
// ============================================================================

/** Load a login account together with its student/teacher profile (null if it doesn't exist). */
function load_account(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT a.id, a.username, a.type AS role, a.is_active, a.fname, a.email, a.phone, a.st_id, a.tc_id,
                s.student_code, s.st_name, s.is_active AS st_active,
                t.teacher_code, t.tc_name, t.is_active AS tc_active
         FROM admininfo a
         LEFT JOIN students s ON s.st_id = a.st_id
         LEFT JOIN teachers t ON t.tc_id = a.tc_id
         WHERE a.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }
    $row['id']    = (int) $row['id'];
    $row['st_id'] = $row['st_id'] === null ? null : (int) $row['st_id'];
    $row['tc_id'] = $row['tc_id'] === null ? null : (int) $row['tc_id'];
    $row['name']  = match ($row['role']) {
        'student' => (string) $row['st_name'],
        'teacher' => (string) $row['tc_name'],
        default   => (string) $row['fname'],
    };
    $row['code'] = match ($row['role']) {
        'student' => (string) $row['student_code'],
        'teacher' => (string) $row['teacher_code'],
        default   => (string) $row['username'],
    };
    return $row;
}

/** Why this account may not use the system, or null if it is fine. */
function account_problem(array $a): ?string
{
    if ((int) $a['is_active'] !== 1) {
        return 'This account has been deactivated. Contact the administrator.';
    }
    if ($a['role'] === 'student') {
        if ($a['st_id'] === null) {
            return 'This login is not linked to a student profile. Contact the administrator.';
        }
        if ((int) $a['st_active'] !== 1) {
            return 'This student profile has been deactivated. Contact the administrator.';
        }
    }
    if ($a['role'] === 'teacher') {
        if ($a['tc_id'] === null) {
            return 'This login is not linked to a teacher profile. Contact the administrator.';
        }
        if ((int) $a['tc_active'] !== 1) {
            return 'This teacher profile has been deactivated. Contact the administrator.';
        }
    }
    return null;
}

/**
 * Every protected page calls this first. Checks (on each request, against the database)
 * that the user is logged in, still active, and has the required role.
 */
function require_login(PDO $pdo, string $role, string $loginUrl = '../index.php'): array
{
    start_session();
    $id = $_SESSION['uid'] ?? null;
    $account = is_int($id) ? load_account($pdo, $id) : null;

    if ($account === null || ($_SESSION['role'] ?? null) !== $role || $account['role'] !== $role) {
        redirect($loginUrl);
    }
    if (account_problem($account) !== null) {
        $_SESSION = [];
        redirect($loginUrl);
    }
    return $account;
}

// ============================================================================
// Semesters
// ============================================================================

function current_semester(PDO $pdo): ?array
{
    $row = $pdo->query('SELECT * FROM semesters WHERE is_current = 1 ORDER BY sem_id DESC LIMIT 1')->fetch();
    return $row === false ? null : $row;
}

function semester_label(array $s): string
{
    return $s['academic_year'] === 'Legacy'
        ? 'Legacy (before v2 upgrade)'
        : 'Semester ' . $s['semester_no'] . ' (' . $s['academic_year'] . ')';
}

function all_semesters(PDO $pdo): array
{
    return $pdo->query(
        "SELECT * FROM semesters ORDER BY (academic_year = 'Legacy'), academic_year DESC, semester_no DESC"
    )->fetchAll();
}

/** [sem_id => label] for building <select> options. */
function semester_options(PDO $pdo): array
{
    $out = [];
    foreach (all_semesters($pdo) as $s) {
        $out[(int) $s['sem_id']] = semester_label($s) . ((int) $s['is_current'] === 1 ? ' - current' : '');
    }
    return $out;
}

// ============================================================================
// Unique institutional IDs and login accounts
// ============================================================================

/**
 * Next free institutional code, e.g. STU0007. The id_counters row is locked, so
 * two admins creating records at once can never receive the same number.
 * Must be called inside a transaction. $table/$column are internal literals only.
 */
function next_code(PDO $pdo, string $prefix, string $table, string $column): string
{
    $sel = $pdo->prepare('SELECT next_no FROM id_counters WHERE prefix = ? FOR UPDATE');
    $sel->execute([$prefix]);
    $n = $sel->fetchColumn();
    if ($n === false) {
        $pdo->prepare('INSERT INTO id_counters (prefix, next_no) VALUES (?, 1)')->execute([$prefix]);
        $n = 1;
    }
    $n = (int) $n;

    $exists = $pdo->prepare("SELECT 1 FROM $table WHERE $column = ?");
    do {
        $code = sprintf('%s%04d', $prefix, $n);
        $exists->execute([$code]);
        $taken = $exists->fetchColumn() !== false;
        $exists->closeCursor();
        $n++;
    } while ($taken);

    $pdo->prepare('UPDATE id_counters SET next_no = ? WHERE prefix = ?')->execute([$n, $prefix]);
    return $code;
}

/**
 * Insert a login row. A student/teacher login is tied to exactly one profile.
 *
 * @throws InvalidArgumentException with a message that is safe to show the user
 */
function create_account(
    PDO $pdo,
    string $username,
    string $password,
    string $email,
    string $fullName,
    string $phone,
    string $role,
    ?int $stId = null,
    ?int $tcId = null
): void {
    if (!in_array($role, ['student', 'teacher', 'admin'], true)) {
        throw new InvalidArgumentException('Invalid role selected.');
    }
    $check = $pdo->prepare('SELECT 1 FROM admininfo WHERE username = ?');
    $check->execute([$username]);
    if ($check->fetchColumn() !== false) {
        throw new InvalidArgumentException('That username is already taken.');
    }
    try {
        $pdo->prepare(
            'INSERT INTO admininfo (username, password, email, fname, phone, type, st_id, tc_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, $fullName, $phone, $role, $stId, $tcId]);
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            throw new InvalidArgumentException('That username is taken or this profile already has a login.');
        }
        throw $ex;
    }
}

/** Run $work in a transaction; roll back and rethrow on any failure. */
function in_transaction(PDO $pdo, callable $work): mixed
{
    $pdo->beginTransaction();
    try {
        $result = $work();
        $pdo->commit();
        return $result;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Validate the Add/Edit Student form. @throws InvalidArgumentException */
function student_input(): array
{
    return profile_input(
        ['name' => 'Full name', 'email' => 'Email', 'dept' => 'Department', 'batch' => 'Batch', 'sem' => 'Semester'],
        ['phone']
    );
}

/** Validate the Add/Edit Teacher form. @throws InvalidArgumentException */
function teacher_input(): array
{
    return profile_input(
        ['name' => 'Full name', 'email' => 'Email', 'dept' => 'Department'],
        ['phone', 'designation']
    );
}

function profile_input(array $required, array $optional): array
{
    $out = [];
    foreach ($required as $key => $label) {
        $out[$key] = post($key);
        if ($out[$key] === '') {
            throw new InvalidArgumentException("$label can't be empty.");
        }
    }
    foreach ($optional as $key) {
        $out[$key] = post($key);
    }
    if (filter_var($out['email'], FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }
    return $out;
}

/**
 * Create a student profile (with a generated STUxxxx ID) and its login in one transaction.
 *
 * @return array{st_id:int, code:string, username:string, password:string}
 */
function create_student(PDO $pdo, array $f): array
{
    return in_transaction($pdo, function () use ($pdo, $f): array {
        $code = next_code($pdo, 'STU', 'students', 'student_code');
        $pdo->prepare(
            'INSERT INTO students (student_code, st_name, st_dept, st_batch, st_sem, st_email, st_phone)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$code, $f['name'], $f['dept'], $f['batch'], $f['sem'], $f['email'], $f['phone'] ?: null]);
        $stId = (int) $pdo->lastInsertId();

        $password = generate_password();
        create_account($pdo, $code, $password, $f['email'], $f['name'], $f['phone'], 'student', $stId, null);
        return ['st_id' => $stId, 'code' => $code, 'username' => $code, 'password' => $password];
    });
}

/**
 * Create a teacher profile (with a generated TCHxxxx ID) and its login in one transaction.
 *
 * @return array{tc_id:int, code:string, username:string, password:string}
 */
function create_teacher(PDO $pdo, array $f): array
{
    return in_transaction($pdo, function () use ($pdo, $f): array {
        $code = next_code($pdo, 'TCH', 'teachers', 'teacher_code');
        $pdo->prepare(
            'INSERT INTO teachers (teacher_code, tc_name, tc_dept, tc_email, tc_phone, tc_designation)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$code, $f['name'], $f['dept'], $f['email'], $f['phone'] ?: null, $f['designation'] ?: null]);
        $tcId = (int) $pdo->lastInsertId();

        $password = generate_password();
        create_account($pdo, $code, $password, $f['email'], $f['name'], $f['phone'], 'teacher', null, $tcId);
        return ['tc_id' => $tcId, 'code' => $code, 'username' => $code, 'password' => $password];
    });
}

/**
 * Reset the password of a profile's login, or create the login if the profile has none
 * (e.g. profiles that existed before v2). Username = institutional ID for new logins.
 *
 * @return array{username:string, password:string, created:bool, code:string}
 */
function issue_login(PDO $pdo, string $role, int $profileId): array
{
    $isStudent = $role === 'student';
    $sql = $isStudent
        ? 'SELECT student_code AS code, st_name AS name, st_email AS email, COALESCE(st_phone, \'\') AS phone FROM students WHERE st_id = ?'
        : 'SELECT teacher_code AS code, tc_name AS name, tc_email AS email, COALESCE(tc_phone, \'\') AS phone FROM teachers WHERE tc_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$profileId]);
    $p = $stmt->fetch();
    if ($p === false) {
        throw new InvalidArgumentException('Profile not found.');
    }

    $col = $isStudent ? 'st_id' : 'tc_id';
    $acc = $pdo->prepare("SELECT id, username FROM admininfo WHERE $col = ?");
    $acc->execute([$profileId]);
    $account = $acc->fetch();

    $password = generate_password();
    if ($account !== false) {
        $pdo->prepare('UPDATE admininfo SET password = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $account['id']]);
        return ['username' => $account['username'], 'password' => $password, 'created' => false, 'code' => $p['code']];
    }

    create_account(
        $pdo, $p['code'], $password, $p['email'], $p['name'], $p['phone'], $role,
        $isStudent ? $profileId : null, $isStudent ? null : $profileId
    );
    return ['username' => $p['code'], 'password' => $password, 'created' => true, 'code' => $p['code']];
}

/** Change the logged-in user's own password. @throws InvalidArgumentException */
function change_own_password(PDO $pdo, int $accountId): void
{
    $current = post('current_password', false);
    $new     = post('new_password', false);
    $confirm = post('confirm_password', false);

    $stmt = $pdo->prepare('SELECT password FROM admininfo WHERE id = ?');
    $stmt->execute([$accountId]);
    $stored = $stmt->fetchColumn();
    if ($stored === false || !password_matches($current, (string) $stored)) {
        throw new InvalidArgumentException('Your current password is incorrect.');
    }
    if (strlen($new) < 8) {
        throw new InvalidArgumentException('The new password must be at least 8 characters.');
    }
    if ($new !== $confirm) {
        throw new InvalidArgumentException('The new passwords do not match.');
    }
    if ($new === $current) {
        throw new InvalidArgumentException('Choose a password different from the current one.');
    }
    $pdo->prepare('UPDATE admininfo SET password = ? WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), $accountId]);
}

// ============================================================================
// Courses, assignments, enrollment, attendance
// ============================================================================

function find_course(PDO $pdo, int $courseId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT c.*, sm.academic_year, sm.semester_no, sm.is_current
         FROM courses c JOIN semesters sm ON sm.sem_id = c.sem_id WHERE c.course_id = ?'
    );
    $stmt->execute([$courseId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** The course, but only if this teacher is assigned to it (the server-side authorization check). */
function teacher_course(PDO $pdo, int $tcId, int $courseId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT c.*, sm.academic_year, sm.semester_no, sm.is_current
         FROM teacher_courses tc
         JOIN courses c ON c.course_id = tc.course_id
         JOIN semesters sm ON sm.sem_id = c.sem_id
         WHERE tc.tc_id = ? AND tc.course_id = ?'
    );
    $stmt->execute([$tcId, $courseId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Active students enrolled in a course. */
function course_roster(PDO $pdo, int $courseId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.st_id, s.student_code, s.st_name, s.st_dept, s.st_batch
         FROM student_courses sc JOIN students s ON s.st_id = sc.st_id
         WHERE sc.course_id = ? AND s.is_active = 1
         ORDER BY s.student_code'
    );
    $stmt->execute([$courseId]);
    return $stmt->fetchAll();
}

/** Per-student totals for a course (enrolled students plus anyone with recorded attendance). */
function course_report(PDO $pdo, int $courseId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.st_id, s.student_code, s.st_name,
                COUNT(a.id) AS total,
                COALESCE(SUM(a.st_status = \'Present\'), 0) AS present
         FROM students s
         LEFT JOIN attendance a ON a.stat_id = s.st_id AND a.course_id = ?
         WHERE s.st_id IN (SELECT st_id FROM student_courses WHERE course_id = ?)
            OR s.st_id IN (SELECT stat_id FROM attendance WHERE course_id = ?)
         GROUP BY s.st_id, s.student_code, s.st_name
         ORDER BY s.student_code'
    );
    $stmt->execute([$courseId, $courseId, $courseId]);
    return $stmt->fetchAll();
}

/** Date-wise totals for a course, newest first. */
function course_sessions(PDO $pdo, int $courseId): array
{
    $stmt = $pdo->prepare(
        'SELECT stat_date, COUNT(*) AS total, SUM(st_status = \'Present\') AS present
         FROM attendance WHERE course_id = ? GROUP BY stat_date ORDER BY stat_date DESC'
    );
    $stmt->execute([$courseId]);
    return $stmt->fetchAll();
}

/** Courses one teacher is assigned to, for a semester (null = all semesters). */
function teacher_courses_list(PDO $pdo, int $tcId, ?int $semId): array
{
    $sql = 'SELECT c.course_id, c.course_code, c.course_name, c.credits, c.status, c.sem_id,
                   sm.academic_year, sm.semester_no, sm.is_current,
                   (SELECT COUNT(*) FROM student_courses sc JOIN students s ON s.st_id = sc.st_id
                     WHERE sc.course_id = c.course_id AND s.is_active = 1) AS students
            FROM teacher_courses tc
            JOIN courses c ON c.course_id = tc.course_id
            JOIN semesters sm ON sm.sem_id = c.sem_id
            WHERE tc.tc_id = ?' . ($semId !== null ? ' AND c.sem_id = ?' : '') . '
            ORDER BY sm.sem_id DESC, c.course_code';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($semId !== null ? [$tcId, $semId] : [$tcId]);
    return $stmt->fetchAll();
}

/** A student's courses with attendance totals, for one semester. */
function student_course_summary(PDO $pdo, int $stId, int $semId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.course_id, c.course_code, c.course_name, c.credits,
                (SELECT COUNT(*) FROM attendance a WHERE a.stat_id = sc.st_id AND a.course_id = c.course_id) AS total,
                (SELECT COUNT(*) FROM attendance a WHERE a.stat_id = sc.st_id AND a.course_id = c.course_id
                   AND a.st_status = \'Present\') AS present
         FROM student_courses sc JOIN courses c ON c.course_id = sc.course_id
         WHERE sc.st_id = ? AND c.sem_id = ?
         ORDER BY c.course_code'
    );
    $stmt->execute([$stId, $semId]);
    return $stmt->fetchAll();
}
