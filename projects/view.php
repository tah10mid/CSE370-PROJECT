<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();
$pid = (int)($_GET['id'] ?? 0);
if ($pid <= 0) { flash('error','Invalid work id.'); redirect('/projects/index.php'); }

// Load work
$q = $conn->prepare('SELECT w.*, u.name AS owner_name, t.name AS supervisor_name FROM work w JOIN user u ON u.id = w.owner_id LEFT JOIN user t ON t.id = w.supervisor_id WHERE w.project_id = ?');
$q->bind_param('i', $pid); $q->execute();
$w = $q->get_result()->fetch_assoc(); $q->close();
if (!$w) { flash('error','Work not found.'); redirect('/projects/index.php'); }

$is_owner      = ((int)$w['owner_id']      === $uid);
$is_supervisor = ((int)$w['supervisor_id'] === $uid);

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update' && $is_owner) {
            $title = trim($_POST['title'] ?? '');
            $desc  = trim($_POST['description'] ?? '');
            $type  = $_POST['type'] ?? 'project';
            $sup   = $_POST['supervisor_id'] !== '' ? (int)$_POST['supervisor_id'] : null;
            if ($title === '') throw new Exception('Title required.');
            $tflag = in_array($type, ['thesis','both'], true) ? 1 : 0;
            $pflag = in_array($type, ['project','both'], true) ? 1 : 0;
            $s = $conn->prepare('UPDATE work SET title=?, description=?, supervisor_id=?, thesis_flag=?, project_flag=? WHERE project_id=?');
            $s->bind_param('ssiiii', $title, $desc, $sup, $tflag, $pflag, $pid);
            $s->execute(); $s->close();
            flash('success', 'Work updated.');
        }
        elseif ($action === 'delete' && $is_owner) {
            $s = $conn->prepare('DELETE FROM work WHERE project_id=? AND owner_id=?');
            $s->bind_param('ii', $pid, $uid);
            $s->execute(); $s->close();
            flash('success', 'Work deleted.');
            redirect('/projects/index.php');
        }
        elseif ($action === 'add_status' && ($is_owner || $is_supervisor)) {
            $st = $_POST['status'] ?? '';
            if (!in_array($st, ['open','in_progress','completed','closed'], true)) throw new Exception('Bad status.');
            $s = $conn->prepare('INSERT IGNORE INTO project_status (project_id, project_status) VALUES (?, ?)');
            $s->bind_param('is', $pid, $st);
            $s->execute(); $s->close();
            flash('success', 'Status added.');
        }
        elseif ($action === 'del_status' && ($is_owner || $is_supervisor)) {
            $st = $_POST['status'] ?? '';
            $s = $conn->prepare('DELETE FROM project_status WHERE project_id=? AND project_status=?');
            $s->bind_param('is', $pid, $st);
            $s->execute(); $s->close();
            flash('success', 'Status removed.');
        }
        elseif ($action === 'join' && is_student() && $is_owner) {
            $s = $conn->prepare('INSERT IGNORE INTO student_join_project (project_id, id) VALUES (?, ?)');
            $s->bind_param('ii', $pid, $uid);
            $s->execute(); $s->close();
            flash('success', 'You joined the project.');
        }
        elseif ($action === 'leave' && is_student()) {
            if ($is_owner) throw new Exception('Owner cannot leave their own work. Delete it instead.');
            $s = $conn->prepare('DELETE FROM student_join_project WHERE project_id=? AND id=?');
            $s->bind_param('ii', $pid, $uid);
            $s->execute(); $s->close();
            flash('success', 'You left the project.');
        }
        elseif ($action === 'supervise' && is_teacher()) {
            $s = $conn->prepare('UPDATE work SET supervisor_id=? WHERE project_id=?');
            $s->bind_param('ii', $uid, $pid);
            $s->execute(); $s->close();
            flash('success', 'You are now supervising this work.');
        }
        elseif ($action === 'unsupervise' && $is_supervisor) {
            $s = $conn->prepare('UPDATE work SET supervisor_id=NULL WHERE project_id=?');
            $s->bind_param('i', $pid);
            $s->execute(); $s->close();
            flash('success', 'Supervision removed.');
        }
        elseif ($action === 'request_join' && !$is_owner) {
            // Create team request + status
            $conn->begin_transaction();
            $ins = $conn->prepare('INSERT INTO team_request (project_id, requester_id) VALUES (?, ?)');
            $ins->bind_param('ii', $pid, $uid);
            $ins->execute();
            $rid = $conn->insert_id;
            $ins->close();
            $st = $conn->prepare("INSERT INTO team_request_status (request_id, team_request_status) VALUES (?, 'pending')");
            $st->bind_param('i', $rid);
            $st->execute(); $st->close();
            $conn->commit();
            flash('success', 'Request sent to owner.');
        }
        elseif ($action === 'cancel_request') {
            // Only the original requester can cancel, and only while pending
            $rid = (int)($_POST['request_id'] ?? 0);
            $chk = $conn->prepare('
                SELECT tr.request_id,
                       (SELECT team_request_status FROM team_request_status trs
                          WHERE trs.request_id = tr.request_id ORDER BY team_request_status LIMIT 1) AS status
                FROM team_request tr
                WHERE tr.request_id = ? AND tr.project_id = ? AND tr.requester_id = ?
            ');
            $chk->bind_param('iii', $rid, $pid, $uid);
            $chk->execute();
            $row = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$row) throw new Exception('Request not found or not yours to cancel.');
            if (($row['status'] ?? 'pending') !== 'pending') throw new Exception('You can only cancel a pending request.');

            $d = $conn->prepare('DELETE FROM team_request WHERE request_id = ? AND requester_id = ?');
            $d->bind_param('ii', $rid, $uid);
            $d->execute(); $d->close();
            flash('success', 'Request cancelled.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/projects/view.php?id=' . $pid);
}

