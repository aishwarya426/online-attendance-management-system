<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me = require_login($pdo, 'admin');

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

page_start('admin', 'Admin Account');
?>
<table class="table table-striped" style="max-width:500px;margin:0 auto 25px">
  <tr><td>Name</td><td><?= e($me['name']) ?></td></tr>
  <tr><td>Username</td><td><?= e($me['username']) ?></td></tr>
  <tr><td>Email</td><td><?= e($me['email']) ?></td></tr>
  <tr><td>Role</td><td>Admin</td></tr>
</table>
<?php password_form(); page_end();
