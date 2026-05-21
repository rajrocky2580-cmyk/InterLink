<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$pdo = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId && in_array($action, ['active','banned','deactivated'])) {
        $pdo->prepare("UPDATE users SET status=? WHERE user_id=?")->execute([$action, $userId]);
    }
    header('Location: users.php');
    exit;
}

$search = sanitize($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = $search ? "WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$total = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$pages = ceil($totalCount / $limit);

$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>InterLink Admin — Users</title>
  <link rel="icon" href="../assets/images/favicon.png" type="image/png">
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="admin-main">

    <div class="admin-page-header fade-in-up">
      <h1>User Management</h1>
      <p>Manage all registered users — view, activate, ban, or deactivate accounts.</p>
    </div>

    <div class="data-table-wrap fade-in-up">
      <div class="data-table-header">
        <h3>All Users <span style="color:var(--text-muted);font-weight:400;font-size:.875rem">(<?= number_format($totalCount) ?>)</span></h3>
        <form method="GET" style="display:flex;gap:8px;align-items:center">
          <input class="table-search" type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search users…">
          <button class="btn btn-ghost btn-sm" type="submit">Search</button>
        </form>
      </div>

      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th>Status</th>
            <th>Role</th>
            <th>Last Seen</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="flex items-center gap-2">
                <div class="avatar-placeholder" style="width:34px;height:34px;background:var(--accent);font-size:.65rem;border-radius:50%;flex-shrink:0">
                  <?= strtoupper(substr($u['full_name']??'?',0,2)) ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($u['full_name']) ?></div>
                  <div class="text-xs text-muted">@<?= htmlspecialchars($u['username']) ?></div>
                </div>
              </div>
            </td>
            <td class="text-sm"><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <span class="status-pill <?= $u['status'] ?>">
                <span class="status-dot"></span>
                <?= ucfirst($u['status']) ?>
              </span>
            </td>
            <td class="text-sm"><?= ucfirst($u['role']) ?></td>
            <td class="text-sm text-muted"><?= $u['last_seen'] ? date('M j, g:i A', strtotime($u['last_seen'])) : '—' ?></td>
            <td class="text-sm text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="flex gap-1">
                <?php if ($u['status'] !== 'active'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                  <input type="hidden" name="action" value="active">
                  <button class="btn btn-sm btn-ghost" type="submit" title="Activate">✅</button>
                </form>
                <?php endif; ?>
                <?php if ($u['status'] !== 'banned'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                  <input type="hidden" name="action" value="banned">
                  <button class="btn btn-sm btn-danger" type="submit" title="Ban" onclick="return confirm('Ban this user?')">🚫</button>
                </form>
                <?php endif; ?>
                <?php if ($u['status'] !== 'deactivated'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                  <input type="hidden" name="action" value="deactivated">
                  <button class="btn btn-sm btn-ghost" type="submit" title="Deactivate" onclick="return confirm('Deactivate this user?')" style="color:var(--yellow)">⏸️</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$users): ?>
          <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if ($pages > 1): ?>
      <div class="pagination">
        <span class="text-muted text-sm">Showing <?= $offset+1 ?>–<?= min($offset+$limit,$totalCount) ?> of <?= $totalCount ?></span>
        <div class="pagination-btns">
          <?php for ($i=1; $i<=$pages; $i++): ?>
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
