<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me = require_login($pdo, 'admin');

$id     = get_int('id');
$course = $id === null ? null : find_course($pdo, $id);
if ($course === null) {
    deny('Course not found.', 404);
}
$cid  = (int) $course['course_id'];
$self = 'course.php?id=' . $cid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $action = post('action');
        if ($action === 'update') {
            $code = strtoupper(post('code'));
            $name = post('name');
            if ($code === '' || $name === '' || strlen($code) > 30) {
                throw new InvalidArgumentException('Enter a course code (30 characters max) and name.');
            }
            $credits = null;
            if (post('credits') !== '') {
                $credits = post_int('credits');
                if ($credits === null || $credits > 30) {
                    throw new InvalidArgumentException('Credits must be a number between 0 and 30.');
                }
            }
            $status = post('status') === 'inactive' ? 'inactive' : 'active';
            try {
                $pdo->prepare('UPDATE courses SET course_code = ?, course_name = ?, credits = ?, department = ?, status = ? WHERE course_id = ?')
                    ->execute([$code, $name, $credits, post('dept'), $status, $cid]);
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    throw new InvalidArgumentException('A course with that code already exists in this semester.');
                }
                throw $ex;
            }
            flash('ok', 'Course updated.');
        }
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect($self);
}

$tstmt = $pdo->prepare(
    'SELECT t.tc_id, t.teacher_code, t.tc_name FROM teacher_courses tc JOIN teachers t ON t.tc_id = tc.tc_id
     WHERE tc.course_id = ? ORDER BY t.teacher_code'
);
$tstmt->execute([$cid]);
$teachers = $tstmt->fetchAll();

$sstmt = $pdo->prepare(
    'SELECT s.st_id, s.student_code, s.st_name, s.st_batch, s.is_active FROM student_courses sc JOIN students s ON s.st_id = sc.st_id
     WHERE sc.course_id = ? ORDER BY s.student_code'
);
$sstmt->execute([$cid]);
$students = $sstmt->fetchAll();

page_start('admin', $course['course_code'] . ' - ' . $course['course_name']);
?>
<p><a href="courses.php?sem_id=<?= e($course['sem_id']) ?>">&larr; All courses</a>
  &nbsp;&middot;&nbsp; <?= e(semester_label($course)) ?> &nbsp;&middot;&nbsp; <?= status_badge($course['status'] === 'active') ?>
  &nbsp;&middot;&nbsp; <a href="report.php?course_id=<?= e($cid) ?>">Attendance report</a></p>

<h4>Course details</h4>
<form method="post" class="form-horizontal" style="max-width:600px;margin:0 auto">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="update">
  <?php form_field('Course code', 'code', 'text', (string) $course['course_code']); ?>
  <?php form_field('Course name', 'name', 'text', (string) $course['course_name']); ?>
  <?php form_field('Credits', 'credits', 'number', (string) $course['credits'], 'min="0" max="30"'); ?>
  <?php form_field('Department', 'dept', 'text', (string) $course['department']); ?>
  <?php form_select('Status', 'status', ['active' => 'Active', 'inactive' => 'Inactive'], (string) $course['status']); ?>
  <?php form_submit('Save Changes', 'save'); ?>
</form>

<h4 style="margin-top:30px">Teachers</h4>
<table class="table table-striped">
  <thead><tr><th>Teacher ID</th><th>Name</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($teachers as $t): ?>
      <tr>
        <td><a href="teacher.php?id=<?= e($t['tc_id']) ?>"><?= e($t['teacher_code']) ?></a></td>
        <td><?= e($t['tc_name']) ?></td>
        <td><?= action_button('remove', ['course_id' => $cid, 'tc_id' => $t['tc_id'], 'back' => $self], 'Remove', 'btn-danger', 'Remove this teacher from the course? Attendance already recorded is kept.') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($teachers === []): ?><tr><td colspan="3">No teacher assigned.</td></tr><?php endif; ?>
  </tbody>
</table>
<p><a class="btn btn-primary" href="assign_teacher.php?sem_id=<?= e($course['sem_id']) ?>&amp;course_id=<?= e($cid) ?>">Assign Teacher</a></p>

<h4 style="margin-top:30px">Enrolled students (<?= count($students) ?>)</h4>
<table class="table table-striped">
  <thead><tr><th>Student ID</th><th>Name</th><th>Batch</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($students as $s): ?>
      <tr>
        <td><a href="student.php?id=<?= e($s['st_id']) ?>"><?= e($s['student_code']) ?></a></td>
        <td><?= e($s['st_name']) ?><?= (int) $s['is_active'] === 1 ? '' : ' <span class="label label-default">Inactive</span>' ?></td>
        <td><?= e($s['st_batch']) ?></td>
        <td><?= action_button('remove', ['course_id' => $cid, 'st_id' => $s['st_id'], 'back' => $self], 'Remove', 'btn-danger', 'Remove this student from the course? Attendance already recorded is kept.') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($students === []): ?><tr><td colspan="4">No students enrolled.</td></tr><?php endif; ?>
  </tbody>
</table>
<p><a class="btn btn-primary" href="enroll.php?sem_id=<?= e($course['sem_id']) ?>&amp;course_id=<?= e($cid) ?>">Enroll Students</a></p>
<?php page_end();
