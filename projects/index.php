<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q    = trim($_GET['q']    ?? '');
$type = $_GET['type'] ?? 'all';     // all | thesis | project
$mine = isset($_GET['mine']);

$sql = "
    SELECT w.project_id, w.title, w.description, w.created_at, w.thesis_flag, w.project_flag,
           u.name AS owner_name, t.name AS supervisor_name,
           (SELECT project_status FROM project_status WHERE project_id = w.project_id ORDER BY project_status LIMIT 1) AS status
    FROM work w
    JOIN user u  ON u.id = w.owner_id
    LEFT JOIN user t ON t.id = w.supervisor_id
    WHERE 1=1
";
$params = [];
$types  = '';

if ($type === 'thesis')  { $sql .= ' AND w.thesis_flag  = 1'; }
if ($type === 'project') { $sql .= ' AND w.project_flag = 1'; }
if ($mine) {
    $uid = current_user_id();
    $sql .= ' AND (w.owner_id = ? OR w.supervisor_id = ? OR EXISTS (SELECT 1 FROM student_join_project sjp WHERE sjp.project_id = w.project_id AND sjp.id = ?))';
    $params[] = $uid; $params[] = $uid; $params[] = $uid;
    $types   .= 'iii';
}
if ($q !== '') {
    $sql .= ' AND (w.title LIKE ? OR w.description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like;
    $types   .= 'ss';
}
$sql .= ' ORDER BY w.created_at DESC LIMIT 100';

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Browse Work';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Browse Thesis &amp; Projects</h1>
    <a class="btn btn-primary" href="<?= url('/projects/create.php') ?>">+ Post new work</a>
</div>

<form class="filters" method="get">
    <input type="search" name="q" placeholder="Search title or description" value="<?= h($q) ?>">
    <select name="type">
        <option value="all"     <?= $type==='all'    ?'selected':'' ?>>All</option>
        <option value="thesis"  <?= $type==='thesis' ?'selected':'' ?>>Thesis only</option>
        <option value="project" <?= $type==='project'?'selected':'' ?>>Projects only</option>
    </select>
    <label class="inline"><input type="checkbox" name="mine" <?= $mine?'checked':'' ?>> Only mine</label>
    <button class="btn" type="submit">Filter</button>
</form>

<?php if (!$rows): ?>
    <div class="empty-state card">
        <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="28" cy="28" r="14"/>
            <path d="M40 40l12 12"/>
        </svg>
        <p>No work matches your filters yet.</p>
        <a class="btn btn-primary btn-sm" href="<?= url('/projects/create.php') ?>">Post the first one</a>
    </div>
<?php else: ?>
<div class="grid-2">
    <?php foreach ($rows as $w): ?>
        <article class="card work-card">
            <h3><a href="<?= url('/projects/view.php') ?>?id=<?= (int)$w['project_id'] ?>"><?= h($w['title']) ?></a></h3>
            <div class="tags">
                <?php if ($w['thesis_flag']):  ?><span class="tag tag-thesis">Thesis</span><?php endif; ?>
                <?php if ($w['project_flag']): ?><span class="tag tag-project">Project</span><?php endif; ?>
                <?php if ($w['status']): ?><span class="tag"><?= h($w['status']) ?></span><?php endif; ?>
            </div>
            <p><?= h(mb_strimwidth($w['description'] ?? '', 0, 180, '...')) ?></p>
            <div class="muted small">
                by <?= h($w['owner_name']) ?>
                <?php if ($w['supervisor_name']): ?> &middot; supervised by <?= h($w['supervisor_name']) ?><?php endif; ?>
                &middot; <?= h($w['created_at']) ?>
            </div>
            <a class="btn btn-sm" href="<?= url('/projects/view.php') ?>?id=<?= (int)$w['project_id'] ?>">Details</a>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
