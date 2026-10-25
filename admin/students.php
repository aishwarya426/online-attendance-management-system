<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'admin');
$sem = current_semester($pdo);

$q    = trim((string) ($_GET['q'] ?? ''));
$like = '%' . $q . '%';
$stmt = $pdo->prepare(
    'SELECT s.*,
            (SELECT COUNT(*) FROM student_courses sc JOIN courses c ON c.course_id = sc.course_id
              WHERE sc.st_id = s.st_id AND c.sem_id = ?) AS course_count,
            (SELECT COUNT(*) FROM admininfo a WHERE a.st_id = s.st_id) AS has_login
     FROM students s
     WHERE ? = \'\' OR s.st_name LIKE ? OR s.student_code LIKE ? OR s.st_email LIKE ? OR s.st_dept LIKE ?
     ORDER BY s.student_code'
);
$stmt->execute([$sem === null ? 0 : $sem['sem_id'], $q, $like, $like, $like, $like]);
$students = $stmt->fetchAll();

page_start('admin', 'Manage Students');
?>
<p>
  <a class="btn btn-primary" href="student_add.php">Add Student</a>
</p>
<form method="get" class="form-inline" style="margin-bottom:15px">
  <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="search name, ID, email, department">
  <button class="btn btn-default" type="submit">Search</button>
  <?php if ($q !== ''): ?><a href="students.php">clear</a><?php endif; ?>
</form>
<table class="table table-striped">
  <thead>
    <tr><th>Student ID</th><th>Name</th><th>Email</th><th>Department</th><th>Semester</th>
        <th>Courses<?= $sem !== null ? ' (current sem.)' : '' ?></th><th>Login</th><th>Status</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($students as $s): ?>
      <tr>
        <td><?= e($s['student_code']) ?></td>
        <td><?= e($s['st_name']) ?></td>
        <td><?= e($s['st_email']) ?></td>
        <td><?= e($s['st_dept']) ?></td>
        <td><?= e($s['st_sem']) ?></td>
        <td><?= e($s['course_count']) ?></td>
        <td><?= (int) $s['has_login'] > 0 ? 'Yes' : '<span class="low">No</span>' ?></td>
        <td><?= status_badge((int) $s['is_active'] === 1) ?></td>
        <td><a href="student.php?id=<?= e($s['st_id']) ?>">View / Edit</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($students === []): ?><tr><td colspan="9">No students found.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php page_end();
