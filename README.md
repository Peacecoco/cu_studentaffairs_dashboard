# CU Student Affairs — Refund Review

Student Affairs monitors replacement applications and approves eligible refund requests. It does not approve or reject ordinary replacement applications.

## Page

- `studentaffairs/review-requests.php` — Applications & Refunds dashboard

The dashboard uses one all-applications table with backend-supported search by reference, matric number, or student name. Its filter supports All Applications, Requested, Approved, Credited, and No Refund. Historical rejected applications remain visible under All Applications; there is no separate rejected-log page.

## Refund workflow

1. Student creates a `requested` refund in the Student portal.
2. Student Affairs reviews safe student, application, payment, refund, and actual event-history data.
3. Student Affairs approves through its domain lifecycle: `requested → approved`.
4. Account Officer completes `approved → credited`.

Approval records trusted actor identity/timestamp and appends `refund_approved`. It never sends money or marks a refund credited.

## Authentication and configuration

Production requires `CU_STUDENTAFFAIRS_IDENTITY_RESOLVER` or `CU_AFFAIRS_IDENTITY_RESOLVER`, resolving a portal session login to `student_affairs`.

For local testing:

```env
CU_AUTH_BYPASS=1
CU_AUTH_BYPASS_AFFAIRS_ACTOR=DEV_STUDENT_AFFAIRS
```

CSRF remains active in bypass mode because the module receives a stable local actor. Database overrides are `CU_STUDENTAFFAIRS_DB_HOST`, `CU_STUDENTAFFAIRS_DB_NAME`, `CU_STUDENTAFFAIRS_DB_USER`, and `CU_STUDENTAFFAIRS_DB_PASS`.

## API

- `GET index.php?action=session`
- `GET index.php?action=applications&search=&filter=`
- `GET index.php?action=paymentdetails&ref=`
- `GET index.php?action=history&ref=`
- `GET index.php?action=refunddetails&ref=`
- `POST index.php?action=approverefund` with `X-CSRF-Token`

Legacy `approvefee`, `reject`, and related ordinary-application review actions are explicitly disabled.
