<?php require 'config/bootstrap.php'; if(uid()){header('Location: dashboard.php');exit;} page_head('Home',false); ?>
<div class="container py-5" style="max-width:820px"><h1 class="display-5 fw-bold">TermTrail <span style="color:var(--primary)">AI</span></h1>
<p class="lead">Understand the contract. Manage every commitment.</p>
<p class="text-muted">Contract library, protected document upload with text extraction, human-confirmed obligations, payment and deadline tracking. Informational software, not legal advice.</p>
<a class="btn btn-primary" href="register.php">Get Started</a> <a class="btn btn-outline-light" href="login.php">Log in</a></div><?php page_foot(false);
