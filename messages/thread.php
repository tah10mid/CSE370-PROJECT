<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$uid   = current_user_id();
$other = (int)($_GET['with'] ?? 0);
if ($other <= 0 || $other === $uid) { flash('error','Invalid conversation.'); redirect('/messages/index.php'); }

// Verify other exists
$q = $conn->prepare('SELECT id, name FROM user WHERE id = ?');
$q->bind_param('i', $other); $q->execute();
$peer = $q->get_result()->fetch_assoc(); $q->close();
if (!$peer) { flash('error','User not found.'); redirect('/messages/index.php'); }

// Send a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body     = trim($_POST['body'] ?? '');
    $reqId    = $_POST['request_id'] !== '' ? (int)$_POST['request_id'] : null;
    if ($body !== '') {
        $conn->begin_transaction();
        try {
            $ins = $conn->prepare('INSERT INTO message (sender_id, receiver_id, body) VALUES (?, ?, ?)');
            $ins->bind_param('iis', $uid, $other, $body);
            $ins->execute();
            $mid = $conn->insert_id;
            $ins->close();

            // Link in sends_recieves (both sides)
            $lnk = $conn->prepare('INSERT IGNORE INTO sends_recieves (message_id, user_id, request_id) VALUES (?, ?, ?)');
            $lnk->bind_param('iii', $mid, $uid, $reqId);
            $lnk->execute();
            $lnk->bind_param('iii', $mid, $other, $reqId);
            $lnk->execute();
            $lnk->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            flash('error', 'Failed to send: ' . $e->getMessage());
        }
    }
    redirect('/messages/thread.php?with=' . $other);
}

// Fetch thread
$t = $conn->prepare('
    SELECT m.*, u.name AS sender_name
    FROM message m
    JOIN user u ON u.id = m.sender_id
    WHERE (m.sender_id = ? AND m.receiver_id = ?)
       OR (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.sent_at ASC
');
$t->bind_param('iiii', $uid, $other, $other, $uid);
$t->execute();
$messages = $t->get_result()->fetch_all(MYSQLI_ASSOC);
$t->close();

// Optional: any open team request between us to tag messages to
$r = $conn->prepare('
    SELECT tr.request_id, w.title
    FROM team_request tr
    JOIN work w ON w.project_id = tr.project_id
    WHERE (tr.requester_id = ? AND w.owner_id = ?)
       OR (tr.requester_id = ? AND w.owner_id = ?)
    ORDER BY tr.requested_at DESC
');
$r->bind_param('iiii', $uid, $other, $other, $uid);
$r->execute();
$linkReqs = $r->get_result()->fetch_all(MYSQLI_ASSOC);
$r->close();

$page_title = 'Chat with ' . $peer['name'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Chat with <?= h($peer['name']) ?></h1>
    <a class="btn btn-ghost" href="<?= url('/messages/index.php') ?>">Back to inbox</a>
</div>

<section class="card chat">
    <?php if (!$messages): ?>
        <p class="muted">No messages yet. Say hi!</p>
    <?php else: ?>
        <ul class="chat-list">
            <?php foreach ($messages as $m):
                $mine = ((int)$m['sender_id'] === $uid); ?>
                <li class="chat-msg <?= $mine ? 'mine' : 'theirs' ?>">
                    <div class="bubble">
                        <?= nl2br(h($m['body'])) ?>
                    </div>
                    <div class="muted small">
                        <?= h($mine ? 'You' : $m['sender_name']) ?> &middot; <?= h($m['sent_at']) ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="chat-form">
        <textarea name="body" rows="3" placeholder="Type your message" required></textarea>
        <?php if ($linkReqs): ?>
            <label class="inline small">Link to request (optional):
                <select name="request_id">
                    <option value="">None</option>
                    <?php foreach ($linkReqs as $lr): ?>
                        <option value="<?= (int)$lr['request_id'] ?>">#<?= (int)$lr['request_id'] ?> · <?= h($lr['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php else: ?>
            <input type="hidden" name="request_id" value="">
        <?php endif; ?>
        <button class="btn btn-primary">Send</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
