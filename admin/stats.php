<?php
// =========================================================
// InterLink Admin — System Statistics
// =========================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$pdo = getDB();

// Overview stats
$stats = [];
$stats['total_users']      = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['active_users']     = $pdo->query("SELECT COUNT(*) FROM users WHERE is_online=1")->fetchColumn();
$stats['banned_users']     = $pdo->query("SELECT COUNT(*) FROM users WHERE status='banned'")->fetchColumn();
$stats['total_msgs']       = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$stats['msgs_today']       = $pdo->query("SELECT COUNT(*) FROM messages WHERE DATE(sent_at)=CURDATE()")->fetchColumn();
$stats['total_convs']      = $pdo->query("SELECT COUNT(*) FROM conversations")->fetchColumn();
$stats['private_convs']    = $pdo->query("SELECT COUNT(*) FROM conversations WHERE type='private'")->fetchColumn();
$stats['group_convs']      = $pdo->query("SELECT COUNT(*) FROM conversations WHERE type='group'")->fetchColumn();
$stats['total_files']      = $pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();
$stats['pending_reports']  = $pdo->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn();

// Messages per day (last 7 days)
$msgsByDay = $pdo->query("
    SELECT DATE(sent_at) AS day, COUNT(*) AS count
    FROM messages
    WHERE sent_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(sent_at)
    ORDER BY day ASC
")->fetchAll();

// Fill in missing days
$last7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $last7[$d] = 0;
}
foreach ($msgsByDay as $row) { $last7[$row['day']] = (int)$row['count']; }

// New users per day (last 7 days)
$usersByDay = $pdo->query("
    SELECT DATE(created_at) AS day, COUNT(*) AS count
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll();
$usersLast7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $usersLast7[$d] = 0;
}
foreach ($usersByDay as $row) { $usersLast7[$row['day']] = (int)$row['count']; }

