<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'student');
$cur = current_semester($pdo);

// Semesters in which this student has enrollments
$stmt = $pdo->prepare(
    'SELECT DISTINCT sm.* FROM student_courses sc JOIN courses c ON c.course_id = sc.course_id
     JOIN semesters sm ON sm.sem_id = c.sem_id WHERE sc.st_id = ?
     ORDER BY (sm.academic_year = \'Legacy\'), sm.academic_year DESC, sm.semester_no DESC'
);
$stmt->execute([$me['st_id']]);
$semesters = [];
foreach ($stmt->fetchAll() as $s) {
    $semesters[(int) $s['sem_id']] = semester_label($s) . ((int) $s['is_current'] === 1 ? ' - current' : '');
}

$semId = get_int('sem_id') ?? ($cur === null ? 0 : (int) $cur['sem_id']);
if (!isset($semesters[$semId]) && $semesters !== []) {
    $semId = array_key_first($semesters);
}
$courses = $semesters === [] ? [] : student_course_summary($pdo, $me['st_id'], $semId);

$sumP = array_sum(array_map(static fn (array $c): int => (int) $c['present'], $courses));
$sumT = array_sum(array_map(static fn (array $c): int => (int) $c['total'], $courses));

page_start('student', 'My Attendance');
?>
<?php if (count($semesters) > 1): ?>
  <form method="get" class="form-inline" style="margin-bottom:15px">
    <label>Semester </label>
    <?php form_select_inline('sem_id', $semesters); ?>
    <button class="btn btn-default" type="submit">Show</button>
  </form>
  <script>document.querySelector('select[name=sem_id]').value = '<?= e($semId) ?>';</script>
<?php endif; ?>
<table class="table table-striped">
  <thead><tr><th>Course</th><th>Classes</th><th>Present</th><th>Absent</th><th>%</th><th>Details</th></tr></thead>
  <tbody>
    <?php foreach ($courses as $c): $p = (int) $c['present']; $t = (int) $c['total']; ?>
      <tr>
        <td><?= e($c['course_code']) ?> - <?= e($c['course_name']) ?></td>
        <td><?= $t ?></td><td><?= $p ?></td><td><?= $t - $p ?></td>
        <td class="<?= is_low($p, $t) ? 'low' : '' ?>"><?= e(pct_text($p, $t)) ?></td>
        <td><a href="course.php?course_id=<?= e($c['course_id']) ?>">View dates</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($courses === []): ?><tr><td colspan="6">No courses to show.</td></tr><?php endif; ?>
  </tbody>
  <?php if ($courses !== []): ?>
    <tfoot><tr><th>Overall</th><th><?= $sumT ?></th><th><?= $sumP ?></th><th><?= $sumT - $sumP ?></th>
      <th class="<?= is_low($sumP, $sumT) ? 'low' : '' ?>"><?= e(pct_text($sumP, $sumT)) ?></th><th></th></tr></tfoot>
  <?php endif; ?>
</table>
<?php page_end();
