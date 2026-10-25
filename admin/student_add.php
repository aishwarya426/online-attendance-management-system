<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me = require_login($pdo, 'admin');

$f = ['name' => '', 'email' => '', 'phone' => '', 'dept' => '', 'batch' => '', 'sem' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($f as $k => $_) {
        $f[$k] = post($k);
    }
    try {
        $r = create_student($pdo, student_input());
        flash('ok', 'Student created successfully.');
        flash_credentials('Student login created', $r['code'], $r['username'], $r['password']);
        redirect('student.php?id=' . $r['st_id']);
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
}

page_start('admin', 'Add Student');
?>
<p>A unique Student ID (e.g. STU0007) and a login are generated automatically.</p>
<form method="post" class="form-horizontal" style="max-width:600px;margin:0 auto">
  <?= csrf_field() ?>
  <?php form_field('Full name', 'name', 'text', $f['name']); ?>
  <?php form_field('Email', 'email', 'email', $f['email']); ?>
  <?php form_field('Phone (optional)', 'phone', 'text', $f['phone']); ?>
  <?php form_field('Department', 'dept', 'text', $f['dept'], 'placeholder="e.g. CSE"'); ?>
  <?php form_field('Batch', 'batch', 'text', $f['batch'], 'placeholder="e.g. 38"'); ?>
  <?php form_field('Semester', 'sem', 'text', $f['sem'], 'placeholder="e.g. 5"'); ?>
  <?php form_submit('Add Student', 'add'); ?>
</form>
<?php page_end();
