<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q = trim($_GET['q'] ?? '');

$sql = "
    SELECT
        u.id, u.name, u.email,
        t.consultation_time,
        GROUP_CONCAT(DISTINCT tpi.project_interest ORDER BY tpi.project_interest SEPARATOR '||') AS project_areas,
        GROUP_CONCAT(DISTINCT tti.thesis_interest  ORDER BY tti.thesis_interest  SEPARATOR '||') AS thesis_areas,
        GROUP_CONCAT(DISTINCT tts.thesis_slot      ORDER BY tts.thesis_slot      SEPARATOR '||') AS thesis_slots,
        (SELECT COUNT(*) FROM work w WHERE w.supervisor_id = t.user_id) AS supervised_count
    FROM teacher t
    JOIN user u ON u.id = t.user_id
    LEFT JOIN teacher_project_interest tpi ON tpi.id = t.user_id
    LEFT JOIN teacher_thesis_interest  tti ON tti.id = t.user_id
    LEFT JOIN teacher_thesis_slot      tts ON tts.id = t.user_id
    WHERE 1=1
";
$params = [];
$types  = '';
if ($q !== '') {
    $sql .= " AND (
        u.name LIKE ?
        OR t.consultation_time LIKE ?
        OR EXISTS (SELECT 1 FROM teacher_project_interest x WHERE x.id = t.user_id AND x.project_interest LIKE ?)
        OR EXISTS (SELECT 1 FROM teacher_thesis_interest  x WHERE x.id = t.user_id AND x.thesis_interest  LIKE ?)
        OR EXISTS (SELECT 1 FROM teacher_thesis_slot      x WHERE x.id = t.user_id AND x.thesis_slot      LIKE ?)
    )";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like, $like];
    $types  = 'sssss';
}
$sql .= " GROUP BY u.id, u.name, u.email, t.consultation_time, t.user_id ORDER BY u.name";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function pipe_to_chips(?string $piped): array {
    if (!$piped) return [];
    return array_filter(explode('||', $piped), 'strlen');
}

$page_title = 'Teachers';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Faculty &amp; Supervisors</h1>
    <span class="muted small"><?= count($teachers) ?> teacher<?= count($teachers) === 1 ? '' : 's' ?></span>
</div>

<form class="filters" method="get">
    <input type="search" name="q" placeholder="Search by name, area, or thesis slot" value="<?= h($q) ?>">
    <button class="btn btn-primary" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= url('/teachers/index.php') ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$teachers): ?>
    <div class="empty-state card">
        <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M32 8 L52 18 L32 28 L12 18 Z"/>
            <path d="M22 23v9c0 4 4 7 10 7s10-3 10-7v-9"/>
            <path d="M52 18v14"/>
        </svg>
        <p>No teachers match your search.</p>
    </div>
<?php else: ?>
<div class="grid-2">
    <?php foreach ($teachers as $t):
        $project_areas = pipe_to_chips($t['project_areas']);
        $thesis_areas  = pipe_to_chips($t['thesis_areas']);
        $thesis_slots  = pipe_to_chips($t['thesis_slots']);
    ?>
    <article class="card teacher-card" data-reveal>
        <div class="teacher-head">
            <div class="teacher-avatar"><?= h(strtoupper(substr($t['name'], 0, 1))) ?></div>
            <div>
                <h3 style="margin:0"><?= h($t['name']) ?></h3>
                <div class="muted small"><?= h($t['email']) ?></div>
            </div>
        </div>

        <dl class="teacher-meta">
            <div>
                <dt>Consultation</dt>
                <dd><?= $t['consultation_time'] ? h($t['consultation_time']) : '<span class="muted">Not set</span>' ?></dd>
            </div>
            <div>
                <dt>Currently supervising</dt>
                <dd><?= (int)$t['supervised_count'] ?> work<?= (int)$t['supervised_count'] === 1 ? '' : 's' ?></dd>
            </div>
        </dl>

        <?php if ($thesis_slots): ?>
            <div class="teacher-section">
                <strong class="teacher-label">Thesis slots</strong>
                <ul class="chips">
                    <?php foreach ($thesis_slots as $s): ?>
                        <li class="chip"><?= h($s) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($thesis_areas): ?>
            <div class="teacher-section">
                <strong class="teacher-label">Thesis areas</strong>
                <ul class="chips">
                    <?php foreach ($thesis_areas as $s): ?>
                        <li class="chip"><?= h($s) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($project_areas): ?>
            <div class="teacher-section">
                <strong class="teacher-label">Project areas</strong>
                <ul class="chips">
                    <?php foreach ($project_areas as $s): ?>
                        <li class="chip"><?= h($s) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a class="btn btn-primary btn-sm" href="<?= url('/messages/thread.php') ?>?with=<?= (int)$t['id'] ?>">Message</a>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
