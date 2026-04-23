<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();

// ---- Handle POST: update profile ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_core') {
            $name  = trim($_POST['name']  ?? '');
            $email = trim($_POST['email'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Name and a valid email are required.');
            }
            $s = $conn->prepare('UPDATE user SET name = ?, email = ? WHERE id = ?');
            $s->bind_param('ssi', $name, $email, $uid);
            $s->execute();
            $s->close();
            $_SESSION['user_name'] = $name;
            flash('success', 'Profile updated.');
        }
        elseif ($action === 'update_student' && is_student()) {
            $cgpa = $_POST['cgpa'] !== '' ? (float)$_POST['cgpa'] : null;
            $lang = trim($_POST['preferable_coding_language'] ?? '');
            $tst  = trim($_POST['thesis_starting_time']       ?? '');
            $pst  = trim($_POST['project_starting_time']      ?? '');
            $dept = trim($_POST['dept']                       ?? '');
            $sem  = trim($_POST['semester']                   ?? '');
            $ug   = isset($_POST['undergrad_flag']) ? 1 : 0;
            $pg   = isset($_POST['postgrad_flag'])  ? 1 : 0;
            $s = $conn->prepare('UPDATE student SET cgpa=?, preferable_coding_language=?, thesis_starting_time=?, project_starting_time=?, dept=?, semester=?, undergrad_flag=?, postgrad_flag=? WHERE user_id=?');
            $s->bind_param('dsssssiii', $cgpa, $lang, $tst, $pst, $dept, $sem, $ug, $pg, $uid);
            $s->execute();
            $s->close();
            flash('success', 'Student info saved.');
        }
        elseif ($action === 'update_teacher' && is_teacher()) {
            $ct = trim($_POST['consultation_time'] ?? '');
            $s = $conn->prepare('UPDATE teacher SET consultation_time = ? WHERE user_id = ?');
            $s->bind_param('si', $ct, $uid);
            $s->execute();
            $s->close();
            flash('success', 'Teacher info saved.');
        }
        elseif ($action === 'add_mv') {
            $table = $_POST['table'] ?? '';
            $col   = $_POST['col']   ?? '';
            $val   = trim($_POST['value'] ?? '');
            $keyCol = $_POST['keyCol'] ?? 'user_id';
            $allowed = [
                'user_previous_work'        => ['user_id','previous_work'],
                'user_project_interest'     => ['user_id','project_interest'],
                'user_project_iskill'       => ['user_id','skill'],
                'user_thesis_interest'      => ['user_id','thesis_interest'],
                'teacher_project_interest'  => ['id','project_interest'],
                'teacher_thesis_interest'   => ['id','thesis_interest'],
                'teacher_thesisslot'        => ['id','thesis_slot'],
            ];
            if (!isset($allowed[$table]) || $allowed[$table][0] !== $keyCol || $allowed[$table][1] !== $col) {
                throw new Exception('Invalid list.');
            }
            if ($val === '') throw new Exception('Value cannot be empty.');
            if (strpos($table, 'teacher_') === 0 && !is_teacher()) throw new Exception('Teachers only.');

            $sql = "INSERT IGNORE INTO `$table` (`$keyCol`, `$col`) VALUES (?, ?)";
            $s = $conn->prepare($sql);
            $s->bind_param('is', $uid, $val);
            $s->execute();
            $s->close();
            flash('success', 'Added.');
        }
        elseif ($action === 'del_mv') {
            $table = $_POST['table'] ?? '';
            $col   = $_POST['col']   ?? '';
            $val   = $_POST['value'] ?? '';
            $keyCol = $_POST['keyCol'] ?? 'user_id';
            $allowed = [
                'user_previous_work'        => ['user_id','previous_work'],
                'user_project_interest'     => ['user_id','project_interest'],
                'user_project_iskill'       => ['user_id','skill'],
                'user_thesis_interest'      => ['user_id','thesis_interest'],
                'teacher_project_interest'  => ['id','project_interest'],
                'teacher_thesis_interest'   => ['id','thesis_interest'],
                'teacher_thesisslot'        => ['id','thesis_slot'],
            ];
            if (!isset($allowed[$table]) || $allowed[$table][0] !== $keyCol || $allowed[$table][1] !== $col) {
                throw new Exception('Invalid list.');
            }
            $sql = "DELETE FROM `$table` WHERE `$keyCol` = ? AND `$col` = ?";
            $s = $conn->prepare($sql);
            $s->bind_param('is', $uid, $val);
            $s->execute();
            $s->close();
            flash('success', 'Removed.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/profile/index.php');
}

// ---- Load data ----
$u = $conn->prepare('SELECT id, name, email, student_flag, teacher_flag, created_at FROM user WHERE id = ?');
$u->bind_param('i', $uid); $u->execute();
$user = $u->get_result()->fetch_assoc(); $u->close();

$student = null;
if (is_student()) {
    $s = $conn->prepare('SELECT * FROM student WHERE user_id = ?');
    $s->bind_param('i', $uid); $s->execute();
    $student = $s->get_result()->fetch_assoc(); $s->close();
}
$teacher = null;
if (is_teacher()) {
    $t = $conn->prepare('SELECT * FROM teacher WHERE user_id = ?');
    $t->bind_param('i', $uid); $t->execute();
    $teacher = $t->get_result()->fetch_assoc(); $t->close();
}

function list_mv(mysqli $c, string $table, string $keyCol, string $col, int $uid): array {
    $s = $c->prepare("SELECT `$col` AS v FROM `$table` WHERE `$keyCol` = ? ORDER BY `$col`");
    $s->bind_param('i', $uid); $s->execute();
    $r = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    return array_column($r, 'v');
}

