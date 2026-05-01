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
$users = $conn->prepare('SELECT id, name, email, student_flag, teacher_flag FROM user WHERE id <> ? ORDER BY name');
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
            <div class="empty-state">
                <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10 16h44v28H22l-8 8v-8h-4z"/>
                    <path d="M20 26h24"/><path d="M20 34h16"/>
                </svg>
                <p>No conversations yet. Pick someone from the list to start chatting.</p>
            </div>
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
        <p class="muted small">Search anyone by name or email to start a conversation.</p>
        <div class="user-search"
             data-thread-url="<?= h(url('/messages/thread.php')) ?>"
             data-users='<?= h(json_encode($allUsers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
            <div class="user-search-input-row">
                <span class="user-search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m20 20-3.5-3.5"/>
                    </svg>
                </span>
                <input type="search" class="user-search-input"
                       placeholder="Type a name or email..."
                       autocomplete="off" aria-label="Search users">
            </div>
            <ul class="user-search-results" hidden></ul>
            <p class="user-search-empty muted small" hidden>No matching users.</p>
        </div>
    </section>
</div>

<script>
(function () {
    var box = document.querySelector('.user-search');
    if (!box) return;
    var input    = box.querySelector('.user-search-input');
    var list     = box.querySelector('.user-search-results');
    var emptyEl  = box.querySelector('.user-search-empty');
    var users    = JSON.parse(box.dataset.users || '[]');
    var threadUrl = box.dataset.threadUrl;
    var activeIdx = -1;

    function open(go) { window.location.href = threadUrl + '?with=' + go; }

    function badge(u) {
        if (u.teacher_flag) return 'Teacher';
        if (u.student_flag) return 'Student';
        return '';
    }

    function render(filter) {
        var f = (filter || '').toLowerCase().trim();
        list.innerHTML = '';
        var matches = users.filter(function (u) {
            if (!f) return true;
            return (u.name && u.name.toLowerCase().indexOf(f) !== -1)
                || (u.email && u.email.toLowerCase().indexOf(f) !== -1);
        }).slice(0, 12);

        matches.forEach(function (u, i) {
            var li = document.createElement('li');
            li.className = 'user-search-item';
            li.dataset.id = u.id;
            li.tabIndex = 0;

            var avatar = document.createElement('span');
            avatar.className = 'user-search-avatar';
            avatar.textContent = (u.name || '?').charAt(0).toUpperCase();
            li.appendChild(avatar);

            var info = document.createElement('div');
            info.className = 'user-search-info';
            var nameEl = document.createElement('div');
            nameEl.className = 'user-search-name';
            nameEl.textContent = u.name;
            info.appendChild(nameEl);
            var emailEl = document.createElement('div');
            emailEl.className = 'user-search-email muted small';
            emailEl.textContent = u.email || '';
            info.appendChild(emailEl);
            li.appendChild(info);

            var b = badge(u);
            if (b) {
                var tag = document.createElement('span');
                tag.className = 'tag';
                tag.textContent = b;
                li.appendChild(tag);
            }
            list.appendChild(li);
        });

        if (matches.length === 0) {
            list.hidden = true;
            emptyEl.hidden = false;
        } else {
            list.hidden = false;
            emptyEl.hidden = true;
        }
        activeIdx = -1;
    }

    function move(delta) {
        var items = list.querySelectorAll('.user-search-item');
        if (!items.length) return;
        if (activeIdx >= 0) items[activeIdx].classList.remove('is-active');
        activeIdx = (activeIdx + delta + items.length) % items.length;
        items[activeIdx].classList.add('is-active');
        items[activeIdx].scrollIntoView({ block: 'nearest' });
    }

    input.addEventListener('input', function () { render(input.value); });
    input.addEventListener('focus', function () { render(input.value); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown')   { e.preventDefault(); move(+1); }
        else if (e.key === 'ArrowUp'){ e.preventDefault(); move(-1); }
        else if (e.key === 'Enter')  {
            var items = list.querySelectorAll('.user-search-item');
            if (items.length === 0) return;
            var pick = activeIdx >= 0 ? items[activeIdx] : items[0];
            if (pick) { e.preventDefault(); open(pick.dataset.id); }
        } else if (e.key === 'Escape') {
            list.hidden = true; emptyEl.hidden = true;
        }
    });
    list.addEventListener('click', function (e) {
        var li = e.target.closest('.user-search-item');
        if (li) open(li.dataset.id);
    });
    document.addEventListener('click', function (e) {
        if (!box.contains(e.target)) { list.hidden = true; emptyEl.hidden = true; }
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
