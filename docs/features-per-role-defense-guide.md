# Features Available per Role

## Defense Guide for the Child Vaccination Management System

This guide summarizes the role-based features currently available in the CVMS codebase and provides panel-ready wording. Access is restricted by role, permission, and assigned location. Users with more than one role receive the combined access allowed by those roles.

## 1. Role and feature overview

| Role | Main access scope | Main features currently available |
|---|---|---|
| **Superadmin** | System-wide | Platform and location/group administration; dashboards and system-wide reports; vaccine schedule and vaccine type management; inventory management; demand forecasting and schedule monitoring; population-background management; audit logs; archive management; synchronization oversight. |
| **Municipal Admin** | Assigned municipality and its barangays | Municipality-scoped dashboard, child registry viewing, reports, verification queue viewing, schedule monitoring and demand forecasting, inventory viewing, population-background management, audit logs and archive access; barangay-admin account management; synchronization oversight. |
| **Barangay Admin** | Assigned barangay | Barangay dashboard and reports; child registry and child-record management; nurse account management; parent-submission review and vaccination verification; inventory management; schedule monitoring and demand forecasting; population-background viewing; audit logs and archive access; synchronization oversight. |
| **Nurse** | Assigned barangay, limited by nurse permissions | Child registry and child-record tasks when permitted; vaccination recording and verification when permitted; parent linking; verification queue; inventory viewing or management when permitted; schedule monitoring and demand forecasting when permitted. The nurse account’s permission set controls available tasks. |
| **Parent** | Only children linked to that parent account | Parent dashboard; linked children’s profiles, vaccination history, timeline and vaccine cards; family schedule and vaccine information; submission of outside-clinic vaccination records with proof; editing pending submissions; tracking verification status; account self-service and parent-relevant notices. |

### How to read the table

- “Available” means the feature is present in the current application routes, access checks, or navigation. A user’s exact screens also depend on their assigned role, location, and permissions.
- **Municipal Admin and Barangay Admin access is geographically scoped.** A municipal account is limited to its municipality; a barangay account is limited to its barangay.
- **Parent access is child-linked.** A parent does not browse the general child registry and should only see children connected to their account.
- **Nurse permissions are configurable.** The role alone does not guarantee every operational feature. The assigned nurse permissions determine which features and actions are enabled.
- Users may have multiple roles. Access is combined subject to the system’s authorization checks.

## 2. Role details

### Superadmin

The Superadmin supports system-wide administration and oversight.

- Manage groups and location structures and oversee staff accounts.
- Manage vaccine types and schedule rules.
- View system-wide dashboards and reports and export reports.
- Manage population-background records, including census-related population inputs.
- Manage vaccine inventory.
- View demand forecasts and schedule monitoring.
- Review audit logs, archived records, and synchronization status.

**Defense point:** Superadmin access is designed for system administration and cross-area oversight. It is not the same as the nurse’s routine clinic workflow.

### Municipal Admin

The Municipal Admin works within the assigned municipality and its barangays.

- View municipality-scoped dashboards, child records, reports, and population context.
- Create and manage barangay-admin accounts for the municipality.
- View the vaccination verification queue.
- View vaccine inventory and planning outputs, including demand forecasts and schedule monitoring.
- Manage population-background data for the municipality.
- Review audit/archive information and synchronization status.

**Important permission distinction:** The current authorization logic lets a Municipal Admin **view** the verification queue and inventory but does not grant vaccination verification actions or inventory management by role alone.

### Barangay Admin

The Barangay Admin supports local operations and staff supervision in the assigned barangay.

- View barangay dashboards, child records, reports, and population context.
- Create and manage nurse accounts.
- Create or update child records and manage parent-child links.
- Review the verification queue and verify or reject parent-submitted vaccination records.
- Manage vaccine inventory.
- View schedule monitoring and vaccine demand forecasts.
- Review audit/archive information and synchronization status.

**Important permission distinction:** Population-background records can be viewed in the oversight area, but creating, importing, editing, or deleting those records is reserved to Superadmin and Municipal Admin access.

### Nurse

The Nurse is an operational role for assigned barangay services. Available actions depend on the nurse’s permission set.

Depending on those permissions, a nurse may:

- View the child registry and create or update child records.
- Record vaccinations and link parent accounts to children.
- View the verification queue and verify or reject submissions.
- View or manage inventory.
- View schedule monitoring and demand forecasts.
- Access other permitted child-record functions.

Nurses do not receive system-wide administration or reporting access just by being nurses. They also do not manage nurse accounts through the nurse role.

### Parent

The Parent role is limited to family-facing services for linked children.

- View linked child profiles, vaccine history, timelines, and vaccine cards.
- See due or upcoming dates and vaccine information.
- Submit outside-clinic vaccination details and proof for staff review.
- Edit a submission while it remains pending and track whether it is pending, verified, or rejected.
- Manage their own account details and parent-side link to a child.

