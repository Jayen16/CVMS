You are working on an existing Laravel Livewire MySQL application for a Web-Based Child Immunization Management System.

I need you to implement an offline-first central/facility architecture using the same Laravel monolith codebase deployed in two different modes.

Do not immediately modify files. First inspect the existing project, including:

* Laravel and PHP versions
* Authentication implementation
* Existing roles and permissions
* Models and migrations
* Livewire version and components
* Route organization
* Queue and scheduler configuration
* Existing child, guardian, vaccine, immunization, inventory, announcement, and schedule-related tables
* Whether Laravel Passport is already installed
* Existing UUID implementation
* Existing tests and coding conventions

After inspection:

1. Summarize the relevant existing structure.
2. Identify conflicts with this architecture.
3. Create a phased implementation plan.
4. Implement one phase at a time.
5. Run relevant tests after every phase.
6. Preserve existing functionality and project conventions.
7. Do not invent duplicate tables when an appropriate table already exists.
8. Do not delete or destructively modify existing production data.

# Architecture objective

Use the same Laravel Livewire monolith project for two deployments:

* Central/online deployment
* Facility/local RHU deployment

They use separate MySQL databases.

The deployment mode is controlled through:

```env
APP_INSTANCE_TYPE=central
```

or:

```env
APP_INSTANCE_TYPE=facility
```

Create an appropriate configuration file such as:

```php
// config/system.php

return [
    'instance_type' => env('APP_INSTANCE_TYPE'),
];
```

Do not use `APP_INSTANCE_TYPE` as an authentication or security mechanism. It only controls which routes, interfaces, jobs, and features are enabled in the current deployment.

The central server must still authenticate facility synchronization requests through Laravel Passport.

# Instance-specific behavior

## Central deployment

The central deployment must provide:

* Superadmin authentication
* Central administrator authentication
* Parent/guardian portal
* Optional remote RHU report viewer
* Facility management
* Activation-code management
* Laravel Passport OAuth2 server
* Synchronization APIs
* Vaccine master management
* Vaccine-dose management
* Immunization schedule-rule management
* Announcement management
* Parent change requests
* Central dashboards and reports
* SMS and application notifications
* Monitoring of facility connections and synchronization
* Online backups
* AI processing in a later phase

The central deployment must not allow ordinary RHU staff to directly edit clinical vaccination or local inventory records through the online interface.

## Facility deployment

The facility deployment must provide:

* Offline local authentication
* Initial facility activation
* Creation of the first local RHU administrator
* Management of local RHU users
* Child registration
* Guardian management
* Immunization recording
* Individual child schedule calculation
* Local vaccine inventory
* Local reports
* Audit logging
* Synchronization status
* Manual “Sync Now” action
* Automatic synchronization when internet is available
* Pending and failed synchronization monitoring

The facility must continue operating when internet connectivity is unavailable.

Do not make local login depend on the central server.

# Instance-specific routes

Organize routes so that central-only routes are not registered on facility deployments and facility-only routes are not registered on central deployments.

Use a structure appropriate to the existing Laravel version, such as:

```text
routes/
├── web.php
├── central.php
├── facility.php
└── api.php
```

Register the appropriate route file according to `APP_INSTANCE_TYPE`.

Do not rely only on hiding navigation links. Unauthorized routes must not be available on the wrong instance.

# User account separation

Use the same application code and potentially the same `User` model structure, but the separate databases contain different login accounts.

## Central MySQL login users

* `superadmin`
* `central_admin`
* `guardian`
* Optional `remote_rhu_viewer`

## Facility MySQL login users

* `rhu_admin`
* `nurse`
* `midwife`
* `inventory_staff`
* `bhw`

Create configuration or policies defining which roles may log in to each application instance.

Reject accounts whose roles are not allowed on the current instance.

Do not synchronize facility-user credentials to the online database.

Never synchronize:

* Passwords
* Password hashes
* Remember tokens
* Login sessions
* Password-reset tokens
* MFA secrets
* Personal access tokens

Only synchronize non-secret staff-directory information when needed for auditing:

