<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Student Affairs | ID Card Requests</title>
  <link rel="stylesheet" href="../assets/css/studentaffairs.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><img src="../assets/images/university-logo.png" alt="Covenant University"><div><strong>Covenant</strong><span>University</span></div></div>
    <nav><button class="nav-link active" data-tab="queue">Review ID Requests</button><button class="nav-link" data-tab="approved">Approved Log</button><button class="nav-link" data-tab="rejected">Rejected Log</button></nav>
  </aside>
  <main class="content">
    <header class="topbar"><div><p>Student Services / ID Card</p><h1 id="pageTitle">ID Card Replacement Queue</h1></div><button id="notificationBell" class="bell" aria-label="Pending applications">&#128276;<b id="notificationCount" hidden>0</b></button></header>
    <div id="notificationDropdown" class="notifications" hidden></div>
    <div id="statusMessage" class="notice" role="status"></div>
    <section id="listView">
      <div id="filters" class="filters"><input id="searchInput" type="search" placeholder="Search name, matric number or reference"><select id="reasonFilter"><option value="">All reasons</option><option value="loststolen">Lost / Stolen</option><option value="damaged">Damaged</option></select></div>
      <section id="applicationList" class="application-list"></section>
    </section>
    <section id="detailView" hidden><button id="backButton" class="back-button" type="button">← Back to list</button><article id="detailContent" class="detail-card"></article></section>
  </main>
</div>
<dialog id="approveDialog"><form method="dialog"><h2>Approve application?</h2><p>The student will be moved to payment processing using the configured fee and deadline.</p><div class="dialog-actions"><button class="btn secondary" value="cancel">Cancel</button><button id="approveButton" class="btn primary" value="confirm">Approve application</button></div></form></dialog>
<dialog id="rejectDialog"><form method="dialog"><h2>Reject application</h2><label>Reason<textarea id="rejectReason" required placeholder="Explain why this request cannot be approved."></textarea></label><div class="dialog-actions"><button class="btn secondary" value="cancel">Cancel</button><button id="rejectButton" class="btn danger" value="confirm">Confirm rejection</button></div></form></dialog>
<dialog id="successDialog"><form method="dialog"><h2 id="successTitle">Success</h2><p id="successText"></p><button class="btn primary">Done</button></form></dialog>
<dialog id="mediaDialog" class="media-dialog"><form method="dialog"><button class="close" aria-label="Close">×</button></form><div id="mediaContent"></div></dialog>
<script src="../assets/js/studentaffairs.js"></script>
</body>
</html>
