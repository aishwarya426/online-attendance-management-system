<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me = require_login($pdo, 'teacher');

$courseId = get_int('course_id');
if ($courseId !== null) {
    // Only courses assigned to this teacher
    $course = teacher_course($pdo, $me['tc_id'], $courseId);
    if ($course === null) {
        deny('You are not assigned to this course.');
    }
    page_start('teacher', 'Course Attendance Report');
    echo '<p><a href="report.php">&larr; All my courses</a> &middot; <a href="course.php?course_id=' . e($courseId) . '">Mark attendance</a></p>';
    render_course_report(
        $course,
        course_report($pdo, $courseId),
        course_sessions($pdo, $courseId),
        'course.php?course_id=' . $courseId
    );
    page_end();
    exit;
}

$courses = teacher_courses_list($pdo, $me['tc_id'], null);
page_start('teacher', 'Attendance Reports');
?>
<table class="table table-striped">
  <thead><tr><th>Semester</th><th>Course</th><th>Students</th><th>Report</th></tr></thead>
  <tbody>
    <?php foreach ($courses as $c): ?>
      <tr>
        <td><?= e(semester_label($c)) ?></td>
        <td><?= e($c['course_code']) ?> - <?= e($c['course_name']) ?></td>
        <td><?= e($c['students']) ?></td>
        <td><a href="report.php?course_id=<?= e($c['course_id']) ?>">View Report</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($courses === []): ?><tr><td colspan="4">No courses are assigned to you.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php page_end();