* Staff UUID
* Facility UUID
* Name
* Role or position
* Active/inactive status

On the central database, store synchronized local staff information in a non-login table such as `facility_staff`, unless an appropriate existing table already provides this distinction.

Facility staff should not automatically receive online login access.

If a facility administrator needs online remote access, create a separate online account through an invitation and let the person set a separate online password.

# Facility installation identity

Do not require a manually configured `FACILITY_ID` in the facility `.env`.

Before activation, a facility installation should only have:

* `APP_INSTANCE_TYPE=facility`
* A locally generated permanent `instance_uuid`

Generate `instance_uuid` once during first-run installation.

Store it locally and preserve it for the life of that installation.

Create or adapt a table such as `system_installations` containing:

* `instance_uuid`
* `facility_uuid`
* `facility_code`
* `facility_name`
* `central_url`
* `passport_client_id` or `client_id`
* Encrypted client secret
* Connection status
* Activation timestamp
* Last successful synchronization timestamp
* Pull cursor/checkpoint
* Optional credential-revocation information

The Client Secret column must support encrypted content, preferably `TEXT`.

Use Laravel’s encrypted cast or the project’s established encryption mechanism. The raw Client Secret must not be stored unencrypted.

# Facility activation process

Implement this flow:

1. Central superadmin registers an RHU/facility.
2. Central superadmin generates a one-time activation code for that facility.
3. The facility installation shows a Livewire activation form.
4. The local administrator enters:

   * Central system URL
   * Activation code
5. The local Laravel backend submits the activation code and its `instance_uuid` to the central activation API over HTTPS.
6. The central API validates the code.
7. The central application creates a facility-specific Laravel Passport Client Credentials client.
8. The central application creates or updates the facility connection.
9. The activation code is marked as used.
10. The central application returns:

    * Facility UUID
    * Facility code
    * Facility name
    * Passport Client ID
    * Plain Client Secret, exactly once
11. The facility encrypts and stores the Client Secret locally.
12. The facility tests authentication by requesting an access token.
13. The facility performs the initial bootstrap synchronization.
14. The facility prompts for creation of the first local RHU administrator.

The activation code must:

* Be cryptographically random
* Be stored as a hash online
* Have an expiration
* Be usable only once
* Be associated with one facility
* Be rate-limited
* Never be used for normal synchronization
* Not be retained locally after successful activation

Create or adapt central tables such as:

```text
facilities
facility_activation_codes
facility_connections
```

`facility_connections` should associate:

* Facility UUID
* Passport client ID
* Instance UUID
* Instance name
* Status
* Activated timestamp
* Last synchronization timestamp
* Suspended timestamp
* Revoked timestamp

Do not expose a Client Secret again after activation.

Use Laravel Passport APIs compatible with the installed version. Do not hardcode an outdated Passport API without verifying the project’s installed version.

# Passport authentication

Use Laravel Passport Client Credentials for machine-to-machine communication.

The facility backend should request an access token from the central server using its stored Client ID and decrypted Client Secret.

The resulting bearer token should be short-lived and used only by the facility Laravel backend.

Never expose the following to Livewire browser state, Blade HTML, or JavaScript:

* Client Secret
* Bearer token
* Activation-code hash
* Database credentials

Protect synchronization routes with the appropriate Laravel Passport client-credentials middleware for the installed Laravel and Passport versions.

Define limited synchronization scopes/permissions such as:

* `master-data:read`
* `sync:pull`
* `sync:push`
* `sync:status`

The central application must determine the authenticated facility from the Passport client-to-facility connection.

Do not trust a `facility_uuid` submitted in the request payload.

If a facility sends a different facility UUID, reject or ignore it and use the facility associated with the authenticated Passport client.

# Data ownership

Implement clear ownership rules.

The system may use the same models and table schemas in both databases, but each synchronized entity must have one authoritative owner.

## Central-owned data: central to facility

Initially synchronize:

* `vaccines`
* `vaccine_doses`
* `schedule_rules`
* `announcements`

Later, if required:

* System settings
* Parent change requests
* Other centrally approved master data

The facility may read and use central-owned data but must not independently overwrite it.

