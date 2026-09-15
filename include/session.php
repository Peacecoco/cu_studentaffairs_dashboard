<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// The source Student Affairs page used STAFF001 from browser storage and had
// no authentication endpoint. Preserve that working fallback while allowing a
// host portal to set $_SESSION['staff_number'] or $_SESSION['idcard_reviewer'].
function currentReviewer(): string
{
    $reviewer = $_SESSION['staff_number'] ?? $_SESSION['idcard_reviewer'] ?? 'STAFF001';
    return trim((string) $reviewer) ?: 'STAFF001';
}
