<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'admin');
$cur = current_semester($pdo);

$semId = get_int('sem_id') ?? ($cur === null ? 0 : (int) $cur['sem_id']);
$stmt = $pdo->prepare(
    'SELECT c.*,
            (SELECT GROUP_CONCAT(t.tc_name ORDER BY t.tc_name SEPARATOR \', \')
               FROM teacher_courses tc JOIN teachers t ON t.tc_id = tc.tc_id WHERE tc.course_id = c.course_id) AS teachers,
            (SELECT COUNT(*) FROM student_courses sc JOIN students s ON s.st_id = sc.st_id
              WHERE sc.course_id = c.course_id AND s.is_active = 1) AS students
     FROM courses c WHERE c.sem_id = ? ORDER BY c.course_code'
);
$stmt->execute([$semId]);
$courses = $stmt->fetchAll();

page_start('admin', 'Manage Courses');
?>
<p><a class="btn btn-primary" href="course_add.php">Add Course</a></p>
<form method="get" class="form-inline" style="margin-bottom:15px">
  <label>Semester </label>
  <?php form_select_inline('sem_id', semester_options($pdo)); ?>
  <button class="btn btn-default" type="submit">Show</button>
</form>
<script>document.querySelector('select[name=sem_id]').value = '<?= e($semId) ?>';</script>
<table class="table table-striped">
  <thead>
    <tr><th>Code</th><th>Name</th><th>Credits</th><th>Department</th><th>Teacher(s)</th><th>Students</th><th>Status</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($courses as $c): ?>
      <tr>
        <td><?= e($c['course_code']) ?></td>
        <td><?= e($c['course_name']) ?></td>
        <td><?= e($c['credits']) ?></td>
        <td><?= e($c['department']) ?></td>
        <td><?= $c['teachers'] === null ? '<span class="low">none</span>' : e($c['teachers']) ?></td>
        <td><?= e($c['students']) ?></td>
        <td><?= status_badge($c['status'] === 'active') ?></td>
        <td><a href="course.php?id=<?= e($c['course_id']) ?>">View / Edit</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($courses === []): ?><tr><td colspan="8">No courses in this semester yet.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php page_end();
