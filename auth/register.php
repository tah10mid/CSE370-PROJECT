<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) redirect('/dashboard.php');

$errors = [];
$old = ['name' => '', 'email' => '', 'role' => 'student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm']  ?? '');
    $role     = $_POST['role'] ?? 'student';
    $old      = ['name' => $name, 'email' => $email, 'role' => $role];

    if ($name === '')                              $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 6)                     $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                    $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['student', 'teacher'], true)) $errors[] = 'Role must be student or teacher.';

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
                $s = $conn->prepare('INSERT INTO student (user_id, undergrad_flag, postgrad_flag) VALUES (?, 1, 0)');
                $s->bind_param('i', $uid);
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
        <button class="btn btn-primary" type="submit">Create account</button>
    </form>
    <p class="muted">Already registered? <a href="/auth/login.php">Log in</a>.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
