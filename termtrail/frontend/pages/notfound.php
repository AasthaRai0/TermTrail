<?php
use TermTrail\helpers\Security as S;
$title = 'Page not found';
if ($user) { $active = ''; require __DIR__ . '/../components/app_shell_start.php'; } else { require __DIR__ . '/../components/header.php'; echo '<body class="auth-body"><main class="auth-card">'; }
?>
<div class="empty-state"><i data-lucide="compass"></i><h2>Page not found</h2><p>The page you requested does not exist.</p>
<a class="btn btn-primary" href="<?= APP_URL ?>/index.php">Go home</a></div>
<?php if ($user) { require __DIR__ . '/../components/app_shell_end.php'; } else { echo '</main><script src="https://unpkg.com/lucide@0.441.0/dist/umd/lucide.min.js"></script><script>lucide.createIcons()</script></body></html>'; } ?>
