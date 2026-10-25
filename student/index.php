<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'student');
$sem = current_semester($pdo);

$courses = $sem === null ? [] : student_course_summary($pdo, $me['st_id'], (int) $sem['sem_id']);

page_start('student');
?>
<h3>Welcome, <?= e($me['name']) ?></h3>
<p>Student ID: <strong><?= e($me['code']) ?></strong></p>
<?php if ($sem === null): ?>
  <p class="msg-err">No current semester has been set. Please contact the administrator.</p>
<?php else: ?>
  <p>Current semester: <strong><?= e(semester_label($sem)) ?></strong></p>
<?php endif; ?>

<h4>My Courses</h4>
<table class="table table-striped">
  <thead><tr><th>Course Code</th><th>Course Name</th><th>Attendance</th><th>Details</th></tr></thead>
  <tbody>
    <?php foreach ($courses as $c): $p = (int) $c['present']; $t = (int) $c['total']; ?>
      <tr>
        <td><?= e($c['course_code']) ?></td>
        <td><?= e($c['course_name']) ?></td>
        <td class="<?= is_low($p, $t) ? 'low' : '' ?>"><?= $t > 0 ? e($p . '/' . $t . ' - ' . pct_text($p, $t)) : 'No classes yet' ?></td>
        <td><a href="course.php?course_id=<?= e($c['course_id']) ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($courses === []): ?><tr><td colspan="4">You are not enrolled in any course this semester.</td></tr><?php endif; ?>
  </tbody>
</table>
<p><a class="btn btn-primary" href="report.php">View Attendance</a></p>
<?php page_end();
