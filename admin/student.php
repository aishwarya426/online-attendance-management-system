<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'admin');
$sem = current_semester($pdo);

$id = get_int('id');
$stmt = $pdo->prepare('SELECT * FROM students WHERE st_id = ?');
$stmt->execute([$id ?? 0]);
$student = $stmt->fetch();
if ($student === false) {
    deny('Student not found.', 404);
}
$self = 'student.php?id=' . (int) $student['st_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $action = post('action');
        if ($action === 'update') {
            $f = student_input();
            in_transaction($pdo, function () use ($pdo, $f, $student): void {
                $pdo->prepare('UPDATE students SET st_name = ?, st_dept = ?, st_batch = ?, st_sem = ?, st_email = ?, st_phone = ? WHERE st_id = ?')
                    ->execute([$f['name'], $f['dept'], $f['batch'], $f['sem'], $f['email'], $f['phone'] ?: null, $student['st_id']]);
                // keep the linked login's contact details in step (no second account is created)
                $pdo->prepare('UPDATE admininfo SET fname = ?, email = ?, phone = ? WHERE st_id = ?')
                    ->execute([$f['name'], $f['email'], $f['phone'], $student['st_id']]);
            });
            flash('ok', 'Student updated.');
        } elseif ($action === 'toggle') {
            $new = (int) $student['is_active'] === 1 ? 0 : 1;
            in_transaction($pdo, function () use ($pdo, $new, $student): void {
                $pdo->prepare('UPDATE students SET is_active = ? WHERE st_id = ?')->execute([$new, $student['st_id']]);
                $pdo->prepare('UPDATE admininfo SET is_active = ? WHERE st_id = ?')->execute([$new, $student['st_id']]);
            });
            flash('ok', $new === 1 ? 'Student activated.' : 'Student deactivated. Their attendance history is kept.');
        } elseif ($action === 'login') {
            $r = issue_login($pdo, 'student', (int) $student['st_id']);
            flash('ok', $r['created'] ? 'Login created.' : 'Password reset.');
            flash_credentials($r['created'] ? 'Student login created' : 'Student password reset', $r['code'], $r['username'], $r['password']);
        }
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect($self);
}

$acc = $pdo->prepare('SELECT username, is_active FROM admininfo WHERE st_id = ?');
$acc->execute([$student['st_id']]);
$account = $acc->fetch();

$enr = $pdo->prepare(
    'SELECT c.course_id, c.course_code, c.course_name, c.status, sm.academic_year, sm.semester_no
     FROM student_courses sc JOIN courses c ON c.course_id = sc.course_id JOIN semesters sm ON sm.sem_id = c.sem_id
     WHERE sc.st_id = ? ORDER BY sm.sem_id DESC, c.course_code'
);
$enr->execute([$student['st_id']]);
$enrollments = $enr->fetchAll();

$available = [];
if ($sem !== null) {
    $av = $pdo->prepare(
        'SELECT course_id, course_code, course_name FROM courses
         WHERE sem_id = ? AND status = \'active\'
           AND course_id NOT IN (SELECT course_id FROM student_courses WHERE st_id = ?)
         ORDER BY course_code'
    );
    $av->execute([$sem['sem_id'], $student['st_id']]);
    foreach ($av->fetchAll() as $c) {
        $available[$c['course_id']] = $c['course_code'] . ' - ' . $c['course_name'];
    }
}

page_start('admin', $student['student_code'] . ' - ' . $student['st_name']);
?>
<p><a href="students.php">&larr; All students</a></p>
<p>Status: <?= status_badge((int) $student['is_active'] === 1) ?>
  &nbsp; Login: <?= $account === false ? '<span class="low">none yet</span>' : 'username <strong>' . e($account['username']) . '</strong>' ?></p>
<p>
  <?= action_button('toggle', [], (int) $student['is_active'] === 1 ? 'Deactivate' : 'Activate', 'btn-warning',
      (int) $student['is_active'] === 1 ? 'Deactivate this student? They will no longer be able to log in or appear on class lists.' : '') ?>
  <?= action_button('login', [], $account === false ? 'Create login' : 'Reset password', 'btn-default',
      $account === false ? '' : 'Generate a new temporary password for this student?') ?>
</p>

<h4>Profile</h4>
<form method="post" class="form-horizontal" style="max-width:600px;margin:0 auto">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="update">
  <?php form_field('Full name', 'name', 'text', (string) $student['st_name']); ?>
  <?php form_field('Email', 'email', 'email', (string) $student['st_email']); ?>
  <?php form_field('Phone', 'phone', 'text', (string) $student['st_phone']); ?>
  <?php form_field('Department', 'dept', 'text', (string) $student['st_dept']); ?>
  <?php form_field('Batch', 'batch', 'text', (string) $student['st_batch']); ?>
  <?php form_field('Semester', 'sem', 'text', (string) $student['st_sem']); ?>
  <?php form_submit('Save Changes', 'save'); ?>
</form>

<h4 style="margin-top:30px">Enrolled courses</h4>
<table class="table table-striped">
  <thead><tr><th>Course</th><th>Semester</th><th>Status</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($enrollments as $c): ?>
      <tr>
        <td><a href="course.php?id=<?= e($c['course_id']) ?>"><?= e($c['course_code']) ?></a> - <?= e($c['course_name']) ?></td>
        <td><?= e(semester_label($c)) ?></td>
        <td><?= status_badge($c['status'] === 'active') ?></td>
        <td><?= action_button('remove', ['course_id' => $c['course_id'], 'st_id' => $student['st_id'], 'back' => $self], 'Remove', 'btn-danger', 'Remove this enrollment? Attendance already recorded is kept.') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($enrollments === []): ?><tr><td colspan="4">Not enrolled in any course.</td></tr><?php endif; ?>
  </tbody>
</table>

<?php if ((int) $student['is_active'] === 1 && $available !== []): ?>
  <form method="post" action="enroll.php" class="form-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="enroll">
    <input type="hidden" name="st_id[]" value="<?= e($student['st_id']) ?>">
    <input type="hidden" name="back" value="<?= e($self) ?>">
    <?php form_select_inline('course_id', $available); ?>
    <button class="btn btn-primary" type="submit">Enroll Student</button>
  </form>
<?php endif; ?>
<?php page_end();
