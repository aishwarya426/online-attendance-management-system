<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me = require_login($pdo, 'student');

// A student may only open a course they are (or were) enrolled in. Read-only page.
$courseId = get_int('course_id') ?? 0;
$stmt = $pdo->prepare(
    'SELECT c.*, sm.academic_year, sm.semester_no, sm.is_current FROM courses c
     JOIN semesters sm ON sm.sem_id = c.sem_id
     WHERE c.course_id = ?
       AND (EXISTS (SELECT 1 FROM student_courses sc WHERE sc.course_id = c.course_id AND sc.st_id = ?)
         OR EXISTS (SELECT 1 FROM attendance a WHERE a.course_id = c.course_id AND a.stat_id = ?))'
);
$stmt->execute([$courseId, $me['st_id'], $me['st_id']]);
$course = $stmt->fetch();
if ($course === false) {
    deny('You are not enrolled in this course.');
}

$rows = $pdo->prepare('SELECT stat_date, st_status FROM attendance WHERE stat_id = ? AND course_id = ? ORDER BY stat_date DESC');
$rows->execute([$me['st_id'], $courseId]);
$history = $rows->fetchAll();
$total   = count($history);
$present = count(array_filter($history, static fn (array $r): bool => $r['st_status'] === 'Present'));

page_start('student');
?>
<p><a href="report.php">&larr; My attendance</a></p>
<h3><?= e($course['course_code']) ?> - <?= e($course['course_name']) ?></h3>
<p><?= e(semester_label($course)) ?></p>
<p>Total classes: <strong><?= $total ?></strong> &middot; Present: <strong><?= $present ?></strong> &middot;
   Absent: <strong><?= $total - $present ?></strong> &middot;
   Attendance: <strong class="<?= is_low($present, $total) ? 'low' : '' ?>"><?= e(pct_text($present, $total)) ?></strong></p>
<table class="table table-striped">
  <thead><tr><th>Date</th><th>Status</th></tr></thead>
  <tbody>
    <?php foreach ($history as $r): ?>
      <tr><td><?= e(display_date((string) $r['stat_date'])) ?></td><td class="<?= $r['st_status'] === 'Absent' ? 'low' : '' ?>"><?= e($r['st_status']) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($history === []): ?><tr><td colspan="2">No classes recorded yet.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php page_end();
