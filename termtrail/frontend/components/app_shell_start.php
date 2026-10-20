<?php
use TermTrail\helpers\Security as S;
require __DIR__ . '/header.php';
$nav = [
    ['dashboard', 'layout-dashboard', 'Dashboard'],
    ['settings',  'settings',         'Settings'],
];
$initial = strtoupper(mb_substr($user['name'], 0, 1));
?>
<body class="app-body">
<a class="skip-link" href="#main">Skip to content</a>
<div class="app-shell" id="appShell">
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand"><span class="brand-mark"><i data-lucide="file-search"></i></span><span class="brand-text">TermTrail <b>AI</b></span></div>
    <nav class="nav flex-column">
      <?php foreach ($nav as [$slug, $icon, $label]): ?>
        <a class="side-link <?= ($active ?? '') === $slug ? 'active' : '' ?>" href="<?= APP_URL ?>/index.php?page=<?= $slug ?>">
          <i data-lucide="<?= $icon ?>"></i><span><?= S::e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot small text-muted">Informational assistance only — not legal advice.</div>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <div class="main-col">
    <header class="topbar">
      <button class="icon-btn" id="sidebarToggle" aria-label="Toggle navigation"><i data-lucide="menu"></i></button>
      <div class="flex-grow-1"></div>
      <button class="icon-btn" id="themeToggle" aria-label="Toggle light or dark mode"><i data-lucide="sun-moon"></i></button>
      <div class="dropdown">
        <button class="user-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="avatar"><?= S::e($initial) ?></span><span class="d-none d-md-inline"><?= S::e($user['name']) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li class="dropdown-header"><?= S::e($user['email']) ?><br><span class="badge badge-role"><?= S::e($user['role']) ?></span></li>
          <li><a class="dropdown-item" href="<?= APP_URL ?>/index.php?page=settings">Account settings</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><button class="dropdown-item" id="logoutBtn">Sign out</button></li>
        </ul>
      </div>
    </header>
    <main id="main" class="content" tabindex="-1">
      <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item">TermTrail</li><li class="breadcrumb-item active"><?= S::e($title) ?></li></ol></nav>
