# Thesis Finder — CSE370 Project

A web application that connects **students** with **thesis/project ideas** and
**supervisors** (teachers). Built on the 3NF schema shown in the
`EER-FINAL.pdf`, `Schema-Final.pdf`, and normalization diagrams of the
submission.

Stack: **PHP 7.4+ / 8.x** + **MySQL 5.7+ (or MariaDB)** + vanilla HTML/CSS.
No frameworks, no build step — drop it in XAMPP / MAMP / stock Apache and go.

---

## Features (fully functional CRUD)

### Accounts
- Register as **Student** or **Teacher** (role stored via `student_flag` /
  `teacher_flag` on `user`, plus a row in `student` or `teacher`).
- Secure password hashing (`password_hash` / `password_verify`).
- Login, logout, session-based auth with role gates.

### Profile
- Edit core account info (name, email).
- **Student** fields: CGPA, preferable coding language, thesis/project
  starting time, dept, semester, undergrad/postgrad flags.
- **Teacher** fields: consultation time.
- Manage **multivalued** attributes (add / delete chips):
  - user → skills, project interests, thesis interests, previous work
  - teacher → project interests, thesis interests, thesis slots

### Work (thesis / project) — full CRUD
- Create, view, edit, delete work (`work` table).
- Mark a work as **thesis**, **project**, or **both** (flag columns).
- Optional supervisor (teacher).
- Manage **project_status** multivalued history (open, in_progress,
  completed, closed).
- Students **join** / **leave** a project (`student_join_project`).
- Teachers can **take** or **drop** supervision.
- Browse & search (title/description) with filters: all / thesis / project /
  only mine.

### Team Requests
- Non-members send **team request** to join a work.
- Status history in `team_request_status` (pending → accepted / rejected).
- Owner accepts (auto-joins the requester if student) or rejects.
- Requester can cancel a pending request.

### Messaging
- 1-to-1 threaded chat between any two users (`message` table).
- Each message is linked in the `sends_recieves` table for both sides, and
  can optionally reference a team request.
- Inbox with latest conversation per partner, plus "new chat" picker.

---

## Project structure

```
CSE370-PROJECT/
├── sql/
│   ├── schema.sql       # Run first — creates database + tables + indexes
│   └── seed.sql         # Optional — inserts demo users / works / messages
├── config/
│   └── db.php           # MySQLi connection (reads env vars, defaults to root/no-pw)
├── includes/
│   ├── auth.php         # session / role helpers / h() escaper / flash
│   ├── header.php       # shared top bar + layout open
│   └── footer.php       # shared layout close
├── auth/
│   ├── register.php
│   ├── login.php
│   └── logout.php
├── profile/
│   └── index.php        # core + student/teacher + multivalued attrs
├── projects/
│   ├── index.php        # browse / filter
│   ├── create.php       # new work
│   └── view.php         # detail + edit / delete / join / supervise / status
├── requests/
│   └── index.php        # incoming + outgoing team requests
├── messages/
│   ├── index.php        # inbox / new chat picker
│   └── thread.php       # 1-to-1 chat
├── assets/css/style.css # styles
├── dashboard.php        # post-login landing
└── index.php            # public landing
```

---

## Setup (XAMPP / MAMP)

1. **Clone** into your web root (e.g. `C:\xampp\htdocs\CSE370-PROJECT` or
   `/Applications/MAMP/htdocs/CSE370-PROJECT`).
2. Start **Apache** + **MySQL** from the XAMPP/MAMP control panel.
3. Open **phpMyAdmin** → Import → choose `sql/schema.sql` → Go.
4. (Optional) Import `sql/seed.sql` for demo data.
   Demo accounts email/password: `alice@uni.edu` / `password123`,
   `drsmith@uni.edu` / `password123`, etc.
5. Edit `config/db.php` if your MySQL user/password differs from
   `root` / empty string, or export env vars `DB_HOST`, `DB_USER`,
   `DB_PASS`, `DB_NAME`.
6. Visit `http://localhost/CSE370-PROJECT/`.

> If you use PHP's built-in dev server from the project root:
> `php -S localhost:8000` then open `http://localhost:8000/`.

---

## How the schema maps to the EER / 3NF

| Entity in EER            | Table(s)                                                            |
| ------------------------ | ------------------------------------------------------------------- |
| USER                     | `user`                                                              |
| Student (ISA User)       | `student`                                                           |
| Teacher (ISA User)       | `teacher`                                                           |
| User multivalued         | `user_project_iskill`, `user_project_interest`, `user_thesis_interest`, `user_previous_work` |
| Teacher multivalued      | `teacher_project_interest`, `teacher_thesis_interest`, `teacher_thesisslot`                 |
| Work (thesis d project)  | `work` (+ `project_status` multivalued)                             |
| supervises               | `work.supervisor_id → teacher.user_id`                              |
| joins                    | `student_join_project`                                              |
| Team_request             | `team_request` (+ `team_request_status` multivalued)                |
| message                  | `message`                                                           |
| sends/recieves           | `sends_recieves`                                                    |

All identifying relationships use `ON DELETE CASCADE`. Supervisor uses
`ON DELETE SET NULL` so a teacher leaving the system does not delete the
thesis/project they were advising.

---

## Security notes

- Every SQL query uses **prepared statements** (`mysqli::prepare` +
  `bind_param`), no string concatenation of user input.
- Output is escaped via `h()` (`htmlspecialchars` with ENT_QUOTES).
- Passwords stored with `password_hash(..., PASSWORD_DEFAULT)` (bcrypt).
- Role checks on every POST action in `view.php` and `requests/index.php`.

---

## Git branch

All development lives on branch **`claude/thesis-finder-database-setup-fgZog`**.
Switch to it on GitHub to see every file listed above — the `main` branch
still only has the original README.