// Reload data for view
$q = $conn->prepare('SELECT w.*, u.name AS owner_name, t.name AS supervisor_name FROM work w JOIN user u ON u.id = w.owner_id LEFT JOIN user t ON t.id = w.supervisor_id WHERE w.project_id = ?');
$q->bind_param('i', $pid); $q->execute();
$w = $q->get_result()->fetch_assoc(); $q->close();

$ss = $conn->prepare('SELECT project_status FROM project_status WHERE project_id = ? ORDER BY project_status');
$ss->bind_param('i', $pid); $ss->execute();
$statuses = array_column($ss->get_result()->fetch_all(MYSQLI_ASSOC), 'project_status');
$ss->close();

$js = $conn->prepare('SELECT u.id, u.name FROM student_join_project sjp JOIN user u ON u.id = sjp.id WHERE sjp.project_id = ? ORDER BY u.name');
$js->bind_param('i', $pid); $js->execute();
$joined = $js->get_result()->fetch_all(MYSQLI_ASSOC);
$js->close();
$is_joined = false;
foreach ($joined as $j) { if ((int)$j['id'] === $uid) { $is_joined = true; break; } }

$tres = $conn->query("SELECT u.id, u.name FROM user u JOIN teacher t ON t.user_id = u.id ORDER BY u.name");
$teachers = $tres->fetch_all(MYSQLI_ASSOC);

