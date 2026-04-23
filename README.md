# Thesis Finder (CSE370 Project)

A web app that lets university students find thesis and project ideas,
request to join teams, and talk to supervisors. Built on the 3NF schema
from our normalization submission.

Stack: PHP + MySQL + plain HTML/CSS. Runs on XAMPP / MAMP / stock Apache.

## Features

Accounts
- Register as Student or Teacher
- Password hashing with bcrypt (`password_hash` / `password_verify`)
- Session login/logout, role-based access

Profile
- Edit name and email
- Student: CGPA, preferred coding language, starting times, dept, semester,
  undergrad/postgrad flags
- Teacher: consultation time
- Add/remove chips for skills, project interests, thesis interests,
  previous work (user multivalued attributes)
- Teacher extras: supervision project areas, thesis areas, thesis slots

Work (thesis / project) CRUD
- Post a thesis, project, or both
- Assign or change supervisor
- Track status history (open, in_progress, completed, closed)
- Edit, delete
- Students can join / leave
- Teachers can take or drop supervision
- Browse and search by keyword, filter by type, filter to only mine

Team requests
- Send a request to join someone else's work
- Owner accepts or rejects; accepted student auto-joins the project
- Requester can cancel a pending request
- Status stored in the multivalued `team_request_status` table

Messaging
- One-to-one chat between any two users
- Each message is linked in `sends_recieves` for both sides
- Optional link to a team request

## Folder layout

```
sql/schema.sql          create DB + tables + indexes
sql/seed.sql            demo data
config/db.php           MySQLi connection
includes/               session, layout, helpers
auth/                   register, login, logout
profile/index.php       profile + multivalued attributes
projects/               browse, create, view (edit/delete/join/supervise)
requests/index.php      incoming + outgoing team requests
messages/               inbox + chat thread
dashboard.php           post-login landing
index.php               public landing
assets/css/style.css    styles
```

## How the schema maps to the EER diagram

| EER                                         | Table(s) |
| ------------------------------------------- | -------- |
| USER                                        | `user` |
| Student ISA User                            | `student` |
| Teacher ISA User                            | `teacher` |
| User multivalued (skill, interests, prev)   | `user_project_iskill`, `user_project_interest`, `user_thesis_interest`, `user_previous_work` |
| Teacher multivalued                         | `teacher_project_interest`, `teacher_thesis_interest`, `teacher_thesisslot` |
| Work (thesis disjoint project)              | `work` (+ `project_status` multivalued) |
| supervises                                  | `work.supervisor_id` FK to `teacher.user_id` |
| joins                                       | `student_join_project` |
| Team_request                                | `team_request` (+ `team_request_status` multivalued) |
| message                                     | `message` |
| sends / recieves                            | `sends_recieves` |

## Setup

1. Put the folder inside your web root (`C:\xampp\htdocs\` or `htdocs` on MAMP).
2. Start Apache and MySQL.
3. Open phpMyAdmin, Import `sql/schema.sql`, then optionally `sql/seed.sql`.
4. If your MySQL user is not `root` with empty password, edit `config/db.php`.
5. Visit `http://localhost/CSE370-PROJECT/`.

You can also run it without Apache:
```
php -S localhost:8000
```
then open http://localhost:8000/

Demo logins from the seed file (password is `password123`):
- alice@g.bracu.ac.bd (student)
- drsmith@bracu.ac.bd (teacher)

Account policy: students must register with their BRACU G-Suite address
(`@g.bracu.ac.bd`); faculty must register with `@bracu.ac.bd`. This is
enforced in `auth/register.php`.

## Notes

- All SQL uses prepared statements.
- Output is escaped with `htmlspecialchars`.
- `ON DELETE CASCADE` on identifying relationships; supervisor uses
  `ON DELETE SET NULL` so a teacher leaving doesn't drop supervised work.
