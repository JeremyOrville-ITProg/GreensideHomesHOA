<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal error | Greenside Homes</title>
    <link rel="icon" type="image/jpeg" href="Assets/logogreenside.jpg">
    <link rel="stylesheet" href="Assets/style.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <a class="brand auth-brand" href="index.php">
            <span class="brand-mark" aria-hidden="true"><img src="Assets/logogreenside.jpg" alt=""></span>
            <span><strong>Greenside</strong><small>HOMES COMMUNITY</small></span>
        </a>
        <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
        <a class="button button-primary auth-submit" href="index.php">Return to the portal</a>
    </main>
</body>
</html>
