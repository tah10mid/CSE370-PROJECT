<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();

$errors = [];
$old = ['title'=>'', 'description'=>'', 'type'=>'project', 'supervisor_id'=>'', 'status'=>'open'];

// Teachers available as supervisors
$tres = $conn->query("SELECT u.id, u.name FROM user u JOIN teacher t ON t.user_id = u.id ORDER BY u.name");
$teachers = $tres->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $type  = $_POST['type'] ?? 'project';
    $sup   = $_POST['supervisor_id'] !== '' ? (int)$_POST['supervisor_id'] : null;
    $status= $_POST['status'] ?? 'open';
    $old   = compact('title','desc','type','sup','status') + $old;
    $old['description'] = $desc; $old['supervisor_id'] = $sup;

    if ($title === '') $errors[] = 'Title is required.';
    if (!in_array($type, ['thesis','project','both'], true)) $errors[] = 'Invalid type.';
    if (!in_array($status, ['open','in_progress','completed','closed'], true)) $errors[] = 'Invalid status.';

    if (!$errors) {
        $tflag = in_array($type, ['thesis','both'], true) ? 1 : 0;
        $pflag = in_array($type, ['project','both'], true) ? 1 : 0;
        $conn->begin_transaction();
        try {
            $ins = $conn->prepare('INSERT INTO work (title, description, owner_id, supervisor_id, thesis_flag, project_flag) VALUES (?, ?, ?, ?, ?, ?)');
            $ins->bind_param('ssiiii', $title, $desc, $uid, $sup, $tflag, $pflag);
            $ins->execute();
            $pid = $conn->insert_id;
            $ins->close();

            $st = $conn->prepare('INSERT INTO project_status (project_id, project_status) VALUES (?, ?)');
            $st->bind_param('is', $pid, $status);
            $st->execute();
            $st->close();

            // Owner who is a student is auto-joined
            if (is_student()) {
                $j = $conn->prepare('INSERT IGNORE INTO student_join_project (project_id, id) VALUES (?, ?)');
                $j->bind_param('ii', $pid, $uid);
                $j->execute();
                $j->close();
            }

            $conn->commit();
            flash('success', 'Work posted!');
            redirect('/projects/view.php?id=' . $pid);
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = 'Failed to create: ' . $e->getMessage();
        }
    }
}

$page_title = 'Post New Work';
require __DIR__ . '/../includes/header.php';
?>

<h1>Post a new thesis or project</h1>
<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" class="form card">
    <label>Title
        <input type="text" name="title" required value="<?= h($old['title']) ?>">
    </label>
    <label>Description
        <textarea name="description" rows="6"><?= h($old['description']) ?></textarea>
    </label>
    <label>Type
        <select name="type">
            <option value="project" <?= $old['type']==='project'?'selected':'' ?>>Project</option>
            <option value="thesis"  <?= $old['type']==='thesis' ?'selected':'' ?>>Thesis</option>
            <option value="both"    <?= $old['type']==='both'   ?'selected':'' ?>>Thesis + Project</option>
        </select>
    </label>
    <label>Supervisor (optional)
        <select name="supervisor_id">
            <option value="">— None —</option>
            <?php foreach ($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (string)$old['supervisor_id']===(string)$t['id']?'selected':'' ?>><?= h($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Initial status
        <select name="status">
            <?php foreach (['open','in_progress','completed','closed'] as $s): ?>
                <option value="<?= $s ?>" <?= $old['status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn btn-primary" type="submit">Create</button>
    <a class="btn btn-ghost" href="/projects/index.php">Cancel</a>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
