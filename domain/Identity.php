<?php
declare(strict_types=1);
namespace CU\IdCard;

final class Identity
{
    public function __construct(public readonly string $id, public readonly array $roles, public readonly ?string $matric = null)
    {
        if (trim($id) === '' || strlen($id) > 100) throw new \DomainException('Authenticated identity is required.');
    }
    public function requireRole(string $role): void
    {
        if (!in_array($role, $this->roles, true)) throw new \DomainException('You are not authorized for this action.');
        if ($role === 'student' && !$this->matric) throw new \DomainException('Authenticated student matric number is required.');
    }
}

interface AuthenticationAdapter { public function current(): Identity; }

/** The host resolves loginid through its trusted user/role store. Never accept request roles. */
final class PortalSessionAdapter implements AuthenticationAdapter
{
    public function __construct(private array $session, private \Closure $resolveLogin) {}
    public function current(): Identity
    {
        $login = $this->session['loginid'] ?? null;
        if (!is_string($login) && !is_int($login)) throw new \DomainException('CU portal login is required.');
        $identity = ($this->resolveLogin)($login);
        if (!$identity instanceof Identity) throw new \DomainException('CU portal identity could not be resolved.');
        return $identity;
    }
}
