<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me = require_login($pdo, 'student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        change_own_password($pdo, $me['id']);
        flash('ok', 'Password changed.');
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('account.php');
}

$stmt = $pdo->prepare('SELECT * FROM students WHERE st_id = ?');
$stmt->execute([$me['st_id']]);
$s = $stmt->fetch();

page_start('student', 'My Profile');
?>
<table class="table table-striped" style="max-width:500px;margin:0 auto 25px">
  <tr><td>Student ID</td><td><?= e($s['student_code']) ?></td></tr>
  <tr><td>Name</td><td><?= e($s['st_name']) ?></td></tr>
  <tr><td>Email</td><td><?= e($s['st_email']) ?></td></tr>
  <tr><td>Phone</td><td><?= e($s['st_phone']) ?></td></tr>
  <tr><td>Department</td><td><?= e($s['st_dept']) ?></td></tr>
  <tr><td>Batch</td><td><?= e($s['st_batch']) ?></td></tr>
  <tr><td>Semester</td><td><?= e($s['st_sem']) ?></td></tr>
  <tr><td>Username</td><td><?= e($me['username']) ?></td></tr>
</table>
<p><small>To change profile details, ask the administrator.</small></p>
<?php password_form(); page_end();
