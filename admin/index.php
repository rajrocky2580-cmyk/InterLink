<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$pdo = getDB();

// Stats
$stats = [];
$stats['total_users']    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['active_users']   = $pdo->query("SELECT COUNT(*) FROM users WHERE is_online=1")->fetchColumn();
$stats['msgs_today']     = $pdo->query("SELECT COUNT(*) FROM messages WHERE DATE(sent_at)=CURDATE()")->fetchColumn();
$stats['total_msgs']     = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$stats['total_groups']   = $pdo->query("SELECT COUNT(*) FROM `groups`")->fetchColumn();
$stats['files_today']    = $pdo->query("SELECT COUNT(*) FROM files WHERE DATE(uploaded_at)=CURDATE()")->fetchColumn();
$stats['pending_reports']= $pdo->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn();

// Recent registrations
$recent = $pdo->query("SELECT user_id,username,full_name,email,created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>InterLink — Admin Dashboard</title>
  <meta name="description" content="InterLink admin panel — system overview and statistics.">
  <link rel="icon" href="../assets/images/favicon.png" type="image/png">
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

  <!-- Admin Sidebar -->
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <!-- Admin Main -->
  <main class="admin-main">

    <div class="admin-page-header fade-in-up">
      <h1>Dashboard</h1>
      <p>Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?>. Here's what's happening.</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid fade-in-up">
      <div class="stat-card">
        <div class="stat-icon blue">👥</div>
        <div class="stat-info">
          <div class="stat-label">Total Users</div>
          <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
          <div class="stat-sub"><?= $stats['active_users'] ?> online now</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple">💬</div>
        <div class="stat-info">
          <div class="stat-label">Messages Today</div>
          <div class="stat-value"><?= number_format($stats['msgs_today']) ?></div>
          <div class="stat-sub"><?= number_format($stats['total_msgs']) ?> total</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">🏘️</div>
        <div class="stat-info">
          <div class="stat-label">Active Groups</div>
          <div class="stat-value"><?= number_format($stats['total_groups']) ?></div>
          <div class="stat-sub">Group conversations</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon yellow">📁</div>
        <div class="stat-info">
          <div class="stat-label">Files Today</div>
          <div class="stat-value"><?= number_format($stats['files_today']) ?></div>
          <div class="stat-sub">Uploads & shares</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon red">⚠️</div>
        <div class="stat-info">
          <div class="stat-label">Pending Reports</div>
          <div class="stat-value"><?= number_format($stats['pending_reports']) ?></div>
          <div class="stat-sub">Needs review</div>
        </div>
      </div>
    </div>

    <!-- Recent Registrations -->
    <div class="data-table-wrap fade-in-up">
      <div class="data-table-header">
        <h3>Recent Registrations</h3>
        <a href="users.php" class="btn btn-ghost btn-sm">View All →</a>
      </div>
      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Username</th>
            <th>Email</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $u): ?>
          <tr>
            <td>
              <div class="flex items-center gap-2">
                <div class="avatar-placeholder" style="width:32px;height:32px;background:var(--accent);font-size:.65rem;border-radius:50%">
                  <?= strtoupper(substr($u['full_name'],0,2)) ?>
                </div>
                <?= htmlspecialchars($u['full_name']) ?>
              </div>
            </td>
            <td class="text-muted">@<?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td class="text-muted text-sm"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </main>
</div>
</body>
</html>
