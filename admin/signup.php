<?php

declare(strict_types=1);

// Admin-only: create another administrator login.
// Students and teachers are created from Students > Add Student / Teachers > Add Teacher.

require_once __DIR__ . '/connect.php';
$me = require_login($pdo, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $required = ['fname' => 'Full name', 'email' => 'Email', 'uname' => 'Username', 'phone' => 'Phone number'];
        foreach ($required as $key => $label) {
            if (post($key) === '') {
                throw new InvalidArgumentException("$label can't be empty.");
            }
        }
        $password = post('pass', false);
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('The password must be at least 8 characters.');
        }
        if (filter_var(post('email'), FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Please enter a valid email address.');
        }
        create_account($pdo, post('uname'), $password, post('email'), post('fname'), post('phone'), 'admin');
        flash('ok', 'Admin account "' . post('uname') . '" created.');
        redirect('signup.php');
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
        redirect('signup.php');
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
        redirect('signup.php');
    }
}

page_start('admin', 'Add Admin');
?>
<form method="post" class="form-horizontal" style="max-width:600px;margin:0 auto">
  <?= csrf_field() ?>
  <?php form_field('Full name', 'fname'); ?>
  <?php form_field('Email', 'email', 'email'); ?>
  <?php form_field('Phone', 'phone'); ?>
  <?php form_field('Username', 'uname', 'text', '', 'autocomplete="off"'); ?>
  <?php form_field('Password', 'pass', 'password', '', 'autocomplete="new-password"'); ?>
  <?php form_submit('Create Admin', 'signup'); ?>
</form>
<?php page_end();
