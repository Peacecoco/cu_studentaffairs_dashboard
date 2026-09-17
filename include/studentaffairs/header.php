<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

$officer = null;
$officerCsrf = '';
$officerError = '';

try {
  $officer = currentAffairsOfficer();
  $officerCsrf = affairsCsrfToken($officer);
} catch (Throwable $e) {
  $officerError = 'Authorized Student Affairs identity required.';
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Student Affairs | <?= htmlspecialchars($pageTitle ?? 'ID Card Applications') ?></title>
  <link rel="stylesheet" href="../assets/css/studentaffairs.css?v=<?= filemtime(__DIR__ . '/../../assets/css/studentaffairs.css') ?>">
</head>

<body data-tab="<?= htmlspecialchars($pageTab ?? 'queue') ?>">
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <img src="../assets/images/university-logo.png" alt="Covenant University">
        <div><strong>Covenant</strong><span>University</span></div>
      </div>
      <nav>
        <?php foreach (
          [
            'queue' => ['review-requests.php', 'Applications & Refunds']
          ] as $key => [$url, $label]
        ): ?>
          <a class="nav-link<?= ($pageTab ?? 'queue') === $key ? ' active' : '' ?>" href="<?= $url ?>" <?= ($pageTab ?? 'queue') === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
        <?php endforeach; ?>
      </nav>
    </aside>
    <main class="content">
      <header class="topbar">
        <div>
          <p>Student Affairs / Replacement ID Card Portal</p>
          <h1 id="pageTitle"><?= htmlspecialchars($pageTitle ?? 'ID Card Replacement Requests') ?></h1>
        </div>
        <div class="user-info">
          <span class="officer-badge"><?= htmlspecialchars($officer ? $officer->id : 'Not signed in') ?></span>
        </div>
      </header>

      <?php if ($officerError !== ''): ?>
        <div class="notice show error" role="alert"><?= htmlspecialchars($officerError) ?> Please ensure you are authenticated through the host portal.</div>
      <?php endif; ?>
      <div id="statusMessage" class="notice" role="status"></div>
