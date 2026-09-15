<?php
declare(strict_types=1);

require_once __DIR__ . '/include/session.php';
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/classes.php';

$module = new StudentAffairs($con);
$method = $_SERVER['REQUEST_METHOD'];
$action = strtolower(trim($_GET['action'] ?? ''));
$body = in_array($method, ['PUT', 'POST'], true) ? (json_decode(file_get_contents('php://input'), true) ?: $_POST) : [];

if ($method === 'GET' && $action === 'all') { $module->allApplications(); }
if ($method === 'GET' && $action === 'department') { $module->applicantDepartment($_GET['ref'] ?? null); }
if ($method === 'PUT' && $action === 'approvefee') { $module->approve($body, currentReviewer()); }
if ($method === 'PUT' && $action === 'reject') { $module->reject($body, currentReviewer()); }

http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