## Facility-owned data: facility to central

Initially synchronize:

* `facility_staff` metadata without credentials
* `children`
* `guardians`
* Child-guardian relationships
* `immunization_records`

Later:

* Individual child appointments
* Inventory transactions
* Notification requests
* Selected audit events

The central database holds synchronized copies of these facility-owned records for the parent portal, notifications, reports, and backup.

The central UI must not directly modify facility-owned clinical records in the initial version.

# Schedule distinction

Do not confuse schedule rules with an individual child’s schedule.

Central-owned schedule rules include:

* Recommended vaccination age
* Minimum interval
* Dose sequence
* Vaccine-dose relationships

These flow:

```text
Central → Facility
```

The facility uses the child’s birth date, history, and the synchronized schedule rules to calculate individual child appointments.

Individual child appointments flow:

```text
Facility → Central
```

This allows the parent portal to display the synchronized child schedule.

Deterministic immunization rules must not be implemented through machine learning.

# Synchronization registry

Do not automatically inspect and synchronize every MySQL table.

Create an explicit allowlist such as `config/sync.php`.

The configuration should define, for every synchronized entity:

* Public synchronization entity name
* Model
* Owner
* Direction
* Serialization/resource class
* Incoming validation class
* Incoming record handler
* Dependency order
* Whether soft deletion is allowed

Example concept:

```php
return [
    'entities' => [
        'vaccines' => [
            'model' => Vaccine::class,
            'owner' => 'central',
            'direction' => 'central_to_facility',
            'order' => 10,
        ],

        'schedule_rules' => [
            'model' => ScheduleRule::class,
            'owner' => 'central',
            'direction' => 'central_to_facility',
            'order' => 20,
        ],

        'facility_staff' => [
            'model' => FacilityStaff::class,
            'owner' => 'facility',
            'direction' => 'facility_to_central',
            'order' => 10,
        ],

        'children' => [
            'model' => Child::class,
            'owner' => 'facility',
            'direction' => 'facility_to_central',
            'order' => 20,
        ],

        'immunization_records' => [
            'model' => ImmunizationRecord::class,
            'owner' => 'facility',
            'direction' => 'facility_to_central',
            'order' => 30,
        ],
    ],
];
```

Adapt names to the actual existing models.

Never accept a PHP class name or raw table name from an API payload.

The API should accept stable public entity identifiers, look them up in the internal allowlist, and reject unknown or unauthorized entities.

# Record identity

Use UUIDs for all synchronized records.

Do not synchronize by auto-increment integer ID because local and central databases may generate the same numeric IDs for unrelated records.

It is acceptable to retain local numeric primary keys internally, but synchronization relationships must use UUIDs.

Add or confirm appropriate fields such as:

* `uuid`
* `facility_uuid`
* `version`
* `created_at`
* `updated_at`
* `deleted_at`, when soft deletion is supported

Use a version field or another explicit concurrency strategy. Do not rely only on timestamps if the application requires stronger ordering.

# Synchronization outbox

Do not scan and resend all tables during every synchronization.

Create a durable local `sync_outbox` table.

Suggested fields:

* ID
* Event UUID
* Entity name
* Record UUID
* Operation: created, updated, or deleted
* Version
* Serialized payload
* Status: pending, processing, synced, failed
* Attempt count
* Last error
* Created timestamp
* Last attempted timestamp
* Synchronized timestamp

When a facility-owned business record changes, save the business record and outbox event in the same MySQL transaction.

Do not use Redis as the only storage for pending health-data synchronization.

The MySQL outbox is the durable source of unsynchronized changes.

The central side must also track processed event UUIDs to guarantee idempotency.

If the same event is received twice, it must not create duplicate children, vaccination histories, or inventory deductions.

# Push and pull APIs

Implement API endpoints appropriate to existing conventions, such as:

```text
POST /api/sync/push
GET  /api/sync/pull
GET  /api/sync/status
POST /api/facility/activate
```

Use API versioning if appropriate, for example:

```text
/api/v1/sync/push
```

## Facility push

Facility pushes pending facility-owned events in controlled batches.

Suggested entity order:

