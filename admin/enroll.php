<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'admin');
$cur = current_semester($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $courseId = post_int('course_id');
    $course   = $courseId === null ? null : find_course($pdo, $courseId);
    $default  = 'enroll.php' . ($course !== null ? '?sem_id=' . (int) $course['sem_id'] . '&course_id=' . (int) $course['course_id'] : '');
    $back     = safe_back(post('back'), $default);
    try {
        $action = post('action');
        if ($course === null) {
            throw new InvalidArgumentException('Please choose a course.');
        }
        if ($action === 'enroll') {
            if ($course['status'] !== 'active') {
                throw new InvalidArgumentException('That course is inactive.');
            }
            $ids = post_int_list('st_id');
            if ($ids === []) {
                throw new InvalidArgumentException('Select at least one student.');
            }
            $active = $pdo->prepare('SELECT 1 FROM students WHERE st_id = ? AND is_active = 1');
            $ins    = $pdo->prepare('INSERT IGNORE INTO student_courses (st_id, course_id) VALUES (?, ?)');
            $added  = 0;
            in_transaction($pdo, function () use ($ids, $active, $ins, $courseId, &$added): void {
                foreach ($ids as $stId) {
                    $active->execute([$stId]);
                    if ($active->fetchColumn() === false) {
                        throw new InvalidArgumentException('One of the selected students is missing or deactivated.');
                    }
                    $active->closeCursor();
                    $ins->execute([$stId, $courseId]);
                    $added += $ins->rowCount();
                }
            });
            flash($added > 0 ? 'ok' : 'error', $added > 0
                ? $added . ' student(s) enrolled in ' . $course['course_code'] . '.'
                : 'Those students were already enrolled.');
        } elseif ($action === 'remove') {
            $stId = post_int('st_id');
            $pdo->prepare('DELETE FROM student_courses WHERE st_id = ? AND course_id = ?')->execute([$stId ?? 0, $courseId]);
            flash('ok', 'Student removed from the course. Attendance already recorded is kept.');
        }
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect($back);
}

$semId    = get_int('sem_id') ?? ($cur === null ? 0 : (int) $cur['sem_id']);
$courseId = get_int('course_id');

$cs = $pdo->prepare('SELECT course_id, course_code, course_name FROM courses WHERE sem_id = ? ORDER BY course_code');
$cs->execute([$semId]);
$courseOpts = [];
foreach ($cs->fetchAll() as $c) {
    $courseOpts[$c['course_id']] = $c['course_code'] . ' - ' . $c['course_name'];
}
$course = $courseId !== null && isset($courseOpts[$courseId]) ? find_course($pdo, $courseId) : null;

$enrolled = $candidates = [];
if ($course !== null) {
    $e = $pdo->prepare(
        'SELECT s.st_id, s.student_code, s.st_name, s.st_batch, s.is_active FROM student_courses sc
         JOIN students s ON s.st_id = sc.st_id WHERE sc.course_id = ? ORDER BY s.student_code'
    );
    $e->execute([$courseId]);
    $enrolled = $e->fetchAll();

    $c = $pdo->prepare(
        'SELECT st_id, student_code, st_name, st_batch FROM students
         WHERE is_active = 1 AND st_id NOT IN (SELECT st_id FROM student_courses WHERE course_id = ?)
         ORDER BY student_code'
    );
    $c->execute([$courseId]);
    $candidates = $c->fetchAll();
}

page_start('admin', 'Enroll Students in Courses');
?>
<form method="get" class="form-inline" style="margin-bottom:20px">
  <label>Semester </label>
  <?php form_select_inline('sem_id', semester_options($pdo)); ?>
  <button class="btn btn-default" type="submit">Show courses</button>
</form>
<script>document.querySelector('select[name=sem_id]').value = '<?= e($semId) ?>';</script>

<?php if ($courseOpts === []): ?>
  <p>No courses in this semester. <a href="course_add.php">Add a course</a></p>
<?php else: ?>
  <form method="get" class="form-inline" style="margin-bottom:20px">
    <input type="hidden" name="sem_id" value="<?= e($semId) ?>">
    <label>Course </label>
    <?php form_select_inline('course_id', $courseOpts); ?>
    <button class="btn btn-primary" type="submit">Open</button>
  </form>
  <?php if ($courseId !== null): ?>
    <script>document.querySelectorAll('select[name=course_id]')[0].value = '<?= e($courseId) ?>';</script>
  <?php endif; ?>
<?php endif; ?>

<?php if ($course !== null): ?>
  <h4><?= e($course['course_code']) ?> - <?= e($course['course_name']) ?> (<?= count($enrolled) ?> enrolled)</h4>

  <?php if ($course['status'] === 'active'): ?>
    <form method="post" style="max-width:700px;margin:0 auto 25px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="enroll">
      <input type="hidden" name="course_id" value="<?= e($course['course_id']) ?>">
      <p><strong>Add students</strong> (tick one or more)</p>
      <div style="max-height:260px;overflow:auto;text-align:left;border:1px solid #ccc;padding:8px">
        <?php foreach ($candidates as $s): ?>
          <label style="display:block;font-weight:normal">
            <input type="checkbox" name="st_id[]" value="<?= e($s['st_id']) ?>">
            <?= e($s['student_code']) ?> - <?= e($s['st_name']) ?> (batch <?= e($s['st_batch']) ?>)
          </label>
        <?php endforeach; ?>
        <?php if ($candidates === []): ?>All active students are already enrolled.<?php endif; ?>
      </div>
      <?php if ($candidates !== []): ?><button class="btn btn-primary" type="submit" style="margin-top:10px">Enroll Selected Students</button><?php endif; ?>
    </form>
  <?php endif; ?>

  <table class="table table-striped">
    <thead><tr><th>Student ID</th><th>Name</th><th>Batch</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($enrolled as $s): ?>
        <tr>
          <td><?= e($s['student_code']) ?></td>
          <td><?= e($s['st_name']) ?><?= (int) $s['is_active'] === 1 ? '' : ' <span class="label label-default">Inactive</span>' ?></td>
          <td><?= e($s['st_batch']) ?></td>
          <td><?= action_button('remove', ['course_id' => $course['course_id'], 'st_id' => $s['st_id']], 'Remove', 'btn-danger', 'Remove this student from the course? Attendance already recorded is kept.') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($enrolled === []): ?><tr><td colspan="4">No students enrolled yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php page_end();
