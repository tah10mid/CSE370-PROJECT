<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Compute the URL prefix where the app is mounted (e.g. "" or "/cse370-project-main").
if (!defined('BASE_URL')) {
    $__base = '';
    $docRoot  = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
    $projRoot = realpath(__DIR__ . '/..');
    if ($docRoot && $projRoot && strpos($projRoot, $docRoot) === 0) {
        $__base = str_replace('\\', '/', substr($projRoot, strlen($docRoot)));
        $__base = '/' . trim($__base, '/');
        if ($__base === '/') $__base = '';
    }
    define('BASE_URL', $__base);
}

function url(string $path): string {
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
    return BASE_URL . $path;
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . url('/auth/login.php'));
        exit;
    }
    // Stale-session guard: if the database was reset while a user was logged
    // in, their session id no longer exists in `user`. Verify and bounce them
    // back to login instead of letting a foreign-key error blow up.
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        $uid = (int)current_user_id();
        $stmt = $conn->prepare('SELECT id, name, student_flag, teacher_flag FROM user WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            session_unset();
            flash('error', 'Your session is no longer valid. Please log in again.');
            header('Location: ' . url('/auth/login.php'));
            exit;
        }
        // Refresh cached session data in case role flags or name changed.
        $_SESSION['user_name']    = $row['name'];
        $_SESSION['student_flag'] = (int)$row['student_flag'];
        $_SESSION['teacher_flag'] = (int)$row['teacher_flag'];
    }
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function current_user_name(): ?string {
    return $_SESSION['user_name'] ?? null;
}

function is_student(): bool {
    return !empty($_SESSION['student_flag']);
}

function is_teacher(): bool {
    return !empty($_SESSION['teacher_flag']);
}

function require_teacher(): void {
    require_login();
    if (!is_teacher()) {
        http_response_code(403);
        die('Only teachers can access this page.');
    }
}

function require_student(): void {
    require_login();
    if (!is_student()) {
        http_response_code(403);
        die('Only students can access this page.');
    }
}

function flash(string $key, ?string $msg = null) {
    if ($msg === null) {
        $v = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $v;
    }
    $_SESSION['flash'][$key] = $msg;
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void {
    if ($path !== '' && $path[0] === '/' && strpos($path, '//') !== 0) {
        $path = url($path);
    }
    header('Location: ' . $path);
    exit;
}