1. Facility staff metadata
2. Children
3. Guardians
4. Child-guardian relationships
5. Immunization records
6. Child appointments
7. Inventory transactions

After successful central acknowledgment, mark the corresponding outbox event as synced.

If the request fails or acknowledgment is not received, leave the event pending and retry later.

## Facility pull

Facility pulls central-owned changes using a server-issued cursor or checkpoint.

Do not redownload the entire database during every synchronization.

Suggested order:

1. Vaccines
2. Vaccine doses
3. Schedule rules
4. Announcements
5. Parent change requests

The central server should return:

* Records changed after the supplied cursor
* Deletions/tombstones where applicable
* A new cursor
* Server synchronization timestamp

Advance the local cursor only after the entire batch is applied successfully.

# Initial bootstrap

After activation, the facility performs a bootstrap synchronization.

Bootstrap should download:

* Facility details
* Vaccines
* Vaccine doses
* Schedule rules
* Announcements
* Other required central master data

Apply the bootstrap in a database transaction where practical.

The facility must be able to display and use the downloaded data after the internet is disconnected.

Do not destroy existing local transactional records during reactivation or re-bootstrap.

# Automatic and manual synchronization

The facility must support:

* Manual “Sync Now” button
* Automatic scheduled synchronization
* Automatic retry after failure
* Automatic synchronization after connectivity returns, when practical
* Backoff between repeated failures
* Clear pending and failed event monitoring

Use a Laravel command/job/service for synchronization.

Do not place sensitive synchronization logic directly in a Livewire component.

The Livewire button should dispatch or invoke the application-layer synchronization process.

Display:

* Online/offline or reachable/unreachable status
* Activation status
* Last successful sync
* Pending event count
* Failed event count
* Current synchronization progress
* Last synchronization error

Do not claim that browser connectivity alone proves the central API is healthy. Test the actual authenticated API or a suitable health endpoint.

# Local audit attribution

The central system must know which local staff member registered a child or recorded a vaccination without receiving that staff member’s credentials.

Local facility users must have permanent UUIDs.

Synchronize their non-secret metadata into the central `facility_staff` directory.

For child registration, use an explicit field such as:

```text
registered_by_uuid
```

For immunization records, distinguish:

```text
administered_by_uuid
recorded_by_uuid
```

`administered_by_uuid` identifies the person who physically administered the vaccine.

`recorded_by_uuid` identifies the logged-in person who entered the record.

Also preserve audit snapshots when appropriate:

* Actor UUID
* Actor name at the time of action
* Actor role at the time of action
* Facility UUID
* Action
* Subject UUID
* Timestamp

This prevents later staff-name changes from rewriting historical audit information.

# Suspend, revoke, and reactivate behavior

Implement separate statuses and actions:

## Suspended

* Synchronization is temporarily rejected.
* Local operations continue.
* Pending outbox events accumulate.
* Superadmin may restore the same connection.
* New activation is not necessarily required.

## Revoked

* Old Client ID and Client Secret permanently stop working.
* Local operations continue.
* Pending outbox events accumulate.
* New activation code and new Passport client credentials are required.
* Existing local children, histories, inventory, users, outbox events, and cursor must not be deleted.

If reactivating the same local installation:

* Preserve the same `instance_uuid`.
* Replace only the Passport Client ID and Client Secret.
* Preserve all pending local events.
* Resume synchronization after successful reactivation.

If installing on a new computer:

* Generate a new `instance_uuid`.
* Revoke the old connection.
* Follow a controlled backup/restore or migration procedure.
* Do not silently treat the new machine as the old installation.

Revocation affects synchronization only. It must not remotely disable offline RHU operations.

# Windows facility deployment

The RHU computer will be Windows and may have limited specifications.

The RHU should not manually install:

* PHP
* Composer
* Node.js
* npm
* MySQL
* Nginx
* Git
* Visual Studio Code
* Docker Desktop
* WSL2

Do not use `php artisan serve` for production deployment.

Prepare the facility application for packaging as one Windows installer.

Preferred deployment concept:

