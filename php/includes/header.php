<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'SMA Portal';
$activeRole = $_SESSION['user']['role'] ?? 'guest';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> · SMA</title>
    <link rel="icon" type="image/png" href="/assets/logo.png">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="app-shell" data-role="<?= htmlspecialchars($activeRole, ENT_QUOTES, 'UTF-8') ?>">