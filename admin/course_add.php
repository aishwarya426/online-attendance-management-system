<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'admin');
$cur = current_semester($pdo);

$f = ['code' => '', 'name' => '', 'credits' => '', 'dept' => '', 'sem_id' => $cur === null ? '' : (string) $cur['sem_id']];

/** Validate course fields shared by add and edit. @throws InvalidArgumentException */
function course_input(): array
{
    $code = strtoupper(post('code'));
    $name = post('name');
    if ($code === '' || $name === '') {
        throw new InvalidArgumentException("Course code and name can't be empty.");
    }
    if (strlen($code) > 30) {
        throw new InvalidArgumentException('Course code is too long (30 characters max).');
    }
    $credits = null;
    if (post('credits') !== '') {
        $credits = post_int('credits');
        if ($credits === null || $credits > 30) {
            throw new InvalidArgumentException('Credits must be a number between 0 and 30.');
        }
    }
    return ['code' => $code, 'name' => $name, 'credits' => $credits, 'dept' => post('dept')];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($f as $k => $_) {
        $f[$k] = post($k);
    }
    try {
        $in    = course_input();
        $semId = post_int('sem_id');
        $chk   = $pdo->prepare('SELECT 1 FROM semesters WHERE sem_id = ?');
        $chk->execute([$semId ?? 0]);
        if ($chk->fetchColumn() === false) {
            throw new InvalidArgumentException('Please choose a semester.');
        }
        try {
            $pdo->prepare('INSERT INTO courses (course_code, course_name, credits, department, sem_id, status) VALUES (?, ?, ?, ?, ?, \'active\')')
                ->execute([$in['code'], $in['name'], $in['credits'], $in['dept'], $semId]);
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                throw new InvalidArgumentException('A course with that code already exists in this semester.');
            }
            throw $ex;
        }
        flash('ok', 'Course ' . $in['code'] . ' created. Now assign a teacher and enroll students.');
        redirect('course.php?id=' . (int) $pdo->lastInsertId());
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
}

page_start('admin', 'Add Course');
?>
<form method="post" class="form-horizontal" style="max-width:600px;margin:0 auto">
  <?= csrf_field() ?>
  <?php form_field('Course code', 'code', 'text', $f['code'], 'placeholder="e.g. BCSE305"'); ?>
  <?php form_field('Course name', 'name', 'text', $f['name'], 'placeholder="e.g. Database Management Systems"'); ?>
  <?php form_field('Credits (optional)', 'credits', 'number', $f['credits'], 'min="0" max="30"'); ?>
  <?php form_field('Department (optional)', 'dept', 'text', $f['dept'], 'placeholder="e.g. CSE"'); ?>
  <?php form_select('Semester', 'sem_id', semester_options($pdo), $f['sem_id']); ?>
  <?php form_submit('Add Course', 'add'); ?>
</form>
<?php page_end();
