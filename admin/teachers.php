<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'admin');
$sem = current_semester($pdo);

$q    = trim((string) ($_GET['q'] ?? ''));
$like = '%' . $q . '%';
$stmt = $pdo->prepare(
    'SELECT t.*,
            (SELECT COUNT(*) FROM teacher_courses tc JOIN courses c ON c.course_id = tc.course_id
              WHERE tc.tc_id = t.tc_id AND c.sem_id = ?) AS course_count,
            (SELECT COUNT(*) FROM admininfo a WHERE a.tc_id = t.tc_id) AS has_login
     FROM teachers t
     WHERE ? = \'\' OR t.tc_name LIKE ? OR t.teacher_code LIKE ? OR t.tc_email LIKE ? OR t.tc_dept LIKE ?
     ORDER BY t.teacher_code'
);
$stmt->execute([$sem === null ? 0 : $sem['sem_id'], $q, $like, $like, $like, $like]);
$teachers = $stmt->fetchAll();

page_start('admin', 'Manage Teachers');
?>
<p><a class="btn btn-primary" href="teacher_add.php">Add Teacher</a></p>
<form method="get" class="form-inline" style="margin-bottom:15px">
  <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="search name, ID, email, department">
  <button class="btn btn-default" type="submit">Search</button>
  <?php if ($q !== ''): ?><a href="teachers.php">clear</a><?php endif; ?>
</form>
<table class="table table-striped">
  <thead>
    <tr><th>Teacher ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Department</th>
        <th>Courses<?= $sem !== null ? ' (current sem.)' : '' ?></th><th>Login</th><th>Status</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($teachers as $t): ?>
      <tr>
        <td><?= e($t['teacher_code']) ?></td>
        <td><?= e($t['tc_name']) ?></td>
        <td><?= e($t['tc_email']) ?></td>
        <td><?= e($t['tc_phone']) ?></td>
        <td><?= e($t['tc_dept']) ?></td>
        <td><?= e($t['course_count']) ?></td>
        <td><?= (int) $t['has_login'] > 0 ? 'Yes' : '<span class="low">No</span>' ?></td>
        <td><?= status_badge((int) $t['is_active'] === 1) ?></td>
        <td><a href="teacher.php?id=<?= e($t['tc_id']) ?>">View / Edit</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($teachers === []): ?><tr><td colspan="9">No teachers found.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php page_end();
