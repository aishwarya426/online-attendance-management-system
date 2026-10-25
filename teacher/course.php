<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me  = require_login($pdo, 'teacher');
$sem = current_semester($pdo);

// Server-side authorization: the course must be assigned to THIS teacher.
$courseId = get_int('course_id') ?? post_int('course_id');
$course   = $courseId === null ? null : teacher_course($pdo, $me['tc_id'], $courseId);
if ($course === null) {
    deny('You are not assigned to this course.');
}
$cid = (int) $course['course_id'];

// Attendance can only be marked for an active course in the current semester.
$markable = $sem !== null && (int) $course['sem_id'] === (int) $sem['sem_id'] && $course['status'] === 'active';

$today    = date('Y-m-d');
$earliest = date('Y-m-d', strtotime('-' . ATTENDANCE_EDIT_DAYS . ' days'));
$date     = $_POST['date'] ?? $_GET['date'] ?? $today;
$date     = is_string($date) && valid_date($date) ? $date : $today;
$editable = $markable && $date <= $today && $date >= $earliest;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $back = 'course.php?course_id=' . $cid . '&date=' . $date;
    try {
        if (!$markable) {
            throw new InvalidArgumentException('Attendance can only be marked for active courses in the current semester.');
        }
        if (!$editable) {
            throw new InvalidArgumentException('You can only mark attendance for today or the last ' . ATTENDANCE_EDIT_DAYS . ' days.');
        }
        $statuses = $_POST['status'] ?? [];
        if (!is_array($statuses) || $statuses === []) {
            throw new InvalidArgumentException('There are no students to record.');
        }

        // Only students enrolled in this course may be marked.
        $enrolled = [];
        foreach (course_roster($pdo, $cid) as $s) {
            $enrolled[(int) $s['st_id']] = true;
        }
        $rows = [];
        foreach ($statuses as $stId => $status) {
            if (!is_int($stId) && !(is_string($stId) && ctype_digit($stId))) {
                throw new InvalidArgumentException('Invalid attendance data submitted.');
            }
            if (!isset($enrolled[(int) $stId])) {
                throw new InvalidArgumentException('A selected student is not enrolled in this course.');
            }
            if (!in_array($status, ['Present', 'Absent'], true)) {
                throw new InvalidArgumentException('Invalid attendance status submitted.');
            }
            $rows[(int) $stId] = $status;
        }

        // One row per student + course + date: saving again updates instead of duplicating.
        $upsert = $pdo->prepare(
            'INSERT INTO attendance (stat_id, course_id, sem_id, teacher_id, st_status, stat_date)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE st_status = VALUES(st_status), teacher_id = VALUES(teacher_id), sem_id = VALUES(sem_id)'
        );
        in_transaction($pdo, function () use ($upsert, $rows, $cid, $course, $me, $date): void {
            foreach ($rows as $stId => $status) {
                $upsert->execute([$stId, $cid, $course['sem_id'], $me['tc_id'], $status, $date]);
            }
        });
        flash('ok', 'Attendance saved successfully.');
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', 'Could not save attendance. Please try again.');
    }
    redirect($back);
}

$roster = course_roster($pdo, $cid);
$saved  = [];
$stmt = $pdo->prepare('SELECT stat_id, st_status FROM attendance WHERE course_id = ? AND stat_date = ?');
$stmt->execute([$cid, $date]);
foreach ($stmt->fetchAll() as $r) {
    $saved[(int) $r['stat_id']] = $r['st_status'];
}

page_start('teacher');
?>
<p><a href="index.php">&larr; My courses</a></p>
<h3><?= e($course['course_code']) ?> - <?= e($course['course_name']) ?></h3>
<p>
  Teacher: <strong><?= e($me['code']) ?> - <?= e($me['name']) ?></strong><br>
  Semester: <strong><?= e(semester_label($course)) ?></strong><br>
  Date: <strong><?= e(display_date($date)) ?></strong>
</p>

<form method="get" class="form-inline" style="margin-bottom:15px">
  <input type="hidden" name="course_id" value="<?= e($cid) ?>">
  <label>Change date </label>
  <input type="date" name="date" value="<?= e($date) ?>" max="<?= e($today) ?>" min="<?= e($earliest) ?>" class="form-control">
  <button class="btn btn-default" type="submit">Go</button>
  <a href="report.php?course_id=<?= e($cid) ?>">View report</a>
</form>

<?php if (!$markable): ?>
  <p class="msg-err">This course is not active in the current semester, so attendance is view-only.</p>
<?php elseif (!$editable): ?>
  <p class="msg-err">Attendance can only be marked for today or the last <?= e(ATTENDANCE_EDIT_DAYS) ?> days.</p>
<?php elseif ($saved === [] && $roster !== []): ?>
  <p>Attendance has not been saved for this date yet. Everyone starts as Present; change anyone who is absent, then save.</p>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="course_id" value="<?= e($cid) ?>">
  <input type="hidden" name="date" value="<?= e($date) ?>">
  <table class="table table-striped">
    <thead><tr><th>Student ID</th><th>Student Name</th><th>Batch</th><th>Attendance</th></tr></thead>
    <tbody>
      <?php foreach ($roster as $s): $stId = (int) $s['st_id']; $current = $saved[$stId] ?? 'Present'; ?>
        <tr>
          <td><?= e($s['student_code']) ?></td>
          <td><?= e($s['st_name']) ?></td>
          <td><?= e($s['st_batch']) ?></td>
          <td>
            <label style="font-weight:normal"><input type="radio" name="status[<?= $stId ?>]" value="Present" <?= $current === 'Present' ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?>> Present</label>
            &nbsp;
            <label style="font-weight:normal"><input type="radio" name="status[<?= $stId ?>]" value="Absent" <?= $current === 'Absent' ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?>> Absent</label>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($roster === []): ?><tr><td colspan="4">No students are enrolled in this course. Ask the administrator to enroll students.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php if ($editable && $roster !== []): ?>
    <p>
      <button type="button" class="btn btn-default btn-sm" onclick="document.querySelectorAll('input[value=Present]').forEach(function (r) { r.checked = true; })">Mark all Present</button>
      <button type="button" class="btn btn-default btn-sm" onclick="document.querySelectorAll('input[value=Absent]').forEach(function (r) { r.checked = true; })">Mark all Absent</button>
    </p>
    <button type="submit" class="btn btn-primary">Save Attendance</button>
  <?php endif; ?>
</form>
<?php page_end();
