# QuizArena: Online Exam Platform

A full-stack quiz and online exam platform built with **PHP, MySQL, HTML, CSS and JavaScript**.
Students take timed multiple-choice exams, get auto-graded results with explanations, and compete on a leaderboard. Admins manage exams, questions and results.

## Features

**Students**
- Register / login (hashed passwords, session security, login lockout after 5 failures)
- Dashboard with stats (exams taken, average score, best score) and result history
- Timed exams with a live countdown (the server is the source of truth for time)
- Autosave of every answer, so a refresh or crash never loses progress, and attempts can be resumed
- Question navigator, mark for review, clear answer, keyboard shortcuts (A-D, 1-4, arrow keys)
- Auto-submit when the timer ends
- Instant auto-grading with a full answer review and explanations
- Pass / fail status, rank, and time taken
- Leaderboard per exam (best score, then fastest time)
- Light and dark theme

**Admin**
- Dashboard with totals, pass rate and average score per exam
- Create, edit, hide and delete exams (duration, pass mark, max attempts)
- Question bank: add, edit, delete, and bulk import from CSV
- Results table with search, exam filter, pagination and CSV export

**Security**
- PDO prepared statements everywhere (no SQL injection)
- CSRF tokens on every form and on the autosave endpoint
- Output escaping (no XSS)
- Correct answers are never sent to the browser while an exam is running
- Server-side timer enforcement and grading
- CSV export protected against formula injection

## Setup (XAMPP / WAMP / any PHP host)

1. Copy the `quiz-platform` folder into `htdocs` (XAMPP) or `www` (WAMP).
2. Start Apache and MySQL.
3. Open phpMyAdmin, go to **Import**, and import `database.sql`. This creates the `quiz_platform` database with demo data.
4. If your MySQL password is not empty, edit the DB settings at the top of `config.php`.
5. Visit `http://localhost/quiz-platform/`.

Requires PHP 8.0 or newer.

### Demo logins
| Role    | Email             | Password    |
|---------|-------------------|-------------|
| Admin   | admin@quiz.com    | Admin@123   |
| Student | student@quiz.com  | Student@123 |

**Change the admin password before putting this online.**

## Deploying (e.g. InfinityFree)
1. Create a MySQL database in the control panel and import `database.sql` (remove the first two lines, `CREATE DATABASE` and `USE`, if your host does not allow them).
2. Put the DB host, name, user and password into `config.php`.
3. Upload all files into `htdocs` with FTP or the file manager.

## Project structure

```
quiz-platform/
  config.php          DB connection, sessions, helpers (CSRF, flash, escaping)
  database.sql        Schema + demo users, 3 exams, 24 questions
  index.php           Landing page
  register.php / login.php / logout.php
  dashboard.php       Student dashboard
  exam.php            Instructions, start, and the exam screen
  save.php            AJAX autosave endpoint
  submit.php          Final submission
  result.php          Score and answer review
  leaderboard.php
  includes/           header, footer, grader (scoring logic)
  admin/              dashboard, exams, questions, results
  assets/             style.css, app.js, exam.js
  sample_questions.csv
```

## Database

`users` -> `attempts` <- `exams` -> `questions`; `answers` links an attempt to the chosen option for each question.

## Resume bullets (edit to match what you built and tested)

**QuizArena: Online Exam Platform** | PHP, MySQL, JavaScript, HTML, CSS
- Built a full-stack exam platform with student and admin roles, timed exams, auto-grading and per-exam leaderboards.
- Designed a relational schema (users, exams, questions, attempts, answers) and implemented server-side timer enforcement with auto-submit and answer autosave via AJAX.
- Secured the app with PDO prepared statements, CSRF protection, password hashing and output escaping.
- Built an admin panel with question-bank management, CSV bulk import, filtered results and CSV export.
