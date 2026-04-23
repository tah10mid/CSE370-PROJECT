<?php
require_once __DIR__ . '/auth.php';
$page_title = $page_title ?? 'Thesis Finder';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> &middot; Thesis Finder</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="/index.php">ThesisFinder</a>
        <nav class="nav">
            <?php if (is_logged_in()): ?>
                <a href="/dashboard.php">Dashboard</a>
                <a href="/projects/index.php">Browse</a>
                <a href="/projects/create.php">New Work</a>
                <a href="/requests/index.php">Requests</a>
                <a href="/messages/index.php">Messages</a>
                <a href="/profile/index.php">Profile</a>
                <span class="sep">|</span>
                <span class="who">Hi, <?= h(current_user_name()) ?><?php
                    if (is_teacher()) echo ' (Teacher)';
                    elseif (is_student()) echo ' (Student)';
                ?></span>
                <a class="btn btn-ghost" href="/auth/logout.php">Logout</a>
            <?php else: ?>
                <a href="/auth/login.php">Login</a>
                <a class="btn btn-primary" href="/auth/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
<?php
$succ = flash('success');
$err  = flash('error');
if ($succ): ?><div class="alert alert-success"><?= h($succ) ?></div><?php endif;
if ($err):  ?><div class="alert alert-error"><?= h($err)  ?></div><?php endif; ?>
