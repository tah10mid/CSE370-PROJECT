<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();

// Handle POST: accept/reject/cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rid    = (int)($_POST['request_id'] ?? 0);
    try {
        // Verify request and relationship
        $q = $conn->prepare('SELECT tr.*, w.owner_id FROM team_request tr JOIN work w ON w.project_id = tr.project_id WHERE tr.request_id = ?');
        $q->bind_param('i', $rid); $q->execute();
        $req = $q->get_result()->fetch_assoc(); $q->close();
        if (!$req) throw new Exception('Request not found.');

        if (in_array($action, ['accept','reject'], true)) {
            if ((int)$req['owner_id'] !== $uid) throw new Exception('Only owner can decide.');
            $new = $action === 'accept' ? 'accepted' : 'rejected';
            // Wipe old statuses then add final
            $conn->begin_transaction();
            $d = $conn->prepare('DELETE FROM team_request_status WHERE request_id = ?');
            $d->bind_param('i', $rid); $d->execute(); $d->close();
            $i = $conn->prepare('INSERT INTO team_request_status (request_id, team_request_status) VALUES (?, ?)');
            $i->bind_param('is', $rid, $new); $i->execute(); $i->close();

            if ($action === 'accept') {
                // If requester is a student, auto-join
                $chk = $conn->prepare('SELECT user_id FROM student WHERE user_id = ?');
                $chk->bind_param('i', $req['requester_id']); $chk->execute();
                $isStu = $chk->get_result()->fetch_assoc(); $chk->close();
                if ($isStu) {
                    $j = $conn->prepare('INSERT IGNORE INTO student_join_project (project_id, id) VALUES (?, ?)');
                    $j->bind_param('ii', $req['project_id'], $req['requester_id']);
                    $j->execute(); $j->close();
                }
            }
            $conn->commit();
            flash('success', 'Request ' . $new . '.');
        }
        elseif ($action === 'cancel') {
            if ((int)$req['requester_id'] !== $uid) throw new Exception('Only requester can cancel.');
            $d = $conn->prepare('DELETE FROM team_request WHERE request_id = ?');
            $d->bind_param('i', $rid); $d->execute(); $d->close();
            flash('success', 'Request cancelled.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/requests/index.php');
}

// Incoming (I am owner of project)
$inc = $conn->prepare("
    SELECT tr.request_id, tr.requested_at, w.project_id, w.title, u.id AS requester_id, u.name AS requester_name,
           (SELECT team_request_status FROM team_request_status WHERE request_id = tr.request_id ORDER BY team_request_status LIMIT 1) AS status
    FROM team_request tr
    JOIN work w ON w.project_id = tr.project_id
    JOIN user u ON u.id = tr.requester_id
    WHERE w.owner_id = ?
    ORDER BY tr.requested_at DESC
");
$inc->bind_param('i', $uid); $inc->execute();
$incoming = $inc->get_result()->fetch_all(MYSQLI_ASSOC); $inc->close();

// Outgoing (I requested)
$out = $conn->prepare("
    SELECT tr.request_id, tr.requested_at, w.project_id, w.title, u.name AS owner_name,
           (SELECT team_request_status FROM team_request_status WHERE request_id = tr.request_id ORDER BY team_request_status LIMIT 1) AS status
    FROM team_request tr
    JOIN work w ON w.project_id = tr.project_id
    JOIN user u ON u.id = w.owner_id
    WHERE tr.requester_id = ?
    ORDER BY tr.requested_at DESC
");
$out->bind_param('i', $uid); $out->execute();
$outgoing = $out->get_result()->fetch_all(MYSQLI_ASSOC); $out->close();

$page_title = 'Team Requests';
require __DIR__ . '/../includes/header.php';
?>

<h1>Team Requests</h1>

<section class="card">
    <h2>Incoming</h2>
    <?php if (!$incoming): ?>
        <p class="muted">No incoming requests.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Requester</th><th>Project</th><th>When</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($incoming as $r): ?>
                <tr id="req-<?= (int)$r['request_id'] ?>">
                    <td><?= h($r['requester_name']) ?></td>
                    <td><a href="/projects/view.php?id=<?= (int)$r['project_id'] ?>"><?= h($r['title']) ?></a></td>
                    <td class="muted small"><?= h($r['requested_at']) ?></td>
                    <td><span class="tag"><?= h($r['status'] ?? 'pending') ?></span></td>
                    <td>
                        <?php if (($r['status'] ?? 'pending') === 'pending'): ?>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                                <button class="btn btn-sm btn-primary" name="action" value="accept">Accept</button>
                                <button class="btn btn-sm btn-danger"  name="action" value="reject">Reject</button>
                            </form>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-ghost" href="/messages/thread.php?with=<?= (int)$r['requester_id'] ?>">Message</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Outgoing</h2>
    <?php if (!$outgoing): ?>
        <p class="muted">You haven't sent any requests.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Project</th><th>Owner</th><th>When</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($outgoing as $r): ?>
                <tr>
                    <td><a href="/projects/view.php?id=<?= (int)$r['project_id'] ?>"><?= h($r['title']) ?></a></td>
                    <td><?= h($r['owner_name']) ?></td>
                    <td class="muted small"><?= h($r['requested_at']) ?></td>
                    <td><span class="tag"><?= h($r['status'] ?? 'pending') ?></span></td>
                    <td>
                        <?php if (($r['status'] ?? 'pending') === 'pending'): ?>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                                <button class="btn btn-sm btn-danger" name="action" value="cancel">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
