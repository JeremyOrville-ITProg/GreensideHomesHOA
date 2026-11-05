<?php
$isRegister = $page === 'register';
$title = $isRegister ? 'Create a resident account' : 'Sign in to your account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#13734c">
    <title><?= e($title) ?> | Greenside Homes</title>
    <link rel="icon" type="image/jpeg" href="Assets/logogreenside.jpg">
    <link rel="stylesheet" href="Assets/style.css">
</head>
<body class="auth-page">
    <main class="auth-layout">
        <section class="auth-welcome" aria-label="Greenside Homes community">
            <a class="auth-logo-link" href="index.php" aria-label="Greenside Homes">
                <img class="auth-logo" src="Assets/logogreenside.jpg" alt="Greenside Homes Homeowners Association logo">
            </a>
            <p class="auth-eyebrow">GREENSIDE HOMES RESIDENT PORTAL</p>
            <h2>Your community,<br>all in one place.</h2>
            <p class="auth-welcome-copy">Stay connected with your neighbors, keep up with community updates, and manage your HOA bills with ease.</p>
            <div class="auth-benefits" aria-label="Portal features">
                <span><i aria-hidden="true">✓</i> Community updates</span>
                <span><i aria-hidden="true">✓</i> Bills and payments</span>
                <span><i aria-hidden="true">✓</i> Resident support</span>
            </div>
            <span class="auth-decoration auth-decoration-one" aria-hidden="true"></span>
            <span class="auth-decoration auth-decoration-two" aria-hidden="true"></span>
        </section>

        <section class="auth-panel" aria-labelledby="auth-title">
            <a class="auth-mobile-brand" href="index.php">
                <img src="Assets/logogreenside.jpg" alt="">
                <span><strong>Greenside Homes</strong><small>RESIDENT PORTAL</small></span>
            </a>
            <div class="auth-heading">
                <p class="auth-form-eyebrow"><?= $isRegister ? 'JOIN YOUR COMMUNITY' : 'WELCOME BACK' ?></p>
                <h1 id="auth-title"><?= e($isRegister ? 'Create your account' : 'Sign in') ?></h1>
                <p><?= $isRegister ? 'Request access to your Greenside Homes resident portal.' : 'Sign in to stay up to date with Greenside Homes.' ?></p>
            </div>
            <?php if ($flashMessage): ?>
                <div class="notice notice-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="index.php?action=<?= $isRegister ? 'register' : 'login' ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <?php if ($isRegister): ?>
                    <label for="full-name">Full name</label>
                    <input id="full-name" name="full_name" type="text" maxlength="150" autocomplete="name" placeholder="Your full name" required>
                <?php endif; ?>
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" maxlength="254" autocomplete="email" placeholder="you@example.com" required>
                <?php if ($isRegister): ?>
                    <label for="phone">Phone <span>(optional)</span></label>
                    <input id="phone" name="phone" type="tel" maxlength="32" autocomplete="tel" placeholder="Your phone number">
                    <label for="house-no">House, block and lot</label>
                    <input id="house-no" name="house_no" type="text" maxlength="100" autocomplete="street-address" placeholder="e.g. Block 4, Lot 12" required>
                <?php endif; ?>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" minlength="<?= $isRegister ? '10' : '1' ?>" maxlength="200" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" placeholder="<?= $isRegister ? 'At least 10 characters' : 'Enter your password' ?>" required>
                <?php if ($isRegister): ?><p class="auth-field-hint">Use at least 10 characters. Your account will need HOA administrator approval.</p><?php endif; ?>
                <button class="button button-primary auth-submit" type="submit"><?= $isRegister ? 'Request account' : 'Sign in to your account' ?></button>
            </form>

            <p class="auth-switch">
                <?= $isRegister ? 'Already have an account?' : 'New to Greenside Homes?' ?>
                <a href="index.php?action=<?= $isRegister ? 'login' : 'register' ?>"><?= $isRegister ? 'Sign in' : 'Create an account' ?></a>
            </p>
            <p class="auth-note"><span aria-hidden="true">▣</span> Your resident account is protected and only accessible to you.</p>
            <p class="auth-copyright">Greenside Homes Homeowners Association</p>
        </section>
    </main>
</body>
</html>
