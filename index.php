<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    redirect('/dashboard.php');
}
$page_title = 'Welcome';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <span class="hero-kicker">CSE370 &middot; BRAC University</span>
    <h1>Find the right thesis.<br>Find the right team.</h1>
    <p class="lead">
        ThesisFinder connects students with supervisors and teammates on thesis and project ideas.
        Post your work, join teams, message supervisors &mdash; everything in one place.
    </p>
    <div class="hero-actions">
        <a class="btn btn-primary" href="<?= url('/auth/register.php') ?>">Get started &rarr;</a>
        <a class="btn btn-ghost" href="<?= url('/auth/login.php') ?>">I already have an account</a>
    </div>
</section>

<section class="features">
    <div class="card" data-reveal>
        <h3>Post thesis &amp; projects</h3>
        <p>Students and teachers can post work with title, description, and supervision info.</p>
    </div>
    <div class="card" data-reveal>
        <h3>Team requests</h3>
        <p>Ask to join a project. Owners accept or reject. Status updates in real time.</p>
    </div>
    <div class="card" data-reveal>
        <h3>Direct messaging</h3>
        <p>Chat with teammates and supervisors straight from the platform.</p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