// Top active users (by messages sent)
$topUsers = $pdo->query("
    SELECT u.full_name, u.username, u.avatar_url, COUNT(m.message_id) AS msg_count
    FROM users u
    LEFT JOIN messages m ON u.user_id = m.sender_id AND m.is_deleted = 0
    GROUP BY u.user_id
    ORDER BY msg_count DESC
    LIMIT 10
")->fetchAll();

// Message type breakdown
$typeBreakdown = $pdo->query("
    SELECT message_type, COUNT(*) AS count
    FROM messages WHERE is_deleted=0
    GROUP BY message_type ORDER BY count DESC
")->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>InterLink Admin — Statistics</title>
  <meta name="description" content="InterLink admin panel — detailed system statistics and analytics.">
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="icon" href="../assets/images/favicon.png" type="image/png">
</head>
<body>
<div class="admin-layout">

  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="admin-main">

    <div class="admin-page-header fade-in-up">
      <h1>System Statistics</h1>
      <p>Detailed analytics and usage metrics for InterLink.</p>
    </div>

    <!-- Overview Stats -->
    <div class="stats-grid fade-in-up">
      <div class="stat-card">
        <div class="stat-icon blue">👥</div>
        <div class="stat-info">
          <div class="stat-label">Total Users</div>
          <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
          <div class="stat-sub"><?= $stats['active_users'] ?> online · <?= $stats['banned_users'] ?> banned</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple">💬</div>
        <div class="stat-info">
          <div class="stat-label">Total Messages</div>
          <div class="stat-value"><?= number_format($stats['total_msgs']) ?></div>
          <div class="stat-sub"><?= number_format($stats['msgs_today']) ?> sent today</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">🏘️</div>
        <div class="stat-info">
          <div class="stat-label">Conversations</div>
          <div class="stat-value"><?= number_format($stats['total_convs']) ?></div>
          <div class="stat-sub"><?= $stats['private_convs'] ?> private · <?= $stats['group_convs'] ?> groups</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon yellow">📁</div>
        <div class="stat-info">
          <div class="stat-label">Files Shared</div>
          <div class="stat-value"><?= number_format($stats['total_files']) ?></div>
          <div class="stat-sub">Total uploads</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon red">⚠️</div>
        <div class="stat-info">
          <div class="stat-label">Pending Reports</div>
          <div class="stat-value"><?= number_format($stats['pending_reports']) ?></div>
          <div class="stat-sub"><a href="reports.php">Review now →</a></div>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

      <!-- Messages Chart -->
      <div class="data-table-wrap fade-in-up" style="padding:24px">
        <h3 style="margin-bottom:20px">📊 Messages (Last 7 Days)</h3>
        <div class="chart-bars" style="display:flex;align-items:flex-end;gap:8px;height:160px">
          <?php
          $maxMsg = max(1, max($last7));
          foreach ($last7 as $day => $count):
            $pct = ($count / $maxMsg) * 100;
            $label = date('D', strtotime($day));
          ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
            <span style="font-size:.7rem;color:var(--text-secondary)"><?= $count ?></span>
            <div style="width:100%;background:linear-gradient(to top,var(--accent),var(--purple));border-radius:6px 6px 0 0;min-height:4px;height:<?= max(4, $pct) ?>%;transition:height .5s ease"></div>
            <span style="font-size:.65rem;color:var(--text-muted)"><?= $label ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- New Users Chart -->
      <div class="data-table-wrap fade-in-up" style="padding:24px">
        <h3 style="margin-bottom:20px">📈 New Users (Last 7 Days)</h3>
        <div class="chart-bars" style="display:flex;align-items:flex-end;gap:8px;height:160px">
          <?php
          $maxUser = max(1, max($usersLast7));
          foreach ($usersLast7 as $day => $count):
            $pct = ($count / $maxUser) * 100;
            $label = date('D', strtotime($day));
          ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
            <span style="font-size:.7rem;color:var(--text-secondary)"><?= $count ?></span>
            <div style="width:100%;background:linear-gradient(to top,var(--green),#06b6d4);border-radius:6px 6px 0 0;min-height:4px;height:<?= max(4, $pct) ?>%;transition:height .5s ease"></div>
            <span style="font-size:.65rem;color:var(--text-muted)"><?= $label ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

      <!-- Top Active Users -->
      <div class="data-table-wrap fade-in-up">
        <div class="data-table-header">
          <h3>🏆 Top Active Users</h3>
        </div>
        <table>
          <thead>
            <tr><th>#</th><th>User</th><th>Messages</th></tr>
          </thead>
          <tbody>
            <?php foreach ($topUsers as $i => $u): ?>
            <tr>
              <td class="text-muted text-sm"><?= $i + 1 ?></td>
              <td>
                <div class="flex items-center gap-2">
                  <div class="avatar-placeholder" style="width:30px;height:30px;background:var(--accent);font-size:.6rem;border-radius:50%">
                    <?= strtoupper(substr($u['full_name']??'?',0,2)) ?>
                  </div>
                  <div>
                    <div style="font-size:.8125rem;font-weight:600"><?= htmlspecialchars($u['full_name']) ?></div>
                    <div class="text-xs text-muted">@<?= htmlspecialchars($u['username']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span style="font-weight:700;color:var(--accent)"><?= number_format($u['msg_count']) ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$topUsers): ?>
            <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--text-muted)">No data yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Message Type Breakdown -->
      <div class="data-table-wrap fade-in-up">
        <div class="data-table-header">
          <h3>📋 Message Types</h3>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:12px">
          <?php
          $totalTypeMsgs = max(1, array_sum(array_column($typeBreakdown, 'count')));
          $typeIcons = ['text'=>'💬','image'=>'📷','file'=>'📎','audio'=>'🎵','system'=>'⚙️'];
          $typeColors = ['text'=>'var(--accent)','image'=>'var(--green)','file'=>'var(--yellow)','audio'=>'var(--purple)','system'=>'var(--text-muted)'];
          foreach ($typeBreakdown as $t):
            $pct = round(($t['count'] / $totalTypeMsgs) * 100, 1);
            $icon = $typeIcons[$t['message_type']] ?? '📄';
            $color = $typeColors[$t['message_type']] ?? 'var(--accent)';
          ?>
          <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <span style="font-size:.875rem;font-weight:600"><?= $icon ?> <?= ucfirst($t['message_type']) ?></span>
              <span style="font-size:.75rem;color:var(--text-muted)"><?= number_format($t['count']) ?> (<?= $pct ?>%)</span>
            </div>
            <div style="height:8px;background:rgba(255,255,255,.06);border-radius:99px;overflow:hidden">
              <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:99px;transition:width .5s ease"></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (!$typeBreakdown): ?>
          <div style="text-align:center;padding:24px;color:var(--text-muted)">No messages yet.</div>
          <?php endif; ?>
        </div>
      </div>

    </div>

  </main>
</div>
</body>
</html>
