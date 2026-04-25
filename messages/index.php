<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();

// Latest message per conversation partner
$sql = "
    SELECT u.id AS other_id, u.name AS other_name, m2.body, m2.sent_at
    FROM (
        SELECT
            CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_id,
            MAX(sent_at) AS latest
        FROM message
        WHERE sender_id = ? OR receiver_id = ?
        GROUP BY other_id
    ) t
    JOIN user u ON u.id = t.other_id
    JOIN message m2 ON m2.sent_at = t.latest
      AND ((m2.sender_id = ? AND m2.receiver_id = t.other_id) OR (m2.receiver_id = ? AND m2.sender_id = t.other_id))
    ORDER BY m2.sent_at DESC
";
$st = $conn->prepare($sql);
$st->bind_param('iiiii', $uid, $uid, $uid, $uid, $uid);
$st->execute();
$convos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

// All other users for starting a new chat
$users = $conn->prepare('SELECT id, name, student_flag, teacher_flag FROM user WHERE id <> ? ORDER BY name');
$users->bind_param('i', $uid); $users->execute();
$allUsers = $users->get_result()->fetch_all(MYSQLI_ASSOC);
$users->close();

$page_title = 'Messages';
require __DIR__ . '/../includes/header.php';
?>

<h1>Messages</h1>

<div class="grid-2">
    <section class="card">
        <h2>Conversations</h2>
        <?php if (!$convos): ?>
            <p class="muted">No conversations yet.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($convos as $c): ?>
                    <li>
                        <a href="<?= url('/messages/thread.php') ?>?with=<?= (int)$c['other_id'] ?>"><strong><?= h($c['other_name']) ?></strong></a>
                        <div><?= h(mb_strimwidth($c['body'], 0, 120, '...')) ?></div>
                        <div class="muted small"><?= h($c['sent_at']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Start a new chat</h2>
        <form method="get" action="<?= url('/messages/thread.php') ?>" class="form-row">
            <label>To
                <select name="with" required>
                    <option value="">Select user</option>
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= h($u['name']) ?><?php
                            if ($u['teacher_flag']) echo ' (Teacher)';
                            elseif ($u['student_flag']) echo ' (Student)';
                        ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" type="submit">Open</button>
        </form>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