$skills          = list_mv($conn, 'user_project_iskill',      'user_id', 'skill',            $uid);
$projInterests   = list_mv($conn, 'user_project_interest',    'user_id', 'project_interest', $uid);
$thesisInterests = list_mv($conn, 'user_thesis_interest',     'user_id', 'thesis_interest',  $uid);
$prevWork        = list_mv($conn, 'user_previous_work',       'user_id', 'previous_work',    $uid);
$tProjInt = $tThesisInt = $tSlots = [];
if (is_teacher()) {
    $tProjInt   = list_mv($conn, 'teacher_project_interest', 'id', 'project_interest', $uid);
    $tThesisInt = list_mv($conn, 'teacher_thesis_interest',  'id', 'thesis_interest',  $uid);
    $tSlots     = list_mv($conn, 'teacher_thesisslot',       'id', 'thesis_slot',      $uid);
}

$page_title = 'My Profile';
require __DIR__ . '/../includes/header.php';

function mv_form(string $table, string $keyCol, string $col, array $items, string $label): void { ?>
    <div class="mv-block">
        <strong><?= h($label) ?></strong>
        <ul class="chips">
            <?php foreach ($items as $it): ?>
                <li class="chip">
                    <?= h($it) ?>
                    <form method="post" class="chip-del">
                        <input type="hidden" name="action" value="del_mv">
                        <input type="hidden" name="table"  value="<?= h($table) ?>">
                        <input type="hidden" name="keyCol" value="<?= h($keyCol) ?>">
                        <input type="hidden" name="col"    value="<?= h($col) ?>">
                        <input type="hidden" name="value"  value="<?= h($it) ?>">
                        <button title="Remove" class="chip-x" type="submit">&times;</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" class="inline-add">
            <input type="hidden" name="action" value="add_mv">
            <input type="hidden" name="table"  value="<?= h($table) ?>">
            <input type="hidden" name="keyCol" value="<?= h($keyCol) ?>">
            <input type="hidden" name="col"    value="<?= h($col) ?>">
            <input type="text" name="value" placeholder="Add <?= h($label) ?>" required>
            <button class="btn btn-sm btn-primary" type="submit">Add</button>
        </form>
    </div>
<?php }
?>

<h1>My Profile</h1>
<p class="muted">Joined <?= h($user['created_at']) ?></p>

<section class="card">
    <h2>Account</h2>
    <form method="post" class="form form-row">
        <input type="hidden" name="action" value="update_core">
        <label>Name <input type="text" name="name" required value="<?= h($user['name']) ?>"></label>
        <label>Email <input type="email" name="email" required value="<?= h($user['email']) ?>"></label>
        <button class="btn btn-primary" type="submit">Save</button>
    </form>
</section>

<?php if (is_student()): ?>
<section class="card">
    <h2>Student Details</h2>
    <form method="post" class="form form-grid">
        <input type="hidden" name="action" value="update_student">
        <label>CGPA <input type="number" step="0.01" min="0" max="4" name="cgpa" value="<?= h($student['cgpa']) ?>"></label>
        <label>Preferred coding language <input type="text" name="preferable_coding_language" value="<?= h($student['preferable_coding_language']) ?>"></label>
        <label>Thesis starting time <input type="text" name="thesis_starting_time" value="<?= h($student['thesis_starting_time']) ?>"></label>
        <label>Project starting time <input type="text" name="project_starting_time" value="<?= h($student['project_starting_time']) ?>"></label>
        <label>Department <input type="text" name="dept" value="<?= h($student['dept']) ?>"></label>
        <label>Semester <input type="text" name="semester" value="<?= h($student['semester']) ?>"></label>
        <label class="inline"><input type="checkbox" name="undergrad_flag" <?= $student['undergrad_flag'] ? 'checked' : '' ?>> Undergrad</label>
        <label class="inline"><input type="checkbox" name="postgrad_flag"  <?= $student['postgrad_flag']  ? 'checked' : '' ?>> Postgrad</label>
        <button class="btn btn-primary" type="submit">Save student info</button>
    </form>
</section>
<?php endif; ?>

<?php if (is_teacher()): ?>
<section class="card">
    <h2>Teacher Details</h2>
    <form method="post" class="form form-row">
        <input type="hidden" name="action" value="update_teacher">
        <label>Consultation time <input type="text" name="consultation_time" value="<?= h($teacher['consultation_time']) ?>" placeholder="e.g. Sun/Tue 3-5 PM"></label>
        <button class="btn btn-primary" type="submit">Save</button>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <h2>Skills &amp; Interests</h2>
    <?php
        mv_form('user_project_iskill',   'user_id', 'skill',            $skills,          'Skill');
        mv_form('user_project_interest', 'user_id', 'project_interest', $projInterests,   'Project interest');
        mv_form('user_thesis_interest',  'user_id', 'thesis_interest',  $thesisInterests, 'Thesis interest');
        mv_form('user_previous_work',    'user_id', 'previous_work',    $prevWork,        'Previous work');
    ?>
</section>

<?php if (is_teacher()): ?>
<section class="card">
    <h2>Teacher Supervision Attributes</h2>
    <?php
        mv_form('teacher_project_interest', 'id', 'project_interest', $tProjInt,   'Supervision project area');
        mv_form('teacher_thesis_interest',  'id', 'thesis_interest',  $tThesisInt, 'Supervision thesis area');
        mv_form('teacher_thesisslot',       'id', 'thesis_slot',      $tSlots,     'Thesis slot');
    ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
