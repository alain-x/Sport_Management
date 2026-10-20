<?php
/**
 * Standalone 403 page.
 * @var string $appName
 * @var string $errorMessage
 */
$message = $errorMessage ?? 'You do not have permission to view this page.';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Forbidden &middot; <?= e($appName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="login-page">
<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center text-white">
        <div class="display-1 fw-bold">403</div>
        <p class="lead mb-1">Access denied.</p>
        <p class="mb-4 text-white-50"><?= e($message) ?></p>
        <a class="btn btn-light" href="<?= e(url('/')) ?>">
            <i class="bi bi-house-door me-1"></i>Back to dashboard
        </a>
    </div>
</div>
</body>
</html>
