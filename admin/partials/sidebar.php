<nav class="admin-sidebar">
  <div class="admin-sidebar-logo">
    <div class="sidebar-logo" style="display:flex;align-items:center;gap:10px;font-weight:800;font-size:1rem">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,var(--accent),var(--purple));border-radius:8px;display:flex;align-items:center;justify-content:center">💬</div>
      Inter<span style="color:var(--accent)">Link</span>
      <span style="font-size:.65rem;background:rgba(239,68,68,.2);color:#fca5a5;padding:2px 6px;border-radius:4px;font-weight:600;margin-left:4px">ADMIN</span>
    </div>
  </div>

  <?php
  $page = basename($_SERVER['PHP_SELF']);
  $links = [
    'index.php'    => ['📊','Dashboard'],
    'users.php'    => ['👥','Users'],
    'messages.php' => ['💬','Message Logs'],
    'reports.php'  => ['⚠️','Reports'],
    'stats.php'    => ['📈','Statistics'],
  ];
  foreach ($links as $file => [$icon, $label]):
    $active = ($page === $file) ? 'active' : '';
  ?>
  <a href="<?= $file ?>" class="admin-nav-item <?= $active ?>">
    <span class="nav-icon"><?= $icon ?></span> <?= $label ?>
  </a>
  <?php endforeach; ?>

  <div style="flex:1"></div>
  <a href="../chat.php" class="admin-nav-item">
    <span class="nav-icon">↩️</span> Back to Chat
  </a>
  <a href="../api/auth/logout.php" class="admin-nav-item" style="color:var(--red)">
    <span class="nav-icon">🚪</span> Logout
  </a>
</nav>
