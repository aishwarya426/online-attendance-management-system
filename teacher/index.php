<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'teacher');
$sem = current_semester($pdo);

$courses = $sem === null ? [] : teacher_courses_list($pdo, $me['tc_id'], (int) $sem['sem_id']);
$courses = array_values(array_filter($courses, static fn (array $c): bool => $c['status'] === 'active'));

page_start('teacher');
?>
<h3>Welcome, <?= e($me['name']) ?></h3>
<p>Teacher ID: <strong><?= e($me['code']) ?></strong></p>
<?php if ($sem === null): ?>
  <p class="msg-err">No current semester has been set. Please ask the administrator.</p>
<?php else: ?>
  <p>Current semester: <strong><?= e(semester_label($sem)) ?></strong></p>
<?php endif; ?>

<h4>My Courses</h4>
<table class="table table-striped">
  <thead><tr><th>Course Code</th><th>Course Name</th><th>Students</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($courses as $c): ?>
      <tr>
        <td><?= e($c['course_code']) ?></td>
        <td><?= e($c['course_name']) ?></td>
        <td><?= e($c['students']) ?></td>
        <td>
          <a class="btn btn-primary btn-xs" href="course.php?course_id=<?= e($c['course_id']) ?>">Open Course</a>
          <a class="btn btn-default btn-xs" href="report.php?course_id=<?= e($c['course_id']) ?>">Report</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($courses === []): ?><tr><td colspan="4">No courses are assigned to you this semester.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php page_end();
