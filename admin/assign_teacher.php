<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'admin');
$cur = current_semester($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $back = safe_back(post('back'), 'assign_teacher.php');
    try {
        $action   = post('action');
        $tcId     = post_int('tc_id');
        $courseId = post_int('course_id');
        $course   = $courseId === null ? null : find_course($pdo, $courseId);
        $t = $pdo->prepare('SELECT tc_id, is_active FROM teachers WHERE tc_id = ?');
        $t->execute([$tcId ?? 0]);
        $teacher = $t->fetch();

        if ($course === null || $teacher === false) {
            throw new InvalidArgumentException('Please choose a teacher and a course.');
        }
        if ($action === 'assign') {
            if ((int) $teacher['is_active'] !== 1) {
                throw new InvalidArgumentException('That teacher is deactivated.');
            }
            $ins = $pdo->prepare('INSERT IGNORE INTO teacher_courses (tc_id, course_id) VALUES (?, ?)');
            $ins->execute([$tcId, $courseId]);
            flash($ins->rowCount() > 0 ? 'ok' : 'error', $ins->rowCount() > 0
                ? 'Course assigned. It now appears on the teacher\'s dashboard.'
                : 'That teacher is already assigned to this course.');
        } elseif ($action === 'remove') {
            $pdo->prepare('DELETE FROM teacher_courses WHERE tc_id = ? AND course_id = ?')->execute([$tcId, $courseId]);
            flash('ok', 'Assignment removed. Attendance already recorded is kept.');
        }
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect($back);
}

$semId  = get_int('sem_id') ?? ($cur === null ? 0 : (int) $cur['sem_id']);
$filter = get_int('tc_id');

$teacherOpts = [];
foreach ($pdo->query('SELECT tc_id, teacher_code, tc_name FROM teachers WHERE is_active = 1 ORDER BY teacher_code')->fetchAll() as $t) {
    $teacherOpts[$t['tc_id']] = $t['teacher_code'] . ' - ' . $t['tc_name'];
}
$courseStmt = $pdo->prepare('SELECT course_id, course_code, course_name FROM courses WHERE sem_id = ? AND status = \'active\' ORDER BY course_code');
$courseStmt->execute([$semId]);
$courseOpts = [];
foreach ($courseStmt->fetchAll() as $c) {
    $courseOpts[$c['course_id']] = $c['course_code'] . ' - ' . $c['course_name'];
}

$list = $pdo->prepare(
    'SELECT t.tc_id, t.teacher_code, t.tc_name, c.course_id, c.course_code, c.course_name
     FROM teacher_courses tc JOIN teachers t ON t.tc_id = tc.tc_id JOIN courses c ON c.course_id = tc.course_id
     WHERE c.sem_id = ?' . ($filter !== null ? ' AND t.tc_id = ?' : '') . '
     ORDER BY t.teacher_code, c.course_code'
);
$list->execute($filter !== null ? [$semId, $filter] : [$semId]);
$assignments = $list->fetchAll();

page_start('admin', 'Assign Courses to Teachers');
?>
<form method="get" class="form-inline" style="margin-bottom:20px">
  <label>Semester </label>
  <?php form_select_inline('sem_id', semester_options($pdo)); ?>
  <button class="btn btn-default" type="submit">Show</button>
</form>
<script>document.querySelector('select[name=sem_id]').value = '<?= e($semId) ?>';</script>

<h4>Assign a course</h4>
<?php if ($teacherOpts === [] || $courseOpts === []): ?>
  <p>You need at least one active teacher and one active course in this semester.
    <a href="teacher_add.php">Add teacher</a> &middot; <a href="course_add.php">Add course</a></p>
<?php else: ?>
  <form method="post" class="form-horizontal" style="max-width:600px;margin:0 auto">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="assign">
    <?php form_select('Teacher', 'tc_id', $teacherOpts, (string) ($filter ?? '')); ?>
    <?php form_select('Course', 'course_id', $courseOpts, (string) (get_int('course_id') ?? '')); ?>
    <?php form_submit('Assign Course', 'assign'); ?>
  </form>
<?php endif; ?>

<h4 style="margin-top:30px">Current assignments</h4>
<table class="table table-striped">
  <thead><tr><th>Teacher</th><th>Course</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($assignments as $a): ?>
      <tr>
        <td><a href="teacher.php?id=<?= e($a['tc_id']) ?>"><?= e($a['teacher_code']) ?></a> - <?= e($a['tc_name']) ?></td>
        <td><a href="course.php?id=<?= e($a['course_id']) ?>"><?= e($a['course_code']) ?></a> - <?= e($a['course_name']) ?></td>
        <td><?= action_button('remove', ['tc_id' => $a['tc_id'], 'course_id' => $a['course_id'], 'back' => 'assign_teacher.php?sem_id=' . $semId], 'Remove', 'btn-danger', 'Remove this assignment? Attendance already recorded is kept.') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($assignments === []): ?><tr><td colspan="3">No assignments in this semester yet.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php page_end();