```text
RHU-Immunization-Setup.exe
├── Production Laravel application
├── Composer vendor packages
├── Compiled frontend assets
├── Windows PHP/web-server runtime such as FrankenPHP
├── MySQL Windows ZIP runtime
├── Database migrations
├── Windows service scripts
├── Scheduler scripts
├── Backup scripts
└── Uninstaller
```

Use an installer builder such as Inno Setup.

The installer should:

1. Require administrator permission.
2. Copy application/runtime files.
3. Place program files under an appropriate Program Files directory.
4. Place writable data under `C:\ProgramData`, not inside Program Files.
5. Initialize MySQL securely.
6. Generate a dedicated MySQL database and application user.
7. Generate a strong random database password.
8. Generate Laravel `APP_KEY`.
9. Create the production `.env`.
10. Run migrations.
11. Register MySQL as an automatically starting Windows service.
12. Register the PHP/web server as an automatically starting Windows service.
13. Configure Laravel Scheduler through Windows Task Scheduler or a suitable service.
14. Configure the queue worker as a controlled service if required.
15. Add a Windows Firewall rule restricted to the local network.
16. Create a desktop shortcut opening the local URL.
17. Open the facility activation page after installation.
18. Preserve the database and backups during application upgrades.
19. Provide a safe uninstall/repair strategy that does not silently delete health records.

Suggested layout:

```text
C:\Program Files\RHU Immunization\
    application and runtime files

C:\ProgramData\RHU Immunization\
    MySQL data
    Laravel storage
    Configuration
    Backups
    Logs
```

MySQL should bind only to loopback, such as:

```text
127.0.0.1
```

Other RHU computers access Laravel through the local web server. They must never connect directly to MySQL.

Use a non-conflicting internal MySQL port if needed.

The local web server should be available through the LAN using a controlled port or hostname, for example:

```text
http://192.168.x.x:8080
```

or a properly configured local hostname.

Do not enable public internet access to the facility web server.

# Low-resource deployment

For the first version:

* Do not require Redis.
* Do not require Laravel Horizon.
* Do not require Laravel Octane unless justified and tested.
* Do not require Docker Desktop.
* Use a database-backed queue if asynchronous work is necessary.
* Use file or database-backed cache according to the existing project.
* Precompile frontend assets before creating the installer.
* Include Composer `vendor` packages in the installer.
* Do not run Node.js on the RHU computer.
* Limit queue-worker concurrency.
* Add log rotation and cleanup.
* Keep the local application focused on clinic operations and synchronization.

AI model training and intensive analytics must remain on the central server.

The facility may pull generated predictions later, but lack of AI results must not prevent local registration or vaccination recording.

# Local backup

Implement or prepare for:

* Automated local MySQL backups
* Backup before application upgrades
* Backup to a separate folder or external device
* Online/off-site backup after synchronization
* Backup retention policy
* Restore procedure
* Verification that backups are not empty or corrupt

The online synchronized copy is not the only backup because recent records may still be pending.

# Security requirements

Apply the following:

* HTTPS for communication with the central API
* Rate-limit activation and synchronization endpoints
* Validate every incoming payload
* Use entity-specific request validation
* Restrict synchronization to configured entity allowlists
* Determine facility identity from Passport credentials
* Never expose Client Secrets or bearer tokens to browsers
* Never log raw activation codes, Client Secrets, bearer tokens, passwords, or health-data payloads unnecessarily
* Encrypt the local Client Secret
* Hash activation codes online
* Use one-time activation
* Support revocation and rotation
* Use UUIDs
* Enforce role-based authorization
* Protect child and guardian personal information
* Create audit logs for important administrative and clinical actions
* Restrict the local firewall rule to the LAN
* Bind MySQL to loopback
* Do not use a blank MySQL password
* Keep `APP_DEBUG=false` in production
* Do not place secrets in Git

# Implementation phases

Implement in the following order.

## Phase 1: Instance foundation

* Inspect existing project.
* Add central/facility configuration.
* Add instance helpers or service.
* Separate central and facility routes.
* Add instance-aware role restrictions.
* Add tests confirming wrong-instance routes and roles are rejected.

