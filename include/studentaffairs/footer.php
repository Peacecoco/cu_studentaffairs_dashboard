  </main>
</div>
<dialog id="approveDialog"><form method="dialog"><h2>Approve application?</h2><p>The student will be moved to payment processing using the configured fee and deadline.</p><div class="dialog-actions"><button class="btn secondary" value="cancel">Cancel</button><button id="approveButton" class="btn primary" value="confirm">Approve application</button></div></form></dialog>
<dialog id="rejectDialog"><form method="dialog"><h2>Reject application</h2><label>Reason<textarea id="rejectReason" required placeholder="Explain why this request cannot be approved."></textarea></label><div class="dialog-actions"><button class="btn secondary" value="cancel">Cancel</button><button id="rejectButton" class="btn danger" value="confirm">Confirm rejection</button></div></form></dialog>
<dialog id="successDialog"><form method="dialog"><h2 id="successTitle">Success</h2><p id="successText"></p><button class="btn primary">Done</button></form></dialog>
<dialog id="mediaDialog" class="media-dialog"><form method="dialog"><button class="close" aria-label="Close">×</button></form><div id="mediaContent"></div></dialog>
<script src="../assets/js/studentaffairs.js?v=<?= filemtime(__DIR__ . '/../../assets/js/studentaffairs.js') ?>"></script>
</body>
</html>