Parent-submitted doses remain pending until reviewed. They should not be described as verified clinic records before staff verification.

## 3. Important availability and scope notes

### Features whose code or permissions exist but are not currently exposed as active user-facing modules

Some project documentation describes features that are currently disabled or not routed in the application. Do not present these as active features in the defense unless they have been enabled in the deployment being demonstrated:

- Clinic announcements: announcement routes are temporarily disabled.
- Standalone duplicate-child detection/merge page: its route is temporarily disabled.
- Standalone defaulter page: its route is temporarily disabled. Schedule Monitoring remains the current active screen for dose-status and follow-up review.
- AEFI reporting and viewing pages: their routes are temporarily disabled, even though related permission checks and code are present.

### Forecasting scope

The vaccine-demand forecast uses registered children and recorded vaccine activity in the selected CVMS area. The admin population-background/census feature is **not currently an input** to that demand equation. Therefore, say:

> “The current demand forecast estimates needs represented by registered CVMS children and recorded vaccine activity in the selected area. It does not yet estimate demand for unregistered children using census population data.”

### Schedule and clinical workflow

Schedule suggestions use the configured vaccine schedule and are advisory. Staff must check dose history, minimum ages and intervals, catch-up guidance, contraindications, and clinical circumstances before vaccination. Parent submissions require review before they count as verified clinic data.

## 4. Key messages for the defense

1. **Access is role-based, permission-based, and location-scoped.** Each user sees the parts of the system needed for their responsibilities.
2. **Superadmin and administrators provide oversight; nurses perform permitted clinic workflows; parents use family-facing services.**
3. **Parents can submit records, but staff verification is part of the data-quality workflow.**
4. **The census/population-background feature supports population context and reporting. It is not currently used by the vaccine-demand formula.**
5. **Predictive outputs are advisory.** Demand is based on registered records; missed-dose risk is a rule-based follow-up score, not a clinically calibrated probability.
6. **Describe only active features as available.** Announcements, standalone duplicates, standalone defaulter page, and AEFI screens are currently disabled/not routed in this codebase.

## 5. Recommended wording for the client

> “The CVMS provides different features according to each user’s role, permissions, and assigned location. Superadmins manage the platform and system-wide settings. Municipal and Barangay Admins oversee records and operations within their jurisdictions, with different management rights. Nurses carry out the clinic tasks enabled for their accounts. Parents can view only children linked to them and submit outside-clinic vaccination records for staff verification. The demand forecast currently uses registered-child schedules and recorded vaccine activity; census population data is not yet part of that formula. Predictive outputs support planning and follow-up, while clinical and procurement decisions remain subject to staff review.”

## 6. Suggested conclusion for the defense

> “The role-based design organizes the vaccination workflow around the responsibilities of administrators, clinic staff, and parents while limiting data access by role and service area. It supports child-record management, vaccination documentation and verification, schedule monitoring, inventory oversight, reporting, and parent participation. The system’s demand forecast and missed-dose score are decision-support features whose scope depends on available CVMS records and configured permissions. They should be interpreted as advisory outputs, with population coverage, data completeness, and clinical review clearly acknowledged as limitations.”

## 7. Likely panel questions and concise answers

### Why do users have different screens?

> “The system uses role, permission, and location checks so each account can perform its assigned work while limiting access to records outside its service area.”

### Can parents add a vaccination directly as verified?

> “Parents submit outside-clinic vaccination information and proof. It remains pending until an authorized staff member reviews and verifies it.”

### Does the demand forecast use census population data?

> “Not in the current implementation. It estimates demand from registered children’s schedules and recorded vaccine use. The population-background feature is available for population context and reporting, but it is not an input to this forecast.”

### Is every feature described in older project documents active?

> “Some capabilities are currently disabled or not routed in the application. For this defense, we identify those separately and demonstrate only the active screens and permissions in the deployed system.”

### Are the risk and demand outputs final decisions?

> “No. They are advisory planning and follow-up outputs. Authorized staff review the underlying records and apply the relevant clinical or supply-planning guidance before acting.”

## 8. Source of this role summary

This summary is based on the current CVMS access and navigation implementation, especially:

- `app/Models/User.php` — role checks, permissions, location scope, and nurse permission definitions.
- `resources/views/layouts/app/sidebar.blade.php` — features exposed in the main navigation.
- `routes/web.php` — active routes and routes temporarily disabled in comments.
- `docs/client-system-feature-document.md` — general feature descriptions, cross-checked against current routes and permissions.

When preparing the final defense presentation, confirm the deployed account configuration and enabled routes, since nurse permissions and deployment settings can change which features are visible.
