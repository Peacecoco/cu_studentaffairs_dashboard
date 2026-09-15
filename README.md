# CU Student Affairs — ID Card Review

PHP dashboard and JSON API for reviewing replacement-card applications. It uses plain JavaScript/CSS and reads the shared `idcard_system` database directly.

[CU Student](../cu_student/README.md) owns submissions and replacement uploads. Student Affairs approves fees/deadlines or rejects requests. Paid applications continue to [ID Card System](../idcard-system/README.md) for card generation and printing.

## Setup

1. Use PHP 8.1+ with `pdo_mysql`, MySQL/MariaDB, and a PHP-capable server. No Composer or frontend build is required here.
2. Follow the [shared database setup](../idcard-system/README.md#database-setup), including `idcardsettings`. This module needs `students`, `idcardapplications`, and `idcardsettings`.
3. Set [include/config.php](include/config.php) to the same database as the other projects.
4. Keep `cu_student`, `cu_studentaffairs`, and `idcard-system` as siblings for media resolution.
5. Open `http://localhost/REFACTOR/cu_studentaffairs/studentaffairs/review-requests.php`, adjusting the base path as needed.

## Dashboard workflow

Navigation now opens dedicated pages: `studentaffairs/review-requests.php`, `studentaffairs/approved-log.php`, and `studentaffairs/rejected-log.php`. Each sets its own fixed list and heading, while sharing the application list, details, dialogs, and API script. `studentaffairs/dashboard.php` redirects to Review ID Requests. Browser refresh/back and direct bookmarks retain the selected page.

| Tab | Statuses | Ordering/filtering |
| --- | --- | --- |
| Review ID Requests | `submitted` | Oldest first; search name, matriculation number, or reference; filter by reason. |
| Approved Log | `awaitingpayment`, `paid`, `printed`, `readyforpickup`, `acknowledged`, `closed` | Most recently updated first. |
| Rejected Log | `rejected`, `cancelled`, `expired` | Most recently updated first. |

Opening a card shows applicant information, prior replacement requests, registered photo, replacement photo, and supporting document. Media opens in a larger viewer. The bell counts submitted requests and shows up to three entries; it does not send email/SMS.

Only submitted applications can be reviewed:

- Approval reads active settings for the reason, copies the fee, calculates the payment deadline from `expirydays`, records the reviewer, and sets `awaitingpayment`.
- Rejection requires a nonempty reason, records the reviewer, and sets `rejected`.
- Updates check the previous status and return a conflict if another request already processed it.
- Listing applications also expires overdue `awaitingpayment` records. This is triggered by API access, not a scheduled job.

## Folder guide

| Path | Purpose |
| --- | --- |
| `studentaffairs/dashboard.php` | Compatibility redirect to `review-requests.php`. |
| `studentaffairs/review-requests.php`, `approved-log.php`, `rejected-log.php` | Dedicated review and log pages. |
| `include/studentaffairs/` | Shared header/navigation, application list/detail view, and footer/dialogs. |
| `index.php` | JSON API router. |
| `class/StudentAffairs.php` | Listing, student joins, approval/rejection, response formatting. |
| `class/General.php` | Responses, cleaning, expiry updates, media URL resolution. |
| `include/config.php` | PDO connection and base path. |
| `include/classes.php` | Class loading. |
| `include/session.php` | Session initialization and reviewer identity. |
| `assets/js/studentaffairs.js` | Page-specific status filtering, search, details, notifications, and review requests. |
| `assets/css/studentaffairs.css` | Layout, responsive rules, and status badges. |
| `assets/images/` | University branding. |
| `idcard/` | Legacy photos referenced by older records; new submissions belong to `cu_student`. |

## API

Routes are relative to `index.php`. Responses contain `success`, `message`, and optional `data`.

| Method | Query | Input / result |
| --- | --- | --- |
| GET | `?action=all` | All applications, joined student data, and media URLs; also expires overdue invoices. |
| GET | `?action=department&ref=...` | Applicant department. |
| PUT | `?action=approvefee` | JSON with `referencenumber`. |
| PUT | `?action=reject` | JSON with `referencenumber` and `rejectionreason`. |

## Identity and shared files

`currentReviewer()` uses `$_SESSION['staff_number']`, then `$_SESSION['idcard_reviewer']`, then `STAFF001`. This records a reviewer identity but does not authenticate staff or enforce roles. Host-portal access control is not implemented in this standalone dashboard/API.

Media resolution checks the relative path under `../idcard-system`, then `../cu_student`, then this project. Preserve or update stored paths when moving files. A missing student match produces “Unknown student”; the left join keeps the application visible.

## Manual verification

With development records, check search/reason filtering, document/photo previews, approval fee/deadline, required rejection reasons, and conflict handling for already reviewed applications. Check paid records in Approved Log and expired invoices in Rejected Log. These checks change shared application records. Navigation regression checks are available in the workspace at [tests/navigation_smoke.py](../tests/navigation_smoke.py). Run `python tests/navigation_smoke.py` from REFACTOR with Apache/PHP running. These check page routes, assets, filters, and legacy redirects; they do not exercise application/payment writes.
