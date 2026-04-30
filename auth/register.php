<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) redirect('/dashboard.php');

$errors = [];
$old = ['name' => '', 'email' => '', 'role' => 'student', 'student_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = (string)($_POST['password'] ?? '');
    $confirm    = (string)($_POST['confirm']  ?? '');
    $role       = $_POST['role'] ?? 'student';
    $student_id = trim($_POST['student_id'] ?? '');
    $old        = ['name' => $name, 'email' => $email, 'role' => $role, 'student_id' => $student_id];

    if ($name === '')                              $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 6)                     $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                    $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['student', 'teacher'], true)) $errors[] = 'Role must be student or teacher.';

    $email_lower     = strtolower($email);
    $is_student_mail = substr($email_lower, -strlen('@g.bracu.ac.bd')) === '@g.bracu.ac.bd';
    $is_faculty_mail = !$is_student_mail
        && substr($email_lower, -strlen('@bracu.ac.bd')) === '@bracu.ac.bd';

    if ($role === 'student' && !$is_student_mail) {
        $errors[] = 'Students must register with their BRACU G-Suite email (ending in @g.bracu.ac.bd).';
    }
    if ($role === 'teacher' && !$is_faculty_mail) {
        $errors[] = 'Faculty must register with their BRACU email (ending in @bracu.ac.bd).';
    }

    if ($role === 'student') {
        if ($student_id === '') {
            $errors[] = 'Student ID is required.';
        } elseif (!preg_match('/^[0-9]{5,10}$/', $student_id)) {
            $errors[] = 'Student ID must be 5-10 digits (your BRACU ID).';
        } else {
            $chk = $conn->prepare('SELECT student_id FROM student WHERE student_id = ?');
            $chk->bind_param('s', $student_id);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $errors[] = 'That Student ID is already registered.';
            }
            $chk->close();
        }
    }

    if (!$errors) {
        $chk = $conn->prepare('SELECT id FROM user WHERE email = ?');
        $chk->bind_param('s', $email);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $errors[] = 'An account with that email already exists.';
        }
        $chk->close();
    }

    if (!$errors) {
        $conn->begin_transaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sflag = $role === 'student' ? 1 : 0;
            $tflag = $role === 'teacher' ? 1 : 0;

            $ins = $conn->prepare('INSERT INTO user (email, password, name, student_flag, teacher_flag) VALUES (?, ?, ?, ?, ?)');
            $ins->bind_param('sssii', $email, $hash, $name, $sflag, $tflag);
            $ins->execute();
            $uid = $conn->insert_id;
            $ins->close();

            if ($role === 'student') {
                $s = $conn->prepare('INSERT INTO student (student_id, user_id, undergrad_flag, postgrad_flag) VALUES (?, ?, 1, 0)');
                $s->bind_param('si', $student_id, $uid);
                $s->execute();
                $s->close();
            } else {
                $t = $conn->prepare('INSERT INTO teacher (user_id, consultation_time) VALUES (?, NULL)');
                $t->bind_param('i', $uid);
                $t->execute();
                $t->close();
            }
            $conn->commit();

            $_SESSION['user_id']      = $uid;
            $_SESSION['user_name']    = $name;
            $_SESSION['student_flag'] = $sflag;
            $_SESSION['teacher_flag'] = $tflag;
            flash('success', 'Welcome to ThesisFinder! Complete your profile to get started.');
            redirect('/profile/index.php');
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

$page_title = 'Register';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <h2>Create your account</h2>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= h($e) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form">
        <label>
            Full name
            <input type="text" name="name" required value="<?= h($old['name']) ?>">
        </label>
        <label>
            Email
            <input type="email" name="email" required value="<?= h($old['email']) ?>">
            <small class="muted">Students: use your @g.bracu.ac.bd address. Faculty: use your @bracu.ac.bd address.</small>
        </label>
        <label>
            Password
            <input type="password" name="password" required minlength="6">
        </label>
        <label>
            Confirm password
            <input type="password" name="confirm" required minlength="6">
        </label>
        <fieldset class="role-select">
            <legend>I am a</legend>
            <label class="inline">
                <input type="radio" name="role" value="student" <?= $old['role'] === 'student' ? 'checked' : '' ?>>
                Student
            </label>
            <label class="inline">
                <input type="radio" name="role" value="teacher" <?= $old['role'] === 'teacher' ? 'checked' : '' ?>>
                Teacher
            </label>
        </fieldset>
        <label data-only-for="student" <?= $old['role'] === 'teacher' ? 'hidden' : '' ?>>
            Student ID
            <input type="text" name="student_id" inputmode="numeric" pattern="[0-9]{5,10}"
                   placeholder="e.g. 21301234"
                   value="<?= h($old['student_id']) ?>">
            <small class="muted">Your BRACU student ID (5-10 digits).</small>
        </label>
        <button class="btn btn-primary" type="submit">Create account</button>
    </form>
    <p class="muted">Already registered? <a href="<?= url('/auth/login.php') ?>">Log in</a>.</p>
</div>

<script>
(function () {
    var radios = document.querySelectorAll('input[name="role"]');
    var sidField = document.querySelector('[data-only-for="student"]');
    var sidInput = sidField ? sidField.querySelector('input') : null;
    function sync() {
        var role = document.querySelector('input[name="role"]:checked');
        var isStudent = role && role.value === 'student';
        if (!sidField) return;
        sidField.hidden = !isStudent;
        if (sidInput) {
            sidInput.required = isStudent;
            if (!isStudent) sidInput.value = '';
        }
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
