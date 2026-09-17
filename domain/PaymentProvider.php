<?php
declare(strict_types=1);
namespace CU\IdCard;

final class PaymentResult
{
    public function __construct(public readonly string $status, public readonly ?string $failure = null)
    {
        if (!in_array($status, ['paid','failed'], true)) throw new \DomainException('Payment has not reached a terminal result.');
    }
}
interface PaymentProvider
{
    public function name(): string;
    /** A real adapter must verify reference, amount, currency and merchant server-side. */
    public function terminalResult(array $attempt): PaymentResult;
}
/** Local development ONLY. Instantiate explicitly in trusted server configuration, never from HTTP input. */
final class LocalPaymentSimulator implements PaymentProvider
{
    public function __construct(private bool $succeeds = true) {}
    public function name(): string { return 'local-simulator'; }
    public function terminalResult(array $attempt): PaymentResult
    {
        return new PaymentResult($this->succeeds ? 'paid' : 'failed', $this->succeeds ? null : 'Simulated payment failure.');
    }
}