// Existing pending request by me
$preq = $conn->prepare("
    SELECT tr.request_id,
           (SELECT team_request_status FROM team_request_status trs WHERE trs.request_id = tr.request_id ORDER BY team_request_status LIMIT 1) AS status
    FROM team_request tr
    WHERE tr.project_id = ? AND tr.requester_id = ?
    ORDER BY tr.requested_at DESC LIMIT 1
");
$preq->bind_param('ii', $pid, $uid); $preq->execute();
$myRequest = $preq->get_result()->fetch_assoc();
$preq->close();

$current_type = ($w['thesis_flag'] && $w['project_flag']) ? 'both' : ($w['thesis_flag'] ? 'thesis' : 'project');
$page_title = $w['title'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1><?= h($w['title']) ?></h1>
    <div class="tags">
        <?php if ($w['thesis_flag']):  ?><span class="tag tag-thesis">Thesis</span><?php endif; ?>
        <?php if ($w['project_flag']): ?><span class="tag tag-project">Project</span><?php endif; ?>
        <?php foreach ($statuses as $s): ?><span class="tag"><?= h($s) ?></span><?php endforeach; ?>
    </div>
</div>

<div class="muted">
    Owner: <strong><?= h($w['owner_name']) ?></strong>
    &middot; Supervisor: <strong><?= $w['supervisor_name'] ? h($w['supervisor_name']) : '-' ?></strong>
    &middot; Created <?= h($w['created_at']) ?>
</div>

<section class="card">
    <h2>Description</h2>
    <p><?= nl2br(h($w['description'] ?? '')) ?></p>
</section>

<section class="card">
    <h2>Team</h2>
    <?php if (!$joined): ?>
        <p class="muted">No students have joined yet.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($joined as $j): ?>
                <li><?= h($j['name']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="actions">
        <?php if (is_student() && !$is_joined && $is_owner): ?>
            <form method="post"><input type="hidden" name="action" value="join"><button class="btn">Join as student</button></form>
        <?php endif; ?>
        <?php if (is_student() && $is_joined && !$is_owner): ?>
            <form method="post"><input type="hidden" name="action" value="leave"><button class="btn btn-danger">Leave</button></form>
        <?php endif; ?>
        <?php if (!$is_owner && !$is_joined): ?>
            <?php if ($myRequest && $myRequest['status'] === 'pending'): ?>
                <span class="tag">Request pending</span>
                <form method="post" onsubmit="return confirm('Cancel your pending request?');">
                    <input type="hidden" name="action" value="cancel_request">
                    <input type="hidden" name="request_id" value="<?= (int)$myRequest['request_id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Cancel request</button>
                </form>
            <?php else: ?>
                <form method="post"><input type="hidden" name="action" value="request_join"><button class="btn btn-primary">Request to join team</button></form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <h2>Status history</h2>
    <ul class="chips">
        <?php foreach ($statuses as $s): ?>
            <li class="chip"><?= h($s) ?>
                <?php if ($is_owner || $is_supervisor): ?>
                    <form method="post" class="chip-del">
                        <input type="hidden" name="action" value="del_status">
                        <input type="hidden" name="status" value="<?= h($s) ?>">
                        <button class="chip-x" title="Remove">&times;</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($is_owner || $is_supervisor): ?>
        <form method="post" class="inline-add">
            <input type="hidden" name="action" value="add_status">
            <select name="status">
                <?php foreach (['open','in_progress','completed','closed'] as $s): ?>
                    <option value="<?= $s ?>"><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary">Add status</button>
        </form>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Supervision</h2>
    <?php if ($is_supervisor): ?>
        <form method="post"><input type="hidden" name="action" value="unsupervise"><button class="btn btn-danger btn-sm">Stop supervising</button></form>
    <?php elseif (is_teacher() && !$w['supervisor_id']): ?>
        <form method="post"><input type="hidden" name="action" value="supervise"><button class="btn btn-primary btn-sm">Take as supervisor</button></form>
    <?php else: ?>
        <p class="muted"><?= $w['supervisor_name'] ? 'Currently supervised by ' . h($w['supervisor_name']) : 'No supervisor assigned.' ?></p>
    <?php endif; ?>
</section>

<?php if ($is_owner): ?>
<section class="card">
    <h2>Edit</h2>
    <form method="post" class="form">
        <input type="hidden" name="action" value="update">
        <label>Title <input type="text" name="title" required value="<?= h($w['title']) ?>"></label>
        <label>Description <textarea name="description" rows="5"><?= h($w['description']) ?></textarea></label>
        <label>Type
            <select name="type">
                <option value="project" <?= $current_type==='project'?'selected':'' ?>>Project</option>
                <option value="thesis"  <?= $current_type==='thesis' ?'selected':'' ?>>Thesis</option>
                <option value="both"    <?= $current_type==='both'   ?'selected':'' ?>>Thesis + Project</option>
            </select>
        </label>
        <label>Supervisor
            <select name="supervisor_id">
                <option value="">None</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$w['supervisor_id']===(int)$t['id']?'selected':'' ?>><?= h($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="actions">
            <button class="btn btn-primary" type="submit">Save</button>
            <button class="btn btn-danger" type="submit" name="action" value="delete" onclick="return confirm('Delete this work? This cannot be undone.')">Delete</button>
        </div>
    </form>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
