<?php
declare(strict_types=1);

class StudentAffairs extends General
{
    public function allApplications(): never
    {
        $this->expireOverdueApplications();
        $query = "SELECT a.*, s.full_name, s.department, s.photo_path
                  FROM idcardapplications a
                  LEFT JOIN students s ON s.matric_no = a.matricnumber
                  ORDER BY a.createdat DESC, a.applicationid DESC";
        $rows = $this->db->query($query)->fetchAll();
        $this->respond(true, count($rows) . ' application(s) retrieved.', array_map([$this, 'formatApplication'], $rows));
    }

    public function applicantDepartment(?string $reference): never
    {
        $application = $this->findApplication($reference);
        if (!$application) {
            $this->respond(false, 'Application not found.', null, 404);
        }
        $this->respond(true, 'Applicant details retrieved.', ['department' => $application['department'] ?? null]);
    }

    public function approve(array $data, string $reviewer): never
    {
        $application = $this->submittedApplication($data['referencenumber'] ?? null);
        $settings = $this->settingFor($application['applicationtype']);
        if (!$settings) {
            $this->respond(false, 'No active fee and expiry setting exists for this application type.', null, 500);
        }
        $days = max(0, (int) $settings['expirydays']);
        $deadline = date('Y-m-d', strtotime("+{$days} day"));
        $statement = $this->db->prepare("UPDATE idcardapplications SET status = 'awaitingpayment', approvedfee = ?, reviewedby = ?, paymentdeadline = ?, updatedat = NOW() WHERE applicationid = ? AND status = 'submitted'");
        $statement->execute([(float) $settings['approvedfee'], $reviewer, $deadline, $application['applicationid']]);
        if ($statement->rowCount() !== 1) {
            $this->respond(false, 'This application was already processed. Refresh the queue.', null, 409);
        }
        $this->respond(true, 'Application approved. Student is now awaiting payment.', ['approvedfee' => (float) $settings['approvedfee'], 'paymentdeadline' => $deadline, 'expirydays' => $days]);
    }

    public function reject(array $data, string $reviewer): never
    {
        $reason = $this->clean($data['rejectionreason'] ?? '');
        if ($reason === '') {
            $this->respond(false, 'A rejection reason is required.', null, 400);
        }
        $application = $this->submittedApplication($data['referencenumber'] ?? null);
        $statement = $this->db->prepare("UPDATE idcardapplications SET status = 'rejected', rejectionreason = ?, reviewedby = ?, updatedat = NOW() WHERE applicationid = ? AND status = 'submitted'");
        $statement->execute([$reason, $reviewer, $application['applicationid']]);
        if ($statement->rowCount() !== 1) {
            $this->respond(false, 'This application was already processed. Refresh the queue.', null, 409);
        }
        $this->respond(true, 'Application has been rejected.');
    }

    private function submittedApplication(?string $reference): array
    {
        $application = $this->findApplication($reference);
        if (!$application) {
            $this->respond(false, 'Application not found.', null, 404);
        }
        if ($application['status'] !== 'submitted') {
            $this->respond(false, 'Only submitted applications can be reviewed. This application is currently ' . $application['status'] . '.', null, 409);
        }
        return $application;
    }

    private function findApplication(?string $reference): ?array
    {
        $reference = $this->clean($reference ?? '');
        if ($reference === '') {
            $this->respond(false, 'Application reference is required.', null, 400);
        }
        $statement = $this->db->prepare("SELECT a.*, s.full_name, s.department, s.photo_path FROM idcardapplications a LEFT JOIN students s ON s.matric_no = a.matricnumber WHERE a.referencenumber = ? LIMIT 1");
        $statement->execute([$reference]);
        return $statement->fetch() ?: null;
    }

    private function settingFor(string $type): ?array
    {
        $statement = $this->db->prepare('SELECT approvedfee, expirydays FROM idcardsettings WHERE applicationtype = ? AND isactive = 1 LIMIT 1');
        $statement->execute([$type]);
        return $statement->fetch() ?: null;
    }

    private function formatApplication(array $row): array
    {
        $row['applicant'] = ['identifier' => $row['matricnumber'], 'name' => $row['full_name'], 'department' => $row['department']];
        $row['registeredphoto'] = ['path' => $row['photo_path'], 'url' => $this->urlFor($row['photo_path'])];
        $row['photo'] = ['path' => $row['photopath'], 'url' => $this->urlFor($row['photopath']), 'mimeType' => $row['photomimetype']];
        $row['document'] = empty($row['documentpath']) ? null : ['name' => $row['documenttype'], 'path' => $row['documentpath'], 'url' => $this->urlFor($row['documentpath']), 'mimeType' => $row['documentmimetype']];
        unset($row['full_name'], $row['department'], $row['photo_path']);
        return $row;
    }
}
