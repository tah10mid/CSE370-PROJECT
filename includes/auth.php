<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /auth/login.php');
        exit;
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
    header('Location: ' . $path);
    exit;
}
