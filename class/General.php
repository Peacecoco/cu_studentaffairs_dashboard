<?php
declare(strict_types=1);

class General
{
    protected PDO $db;

    public function __construct(PDO $con)
    {
        $this->db = $con;
    }

    protected function respond(bool $success, string $message, mixed $data = null, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        $response = ['success' => $success, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        echo json_encode($response);
        exit;
    }

    protected function clean(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }

    protected function expireOverdueApplications(): void
    {
        $statement = $this->db->prepare("UPDATE idcardapplications SET status = 'expired', updatedat = NOW() WHERE status = 'awaitingpayment' AND paymentdeadline IS NOT NULL AND paymentdeadline < CURDATE()");
        $statement->execute();
    }

    protected function urlFor(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        // Student master photos are owned by idcard-system; replacement uploads
        // are owned by cu_student. Both are existing shared records, not copies.
        foreach (['../idcard-system', '../cu_student'] as $project) {
            if (is_file(BASE_PATH . '/' . $project . '/' . $path)) {
                return $scheme . '://' . $host . $base . '/' . $project . '/' . $path;
            }
        }
        return $scheme . '://' . $host . $base . '/' . $path;
    }
}
