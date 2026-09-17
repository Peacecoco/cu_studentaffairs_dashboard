  </main>
  </div>
  <script>
    window.affairsCsrf = <?= json_encode($officerCsrf ?? '') ?>;
    window.currentReviewer = <?= json_encode($officer ? $officer->id : '') ?>;
  </script>
  <script src="../assets/js/studentaffairs.js?v=<?= filemtime(__DIR__ . '/../../assets/js/studentaffairs.js') ?>"></script>
  </body>

  </html>