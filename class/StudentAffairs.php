<?php

declare(strict_types=1);

use CU\IdCard\{Identity, Lifecycle};

class StudentAffairs extends General
{
    private Lifecycle $lifecycle;
    private Identity $identity;

    public function __construct(PDO $con, Lifecycle $lifecycle, Identity $identity)
    {
        parent::__construct($con);
        $this->lifecycle = $lifecycle;
        $this->identity = $identity;
    }

    /**
     * Retrieve all replacement applications across students with safe payment and refund state.
     * Pending refund requests ('requested') are sorted to the top.
     */
    public function allApplications(?string $search = null, ?string $refundFilter = null): never
    {
        $search = $this->clean($search ?? '');
        $refundFilter = strtolower($this->clean($refundFilter ?? ''));

        $query = "SELECT a.applicationid, a.referencenumber, a.matricnumber, a.applicationtype,
                         a.status, a.paymentstatus, a.paidat, a.createdat, a.updatedat,
                         s.full_name, s.department, s.programme,
                         r.refundid, r.status AS refundstatus, r.amount AS refundamount,
                         r.requestedat, r.requestedby, r.approvedat, r.approvedby, r.creditedat, r.creditedby
                  FROM idcardapplications a
                  LEFT JOIN students s ON s.matric_no = a.matricnumber
                  LEFT JOIN idcardrefunds r ON r.applicationid = a.applicationid";

        $conditions = [];
        $params = [];

        if ($search !== '') {
            $needle = '%' . $search . '%';
            $conditions[] = "(a.referencenumber LIKE ? OR a.matricnumber LIKE ? OR s.full_name LIKE ?)";
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }

        if ($refundFilter !== '' && $refundFilter !== 'all') {
            if ($refundFilter === 'norefund' || $refundFilter === 'none') {
                $conditions[] = "r.status IS NULL";
            } elseif (in_array($refundFilter, ['requested', 'approved', 'credited'], true)) {
                $conditions[] = "r.status = ?";
                $params[] = $refundFilter;
            }
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $query .= " ORDER BY 
                      CASE WHEN r.status = 'requested' THEN 0 ELSE 1 END,
                      a.createdat DESC, a.applicationid DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $formatted = array_map([$this, 'formatApplicationRow'], $rows);
        $this->respond(true, count($formatted) . ' application(s) retrieved.', $formatted);
    }

    /**
     * Retrieve safe payment transaction details for an application.
     * Never exposes credentials, secret keys, or raw provider payloads.
     */
    public function paymentDetails(string $reference): never
    {
        $ref = $this->clean($reference);
        if ($ref === '') {
            $this->respond(false, 'Application reference is required.', null, 400);
        }

        $stmt = $this->db->prepare("SELECT a.applicationid, a.referencenumber, a.matricnumber, a.paymentstatus, a.status, a.paidat, a.createdat,
                                           s.full_name
                                    FROM idcardapplications a
                                    LEFT JOIN students s ON s.matric_no = a.matricnumber
                                    WHERE a.referencenumber = ? LIMIT 1");
        $stmt->execute([$ref]);
        $app = $stmt->fetch();
        if (!$app) {
            $this->respond(false, 'Application not found.', null, 404);
        }

        $txStmt = $this->db->prepare("SELECT paymentreference, paymentoptionname, baseamount, chargeamount, totalamount,
                                             currency, provider, status, paidat, completedat, createdat, failuremessage
                                      FROM paymenttransactions
                                      WHERE applicationid = ?
                                      ORDER BY createdat ASC, paymentid ASC");
        $txStmt->execute([(int)$app['applicationid']]);
        $transactions = $txStmt->fetchAll();

        unset($app['applicationid']);
        $this->respond(true, 'Payment details retrieved.', [
            'application' => $app,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Retrieve chronological lifecycle events from the append-only audit table.
     * Legacy records without events return an empty array with no fabricated history.
     */
    public function history(string $reference): never
    {
        $ref = $this->clean($reference);
        if ($ref === '') {
            $this->respond(false, 'Application reference is required.', null, 400);
        }

        try {
            $events = $this->lifecycle->history($this->identity, $ref);
            $this->respond(true, 'Application history retrieved.', $events);
        } catch (DomainException $e) {
            $this->respond(false, $e->getMessage(), null, 404);
        } catch (Throwable $e) {
            $this->respond(false, 'Unable to load application history.', null, 500);
        }
    }

    /**
     * Context for the Refund Review modal.
     */
    public function refundDetails(string $reference): never
    {
        $ref = $this->clean($reference);
        if ($ref === '') {
            $this->respond(false, 'Application reference is required.', null, 400);
        }

        $stmt = $this->db->prepare("SELECT a.applicationid, a.referencenumber, a.matricnumber, a.applicationtype,
                                           a.status, a.paymentstatus, a.paidat, a.createdat,
                                           s.full_name, s.department, s.programme,
                                           r.refundid, r.status AS refundstatus, r.amount AS refundamount,
                                           r.requestedat, r.requestedby, r.approvedat, r.approvedby, r.creditedat, r.creditedby
                                    FROM idcardapplications a
                                    LEFT JOIN students s ON s.matric_no = a.matricnumber
                                    LEFT JOIN idcardrefunds r ON r.applicationid = a.applicationid
                                    WHERE a.referencenumber = ? LIMIT 1");
        $stmt->execute([$ref]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->respond(false, 'Application not found.', null, 404);
        }

        if (empty($row['refundstatus'])) {
            $this->respond(false, 'No refund has been requested for this application.', null, 404);
        }

        $events = [];
        try {
            $events = $this->lifecycle->history($this->identity, $ref);
        } catch (Throwable $e) {
            // Non-fatal if history is empty
        }

        $details = [
            'referencenumber' => $row['referencenumber'],
            'matricnumber' => $row['matricnumber'],
            'student_name' => $row['full_name'] ?: $row['matricnumber'],
            'department' => $row['department'] ?: 'Not available',
            'programme' => $row['programme'] ?: 'Not available',
            'applicationtype' => $this->formatReason($row['applicationtype']),
            'applicationstatus' => $row['status'],
            'paymentstatus' => $row['paymentstatus'] ?: 'unknown',
            'paidat' => $row['paidat'],
            'refund' => [
                'refundid' => (int)$row['refundid'],
                'status' => $row['refundstatus'],
                'amount' => (float)$row['refundamount'],
                'requestedat' => $row['requestedat'],
                'requestedby' => $row['requestedby'],
                'approvedat' => $row['approvedat'],
                'approvedby' => $row['approvedby'],
                'creditedat' => $row['creditedat'],
                'creditedby' => $row['creditedby'],
            ],
            'events' => $events,
        ];

        $this->respond(true, 'Refund details retrieved.', $details);
    }

    /**
     * Advance refund status from 'requested' to 'approved' using the domain lifecycle service.
     */
    public function approveRefund(string $reference): never
    {
        $ref = $this->clean($reference);
        if ($ref === '') {
            $this->respond(false, 'Application reference is required.', null, 400);
        }

        try {
            $this->lifecycle->approveRefund($this->identity, $ref);
            $this->respond(true, 'Refund request approved successfully. It will now be forwarded to the Account Office for processing.', [
                'referencenumber' => $ref,
                'refundstatus' => 'approved'
            ]);
        } catch (DomainException $exception) {
            $this->respond(false, $exception->getMessage(), null, 409);
        } catch (Throwable $exception) {
            error_log('Refund approval error: ' . $exception->getMessage());
            $this->respond(false, 'Unable to approve this refund request. Refresh and try again.', null, 500);
        }
    }

    /**
     * Decommissioned: Normal replacement applications no longer require Student Affairs approval.
     */
    public function approve(array $data, string $reviewer): never
    {
        $this->respond(false, 'This workflow is obsolete. Ordinary replacement applications no longer require Student Affairs approval.', null, 400);
    }

    /**
     * Decommissioned: Ordinary replacement applications cannot be rejected through Student Affairs.
     */
    public function reject(array $data, string $reviewer): never
    {
        $this->respond(false, 'This workflow is obsolete. Ordinary replacement applications cannot be rejected through Student Affairs.', null, 400);
    }

    private function formatApplicationRow(array $row): array
    {
        $refundStatus = $row['refundstatus'] ?? null;
        $refundLabel = 'No Refund';
        $isActionable = false;

        if ($refundStatus === 'requested') {
            $refundLabel = 'Requested — Review';
            $isActionable = true;
        } elseif ($refundStatus === 'approved') {
            $refundLabel = 'Approved';
        } elseif ($refundStatus === 'credited') {
            $refundLabel = 'Credited';
        }

        return [
            'referencenumber' => $row['referencenumber'],
            'matricnumber' => $row['matricnumber'],
            'applicant_name' => $row['full_name'] ?: $row['matricnumber'],
            'department' => $row['department'] ?: 'Not available',
            'programme' => $row['programme'] ?: 'Not available',
            'applicationtype' => $this->formatReason($row['applicationtype']),
            'status' => $row['status'],
            'paymentstatus' => $row['paymentstatus'] ?? null,
            'paymentstatus_label' => $this->formatPaymentStatus($row['paymentstatus']),
            'paidat' => $row['paidat'],
            'createdat' => $row['createdat'],
            'refund' => [
                'status' => $refundStatus ?: 'none',
                'label' => $refundLabel,
                'is_actionable' => $isActionable,
                'amount' => $row['refundamount'] ? (float)$row['refundamount'] : null,
                'requestedat' => $row['requestedat'],
                'approvedat' => $row['approvedat'],
                'creditedat' => $row['creditedat'],
            ],
        ];
    }

    private function formatReason(?string $type): string
    {
        return match ($type) {
            'loststolen' => 'Lost / Stolen',
            'damaged' => 'Damaged / Faded',
            default => ucfirst((string)$type),
        };
    }

    private function formatPaymentStatus(?string $status): string
    {
        return match ($status) {
            'paid' => 'Paid',
            'failed' => 'Failed',
            default => 'Not available (legacy)',
        };
    }
}
