# Child Vaccination Management System

## System Overview

This system is a role-based Child Vaccination Management System for an RHU and barangay clinics. It centralizes child profiles, vaccination records, schedule guidance, verification workflows, clinic communication, and monitoring tools for staff and parents.

## User Roles

### 1. Superadmin

#### Main features
- `Dashboard`
- `Barangay admin account management`
- `Vaccine schedule management`
- `System-wide reports`
- `Clinic announcements`

#### Functions inside the features
- View total barangays, barangay admins, nurses, children, vaccinations, pending submissions, and pending sync items
- View barangay-level counts for children, nurses, and barangay admins
- Create barangay admin accounts by email
- Optionally give a barangay admin an additional nurse role
- Resend password setup links for staff
- Remove barangay admin accounts
- Activate or deactivate announcements
- Create and delete announcements
- Manage vaccine types and dose schedules
- Activate or deactivate schedules and vaccine types
- Export reports to PDF
- View vaccination totals and verification status summaries

### 2. Barangay Admin

#### Main features
- `Dashboard`
- `Nurse account management`
- `Children registry viewing`
- `Verification queue`
- `Defaulter monitoring`
- `Duplicate child detection`
- `AEFI viewing`
- `Barangay reports`
- `Clinic announcements`

#### Functions inside the features
- View barangay-specific counts for nurses, children, vaccinations, pending submissions, and pending sync items
- View recent children in the barangay
- Create nurse accounts by email
- Resend nurse setup links
- Activate or deactivate nurse accounts after setup
- Remove nurse accounts
- View child profiles, vaccination history, timeline, and vaccine card
- View parent-submitted vaccination records waiting for review
- Filter verification queue by barangay, vaccine, source, and date range
- Review and monitor late or overdue children in the defaulter list
- Detect likely duplicate child records using matching birthdate with child name or guardian contact
- View AEFI reports submitted for children in the barangay
- View barangay-scoped reports and export PDF
- Create, activate, deactivate, and delete announcements for staff or parents

### 3. Nurse

#### Main features
- `Dashboard`
- `Child profile management`
- `Vaccination recording`
- `Parent linking`
- `Verification queue`
- `Defaulter monitoring`
- `Duplicate child detection`
- `AEFI reporting and viewing`
- `Announcements`
- `Vaccine card and timeline viewing`

#### Functions inside the features
- Create new child profiles assigned to the nurse's barangay
- Edit child demographic and guardian details
- View child profile details and vaccination history
- Record vaccinations directly in the system
- Automatically save nurse-entered vaccinations as verified
- View AI-assisted next-dose suggestions based on configured vaccine schedules
- Link parent accounts to child profiles
- Invite parents by email and resend setup links
- Unlink parents from child profiles
- Review proof photos uploaded by parents
- Verify or reject parent-submitted vaccination records
- Submit AEFI reports for a child and connect them to a vaccine record
- View all AEFI reports in the assigned barangay
- Review duplicate child matches
- Review defaulter lists for follow-up
- Create, activate, deactivate, and delete clinic announcements
- View vaccine card pages, PDF cards, and QR-based validation pages

### 4. Parent

#### Main features
- `Dashboard`
- `Linked children access`
- `Vaccination submission`
- `Child timeline and vaccine card viewing`
- `Announcements`
- `Account self-service`

#### Functions inside the features
- View only child profiles linked to the parent account
- View vaccination history for linked children
- View due or upcoming vaccination dates shown on the dashboard calendar
- Submit outside-clinic vaccination records
- Add clinic name, clinic location, date given, dose number, and proof photo for submitted records
- Edit pending parent-submitted vaccination entries before verification
- Track whether a submitted vaccination is pending, verified, or rejected
- View child vaccine cards and timeline pages
- Unlink the parent account from a child profile
- View parent-relevant clinic announcements

## Cross-System Features

### Vaccination schedule intelligence
- The system calculates the next suggested dose from the configured routine vaccine schedule
- The vaccine schedule is based on the `2026 PIDSP` (`Pediatric Infectious Disease Society of the Philippines`) routine immunization guidance
- The July 1, 2026 correction to the PPS-PIDSP-PFV childhood immunization calendar should be reflected in the configured schedule data
- For `Meningococcal B`, infants who receive the first dose at `6-11 months` should use the corrected booster timing of `12 months onwards`, with at least `2 months` after the primary series
- A booster given at `2 years old and above` is still considered valid because it also satisfies the corrected minimum timing
- Suggestions include due date, action date, overdue status, and staff review reminders
- The current system uses one configured active schedule dataset for suggestions, reminders, and timeline markers

### Verification workflow
- Parent-submitted vaccination records are stored as pending
- Nurses can verify or reject pending submissions
- Rejected entries are excluded from schedule completion logic

### Vaccine cards
- Each child can have a vaccine card page
- Vaccine cards can be exported as PDF
- QR codes link to a public validation page for the card

### Offline and sync support
- The system can queue selected updates into an offline outbox
- Supported queued records include child profiles, vaccination records, announcements, and AEFI reports
- Queued records can be synced later to a remote database connection
- Offline batch submission supports idempotent saving using a client submission ID

### Notifications and reminders
- Parents can receive vaccination reminders by email and optionally SMS
- Reminder sending checks for duplicates so the same reminder is not sent twice for the same due dose

### Security and access control
- Role-based access limits each user to allowed screens and actions
- Email verification is supported
- Password reset links are supported

## Important Notes for the Client

- This is a multi-role system, so the user experience changes depending on the account type.
- Superadmin focuses on platform oversight, staff management, schedules, and reporting.
- Barangay admin focuses on local oversight and nurse management.
- Nurse is the main operational role for creating child records, recording vaccinations, reviewing submissions, and filing AEFI reports.
- Parent access is intentionally limited to linked children and parent-side submissions.
- Announcements support audience targeting such as all users, parents only, or staff only.
- Some records and dashboards are scoped by barangay, not system-wide.
- Parent vaccination submissions require review before they count as verified clinic data.
- Reminder delivery depends on system configuration for email and SMS.
- Offline sync also depends on deployment configuration and a remote connection target.
- Vaccine schedule version history is not yet implemented in the current codebase.
- The current schedule management supports editing active dose rules, but not saving formal editions such as `ver2025` and `ver2026` with a selectable applied version.
- If the client wants versioned schedules, the system will need an additional schedule-version layer such as `schedule name`, `version year`, `effective date`, `status`, and a way to apply one version as the active basis for suggestions and reminders.
