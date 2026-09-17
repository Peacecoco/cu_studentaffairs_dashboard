<?php

declare(strict_types=1);

require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/classes.php';
require_once __DIR__ . '/include/session.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = strtolower(trim((string)($_GET['action'] ?? '')));
$body = in_array($method, ['PUT', 'POST'], true) ? (json_decode(file_get_contents('php://input'), true) ?: $_POST) : [];

try {
    $officer = currentAffairsOfficer();
} catch (DomainException $e) {
    $code = str_contains($e->getMessage(), 'not authorized') ? 403 : 401;
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Student Affairs service is temporarily unavailable.']);
    exit;
}

if ($method === 'GET' && $action === 'session') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'actor' => $officer->id,
            'role' => 'Student Affairs',
            'csrf_token' => affairsCsrfToken($officer),
        ]
    ]);
    exit;
}

$lifecycle = affairsLifecycle($con);
$module = new StudentAffairs($con, $lifecycle, $officer);

if ($method === 'GET' && in_array($action, ['applications', 'all'], true)) {
    $search = isset($_GET['search']) ? (string)$_GET['search'] : null;
    $filter = isset($_GET['filter']) ? (string)$_GET['filter'] : (isset($_GET['refund_filter']) ? (string)$_GET['refund_filter'] : null);
    $module->allApplications($search, $filter);
}

if ($method === 'GET' && $action === 'paymentdetails') {
    $ref = (string)($_GET['ref'] ?? ($_GET['referencenumber'] ?? ''));
    $module->paymentDetails($ref);
}

if ($method === 'GET' && $action === 'history') {
    $ref = (string)($_GET['ref'] ?? ($_GET['referencenumber'] ?? ''));
    $module->history($ref);
}

if ($method === 'GET' && $action === 'refunddetails') {
    $ref = (string)($_GET['ref'] ?? ($_GET['referencenumber'] ?? ''));
    $module->refundDetails($ref);
}

if ($method === 'POST' && in_array($action, ['approverefund', 'approve_refund'], true)) {
    requireAffairsCsrf($officer);
    $ref = (string)($body['referencenumber'] ?? ($body['ref'] ?? ''));
    $module->approveRefund($ref);
}

// Obsolete business flows explicitly disabled on the backend
if (in_array($action, ['approvefee', 'reject', 'approve', 'rejectapplication'], true)) {
    $module->approve([], $officer->id);
}

http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;
