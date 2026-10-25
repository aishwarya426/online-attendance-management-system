<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$me = require_login($pdo, 'admin');

$id     = get_int('course_id');
$course = $id === null ? null : find_course($pdo, $id);
if ($course === null) {
    deny('Course not found.', 404);
}

page_start('admin', 'Course Attendance Report');
echo '<p><a href="attendance.php?sem_id=' . e($course['sem_id']) . '">&larr; Attendance overview</a></p>';
render_course_report($course, course_report($pdo, (int) $course['course_id']), course_sessions($pdo, (int) $course['course_id']));
page_end();
