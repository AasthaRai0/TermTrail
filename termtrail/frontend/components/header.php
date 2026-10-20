<?php
use TermTrail\helpers\Security as S;
/** @var array|null $user  @var string $title  @var string $active */
$theme = $user['theme'] ?? 'dark';
?><!doctype html>
<html lang="en" data-bs-theme="<?= S::e($theme) ?>" data-theme="<?= S::e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= S::e(S::csrfToken()) ?>">
<meta name="app-url" content="<?= S::e(APP_URL) ?>">
<title><?= S::e($title ?? 'TermTrail AI') ?> · TermTrail AI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= APP_URL ?>/frontend/assets/css/style.css" rel="stylesheet">
<link href="<?= APP_URL ?>/frontend/assets/css/components.css" rel="stylesheet">
<link href="<?= APP_URL ?>/frontend/assets/css/responsive.css" rel="stylesheet">
<?php if (!$user): ?><script>try{var t=localStorage.getItem('tt-theme');if(t){document.documentElement.dataset.theme=t;document.documentElement.setAttribute('data-bs-theme',t);}}catch(e){}</script><?php endif; ?>
</head>
