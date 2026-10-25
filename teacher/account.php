<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me = require_login($pdo, 'teacher');

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

$stmt = $pdo->prepare('SELECT * FROM teachers WHERE tc_id = ?');
$stmt->execute([$me['tc_id']]);
$t = $stmt->fetch();

page_start('teacher', 'My Profile');
?>
<table class="table table-striped" style="max-width:500px;margin:0 auto 25px">
  <tr><td>Teacher ID</td><td><?= e($t['teacher_code']) ?></td></tr>
  <tr><td>Name</td><td><?= e($t['tc_name']) ?></td></tr>
  <tr><td>Email</td><td><?= e($t['tc_email']) ?></td></tr>
  <tr><td>Phone</td><td><?= e($t['tc_phone']) ?></td></tr>
  <tr><td>Department</td><td><?= e($t['tc_dept']) ?></td></tr>
  <tr><td>Designation</td><td><?= e($t['tc_designation']) ?></td></tr>
  <tr><td>Username</td><td><?= e($me['username']) ?></td></tr>
</table>
<p><small>To change profile details, ask the administrator.</small></p>
<?php password_form(); page_end();
