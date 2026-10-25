# Online Attendance Management System 1.0
Online Attendance Management System 1.0 is developed in a standard way using  HTML, CSS, PHP and MySQL for course project purpose. :blush:
## Requirements
  - PHP 8.1 or higher (with the `pdo_mysql` extension).
  - MySQL 5.7+ / 8.x or MariaDB 10.x.
  - XAMPP or WAMP server for localhost.
## Installation
 - Intall the requirements, if you already have it then skip.
 - Download the source code and extract the source code to a web accessible folder.
 - Import `database/attsystem.sql` (it creates the `attsystem` database itself).
 - Database settings (host `127.0.0.1`, **port 3307**, user, password) are in `connect.php` in the project root. Change them there if your setup differs.
 - **Upgrading an existing database?** Back it up, then run `database/migrate_v2.sql` once instead (see `database/Readme.md`). Existing students, teachers, logins and attendance are kept.
 - Open the project in your browser.
 - Sample logins (password `admin` for all): `admin` (Admin), `teacher` (Teacher, TCH0002), `student` (Student, STU0001).
 - There is no public signup. Only the admin creates accounts.

## How it works

 - **Admin** manages students, teachers, courses and semesters. Adding a student/teacher generates a unique ID (`STU0001`, `TCH0001`, ...) and a login whose username is that ID, with a one-time temporary password shown to the admin. The admin assigns courses to teachers and enrolls students in courses.
 - **Teacher** sees only the courses assigned to them for the current semester, opens a course, sees only its enrolled students, and marks Present/Absent (saving again for the same date updates it). Reports cover only their courses.
 - **Student** sees their own enrolled courses and course-wise attendance.
 - The **current semester** is set under Admin > Semesters. Old semesters and their attendance are kept.
 - Passwords are changed under Account / My Profile; admins can reset a password (a new temporary one is generated).

 
## Features
- User Friendly, Secured :lock:
- Clean Students Information :boy:
- Clean Teachers Information :man:
- Hassle Free Attendance within seconds :smile:
- Reports Generation :chart_with_upwards_trend:
- Easiest Login,Logout,Signup,Data Update :hourglass:
- Nicely Designed Database :mailbox_closed:

## Supporting Materials/Semester Courses
- Exploring CRUD (Create,Read,Update,Delete) in PHP
- Database Management System.
- Database Design.
- Simple Flat Front End design at thinkful.com
- Exploring HTML,CSS at w3schools.com
- System Analysis and Design.
- Learn how to learn.  

## License
MIT   

## Thanks
Feel free to create PRs and create issues for suggestions or discussion.
