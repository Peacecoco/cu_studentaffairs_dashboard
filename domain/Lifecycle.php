<?php
declare(strict_types=1);
namespace CU\IdCard;

/** All v2 writes go through this service. Inject a dedicated PDO connection. */
final class Lifecycle
{
    public function __construct(private \PDO $db, private PaymentProvider $provider)
    {
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        // Lagos is UTC+1 without DST; numeric offset works without MySQL timezone tables.
        $db->exec("SET time_zone = '+01:00'");
    }
    private function query(string $sql, array $args = []): \PDOStatement
    {
        $q = $this->db->prepare($sql); $q->execute($args); return $q;
    }
    private function transaction(\Closure $action): mixed
    {
        if ($this->db->inTransaction()) throw new \LogicException('Lifecycle owns its transaction boundary.');
        $this->db->beginTransaction();
        try { $result = $action(); $this->db->commit(); return $result; }
        catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->query('SELECT NOW()')->fetchColumn(), new \DateTimeZone('Africa/Lagos'));
    }
    private function event(int $id, string $type, Identity $actor, string $role, array $metadata = []): void
    {
        if (!$this->db->inTransaction()) throw new \LogicException('Events require the transition transaction.');
        $this->query('INSERT INTO idcardapplicationevents (applicationid,eventtype,actorid,actorrole,occurredat,metadata) VALUES (?,?,?,?,NOW(),?)', [$id,$type,$actor->id,$role,json_encode($metadata, JSON_THROW_ON_ERROR)]);
    }
    private function application(string $reference, ?string $matric = null): array
    {
        $sql = 'SELECT * FROM idcardapplications WHERE referencenumber=?'; $args = [$reference];
        if ($matric !== null) { $sql .= ' AND matricnumber=?'; $args[] = $matric; }
        $a = $this->query($sql . ' FOR UPDATE', $args)->fetch();
        if (!$a) throw new \DomainException('Application not found.');
        $a['refundstatus'] = $this->query('SELECT status FROM idcardrefunds WHERE applicationid=? FOR UPDATE', [$a['applicationid']])->fetchColumn() ?: null;
        return $a;
    }
    private function lockStudent(string $matric): void
    {
        if (!$this->query('SELECT id FROM students WHERE matric_no=? FOR UPDATE', [$matric])->fetch()) throw new \DomainException('Student not found.');
    }
    private function checkActive(string $matric, int $cooldown): void
    {
        if ($this->query("SELECT applicationid FROM idcardapplications WHERE matricnumber=? AND (status IN ('submitted','awaitingpayment','paid','printed','readyforpickup','acknowledged') OR (status='refunded' AND EXISTS (SELECT 1 FROM idcardrefunds r WHERE r.applicationid=idcardapplications.applicationid AND r.status<>'credited'))) LIMIT 1 FOR UPDATE", [$matric])->fetch()) throw new \DomainException('Applicant already has an active application.');
        if ($cooldown > 0 && $this->query("SELECT applicationid FROM idcardapplications WHERE matricnumber=? AND status IN ('closed','cancelled') AND COALESCE(closedat,cancelledat,updatedat)>=DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 1 FOR UPDATE", [$matric,$cooldown])->fetch()) throw new \DomainException('A previous application is within the configured cooldown period.');
    }

    /** Preliminary gate; checkout and finalization recheck under their own locks. */
    public function checkEligibility(Identity $actor, ?string $reason = null): void
    {
        $actor->requireRole('student');
        $this->transaction(function () use ($actor,$reason) {
            $this->lockStudent($actor->matric);
            $days=$reason===null
                ? $this->query('SELECT MIN(cooldowndays) FROM idcardsettings WHERE isactive=1')->fetchColumn()
                : $this->query('SELECT cooldowndays FROM idcardsettings WHERE applicationtype=? AND isactive=1',[$reason])->fetchColumn();
            if ($days===false || $days===null) throw new \DomainException('Replacement settings are unavailable.');
            $this->checkActive($actor->matric,max(0,(int)$days));
        });
    }

    /** Photo metadata must come from the existing server-side validated upload service. No documents. */
    public function beginCheckout(Identity $actor, string $reason, string $option, array $photo): string
    {
        $actor->requireRole('student');
        if (!in_array($reason, ['loststolen','damaged'], true)) throw new \DomainException('Invalid replacement reason.');
        return $this->transaction(function () use ($actor,$reason,$option,$photo) {
            $this->lockStudent($actor->matric);
            $setting = $this->query('SELECT * FROM idcardsettings WHERE applicationtype=? AND isactive=1', [$reason])->fetch();
            $payment = $this->query('SELECT * FROM paymentoptions WHERE optioncode=? AND isactive=1', [$option])->fetch();
            if (!$setting || !$payment) throw new \DomainException('Replacement fee or payment option is unavailable.');
            $this->checkActive($actor->matric, max(0,(int)$setting['cooldowndays']));
            if (empty($photo['path']) || strlen($photo['path']) > 255 || !in_array($photo['mime'] ?? '', array_map('trim',explode(',',$setting['allowedphotomimetypes'])), true) || ($photo['size'] ?? 0) <= 0 || $photo['size'] > $setting['maxfilesizebytes']) throw new \DomainException('A valid replacement photo is required.');
            // Reuse an outstanding checkout; never open two chargeable attempts for one student.
            $pending = $this->query("SELECT paymentreference FROM idcardpaymentattempts WHERE matricnumber=? AND status='pending' FOR UPDATE", [$actor->matric])->fetchColumn();
            if ($pending) return $pending;
            $base = (float)$setting['approvedfee'];
            $charge = round($base*(float)$payment['chargepercentage']+(float)$payment['fixedcharge'],2);
            if ($base < 0 || $charge < 0) throw new \DomainException('Invalid payment amount configuration.');
            $ref = 'PAY-' . bin2hex(random_bytes(16));
            $this->query('INSERT INTO idcardpaymentattempts (paymentreference,matricnumber,applicationtype,photopath,photosizebytes,photomimetype,paymentoptioncode,paymentoptionname,baseamount,chargeamount,totalamount,provider,createdat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())', [$ref,$actor->matric,$reason,$photo['path'],$photo['size'],$photo['mime'],$option,$payment['optionname'],$base,$charge,round($base+$charge,2),$this->provider->name()]);
            return $ref;
        });
    }
    public function cancelCheckout(Identity $actor, string $ref): void
    {
        $actor->requireRole('student');
        $this->transaction(function () use ($actor,$ref) {
            $this->lockStudent($actor->matric);
            $p = $this->query('SELECT * FROM idcardpaymentattempts WHERE paymentreference=? AND matricnumber=? FOR UPDATE', [$ref,$actor->matric])->fetch();
            if (!$p || !in_array($p['status'], ['pending','cancelled'],true)) throw new \DomainException('Checkout cannot be cancelled after a terminal payment result.');
            $this->query("UPDATE idcardpaymentattempts SET status='cancelled',completedat=NOW() WHERE attemptid=? AND status='pending'", [$p['attemptid']]);
        });
    }
    public function completePayment(Identity $actor, string $ref): array
    {
        $actor->requireRole('student');
        return $this->transaction(function () use ($actor,$ref) {
            $this->lockStudent($actor->matric);
            $p = $this->query('SELECT * FROM idcardpaymentattempts WHERE paymentreference=? AND matricnumber=? FOR UPDATE', [$ref,$actor->matric])->fetch();
            if (!$p) throw new \DomainException('Checkout not found.');
            if ($p['applicationid']) return $this->applicationById((int)$p['applicationid']);
            if ($p['status'] !== 'pending') throw new \DomainException('Checkout was cancelled.');
            if ($p['provider'] !== $this->provider->name()) throw new \DomainException('Payment adapter does not match checkout.');
            $cooldown = (int)$this->query('SELECT cooldowndays FROM idcardsettings WHERE applicationtype=?', [$p['applicationtype']])->fetchColumn();
            $this->checkActive($actor->matric, $cooldown);
            $result = $this->provider->terminalResult($p);
            $reference = 'IDC-' . bin2hex(random_bytes(16));
            // Failed payment has no operational card state; the existing varchar stores 'failed'.
            $this->query('INSERT INTO idcardapplications (referencenumber,matricnumber,applicationtype,status,paymentstatus,paidat,photopath,photosizebytes,photomimetype,approvedfee,submittedat,createdat) VALUES (?,?,?,?,?,IF(?=\'paid\',NOW(),NULL),?,?,?,?,NOW(),NOW())', [$reference,$p['matricnumber'],$p['applicationtype'],$result->status,$result->status,$result->status,$p['photopath'],$p['photosizebytes'],$p['photomimetype'],$p['baseamount']]);
            $id = (int)$this->db->lastInsertId();
            $this->query('INSERT INTO paymenttransactions (applicationid,referencenumber,matricnumber,paymentoptioncode,paymentoptionname,baseamount,chargeamount,totalamount,paymentreference,status,paidat,createdat,attemptid,provider,currency,completedat,failuremessage) VALUES (?,?,?,?,?,?,?,?,?,?,IF(?=\'paid\',NOW(),NULL),NOW(),?,?,?,NOW(),?)', [$id,$reference,$p['matricnumber'],$p['paymentoptioncode'],$p['paymentoptionname'],$p['baseamount'],$p['chargeamount'],$p['totalamount'],$ref,$result->status,$result->status,$p['attemptid'],$p['provider'],$p['currency'],$result->failure]);
            $this->query('UPDATE idcardpaymentattempts SET applicationid=?,status=?,completedat=NOW(),failuremessage=? WHERE attemptid=?', [$id,$result->status,$result->failure,$p['attemptid']]);
            $this->event($id, 'payment_' . $result->status, $actor, 'student', ['provider'=>$p['provider'],'paymentreference'=>$ref]);
            return $this->applicationById($id);
        });
    }
    private function applicationById(int $id): array
    {
        return $this->query('SELECT applicationid,referencenumber,paymentstatus,status,paidat FROM idcardapplications WHERE applicationid=?', [$id])->fetch();
    }
    public function requestRefund(Identity $actor, string $reference): void
    {
        $actor->requireRole('student');
        $this->transaction(function () use ($actor,$reference) {
            $a = $this->application($reference,$actor->matric);
            Rules::refund($a,$this->now());
            $p = $this->query("SELECT baseamount FROM paymenttransactions WHERE applicationid=? AND status='paid' AND attemptid IS NOT NULL", [$a['applicationid']])->fetch();
            if (!$p) throw new \DomainException('Payment requires reconciliation before a refund can be requested.');
            $this->query("INSERT INTO idcardrefunds (applicationid,status,amount,requestedat,requestedby) VALUES (?,'requested',?,NOW(),?)", [$a['applicationid'],$p['baseamount'],$actor->id]);
            $this->query("UPDATE idcardapplications SET status='refunded',updatedat=NOW() WHERE applicationid=?", [$a['applicationid']]);
            $this->event((int)$a['applicationid'],'refund_requested',$actor,'student');
        });
    }
    public function approveRefund(Identity $actor, string $reference): void { $this->advanceRefund($actor,$reference,'requested','approved','student_affairs'); }
    public function creditRefund(Identity $actor, string $reference): void { $this->advanceRefund($actor,$reference,'approved','credited','account_officer'); }
    private function advanceRefund(Identity $actor, string $reference, string $from, string $to, string $role): void
    {
        $actor->requireRole($role);
        $this->transaction(function () use ($actor,$reference,$from,$to,$role) {
            $a = $this->application($reference);
            if ($a['status'] !== 'refunded' || $a['paymentstatus'] !== 'paid' || $a['printedat'] || $a['collectedat'] || $a['refundstatus'] !== $from) throw new \DomainException('Refund is not eligible for this transition or has already been processed.');
            $this->query("UPDATE idcardrefunds SET status=?, {$to}at=NOW(), {$to}by=? WHERE applicationid=? AND status=?", [$to,$actor->id,$a['applicationid'],$from]);
            $this->event((int)$a['applicationid'],'refund_'.$to,$actor,$role);
        });
    }
    public function collect(Identity $actor, string $reference): void
    {
        $actor->requireRole('id_card_officer');
        $this->transaction(function () use ($actor,$reference) {
            $a = $this->application($reference);
            if ($a['status'] !== 'printed' || $a['paymentstatus'] !== 'paid' || !$a['printedat'] || $a['collectedat'] || $a['refundstatus']) throw new \DomainException('Only a printed, uncollected card can be collected.');
            $this->query("UPDATE idcardapplications SET status='collected',collectedat=NOW(),collectedby=?,updatedat=NOW() WHERE applicationid=?", [$actor->id,$a['applicationid']]);
            $this->event((int)$a['applicationid'],'card_collected',$actor,'id_card_officer');
        });
    }
    public function collectionQueue(Identity $actor, string $search = ''): array
    {
        $actor->requireRole('id_card_officer');
        return $this->query("SELECT a.*,s.full_name FROM idcardapplications a LEFT JOIN students s ON s.matric_no=a.matricnumber WHERE a.status='printed' AND a.paymentstatus='paid' AND a.printedat IS NOT NULL AND a.collectedat IS NULL AND NOT EXISTS (SELECT 1 FROM idcardrefunds r WHERE r.applicationid=a.applicationid) AND (a.referencenumber LIKE ? OR a.matricnumber LIKE ? OR s.full_name LIKE ?) ORDER BY a.printedat,a.applicationid",array_fill(0,3,'%'.$search.'%'))->fetchAll();
    }

    /** Read-only confirmation context; the committing service still re-locks and validates. */
    public function printConfirmation(Identity $actor, int $batch): array
    {
        $actor->requireRole('id_card_officer');
        $b=$this->query('SELECT * FROM id_card_batches WHERE id=?',[$batch])->fetch();
        if (!$b || $b['status']!=='completed' || $b['print_status']!=='awaiting_print') throw new \DomainException('Batch is not available for print confirmation.');
        $emergency=false;
        if ($b['generated_by']==='awaiting-print') {
            $items=$this->query("SELECT a.*,r.status AS refundstatus FROM id_card_batch_items i LEFT JOIN idcardapplications a ON a.applicationid=i.applicationid LEFT JOIN idcardrefunds r ON r.applicationid=a.applicationid WHERE i.batch_id=? AND i.status='success'",[$batch])->fetchAll();
            if (!$items) throw new \DomainException('Batch has no successfully generated cards.');
            foreach ($items as $a) {
                if (!$a['applicationid']) throw new \DomainException('Legacy/unlinked batch requires reconciliation.');
                Rules::unprintedPaid($a);
                $emergency=$emergency || $this->now()<Rules::deadline($a);
            }
        }
        return ['batch_id'=>$batch,'emergency'=>$emergency,'replacement'=>$b['generated_by']==='awaiting-print'];
    }

    /** Call before rendering; returns exact authorized application/student pairs. */
    public function printingSelection(Identity $actor, array $references, string $window = 'after'): array
    {
        $actor->requireRole('id_card_officer');
        $references = array_values(array_unique($references)); sort($references);
        if (!$references) throw new \DomainException('Select at least one application.');
        return $this->transaction(function () use ($references,$window) {
            $rows=[];
            foreach ($references as $ref) {
                $a=$this->application($ref); Rules::printing($a,$this->now(),$window);
                $student=$this->query("SELECT * FROM students WHERE matric_no=? AND status='active'", [$a['matricnumber']])->fetch();
                if (!$student) throw new \DomainException('Matching active student record required.');
                $rows[]=['application'=>$a,'student'=>$student];
            }
            return $rows;
        });
    }
    public function printingQueue(Identity $actor, string $search = '', string $window = 'after'): array
    {
        $actor->requireRole('id_card_officer');
        if (!in_array($window,['before','after'],true)) throw new \DomainException('Invalid printing filter.');
        $comparison=$window === 'before' ? '>' : '<=';
        $needle='%'.$search.'%';
        return $this->query("SELECT a.*,s.id AS student_id,s.full_name,s.department,s.programme FROM idcardapplications a JOIN students s ON s.matric_no=a.matricnumber AND s.status='active' WHERE a.paymentstatus='paid' AND a.status='paid' AND a.paidat IS NOT NULL AND a.paidat<=NOW() AND a.printedat IS NULL AND a.collectedat IS NULL AND NOT EXISTS (SELECT 1 FROM idcardrefunds r WHERE r.applicationid=a.applicationid) AND DATE_ADD(a.paidat,INTERVAL 72 HOUR) {$comparison} NOW() AND (a.referencenumber LIKE ? OR a.matricnumber LIKE ? OR s.full_name LIKE ? OR s.department LIKE ? OR s.programme LIKE ?) ORDER BY a.paidat,a.applicationid",array_fill(0,5,$needle))->fetchAll();
    }
    /** Renderer calls this for each outcome; selection alone never records physical printing. */
    public function recordBatchItem(Identity $actor, int $batch, int $student, string $reference, string $outcome, string $window = 'after', ?string $error = null): void
    {
        $actor->requireRole('id_card_officer');
        if (!in_array($outcome,['success','failed','skipped'],true)) throw new \DomainException('Invalid generation outcome.');
        $this->transaction(function () use ($actor,$batch,$student,$reference,$outcome,$window,$error) {
            $b=$this->query('SELECT * FROM id_card_batches WHERE id=? FOR UPDATE',[$batch])->fetch();
            if (!$b || $b['status'] !== 'pending' || $b['print_status'] !== 'awaiting_print' || $b['generated_by'] !== 'awaiting-print') throw new \DomainException('Replacement batch is not open for generation.');
            $a=$this->application($reference); Rules::printing($a,$this->now(),$window);
            if (!$this->query("SELECT id FROM students WHERE id=? AND matric_no=? AND status='active'",[$student,$a['matricnumber']])->fetch()) throw new \DomainException('Batch student does not match application.');
            $this->query('INSERT INTO id_card_batch_items (batch_id,student_id,applicationid,status,error_message) VALUES (?,?,?,?,?)',[$batch,$student,$a['applicationid'],$outcome,$error]);
        });
    }
    /** All-or-nothing physical confirmation, using persisted successful items, never browser references. */
    public function confirmPrinted(Identity $actor, int $batch): int
    {
        $actor->requireRole('id_card_officer');
        return $this->transaction(function () use ($actor,$batch) {
            $b=$this->query('SELECT * FROM id_card_batches WHERE id=? FOR UPDATE',[$batch])->fetch();
            if (!$b || $b['status'] !== 'completed' || $b['print_status'] !== 'awaiting_print' || $b['generated_by'] !== 'awaiting-print') throw new \DomainException('Batch is not available for replacement print confirmation.');
            $items=$this->query("SELECT i.*,a.referencenumber FROM id_card_batch_items i LEFT JOIN idcardapplications a ON a.applicationid=i.applicationid WHERE i.batch_id=? AND i.status='success' ORDER BY a.referencenumber FOR UPDATE",[$batch])->fetchAll();
            if (!$items) throw new \DomainException('Batch has no successfully generated cards.');
            foreach ($items as $item) {
                if (!$item['applicationid']) throw new \DomainException('Legacy/unlinked batch requires reconciliation.');
                $a=$this->application($item['referencenumber']); Rules::unprintedPaid($a);
                // Both normal and emergency printing are allowed here, including crossing the deadline after preview.
                if ($this->now() < new \DateTimeImmutable($a['paidat'], new \DateTimeZone('Africa/Lagos'))) throw new \DomainException('Payment timestamp requires reconciliation.');
                if (!$this->query("SELECT id FROM students WHERE id=? AND matric_no=? AND status='active'",[$item['student_id'],$a['matricnumber']])->fetch()) throw new \DomainException('Batch student does not match application.');
                $this->query("UPDATE idcardapplications SET status='printed',printmethod='local',printedat=NOW(),updatedat=NOW() WHERE applicationid=?",[$a['applicationid']]);
                $this->event((int)$a['applicationid'],'card_printed',$actor,'id_card_officer',['batch_id'=>$batch]);
            }
            $this->query("UPDATE id_card_batches SET print_status='printed',print_method='local',printed_at=NOW() WHERE id=?",[$batch]);
            return count($items);
        });
    }
    public function history(Identity $actor, string $reference): array
    {
        return $this->transaction(function () use ($actor,$reference) {
            $staff = array_intersect($actor->roles,['student_affairs','account_officer','id_card_officer']);
            if (!$staff) $actor->requireRole('student');
            $a=$this->application($reference,$staff ? null : $actor->matric);
            return $this->query('SELECT eventtype,occurredat,actorrole,metadata FROM idcardapplicationevents WHERE applicationid=? ORDER BY occurredat,eventid',[$a['applicationid']])->fetchAll();
        });
    }
}
