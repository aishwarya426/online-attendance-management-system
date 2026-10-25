<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'admin');
$cur = current_semester($pdo);

$semId = get_int('sem_id') ?? ($cur === null ? 0 : (int) $cur['sem_id']);
$stmt = $pdo->prepare(
    'SELECT c.course_id, c.course_code, c.course_name, c.status,
            (SELECT GROUP_CONCAT(t.tc_name ORDER BY t.tc_name SEPARATOR \', \')
               FROM teacher_courses tc JOIN teachers t ON t.tc_id = tc.tc_id WHERE tc.course_id = c.course_id) AS teachers,
            (SELECT COUNT(*) FROM student_courses sc WHERE sc.course_id = c.course_id) AS enrolled,
            (SELECT COUNT(DISTINCT a.stat_date) FROM attendance a WHERE a.course_id = c.course_id) AS sessions,
            (SELECT COUNT(*) FROM attendance a WHERE a.course_id = c.course_id) AS total,
            (SELECT COUNT(*) FROM attendance a WHERE a.course_id = c.course_id AND a.st_status = \'Present\') AS present
     FROM courses c WHERE c.sem_id = ? ORDER BY c.course_code'
);
$stmt->execute([$semId]);
$courses = $stmt->fetchAll();

page_start('admin', 'Attendance Overview');
?>
<form method="get" class="form-inline" style="margin-bottom:15px">
  <label>Semester </label>
  <?php form_select_inline('sem_id', semester_options($pdo)); ?>
  <button class="btn btn-default" type="submit">Show</button>
</form>
<script>document.querySelector('select[name=sem_id]').value = '<?= e($semId) ?>';</script>
<table class="table table-striped">
  <thead>
    <tr><th>Course</th><th>Teacher(s)</th><th>Students</th><th>Classes held</th><th>Overall attendance</th><th>Report</th></tr>
  </thead>
  <tbody>
    <?php foreach ($courses as $c): $p = (int) $c['present']; $t = (int) $c['total']; ?>
      <tr>
        <td><?= e($c['course_code']) ?> - <?= e($c['course_name']) ?></td>
        <td><?= $c['teachers'] === null ? '<span class="low">none</span>' : e($c['teachers']) ?></td>
        <td><?= e($c['enrolled']) ?></td>
        <td><?= e($c['sessions']) ?></td>
        <td class="<?= is_low($p, $t) ? 'low' : '' ?>"><?= e(pct_text($p, $t)) ?></td>
        <td><a href="report.php?course_id=<?= e($c['course_id']) ?>">View Report</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($courses === []): ?><tr><td colspan="6">No courses in this semester.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php page_end();
