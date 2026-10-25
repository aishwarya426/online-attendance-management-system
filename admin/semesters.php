<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me = require_login($pdo, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $action = post('action');
        if ($action === 'add') {
            $year = post('academic_year');
            $no   = post_int('semester_no');
            if (preg_match('/^(\d{4})-(\d{2})$/', $year, $m) !== 1 || ((int) $m[1] + 1) % 100 !== (int) $m[2]) {
                throw new InvalidArgumentException('Academic year must look like 2026-27.');
            }
            if ($no === null || $no < 1 || $no > 12) {
                throw new InvalidArgumentException('Semester number must be between 1 and 12.');
            }
            try {
                $pdo->prepare('INSERT INTO semesters (academic_year, semester_no, is_current) VALUES (?, ?, 0)')->execute([$year, $no]);
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    throw new InvalidArgumentException('That semester already exists.');
                }
                throw $ex;
            }
            flash('ok', 'Semester added. Use "Make current" when it starts.');
        } elseif ($action === 'current') {
            $id = post_int('sem_id');
            in_transaction($pdo, function () use ($pdo, $id): void {
                $exists = $pdo->prepare('SELECT 1 FROM semesters WHERE sem_id = ?');
                $exists->execute([$id]);
                if ($id === null || $exists->fetchColumn() === false) {
                    throw new InvalidArgumentException('Semester not found.');
                }
                $pdo->exec('UPDATE semesters SET is_current = 0');
                $pdo->prepare('UPDATE semesters SET is_current = 1 WHERE sem_id = ?')->execute([$id]);
            });
            flash('ok', 'Current semester updated. Teachers and students now see this semester\'s courses.');
        }
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect('semesters.php');
}

$rows = $pdo->query(
    'SELECT sm.*, (SELECT COUNT(*) FROM courses c WHERE c.sem_id = sm.sem_id) AS courses,
            (SELECT COUNT(*) FROM attendance a WHERE a.sem_id = sm.sem_id) AS records
     FROM semesters sm ORDER BY (sm.academic_year = \'Legacy\'), sm.academic_year DESC, sm.semester_no DESC'
)->fetchAll();

page_start('admin', 'Semesters');
?>
<p>The <strong>current</strong> semester decides which courses teachers and students see. Older semesters and their attendance are kept.</p>
<table class="table table-striped">
  <thead><tr><th>Semester</th><th>Courses</th><th>Attendance records</th><th>Status</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(semester_label($r)) ?></td>
        <td><?= e($r['courses']) ?></td>
        <td><?= e($r['records']) ?></td>
        <td><?= (int) $r['is_current'] === 1 ? '<span class="label label-success">Current</span>' : '' ?></td>
        <td><?= (int) $r['is_current'] === 1 ? '' : action_button('current', ['sem_id' => $r['sem_id']], 'Make current', 'btn-primary', 'Make this the current semester?') ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h4>Add semester</h4>
<form method="post" class="form-horizontal" style="max-width:600px;margin:0 auto">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add">
  <?php form_field('Academic year', 'academic_year', 'text', '', 'placeholder="2026-27"'); ?>
  <?php form_field('Semester number', 'semester_no', 'number', '', 'min="1" max="12" placeholder="5"'); ?>
  <?php form_submit('Add Semester', 'add'); ?>
</form>
<?php page_end();
