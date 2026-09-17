    <section id="listView" class="applications-container">
      <div class="toolbar">
        <div class="search-box">
          <label for="searchInput" class="sr-only">Search applications</label>
          <input id="searchInput" type="search" placeholder="Search reference, matric, or student name">
        </div>
        <div class="filter-box">
          <label for="refundFilter">Refund status</label>
          <select id="refundFilter">
            <option value="all">All Applications</option>
            <option value="requested">Refund: Requested</option>
            <option value="approved">Refund: Approved</option>
            <option value="credited">Refund: Credited</option>
            <option value="norefund">No Refund</option>
          </select>
        </div>
        <button id="refreshBtn" class="btn secondary" type="button">Refresh</button>
      </div>

      <div class="table-wrap">
        <table class="app-table" id="applicationsTable">
          <thead>
            <tr>
              <th scope="col">Matric Number</th>
              <th scope="col">Student Name</th>
              <th scope="col">Reference ID</th>
              <th scope="col">Payment Status</th>
              <th scope="col">Application History</th>
              <th scope="col">Status</th>
              <th scope="col">Refund</th>
            </tr>
          </thead>
          <tbody id="applicationsTableBody">
            <tr>
              <td colspan="7" class="empty-state">Loading applications...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Payment Details Modal -->
    <dialog id="paymentModal" class="modal-dialog">
      <div class="modal-card">
        <header class="modal-header">
          <h2>Payment Details</h2>
          <button class="modal-close" type="button" aria-label="Close dialog">&times;</button>
        </header>
        <div class="modal-body" id="paymentModalContent">
          Loading payment information...
        </div>
        <footer class="modal-footer">
          <button class="btn secondary modal-close" type="button">Close</button>
        </footer>
      </div>
    </dialog>

    <!-- Application History Modal -->
    <dialog id="historyModal" class="modal-dialog">
      <div class="modal-card">
        <header class="modal-header">
          <h2>Application History</h2>
          <button class="modal-close" type="button" aria-label="Close dialog">&times;</button>
        </header>
        <div class="modal-body">
          <div id="historyRef" class="modal-subtitle"></div>
          <div id="historyTimeline" class="timeline-container">
            Loading chronological history...
          </div>
        </div>
        <footer class="modal-footer">
          <button class="btn secondary modal-close" type="button">Close</button>
        </footer>
      </div>
    </dialog>

    <!-- Refund Review Modal -->
    <dialog id="refundReviewModal" class="modal-dialog refund-modal">
      <div class="modal-card">
        <header class="modal-header">
          <h2>Refund Request Review</h2>
          <button class="modal-close" type="button" aria-label="Close dialog">&times;</button>
        </header>
        <div class="modal-body" id="refundReviewContent">
          Loading refund details...
        </div>
        <footer class="modal-footer">
          <button class="btn secondary modal-close" type="button">Cancel</button>
          <button id="openApproveConfirmBtn" class="btn primary" type="button">Approve Refund</button>
        </footer>
      </div>
    </dialog>

    <!-- Approve Confirmation Modal -->
    <dialog id="approveConfirmModal" class="modal-dialog confirm-dialog">
      <div class="modal-card">
        <header class="modal-header">
          <h2>Confirm Refund Approval</h2>
        </header>
        <div class="modal-body">
          <p id="approveConfirmPrompt">Do you want to approve this refund request? Once approved, it will be forwarded to the Account Office for processing.</p>
          <div class="callout-box">
            <strong>Reference: </strong><span id="confirmRef"></span><br>
            <strong>Amount: </strong><span id="confirmAmount"></span>
          </div>
        </div>
        <footer class="modal-footer">
          <button id="cancelConfirmBtn" class="btn secondary" type="button">Cancel</button>
          <button id="executeApproveBtn" class="btn primary" type="button">Confirm Approval</button>
        </footer>
      </div>
    </dialog>
