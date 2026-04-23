<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = current_user_id();

// My works (owner or joined or supervising)
$mine = $conn->prepare("
    SELECT w.project_id, w.title, w.thesis_flag, w.project_flag, w.created_at,
           u.name AS owner_name,
           (SELECT project_status FROM project_status WHERE project_id = w.project_id ORDER BY project_status LIMIT 1) AS status
    FROM work w
    JOIN user u ON u.id = w.owner_id
    WHERE w.owner_id = ?
       OR w.supervisor_id = ?
       OR EXISTS (SELECT 1 FROM student_join_project sjp WHERE sjp.project_id = w.project_id AND sjp.id = ?)
    ORDER BY w.created_at DESC
    LIMIT 10
");
$mine->bind_param('iii', $uid, $uid, $uid);
$mine->execute();
$myWorks = $mine->get_result()->fetch_all(MYSQLI_ASSOC);
$mine->close();

// Incoming team requests on projects I own
$inc = $conn->prepare("
    SELECT tr.request_id, tr.requested_at, w.title, u.name AS requester_name,
           (SELECT team_request_status FROM team_request_status WHERE request_id = tr.request_id ORDER BY team_request_status LIMIT 1) AS status
    FROM team_request tr
    JOIN work w ON w.project_id = tr.project_id
    JOIN user u ON u.id = tr.requester_id
    WHERE w.owner_id = ?
    ORDER BY tr.requested_at DESC
    LIMIT 5
");
$inc->bind_param('i', $uid);
$inc->execute();
$incoming = $inc->get_result()->fetch_all(MYSQLI_ASSOC);
$inc->close();

// Unread-ish messages (latest 5)
$msg = $conn->prepare("
    SELECT m.message_id, m.body, m.sent_at, u.name AS sender_name
    FROM message m
    JOIN user u ON u.id = m.sender_id
    WHERE m.receiver_id = ?
    ORDER BY m.sent_at DESC
    LIMIT 5
");
$msg->bind_param('i', $uid);
$msg->execute();
$latestMsgs = $msg->get_result()->fetch_all(MYSQLI_ASSOC);
$msg->close();

$page_title = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<h1>Dashboard</h1>
<p class="muted">Quick look at your thesis / project activity.</p>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <h2>My Work</h2>
            <a class="btn btn-primary btn-sm" href="/projects/create.php">+ New</a>
        </div>
        <?php if (!$myWorks): ?>
            <p class="muted">You haven't created or joined any work yet. <a href="/projects/index.php">Browse work</a>.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($myWorks as $w): ?>
                    <li>
                        <a href="/projects/view.php?id=<?= (int)$w['project_id'] ?>"><strong><?= h($w['title']) ?></strong></a>
                        <span class="tags">
                            <?php if ($w['thesis_flag']):  ?><span class="tag tag-thesis">Thesis</span><?php endif; ?>
                            <?php if ($w['project_flag']): ?><span class="tag tag-project">Project</span><?php endif; ?>
                            <?php if ($w['status']): ?><span class="tag"><?= h($w['status']) ?></span><?php endif; ?>
                        </span>
                        <div class="muted small">by <?= h($w['owner_name']) ?> &middot; <?= h($w['created_at']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>Incoming Requests</h2>
            <a class="btn btn-ghost btn-sm" href="/requests/index.php">View all</a>
        </div>
        <?php if (!$incoming): ?>
            <p class="muted">No team requests right now.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($incoming as $r): ?>
                    <li>
                        <strong><?= h($r['requester_name']) ?></strong> wants to join
                        <em><?= h($r['title']) ?></em>
                        <span class="tag"><?= h($r['status'] ?? 'pending') ?></span>
                        <div class="muted small"><?= h($r['requested_at']) ?></div>
                        <a class="btn btn-sm" href="/requests/index.php#req-<?= (int)$r['request_id'] ?>">Review</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-head">
        <h2>Latest Messages</h2>
        <a class="btn btn-ghost btn-sm" href="/messages/index.php">Open inbox</a>
    </div>
    <?php if (!$latestMsgs): ?>
        <p class="muted">No messages yet.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($latestMsgs as $m): ?>
                <li>
                    <strong><?= h($m['sender_name']) ?></strong>
                    <div><?= h(mb_strimwidth($m['body'], 0, 140, '...')) ?></div>
                    <div class="muted small"><?= h($m['sent_at']) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
