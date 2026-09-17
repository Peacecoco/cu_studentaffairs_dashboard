<?php

declare(strict_types=1);

require_once __DIR__ . '/../../idcard-system/shared/lifecycle/bootstrap.php';

use CU\IdCard\{Identity, PortalSessionAdapter, Lifecycle, LocalPaymentSimulator};

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    if (!session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ])) {
        throw new RuntimeException('Session storage unavailable.');
    }
}

/**
 * Resolves the authenticated Student Affairs identity from the portal session adapter.
 * Never trust arbitrary client/browser supplied staff IDs or roles.
 */
function currentAffairsOfficer(): Identity
{
    $session = $_SESSION ?? [];
    $resolverPath = getenv('CU_STUDENTAFFAIRS_IDENTITY_RESOLVER') ?: getenv('CU_AFFAIRS_IDENTITY_RESOLVER');

    $bypass = getenv('CU_AUTH_BYPASS') === '1' && (PHP_SAPI === 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true));
    if ($bypass) {
        $session['loginid'] = 'auth-bypass';
        $actorId = getenv('CU_AUTH_BYPASS_AFFAIRS_ACTOR') ?: 'DEV_STUDENT_AFFAIRS';
        $resolver = static fn($login) => new Identity($actorId, ['student_affairs']);
    } elseif ($resolverPath) {
        $resolver = require $resolverPath;
        if (!$resolver instanceof Closure) {
            throw new RuntimeException('Invalid Student Affairs resolver configuration.');
        }
    } else {
        $resolver = static fn($login) => null;
        $isDevMode = getenv('CU_STUDENTAFFAIRS_DEV_MODE') === '1' || getenv('CU_AFFAIRS_DEV_MODE') === '1';
        $isLoopback = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) || PHP_SAPI === 'cli';

        if (!isset($session['loginid']) && $isDevMode && $isLoopback) {
            $actorId = getenv('CU_STUDENTAFFAIRS_DEV_ACTOR') ?: (getenv('CU_AFFAIRS_DEV_ACTOR') ?: 'dev-affairs-officer');
            $session['loginid'] = 'local-development';
            $resolver = static fn($login) => new Identity($actorId, ['student_affairs']);
        }
    }

    if (PHP_SAPI === 'cli' && ($cliLogin = getenv('CU_AFFAIRS_CLI_LOGIN') ?: getenv('CU_STUDENTAFFAIRS_CLI_LOGIN'))) {
        $session['loginid'] = $cliLogin;
    }

    $actor = (new PortalSessionAdapter($session, $resolver))->current();
    $actor->requireRole('student_affairs');
    return $actor;
}

/**
 * Returns the current authenticated reviewer ID, replacing legacy hardcoded STAFF001.
 */
function currentReviewer(): string
{
    try {
        return currentAffairsOfficer()->id;
    } catch (Throwable $e) {
        return 'Sign in required';
    }
}

function affairsCsrfToken(Identity $actor): string
{
    $owner = $actor->id . '|affairs';
    if (($_SESSION['affairs_csrf_owner'] ?? null) !== $owner || empty($_SESSION['affairs_csrf'])) {
        $_SESSION['affairs_csrf_owner'] = $owner;
        $_SESSION['affairs_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['affairs_csrf'];
}

function requireAffairsCsrf(Identity $actor): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(affairsCsrfToken($actor), $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Your session token is invalid. Refresh the page and try again.'
        ]);
        exit;
    }
}

function affairsLifecycle(PDO $con): Lifecycle
{
    return new Lifecycle($con, new LocalPaymentSimulator(false));
}
