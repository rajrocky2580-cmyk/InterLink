<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$pdo    = getDB();
$search = sanitize($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 30;
$offset = ($page - 1) * $limit;

$where  = $search ? "AND (m.content LIKE ? OR u.username LIKE ?)" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$total = $pdo->prepare("SELECT COUNT(*) FROM messages m JOIN users u ON m.sender_id=u.user_id WHERE m.is_deleted=0 $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$pages = ceil($totalCount / $limit);

$stmt = $pdo->prepare("
    SELECT m.message_id, m.content, m.message_type, m.sent_at, m.is_edited,
           u.full_name AS sender, u.username, m.conversation_id
    FROM messages m
    JOIN users u ON m.sender_id=u.user_id
    WHERE m.is_deleted=0 $where
    ORDER BY m.sent_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$messages = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>InterLink Admin — Message Logs</title>
  <link rel="icon" href="../assets/images/favicon.png" type="image/png">
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="admin-main">

    <div class="admin-page-header fade-in-up">
      <h1>Message Logs</h1>
      <p>Search and review all messages sent across the platform.</p>
    </div>

    <div class="data-table-wrap fade-in-up">
      <div class="data-table-header">
        <h3>Messages <span style="color:var(--text-muted);font-weight:400;font-size:.875rem">(<?= number_format($totalCount) ?>)</span></h3>
        <form method="GET" style="display:flex;gap:8px;align-items:center">
          <input class="table-search" type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search messages or senders…">
          <button class="btn btn-ghost btn-sm" type="submit">Search</button>
        </form>
      </div>

      <table>
        <thead>
          <tr>
            <th>Sender</th>
            <th>Message</th>
            <th>Type</th>
            <th>Conv ID</th>
            <th>Sent At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($messages as $m): ?>
          <tr>
            <td>
              <div style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($m['sender']) ?></div>
              <div class="text-xs text-muted">@<?= htmlspecialchars($m['username']) ?></div>
            </td>
            <td style="max-width:360px">
              <div class="truncate text-sm" title="<?= htmlspecialchars($m['content']) ?>">
                <?= htmlspecialchars(mb_substr($m['content'],0,100)) ?><?= mb_strlen($m['content'])>100?'…':'' ?>
              </div>
              <?php if ($m['is_edited']): ?><span class="text-xs text-muted">edited</span><?php endif; ?>
            </td>
            <td>
              <span class="status-pill" style="background:rgba(79,142,247,.12);color:#93c5fd">
                <?= ucfirst($m['message_type']) ?>
              </span>
            </td>
            <td class="text-sm text-muted">#<?= $m['conversation_id'] ?></td>
            <td class="text-sm text-muted"><?= date('M j, Y g:i A', strtotime($m['sent_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$messages): ?>
          <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">No messages found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if ($pages > 1): ?>
      <div class="pagination">
        <span class="text-muted text-sm">Page <?= $page ?> of <?= $pages ?></span>
        <div class="pagination-btns">
          <?php for ($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
          <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>
</body>
</html>
