<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'admin');
$sem = current_semester($pdo);
$semId = $sem === null ? 0 : (int) $sem['sem_id'];

$count = static function (string $sql, array $params = []) use ($pdo): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
};

$stats = [
    'Active students'            => $count('SELECT COUNT(*) FROM students WHERE is_active = 1'),
    'Active teachers'            => $count('SELECT COUNT(*) FROM teachers WHERE is_active = 1'),
    'Courses this semester'      => $count('SELECT COUNT(*) FROM courses WHERE sem_id = ? AND status = \'active\'', [$semId]),
    'Enrollments this semester'  => $count('SELECT COUNT(*) FROM student_courses sc JOIN courses c ON c.course_id = sc.course_id WHERE c.sem_id = ?', [$semId]),
    'Attendance records this semester' => $count('SELECT COUNT(*) FROM attendance WHERE sem_id = ?', [$semId]),
];
$noTeacher = $count(
    'SELECT COUNT(*) FROM courses c WHERE c.sem_id = ? AND c.status = \'active\'
     AND NOT EXISTS (SELECT 1 FROM teacher_courses tc WHERE tc.course_id = c.course_id)',
    [$semId]
);

page_start('admin', 'Admin Overview');
?>
<p>Welcome, <strong><?= e($me['name']) ?></strong></p>
<?php if ($sem === null): ?>
  <p class="msg-err">No current semester is set. <a href="semesters.php">Set one here</a> before assigning courses.</p>
<?php else: ?>
  <p>Current semester: <strong><?= e(semester_label($sem)) ?></strong> (<a href="semesters.php">change</a>)</p>
<?php endif; ?>

<table class="table table-striped" style="max-width:600px;margin:0 auto">
  <tbody>
    <?php foreach ($stats as $label => $value): ?>
      <tr><td><?= e($label) ?></td><td><strong><?= e($value) ?></strong></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($noTeacher > 0): ?>
  <p class="msg-err"><?= e($noTeacher) ?> active course(s) this semester have no teacher yet. <a href="assign_teacher.php">Assign teachers</a></p>
<?php endif; ?>

<p style="margin-top:25px">
  <a class="btn btn-primary" href="student_add.php">Add Student</a>
  <a class="btn btn-primary" href="teacher_add.php">Add Teacher</a>
  <a class="btn btn-primary" href="course_add.php">Add Course</a>
  <a class="btn btn-default" href="signup.php">Add Admin</a>
</p>
<?php page_end();
