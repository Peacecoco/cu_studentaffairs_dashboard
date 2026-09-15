<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Student Affairs | ID Card Requests</title>
  <link rel="stylesheet" href="../assets/css/studentaffairs.css?v=<?= filemtime(__DIR__ . '/../../assets/css/studentaffairs.css') ?>">
</head>
<body data-tab="<?= htmlspecialchars($pageTab) ?>">
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><img src="../assets/images/university-logo.png" alt="Covenant University"><div><strong>Covenant</strong><span>University</span></div></div>
    <nav>
      <?php foreach (['queue' => ['review-requests.php', 'Review ID Requests'], 'approved' => ['approved-log.php', 'Approved Log'], 'rejected' => ['rejected-log.php', 'Rejected Log']] as $key => [$url, $label]): ?>
      <a class="nav-link<?= $pageTab === $key ? ' active' : '' ?>" href="<?= $url ?>"<?= $pageTab === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <main class="content">
    <header class="topbar"><div><p>Student Services / ID Card</p><h1 id="pageTitle"><?= htmlspecialchars($pageTitle) ?></h1></div><button id="notificationBell" class="bell" aria-label="Pending applications">&#128276;<b id="notificationCount" hidden>0</b></button></header>
    <div id="notificationDropdown" class="notifications" hidden></div>
    <div id="statusMessage" class="notice" role="status"></div>
