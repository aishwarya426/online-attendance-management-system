<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'admin');
$sem = current_semester($pdo);

$id = get_int('id');
$stmt = $pdo->prepare('SELECT * FROM teachers WHERE tc_id = ?');
$stmt->execute([$id ?? 0]);
$teacher = $stmt->fetch();
if ($teacher === false) {
    deny('Teacher not found.', 404);
}
$self = 'teacher.php?id=' . (int) $teacher['tc_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $action = post('action');
        if ($action === 'update') {
            $f = teacher_input();
            in_transaction($pdo, function () use ($pdo, $f, $teacher): void {
                $pdo->prepare('UPDATE teachers SET tc_name = ?, tc_dept = ?, tc_email = ?, tc_phone = ?, tc_designation = ? WHERE tc_id = ?')
                    ->execute([$f['name'], $f['dept'], $f['email'], $f['phone'] ?: null, $f['designation'] ?: null, $teacher['tc_id']]);
                $pdo->prepare('UPDATE admininfo SET fname = ?, email = ?, phone = ? WHERE tc_id = ?')
                    ->execute([$f['name'], $f['email'], $f['phone'], $teacher['tc_id']]);
            });
            flash('ok', 'Teacher updated.');
        } elseif ($action === 'toggle') {
            $new = (int) $teacher['is_active'] === 1 ? 0 : 1;
            in_transaction($pdo, function () use ($pdo, $new, $teacher): void {
                $pdo->prepare('UPDATE teachers SET is_active = ? WHERE tc_id = ?')->execute([$new, $teacher['tc_id']]);
                $pdo->prepare('UPDATE admininfo SET is_active = ? WHERE tc_id = ?')->execute([$new, $teacher['tc_id']]);
            });
            flash('ok', $new === 1 ? 'Teacher activated.' : 'Teacher deactivated. Their attendance history is kept.');
        } elseif ($action === 'login') {
            $r = issue_login($pdo, 'teacher', (int) $teacher['tc_id']);
            flash('ok', $r['created'] ? 'Login created.' : 'Password reset.');
            flash_credentials($r['created'] ? 'Teacher login created' : 'Teacher password reset', $r['code'], $r['username'], $r['password']);
        }
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect($self);
}

$acc = $pdo->prepare('SELECT username FROM admininfo WHERE tc_id = ?');
$acc->execute([$teacher['tc_id']]);
$account = $acc->fetch();

$courses = teacher_courses_list($pdo, (int) $teacher['tc_id'], null);

$available = [];
if ($sem !== null) {
    $av = $pdo->prepare(
        'SELECT course_id, course_code, course_name FROM courses
         WHERE sem_id = ? AND status = \'active\'
           AND course_id NOT IN (SELECT course_id FROM teacher_courses WHERE tc_id = ?)
         ORDER BY course_code'
    );
    $av->execute([$sem['sem_id'], $teacher['tc_id']]);
    foreach ($av->fetchAll() as $c) {
        $available[$c['course_id']] = $c['course_code'] . ' - ' . $c['course_name'];
    }
}

$act = $pdo->prepare('SELECT COUNT(DISTINCT course_id, stat_date) AS sessions, MAX(stat_date) AS last_date FROM attendance WHERE teacher_id = ?');
$act->execute([$teacher['tc_id']]);
$activity = $act->fetch();

page_start('admin', $teacher['teacher_code'] . ' - ' . $teacher['tc_name']);
?>
<p><a href="teachers.php">&larr; All teachers</a></p>
<p>Status: <?= status_badge((int) $teacher['is_active'] === 1) ?>
  &nbsp; Login: <?= $account === false ? '<span class="low">none yet</span>' : 'username <strong>' . e($account['username']) . '</strong>' ?>
  &nbsp; Attendance sessions marked: <strong><?= e($activity['sessions']) ?></strong>
  <?= $activity['last_date'] !== null ? '(last: ' . e(display_date((string) $activity['last_date'])) . ')' : '' ?></p>
<p>
  <?= action_button('toggle', [], (int) $teacher['is_active'] === 1 ? 'Deactivate' : 'Activate', 'btn-warning',
      (int) $teacher['is_active'] === 1 ? 'Deactivate this teacher? They will no longer be able to log in.' : '') ?>
  <?= action_button('login', [], $account === false ? 'Create login' : 'Reset password', 'btn-default',
      $account === false ? '' : 'Generate a new temporary password for this teacher?') ?>
</p>

<h4>Profile</h4>
<form method="post" class="form-horizontal" style="max-width:600px;margin:0 auto">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="update">
  <?php form_field('Full name', 'name', 'text', (string) $teacher['tc_name']); ?>
  <?php form_field('Email', 'email', 'email', (string) $teacher['tc_email']); ?>
  <?php form_field('Phone', 'phone', 'text', (string) $teacher['tc_phone']); ?>
  <?php form_field('Department', 'dept', 'text', (string) $teacher['tc_dept']); ?>
  <?php form_field('Designation', 'designation', 'text', (string) $teacher['tc_designation']); ?>
  <?php form_submit('Save Changes', 'save'); ?>
</form>

<h4 style="margin-top:30px">Assigned courses</h4>
<table class="table table-striped">
  <thead><tr><th>Course</th><th>Semester</th><th>Students</th><th>Status</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($courses as $c): ?>
      <tr>
        <td><a href="course.php?id=<?= e($c['course_id']) ?>"><?= e($c['course_code']) ?></a> - <?= e($c['course_name']) ?></td>
        <td><?= e(semester_label($c)) ?></td>
        <td><?= e($c['students']) ?></td>
        <td><?= status_badge($c['status'] === 'active') ?></td>
        <td><?= action_button('remove', ['course_id' => $c['course_id'], 'tc_id' => $teacher['tc_id'], 'back' => $self], 'Remove', 'btn-danger', 'Remove this assignment? Attendance already recorded is kept.') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($courses === []): ?><tr><td colspan="5">No courses assigned yet.</td></tr><?php endif; ?>
  </tbody>
</table>

<?php if ((int) $teacher['is_active'] === 1 && $available !== []): ?>
  <form method="post" action="assign_teacher.php" class="form-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="assign">
    <input type="hidden" name="tc_id" value="<?= e($teacher['tc_id']) ?>">
    <input type="hidden" name="back" value="<?= e($self) ?>">
    <?php form_select_inline('course_id', $available); ?>
    <button class="btn btn-primary" type="submit">Assign Course</button>
  </form>
<?php endif; ?>
<?php page_end();
