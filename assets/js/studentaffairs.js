(() => {
  const api = new URL('../index.php', window.location.href).toString();
  const searchInput = document.getElementById('searchInput');
  const refundFilter = document.getElementById('refundFilter');
  const refreshBtn = document.getElementById('refreshBtn');
  const tableBody = document.getElementById('applicationsTableBody');
  const statusMessage = document.getElementById('statusMessage');

  const paymentModal = document.getElementById('paymentModal');
  const paymentContent = document.getElementById('paymentModalContent');
  const historyModal = document.getElementById('historyModal');
  const historyTimeline = document.getElementById('historyTimeline');
  const historyRef = document.getElementById('historyRef');
  const refundReviewModal = document.getElementById('refundReviewModal');
  const refundReviewContent = document.getElementById('refundReviewContent');
  const openApproveConfirmBtn = document.getElementById('openApproveConfirmBtn');
  const approveConfirmModal = document.getElementById('approveConfirmModal');
  const executeApproveBtn = document.getElementById('executeApproveBtn');
  const cancelConfirmBtn = document.getElementById('cancelConfirmBtn');
  const confirmRef = document.getElementById('confirmRef');
  const confirmAmount = document.getElementById('confirmAmount');

  let applications = [];
  let currentRefund = null;

  const esc = str => String(str ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  
  const formatDate = val => {
    if (!val) return 'Not available';
    const d = new Date(String(val).replace(' ', 'T'));
    return isNaN(d.getTime()) ? val : d.toLocaleString();
  };

  const showNotice = (msg, kind = 'success') => {
    if (!statusMessage) return;
    statusMessage.textContent = msg;
    statusMessage.className = `notice show ${kind}`;
    setTimeout(() => {
      if (statusMessage.textContent === msg) {
        statusMessage.className = 'notice';
      }
    }, 6000);
  };

  const request = async (path, options = {}) => {
    const headers = { 'Accept': 'application/json', ...(options.headers || {}) };
    const response = await fetch(`${api}${path}`, { ...options, headers });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Request failed.');
    }
    return data;
  };

  const renderTable = () => {
    const searchVal = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const filterVal = refundFilter ? refundFilter.value : 'all';

    const filtered = applications.filter(app => {
      if (filterVal === 'requested' && app.refund.status !== 'requested') return false;
      if (filterVal === 'approved' && app.refund.status !== 'approved') return false;
      if (filterVal === 'credited' && app.refund.status !== 'credited') return false;
      if (filterVal === 'norefund' && app.refund.status !== 'none' && app.refund.status) return false;

      if (!searchVal) return true;
      const ref = String(app.referencenumber || '').toLowerCase();
      const matric = String(app.matricnumber || '').toLowerCase();
      const name = String(app.applicant_name || '').toLowerCase();
      return ref.includes(searchVal) || matric.includes(searchVal) || name.includes(searchVal);
    });

    if (!filtered.length) {
      tableBody.innerHTML = `<tr><td colspan="7" class="empty-state">No replacement applications found.</td></tr>`;
      return;
    }

    tableBody.innerHTML = filtered.map(app => {
      const isPaid = app.paymentstatus === 'paid';
      const isFailed = app.paymentstatus === 'failed';
      const payStatusClass = isPaid ? 'paid' : (isFailed ? 'failed' : 'legacy');
      
      let refundHtml = '';
      if (app.refund.status === 'requested') {
        refundHtml = `<button type="button" class="refund-action-btn" data-action="review-refund" data-ref="${esc(app.referencenumber)}" aria-label="Review refund request for ${esc(app.referencenumber)}">
          <span aria-hidden="true">&#9888;</span> Requested — Review
        </button>`;
      } else if (app.refund.status === 'approved') {
        refundHtml = `<span class="badge approved">Approved</span>`;
      } else if (app.refund.status === 'credited') {
        refundHtml = `<span class="badge credited">Credited</span>`;
      } else {
        refundHtml = `<span class="badge norefund">No Refund</span>`;
      }

      return `
        <tr data-ref="${esc(app.referencenumber)}">
          <td><strong>${esc(app.matricnumber)}</strong></td>
          <td>
            ${esc(app.applicant_name)}
            <span class="student-sub">${esc(app.department || app.programme || 'Student')}</span>
          </td>
          <td><code>${esc(app.referencenumber)}</code></td>
          <td>
            <button type="button" class="btn-link" data-action="view-payment" data-ref="${esc(app.referencenumber)}">
              <span class="badge ${payStatusClass}">${esc(app.paymentstatus_label || app.paymentstatus || 'Legacy')}</span>
            </button>
          </td>
          <td>
            <button type="button" class="btn secondary btn-sm" data-action="view-history" data-ref="${esc(app.referencenumber)}">
              View History
            </button>
          </td>
          <td>
            <span class="badge ${esc(app.status)}">${esc(app.status)}</span>
          </td>
          <td>${refundHtml}</td>
        </tr>
      `;
    }).join('');
  };

  const loadApplications = async () => {
    try {
      tableBody.innerHTML = `<tr><td colspan="7" class="empty-state">Loading applications...</td></tr>`;
      const res = await request('?action=applications');
      applications = res.data || [];
      renderTable();
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="7" class="empty-state">Error loading applications: ${esc(err.message)}</td></tr>`;
      showNotice(err.message, 'error');
    }
  };

  const showPaymentModal = async ref => {
    paymentContent.innerHTML = 'Loading payment details...';
    paymentModal.showModal();
    try {
      const res = await request(`?action=paymentdetails&ref=${encodeURIComponent(ref)}`);
      const { application, transactions } = res.data;
      
      let txHtml = '';
      if (!transactions || !transactions.length) {
        txHtml = '<p class="modal-subtitle">No payment transactions recorded for this application.</p>';
      } else {
        txHtml = transactions.map((tx, idx) => `
          <div class="callout-box" style="margin-top: 10px;">
            <div><strong>Transaction #${idx + 1}</strong> (${esc(tx.status || 'unknown')})</div>
            <div><strong>Payment Ref: </strong><code>${esc(tx.paymentreference || 'N/A')}</code></div>
            <div><strong>Base Amount: </strong>₦${Number(tx.baseamount || 0).toLocaleString()}</div>
            <div><strong>Charges: </strong>₦${Number(tx.chargeamount || 0).toLocaleString()}</div>
            <div><strong>Total: </strong>₦${Number(tx.totalamount || 0).toLocaleString()}</div>
            <div><strong>Provider: </strong>${esc(tx.provider || 'local-simulator')} (${esc(tx.currency || 'NGN')})</div>
            <div><strong>Completed At: </strong>${formatDate(tx.completedat || tx.paidat || tx.createdat)}</div>
            ${tx.failuremessage ? `<div style="color: var(--danger);"><strong>Failure: </strong>${esc(tx.failuremessage)}</div>` : ''}
          </div>
        `).join('');
      }

      paymentContent.innerHTML = `
        <div class="detail-grid-view">
          <div class="detail-item">
            <small>Application Reference</small>
            <strong>${esc(application.referencenumber)}</strong>
          </div>
          <div class="detail-item">
            <small>Matric Number</small>
            <strong>${esc(application.matricnumber)}</strong>
          </div>
          <div class="detail-item">
            <small>Payment Status</small>
            <span class="badge ${application.paymentstatus === 'paid' ? 'paid' : (application.paymentstatus === 'failed' ? 'failed' : 'legacy')}">${esc(application.paymentstatus || 'Legacy / Not Available')}</span>
          </div>
          <div class="detail-item">
            <small>Paid At</small>
            <strong>${formatDate(application.paidat)}</strong>
          </div>
        </div>
        <h3 style="font-size: 1rem; margin: 16px 0 8px;">Recorded Transactions</h3>
        ${txHtml}
      `;
    } catch (err) {
      paymentContent.innerHTML = `<div class="notice show error">${esc(err.message)}</div>`;
    }
  };

  const showHistoryModal = async ref => {
    historyRef.textContent = `Reference: ${ref}`;
    historyTimeline.innerHTML = 'Loading events...';
    historyModal.showModal();
    try {
      const res = await request(`?action=history&ref=${encodeURIComponent(ref)}`);
      const events = res.data || [];
      if (!events.length) {
        historyTimeline.innerHTML = '<p class="modal-subtitle">No lifecycle events recorded for this application.</p>';
        return;
      }
      const eventLabels = {
        'payment_paid': 'Payment successful',
        'payment_failed': 'Payment failed',
        'refund_requested': 'Refund requested by student',
        'refund_approved': 'Refund approved by Student Affairs',
        'refund_credited': 'Refund credited by Account Office',
        'card_printed': 'ID Card physically printed',
        'card_collected': 'ID Card collected by student'
      };
      historyTimeline.innerHTML = events.map(ev => `
        <div class="timeline-entry">
          <div class="timeline-dot"></div>
          <div class="timeline-content">
            <strong>${esc(eventLabels[ev.eventtype] || ev.eventtype)}</strong>
            <span>${formatDate(ev.occurredat)} · Actor: ${esc(ev.actorrole || 'system')}</span>
          </div>
        </div>
      `).join('');
    } catch (err) {
      historyTimeline.innerHTML = `<div class="notice show error">${esc(err.message)}</div>`;
    }
  };

  const showRefundReviewModal = async ref => {
    refundReviewContent.innerHTML = 'Loading refund request details...';
    openApproveConfirmBtn.hidden = true;
    refundReviewModal.showModal();
    try {
      const res = await request(`?action=refunddetails&ref=${encodeURIComponent(ref)}`);
      currentRefund = res.data;
      const r = currentRefund;
      
      const isRequested = r.refund && r.refund.status === 'requested';
      openApproveConfirmBtn.hidden = !isRequested;
      openApproveConfirmBtn.disabled = !isRequested;

      let historyList = '';
      if (r.events && r.events.length) {
        historyList = `
          <div class="timeline-container" style="margin-top: 10px;">
            ${r.events.map(ev => `
              <div class="timeline-entry">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <strong>${esc(ev.eventtype)}</strong>
                  <span>${formatDate(ev.occurredat)}</span>
                </div>
              </div>
            `).join('')}
          </div>
        `;
      } else {
        historyList = '<span class="modal-subtitle">No events logged.</span>';
      }

      refundReviewContent.innerHTML = `
        <div class="detail-grid-view">
          <div class="detail-item">
            <small>Student Name</small>
            <strong>${esc(r.student_name)}</strong>
          </div>
          <div class="detail-item">
            <small>Matric Number</small>
            <strong>${esc(r.matricnumber)}</strong>
          </div>
          <div class="detail-item">
            <small>Reference ID</small>
            <strong><code>${esc(r.referencenumber)}</code></strong>
          </div>
          <div class="detail-item">
            <small>Replacement Reason</small>
            <strong>${esc(r.applicationtype)}</strong>
          </div>
          <div class="detail-item">
            <small>Payment Status</small>
            <span class="badge ${r.paymentstatus === 'paid' ? 'paid' : 'failed'}">${esc(r.paymentstatus)}</span>
          </div>
          <div class="detail-item">
            <small>Payment Date</small>
            <strong>${formatDate(r.paidat)}</strong>
          </div>
          <div class="detail-item">
            <small>Refund Requested Date</small>
            <strong>${formatDate(r.refund.requestedat)}</strong>
          </div>
          <div class="detail-item">
            <small>Refund Amount</small>
            <strong style="color: var(--primary); font-size: 1.1rem;">₦${Number(r.refund.amount).toLocaleString()}</strong>
          </div>
          <div class="detail-item">
            <small>Application Status</small>
            <span class="badge ${esc(r.applicationstatus)}">${esc(r.applicationstatus)}</span>
          </div>
          <div class="detail-item">
            <small>Refund Status</small>
            <span class="badge ${esc(r.refund.status)}">${esc(r.refund.status)}</span>
          </div>
        </div>
        <h4 style="margin: 16px 0 8px; font-size: 0.95rem;">Application History Timeline</h4>
        ${historyList}
      `;
    } catch (err) {
      refundReviewContent.innerHTML = `<div class="notice show error">${esc(err.message)}</div>`;
    }
  };

  // Event Listeners
  if (searchInput) searchInput.addEventListener('input', renderTable);
  if (refundFilter) refundFilter.addEventListener('change', renderTable);
  if (refreshBtn) refreshBtn.addEventListener('click', loadApplications);

  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    const ref = trigger.dataset.ref;
    if (action === 'view-payment') showPaymentModal(ref);
    if (action === 'view-history') showHistoryModal(ref);
    if (action === 'review-refund') showRefundReviewModal(ref);
  });

  // Close modals
  document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => {
      const dialog = btn.closest('dialog');
      if (dialog) dialog.close();
    });
  });

  // Refund Approval flow
  if (openApproveConfirmBtn) {
    openApproveConfirmBtn.addEventListener('click', () => {
      if (!currentRefund) return;
      confirmRef.textContent = currentRefund.referencenumber;
      confirmAmount.textContent = `₦${Number(currentRefund.refund.amount).toLocaleString()}`;
      approveConfirmModal.showModal();
    });
  }

  if (cancelConfirmBtn) {
    cancelConfirmBtn.addEventListener('click', () => {
      approveConfirmModal.close();
    });
  }

  if (executeApproveBtn) {
    executeApproveBtn.addEventListener('click', async () => {
      if (!currentRefund) return;
      executeApproveBtn.disabled = true;
      executeApproveBtn.textContent = 'Approving...';
      try {
        const csrf = window.affairsCsrf || '';
        const res = await request('?action=approverefund', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({ referencenumber: currentRefund.referencenumber })
        });
        approveConfirmModal.close();
        refundReviewModal.close();
        showNotice(res.message || 'Refund approved successfully.', 'success');
        await loadApplications();
      } catch (err) {
        alert(err.message || 'Failed to approve refund.');
      } finally {
        executeApproveBtn.disabled = false;
        executeApproveBtn.textContent = 'Confirm Approval';
      }
    });
  }

  // Initial Load
  loadApplications();
})();
