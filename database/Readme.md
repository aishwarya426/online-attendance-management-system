# Database

Database name: `attsystem` (the app connects on port 3307; see `connect.php` in the project root).

## Fresh install
Import `attsystem.sql`. It creates the database, all tables and sample data.

    mysql -h 127.0.0.1 -P 3307 -u root -p < attsystem.sql

## Upgrading an existing (v1) database
1. **Back up first:** `mysqldump -h 127.0.0.1 -P 3307 -u root -p attsystem > backup.sql`
2. Run once: `mysql -h 127.0.0.1 -P 3307 -u root -p attsystem < migrate_v2.sql`

What the migration does (nothing is wiped):
* Adds `semesters`, `courses`, `teacher_courses`, `student_courses`, `id_counters`.
* Gives every existing student/teacher a unique ID (`STU0001`.., `TCH0001`.., in old-ID order).
* Links each existing login to a student/teacher profile (by matching email, otherwise a profile is created from the login).
* Old free-text attendance courses (`swe`, `networking`) become real courses in a "Legacy" semester, and the students who have attendance in them are enrolled. A current semester is created; adjust it under Admin > Semesters.
* Attendance gains `id`, `course_id`, `sem_id`, `teacher_id` (NULL for old rows) and a unique key on student + course + date. Duplicate rows for the same student/course/date are reduced to the latest one; the removed rows are saved in `attendance_legacy_duplicates`.
* Existing profiles that had no login keep none until the admin clicks "Create login" on their page.
* Plaintext passwords keep working and are upgraded to hashes on first login.

Requirements: every `attendance.stat_id` must match an existing student (otherwise adding the foreign key fails), and logins must have unique usernames.

## Tables
| Table | Purpose |
|---|---|
| `admininfo` | Login accounts. `st_id` / `tc_id` link a login to exactly one profile; `is_active` blocks login |
| `students`, `teachers` | Profiles. `student_code` / `teacher_code` are the institutional IDs |
| `semesters` | Academic year + semester number; exactly one has `is_current = 1` |
| `courses` | Course offerings in a semester (`course_code` is unique per semester) |
| `teacher_courses` | Which teacher teaches which course (unique pair) |
| `student_courses` | Which student is enrolled in which course (unique pair) |
| `attendance` | One row per student + course + date, with the teacher who recorded it |
| `id_counters` | Next number for `STU` / `TCH` IDs (row-locked, so IDs are never duplicated) |
| `reports` (view) | Flattened attendance, kept for backward compatibility |
