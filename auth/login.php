<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) redirect('/dashboard.php');

$errors = [];
$email_in = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_in = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if ($email_in === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        $stmt = $conn->prepare('SELECT id, name, password, student_flag, teacher_flag FROM user WHERE email = ?');
        $stmt->bind_param('s', $email_in);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !password_verify($password, $row['password'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            $_SESSION['user_id']      = (int)$row['id'];
            $_SESSION['user_name']    = $row['name'];
            $_SESSION['student_flag'] = (int)$row['student_flag'];
            $_SESSION['teacher_flag'] = (int)$row['teacher_flag'];
            flash('success', 'Welcome back, ' . $row['name'] . '!');
            redirect('/dashboard.php');
        }
    }
}

$page_title = 'Login';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <h2>Log in</h2>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= h($e) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form">
        <label>
            Email
            <input type="email" name="email" required value="<?= h($email_in) ?>">
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <button class="btn btn-primary" type="submit">Log in</button>
    </form>
    <p class="muted">No account? <a href="/auth/register.php">Register here</a>.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
