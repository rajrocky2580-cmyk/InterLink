<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $action   = $_POST['action'] ?? '';
    if ($reportId && in_array($action, ['reviewed','dismissed'])) {
        $pdo->prepare("UPDATE reports SET status=? WHERE report_id=?")->execute([$action, $reportId]);
    }
    header('Location: reports.php');
    exit;
}

$status = sanitize($_GET['status'] ?? '');
$where  = $status ? "WHERE r.status=?" : "";
$params = $status ? [$status] : [];

$stmt = $pdo->prepare("
    SELECT r.*, u1.full_name AS reporter_name, u2.full_name AS reported_name
    FROM reports r
    JOIN users u1 ON r.reported_by=u1.user_id
    LEFT JOIN users u2 ON r.reported_user=u2.user_id
    $where ORDER BY r.created_at DESC LIMIT 50
");
$stmt->execute($params);
$reports = $stmt->fetchAll();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>InterLink Admin — Reports</title>
  <link rel="icon" href="../assets/images/favicon.png" type="image/png">
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="admin-main">
    <div class="admin-page-header fade-in-up">
      <h1>Reports</h1>
      <p><?= $pendingCount ?> pending report<?= $pendingCount!=1?'s':'' ?> need review.</p>
    </div>
    <div style="display:flex;gap:8px;margin-bottom:20px">
      <?php foreach ([''=>'All','pending'=>'Pending','reviewed'=>'Reviewed','dismissed'=>'Dismissed'] as $v=>$l): ?>
      <a href="?status=<?=$v?>" class="btn btn-sm <?=$status===$v?'btn-primary':'btn-ghost'?>"><?=$l?></a>
      <?php endforeach; ?>
    </div>
    <div class="data-table-wrap fade-in-up">
      <div class="data-table-header"><h3>Reports (<?=count($reports)?>)</h3></div>
      <table>
        <thead><tr><th>Reporter</th><th>Reported</th><th>Reason</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($reports as $r): ?>
          <tr>
            <td class="text-sm"><?=htmlspecialchars($r['reporter_name'])?></td>
            <td class="text-sm"><?=htmlspecialchars($r['reported_name']??'—')?></td>
            <td class="text-sm" style="max-width:240px"><div class="truncate"><?=htmlspecialchars(mb_substr($r['reason']??'',0,80))?></div></td>
            <td><span class="status-pill <?=$r['status']?>"><span class="status-dot"></span><?=ucfirst($r['status'])?></span></td>
            <td class="text-sm text-muted"><?=date('M j, Y',strtotime($r['created_at']))?></td>
            <td>
              <?php if($r['status']==='pending'): ?>
              <div class="flex gap-1">
                <form method="POST" style="display:inline">
                  <input type="hidden" name="report_id" value="<?=$r['report_id']?>">
                  <input type="hidden" name="action" value="reviewed">
                  <button class="btn btn-sm btn-primary">✅ Review</button>
                </form>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="report_id" value="<?=$r['report_id']?>">
                  <input type="hidden" name="action" value="dismissed">
                  <button class="btn btn-sm btn-ghost">✕ Dismiss</button>
                </form>
              </div>
              <?php else: ?><span class="text-muted text-sm">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$reports): ?>
          <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">No reports found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