## Phase 2: Facility registration and activation

* Add facilities.
* Add activation-code management.
* Install/configure Passport if not present.
* Add facility connections.
* Add facility `system_installations`.
* Build central superadmin facility UI.
* Build facility activation UI.
* Implement secure one-time activation.
* Test successful, expired, reused, and invalid codes.

## Phase 3: Initial bootstrap

Start only with:

```text
Central → Facility
- vaccines
- vaccine_doses
- schedule_rules
- announcements
```

Implement cursor/checkpoint behavior and transactional application.

Verify that the facility can use downloaded data after disconnecting from the central server.

## Phase 4: Facility push

Start only with:

```text
Facility → Central
- facility_staff metadata
- children
- immunization_records
```

Add UUIDs, outbox, validation, idempotency, authentication, and acknowledgment.

Verify duplicate event delivery does not duplicate records.

## Phase 5: Remaining synchronized entities

Add:

* Guardians
* Child-guardian relationships
* Individual appointments
* Inventory transactions
* Parent change requests
* Notification requests
* Selected audit events

Respect dependency order.

## Phase 6: Synchronization user experience

* Sync Now
* Automatic schedule
* Pending and failed monitoring
* Retry
* Last synchronization
* Suspended/revoked states
* Reactivation without data loss

## Phase 7: Parent portal and notifications

Use synchronized online copies.

Do not permit parents to directly change official clinical records.

Parent modifications should create requests for facility review.

## Phase 8: Windows packaging

Only after the core facility system and synchronization tests pass:

* Prepare production build
* Bundle runtimes
* Build Inno Setup installer
* Register services
* Configure scheduler
* Configure firewall
* Implement backup and upgrade behavior
* Test installation on a clean Windows virtual machine

## Phase 9: AI integration

Only after sufficient data and core functions are reliable:

* Central vaccine-demand forecasting
* Central missed-dose risk prediction
* Pull predictions to facility
* Keep AI outputs advisory
* Do not allow AI to make vaccination or procurement decisions

# Required tests

At minimum, add automated tests for:

* Central routes unavailable on facility
* Facility routes unavailable on central
* Role restrictions per instance
* Activation success
* Invalid activation code
* Expired activation code
* Reused activation code
* Revoked facility credentials
* Suspended facility connection
* Passport-authenticated sync
* Facility cannot access another facility’s data
* Facility cannot push central-owned entities
* Central cannot overwrite facility-owned records through sync
* Initial bootstrap
* Incremental pull using cursor
* Facility push acknowledgment
* Retry after network failure
* Duplicate event idempotency
* UUID-based record matching
* Out-of-order version rejection
* Soft-delete/tombstone behavior
* Staff credentials excluded from payloads
* Staff attribution on child registration
* Administered-by and recorded-by attribution
* Reactivation preserves local data
* Pending outbox events resume after reactivation

# Initial acceptance scenario

The first complete demonstration must prove:

1. Central superadmin creates Indang RHU.
2. Central superadmin generates an activation code.
3. A clean facility installation has an `instance_uuid` but no manually configured facility ID.
4. Local administrator enters the central URL and activation code.
5. Central validates the code and generates Passport credentials.
6. Facility stores its identity and encrypted Client Secret.
7. Facility obtains a bearer token.
8. Facility downloads vaccines, vaccine doses, schedule rules, and announcements.
9. Internet is disconnected.
10. RHU administrator creates a local nurse account.
11. Nurse logs in locally.
12. Nurse registers a child.
13. Nurse records an immunization.
14. The child and immunization records are saved locally with pending outbox events.
15. Internet is restored.
16. Facility synchronizes staff metadata, child, and immunization record.
17. Central displays who registered the child, who administered the vaccine, and who recorded the vaccination.
18. No local password or password hash exists in the central database.
19. If the facility credentials are revoked, local operations continue while synchronization stops.
20. After reactivation with new credentials, existing pending events synchronize without deleting local data.

Before implementing, inspect the repository and present the planned file changes and migration changes. If any existing schema or package conflicts with these requirements, explain the conflict and adapt the plan instead of creating duplicate or incompatible functionality.
