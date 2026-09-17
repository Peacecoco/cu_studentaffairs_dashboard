<?php
declare(strict_types=1);
namespace CU\IdCard;

final class Rules
{
    public static function unprintedPaid(array $a): void
    {
        if (!empty($a['printedat']) || $a['status'] === 'printed') throw new \DomainException('This replacement ID card has already been printed and is no longer eligible.');
        if (!empty($a['collectedat']) || in_array($a['status'], ['collected','acknowledged','readyforpickup','closed'], true)) throw new \DomainException('This card is no longer eligible.');
        if (!empty($a['refundstatus']) || $a['status'] === 'refunded') throw new \DomainException('A refund request has already been submitted for this application.');
        if ($a['paymentstatus'] !== 'paid' || $a['status'] !== 'paid' || empty($a['paidat'])) throw new \DomainException('A confirmed paid application with a payment timestamp is required.');
    }
    public static function deadline(array $a): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($a['paidat'], new \DateTimeZone('Africa/Lagos')))->modify('+72 hours');
    }
    public static function refund(array $a, \DateTimeImmutable $now): void
    {
        if (!empty($a['collectedat']) || in_array($a['status'],['collected','acknowledged'],true)) throw new \DomainException('This replacement ID card has already been collected and is no longer eligible for a refund.');
        if (!empty($a['printedat']) || $a['status']==='printed') throw new \DomainException('This replacement ID card has already been printed and is no longer eligible for a refund.');
        if ($a['paymentstatus']==='failed') throw new \DomainException('This application is not eligible for a refund because the payment was not successful.');
        self::unprintedPaid($a);
        if ($now < new \DateTimeImmutable($a['paidat'], new \DateTimeZone('Africa/Lagos'))) throw new \DomainException('Payment timestamp requires reconciliation.');
        if ($now >= self::deadline($a)) throw new \DomainException('Refund requests are only accepted within 72 hours of payment. The refund window for this application has expired.');
    }
    public static function printing(array $a, \DateTimeImmutable $now, string $window): void
    {
        self::unprintedPaid($a);
        if (!in_array($window, ['before','after'], true)) throw new \DomainException('Invalid printing filter.');
        if ($now < new \DateTimeImmutable($a['paidat'], new \DateTimeZone('Africa/Lagos'))) throw new \DomainException('Payment timestamp requires reconciliation.');
        if (($now < self::deadline($a)) !== ($window === 'before')) throw new \DomainException('Application is outside the selected 72-hour printing window. Refresh the queue.');
    }
}
