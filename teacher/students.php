<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'teacher');
$sem = current_semester($pdo);

// Only students enrolled in this teacher's courses (current semester)
$students = [];
if ($sem !== null) {
    $stmt = $pdo->prepare(
        'SELECT s.student_code, s.st_name, s.st_dept, s.st_batch,
                GROUP_CONCAT(DISTINCT c.course_code ORDER BY c.course_code SEPARATOR \', \') AS courses
         FROM teacher_courses tc
         JOIN courses c ON c.course_id = tc.course_id AND c.sem_id = ? AND c.status = \'active\'
         JOIN student_courses sc ON sc.course_id = c.course_id
         JOIN students s ON s.st_id = sc.st_id AND s.is_active = 1
         WHERE tc.tc_id = ?
         GROUP BY s.st_id, s.student_code, s.st_name, s.st_dept, s.st_batch
         ORDER BY s.student_code'
    );
    $stmt->execute([$sem['sem_id'], $me['tc_id']]);
    $students = $stmt->fetchAll();
}

page_start('teacher', 'My Students');
?>
<p>Students enrolled in your courses this semester.</p>
<table class="table table-striped">
  <thead><tr><th>Student ID</th><th>Name</th><th>Department</th><th>Batch</th><th>Your course(s)</th></tr></thead>
  <tbody>
    <?php foreach ($students as $s): ?>
      <tr>
        <td><?= e($s['student_code']) ?></td><td><?= e($s['st_name']) ?></td><td><?= e($s['st_dept']) ?></td>
        <td><?= e($s['st_batch']) ?></td><td><?= e($s['courses']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($students === []): ?><tr><td colspan="5">No students yet.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php page_end();
