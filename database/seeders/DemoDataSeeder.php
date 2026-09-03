<?php

namespace Database\Seeders;

use App\Models\AdverseEventReport;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\ClinicAnnouncement;
use App\Models\Municipality;
use App\Models\OfflineSyncOutbox;
use App\Models\SyncStatus;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccinationReminder;
use App\Models\VaccineInventoryItem;
use App\Models\VaccineInventoryTransaction;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use App\Notifications\InAppNotification;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private Collection $vaccines;

    private VaccineScheduleVersion $activeVersion;

    private VaccineScheduleVersion $archivedVersion;

    private VaccineScheduleVersion $draftVersion;

    /**
     * @var array<string, Barangay>
     */
    private array $barangays = [];

    /**
     * @var array<string, User>
     */
    private array $users = [];

    /**
     * @var array<string, ChildProfile>
     */
    private array $children = [];

    public function run(): void
    {
        $this->vaccines = VaccineType::query()->orderBy('name')->get()->keyBy('code');
        $this->activeVersion = VaccineScheduleVersion::query()->where('status', 'active')->firstOrFail();

        $this->seedScheduleVersions();
        $this->seedBarangays();
        $this->seedUsers();
        // Announcements are temporarily disabled; their routes are commented out.
        // $this->seedAnnouncements();
        $this->seedChildrenAndVaccinationHistories();
        $this->seedInventory();
        // AEFI reports are temporarily disabled; their routes are commented out.
        // $this->seedAefiReports();
        $this->seedReminders();
        $this->seedNotifications();
        $this->seedSyncData();
        $this->seedAuditLogs();
    }

    private function seedAuditLogs(): void
    {
        $child = ChildProfile::query()->first();
        $barangay = Barangay::query()->first();
        $admin = $this->users['superadmin'] ?? User::query()->whereJsonContains('roles', 'superadmin')->first();
        $nurse = $this->users['starter_nurse'] ?? User::query()->whereJsonContains('roles', 'nurse')->first();

        if ($admin === null || $nurse === null || $child === null || $barangay === null) {
            return;
        }

        $samples = [
            [$nurse, 'created', 'Created Child Profile', $child, ['first_name' => 'Maria', 'last_name' => 'Santos'], [], '/children'],
            [$nurse, 'updated', 'Updated Vaccination Record', $child, ['verification_status' => 'verified'], ['verification_status' => 'pending'], '/vaccinations/record/verify'],
            [$admin, 'printed', 'Printed child vaccination timeline', $child, ['format' => 'pdf'], [], '/children/'.$child?->id.'/timeline/pdf'],
            [$admin, 'printed', 'Printed vaccine inventory report', $barangay, ['format' => 'pdf'], [], '/vaccine-inventory/report'],
        ];

        foreach ($samples as [$user, $event, $description, $target, $newValues, $oldValues, $url]) {
            AuditLog::query()->firstOrCreate([
                'user_id' => $user->id,
                'event' => $event,
                'description' => $description,
                'auditable_id' => $target?->id,
            ], [
                'auditable_type' => $target?->getMorphClass() ?? AuditLog::class,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'url' => $url,
                'ip_address' => '192.168.1.'.random_int(10, 99),
                'user_agent' => 'Mozilla/5.0 (Demo Audit Seeder)',
                'created_at' => now()->subMinutes(random_int(5, 180)),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedScheduleVersions(): void
    {
        $this->archivedVersion = VaccineScheduleVersion::updateOrCreate(
            ['version_code' => '2025.4'],
            [
                'name' => 'PIDSP 2025 Final',
                'effective_date' => '2025-01-01',
                'status' => 'archived',
                'source' => config('immunization.source'),
                'source_url' => config('immunization.source_url'),
                'notes' => 'Archived legacy schedule retained for children who started an older series.',
                'published_at' => Carbon::parse('2025-01-01')->startOfDay(),
            ],
        );

        $this->draftVersion = VaccineScheduleVersion::updateOrCreate(
            ['version_code' => '2026.2-draft'],
            [
                'name' => 'PIDSP 2026 Draft Catch-up Review',
                'effective_date' => '2026-10-01',
                'status' => 'draft',
                'source' => config('immunization.source'),
                'source_url' => config('immunization.source_url'),
                'notes' => 'Draft review version for planning and training purposes.',
                'published_at' => null,
            ],
        );

        $this->cloneSchedulesToVersion($this->archivedVersion, false);
        $this->cloneSchedulesToVersion($this->draftVersion, true);
    }

    private function cloneSchedulesToVersion(VaccineScheduleVersion $targetVersion, bool $annotateDraft): void
    {
        $activeSchedules = VaccineSchedule::query()
            ->where('vaccine_schedule_version_id', $this->activeVersion->id)
            ->get();

        foreach ($activeSchedules as $schedule) {
            VaccineSchedule::updateOrCreate(
                [
                    'vaccine_schedule_version_id' => $targetVersion->id,
                    'vaccine_type_id' => $schedule->vaccine_type_id,
                    'dose_number' => $schedule->dose_number,
                ],
                [
                    'age_days' => $schedule->age_days,
                    'age_weeks' => $schedule->age_weeks,
                    'age_months' => $schedule->age_months,
                    'age_years' => $schedule->age_years,
                    'label' => $schedule->label,
                    'indication' => $schedule->indication,
                    'notes' => $annotateDraft
                        ? trim(($schedule->notes ? $schedule->notes.' ' : '').'Draft review copy for training.')
                        : $schedule->notes,
                    'active' => true,
                ],
            );
        }
    }

    private function seedBarangays(): void
    {
        $indang = Municipality::query()->where('name', 'Indang')->first();
        $barangay = Barangay::query()
            ->when($indang, fn ($query) => $query->where('municipality_id', $indang->id))
            ->orderBy('name')
            ->first();

        if ($barangay === null) {
            throw new \RuntimeException('No Indang barangay found. Run PsgcSeeder first.');
        }

        foreach (['barangay_1', 'san_isidro', 'santa_maria', 'riverside'] as $key) {
            $this->barangays[$key] = $barangay;
        }
    }

    private function seedUsers(): void
    {
        $this->users['superadmin'] = User::where('email', 'superadmin@example.com')->firstOrFail();
        $this->users['starter_barangay_admin'] = User::where('email', 'barangay-bancod@example.com')->firstOrFail();
        $this->users['starter_nurse'] = User::where('email', 'nurse-bancod@example.com')->firstOrFail();
        $this->users['second_bancod_nurse'] = User::where('email', 'nurse-bancod2@example.com')->firstOrFail();
        $this->users['kaytapos_nurse'] = User::where('email', 'nurse-kaytapos@example.com')->firstOrFail();

        $this->users['starter_parent'] = $this->upsertUser(
            'Demo Parent',
            'parent@example.com',
            '09171234567',
            'parent',
        );

        $this->users['san_isidro_admin'] = $this->upsertUser(
            'Bernadette Cruz',
            'sanisidro.admin@example.com',
            '09170000002',
            'barangay_admin',
            $this->barangays['san_isidro'],
        );

        $this->users['santa_maria_admin'] = $this->upsertUser(
            'Joel Navarro',
            'santamaria.admin@example.com',
            '09170000003',
            'barangay_admin',
            $this->barangays['santa_maria'],
        );

        $this->users['riverside_admin'] = $this->upsertUser(
            'Diana Flores',
            'riverside.admin@example.com',
            '09170000004',
            'barangay_admin',
            $this->barangays['riverside'],
        );

        $this->users['nurse_lara'] = $this->upsertUser(
            'Lara Mendoza',
            'lara.mendoza@example.com',
            '09170000011',
            'nurse',
            $this->barangays['san_isidro'],
        );

        $this->users['nurse_ian'] = $this->upsertUser(
            'Ian Bautista',
            'ian.bautista@example.com',
            '09170000012',
            'nurse',
            $this->barangays['san_isidro'],
        );

        $this->users['nurse_sofia'] = $this->upsertUser(
            'Sofia Garcia',
            'sofia.garcia@example.com',
            '09170000013',
            'nurse',
            $this->barangays['santa_maria'],
        );

        $this->users['nurse_ella'] = $this->upsertUser(
            'Ella Santos',
            'ella.santos@example.com',
            '09170000014',
            'nurse',
            $this->barangays['riverside'],
        );

        $this->users['nurse_invited'] = $this->upsertUser(
            'Pending Nurse Invite',
            'pending.nurse@example.com',
            '09170000015',
            'nurse',
            $this->barangays['riverside'],
            active: false,
            invitationAcceptedAt: null,
            emailVerifiedAt: null,
        );

        $this->users['maria_lopez'] = $this->upsertUser(
            'Maria Lopez',
            'maria.lopez@example.com',
            '09171111111',
            'parent',
        );

        $this->users['rafael_lopez'] = $this->upsertUser(
            'Rafael Lopez',
            'rafael.lopez@example.com',
            '09171111112',
            'parent',
        );

        $this->users['ana_santos'] = $this->upsertUser(
            'Ana Santos',
            'ana.santos@example.com',
            '09172222221',
            'parent',
        );

        $this->users['paolo_rivera'] = $this->upsertUser(
            'Paolo Rivera',
            'paolo.rivera@example.com',
            '09173333331',
            'parent',
        );

        $this->users['jessa_cruz'] = $this->upsertUser(
            'Jessa Cruz',
            'jessa.cruz@example.com',
            '09174444441',
            'parent',
        );

        $this->users['maricar_dela_cruz'] = $this->upsertUser(
            'Maricar Dela Cruz',
            'maricar.delacruz@example.com',
            '09175555551',
            'parent',
        );

        $this->users['oliver_reyes'] = $this->upsertUser(
            'Oliver Reyes',
            'oliver.reyes@example.com',
            '09176666661',
            'parent',
        );

        $this->users['sylvia_garcia'] = $this->upsertUser(
            'Sylvia Garcia',
            'sylvia.garcia@example.com',
            '09177777771',
            'parent',
        );
    }

    private function seedInventory(): void
    {
        $barangay = $this->barangays['barangay_1'];
        $recorder = $this->users['starter_nurse'];
        $today = Carbon::today();

        foreach ([
            ['code' => 'bcg', 'item' => 'DEMO-BCG-001', 'batch' => 'BCG-2026-01', 'quantity' => 40],
            ['code' => 'hepb', 'item' => 'DEMO-HEPB-001', 'batch' => 'HEPB-2026-01', 'quantity' => 50],
            ['code' => 'dtap', 'item' => 'DEMO-DTAP-001', 'batch' => 'DTAP-2026-01', 'quantity' => 75],
            ['code' => 'pcv', 'item' => 'DEMO-PCV-001', 'batch' => 'PCV-2026-01', 'quantity' => 60],
            ['code' => 'mmr', 'item' => 'DEMO-MMR-001', 'batch' => 'MMR-2026-01', 'quantity' => 45],
        ] as $stock) {
            $vaccine = $this->vaccines->get($stock['code']);

            if ($vaccine === null) {
                continue;
            }

            $item = VaccineInventoryItem::updateOrCreate(
                ['item_code' => $stock['item']],
                [
                    'barangay_id' => $barangay->id,
                    'vaccine_type_id' => $vaccine->id,
                    'batch_number' => $stock['batch'],
                    'expiry_date' => $today->copy()->addYear(),
                    'received_at' => $today->copy()->subMonth(),
                    'reference_number' => 'DEMO-RECEIPT-2026-01',
                    'notes' => 'Demo inventory stock for testing and training.',
                ],
            );

            VaccineInventoryTransaction::updateOrCreate(
                ['sync_uuid' => 'demo-inventory-receipt-'.$stock['code']],
                [
                    'barangay_id' => $barangay->id,
                    'vaccine_type_id' => $vaccine->id,
                    'vaccine_inventory_item_id' => $item->id,
                    'recorded_by' => $recorder->id,
                    'transaction_type' => 'receipt',
                    'movement' => 'in',
                    'quantity' => $stock['quantity'],
                    'batch_number' => $item->batch_number,
                    'expiry_date' => $item->expiry_date,
                    'transaction_date' => $today,
                    'reference_number' => 'DEMO-RECEIPT-2026-01',
                    'notes' => 'Demo inventory receipt.',
                ],
            );
        }
    }

    private function seedAnnouncements(): void
    {
        ClinicAnnouncement::updateOrCreate(
            ['title' => 'Measles catch-up week', 'starts_on' => '2026-07-06'],
            [
                'barangay_id' => null,
                'created_by' => $this->users['superadmin']->id,
                'category' => 'campaign',
                'audience' => 'all',
                'ends_on' => '2026-07-12',
                'location' => 'All partner barangay health stations',
                'message' => 'Bring the child vaccine card and any outside-clinic proof for catch-up review.',
                'active' => true,
            ],
        );

        ClinicAnnouncement::updateOrCreate(
            ['title' => 'Saturday well-baby clinic', 'starts_on' => '2026-07-04'],
            [
                'barangay_id' => $this->barangays['san_isidro']->id,
                'created_by' => $this->users['san_isidro_admin']->id,
                'category' => 'schedule',
                'audience' => 'parents',
                'ends_on' => '2026-07-31',
                'location' => 'San Isidro Health Center',
                'message' => 'Walk-in vaccination and growth monitoring every Saturday morning.',
                'active' => true,
            ],
        );

        ClinicAnnouncement::updateOrCreate(
            ['title' => 'Cold chain maintenance window', 'starts_on' => '2026-07-08'],
            [
                'barangay_id' => $this->barangays['santa_maria']->id,
                'created_by' => $this->users['santa_maria_admin']->id,
                'category' => 'closure',
                'audience' => 'staff',
                'ends_on' => '2026-07-08',
                'location' => 'Santa Maria Vaccine Room',
                'message' => 'Routine immunization resumes after noon once the cold chain check is completed.',
                'active' => true,
            ],
        );

        ClinicAnnouncement::updateOrCreate(
            ['title' => 'PCV stock advisory', 'starts_on' => '2026-06-28'],
            [
                'barangay_id' => $this->barangays['riverside']->id,
                'created_by' => $this->users['riverside_admin']->id,
                'category' => 'stock',
                'audience' => 'staff',
                'ends_on' => '2026-07-10',
                'location' => 'Riverside BHS',
                'message' => 'Reserve remaining PCV doses for scheduled second and third doses while waiting for restock.',
                'active' => false,
            ],
        );
    }

    private function seedChildrenAndVaccinationHistories(): void
    {
        $this->children['starter_child'] = $this->createChild(
            'starter_child',
            $this->barangays['barangay_1'],
            $this->users['starter_nurse'],
            [
                'first_name' => 'Mika',
                'middle_name' => 'Anne',
                'last_name' => 'Domingo',
                'birthdate' => '2025-12-15',
                'sex' => 'female',
                'guardian_name' => 'Demo Parent',
                'guardian_contact' => '09171234567',
                'address' => 'Purok 2, Barangay 1, Starter Municipality',
            ],
            [
                ['user' => $this->users['starter_parent'], 'relationship' => 'mother'],
            ],
        );

        $this->seedHistory($this->children['starter_child'], [
            $this->clinicDose('bcg', 1, '2025-12-15'),
            $this->clinicDose('hepb', 1, '2025-12-15'),
            $this->clinicDose('hepb', 2, '2026-01-16'),
            $this->clinicDose('dtap', 1, '2026-01-28'),
            $this->clinicDose('opv', 1, '2026-01-28'),
            $this->clinicDose('hib', 1, '2026-01-28'),
            $this->clinicDose('pcv', 1, '2026-01-28'),
            $this->clinicDose('rv', 1, '2026-01-28'),
            $this->parentDose('starter-mika', 'dtap', 2, '2026-03-01', 'Starter Family Clinic', 'Starter Municipality', 'pending', 'Submitted by parent for review.', true),
        ], $this->users['starter_nurse'], $this->users['starter_parent'], $this->activeVersion);

        $this->children['starter_defaulter'] = $this->createChild(
            'starter_defaulter',
            $this->barangays['barangay_1'],
            $this->users['starter_nurse'],
            [
                'first_name' => 'Jules',
                'middle_name' => 'Marie',
                'last_name' => 'Fernandez',
                'birthdate' => '2025-08-01',
                'sex' => 'male',
                'guardian_name' => 'Demo Parent',
                'guardian_contact' => '09171234567',
                'address' => 'Purok 5, Barangay 1, Starter Municipality',
            ],
            [
                ['user' => $this->users['starter_parent'], 'relationship' => 'guardian'],
            ],
        );

        $this->seedHistory($this->children['starter_defaulter'], [
            $this->clinicDose('bcg', 1, '2025-08-01'),
            $this->clinicDose('hepb', 1, '2025-08-01'),
            $this->clinicDose('hepb', 2, '2025-09-01'),
            $this->clinicDose('dtap', 1, '2025-09-12'),
            $this->clinicDose('opv', 1, '2025-09-12'),
            $this->clinicDose('hib', 1, '2025-09-12'),
            $this->clinicDose('pcv', 1, '2025-09-12'),
        ], $this->users['starter_nurse'], $this->users['starter_parent'], $this->activeVersion);

        $this->children['sofia_lopez'] = $this->createChild(
            'sofia_lopez',
            $this->barangays['san_isidro'],
            $this->users['nurse_lara'],
            [
                'first_name' => 'Sofia',
                'middle_name' => 'Mae',
                'last_name' => 'Lopez',
                'birthdate' => '2025-01-10',
                'sex' => 'female',
                'guardian_name' => 'Maria Lopez',
                'guardian_contact' => '09171111111',
                'address' => 'Purok 1, San Isidro, Cabanatuan City',
            ],
            [
                ['user' => $this->users['maria_lopez'], 'relationship' => 'mother'],
                ['user' => $this->users['rafael_lopez'], 'relationship' => 'father'],
            ],
        );

        $this->seedHistory($this->children['sofia_lopez'], [
            $this->clinicDose('bcg', 1, '2025-01-10', 'Birth dose completed.'),
            $this->clinicDose('hepb', 1, '2025-01-10'),
            $this->clinicDose('hepb', 2, '2025-02-12'),
            $this->clinicDose('dtap', 1, '2025-02-21'),
            $this->clinicDose('opv', 1, '2025-02-21'),
            $this->clinicDose('hib', 1, '2025-02-21'),
            $this->clinicDose('pcv', 1, '2025-02-21'),
            $this->clinicDose('rv', 1, '2025-02-21'),
            $this->clinicDose('dtap', 2, '2025-03-21'),
            $this->clinicDose('opv', 2, '2025-03-21'),
            $this->clinicDose('hib', 2, '2025-03-21'),
            $this->clinicDose('pcv', 2, '2025-03-21'),
            $this->clinicDose('rv', 2, '2025-03-21'),
            $this->clinicDose('dtap', 3, '2025-04-18'),
            $this->clinicDose('opv', 3, '2025-04-18'),
            $this->clinicDose('ipv', 1, '2025-04-18'),
            $this->clinicDose('hib', 3, '2025-04-18'),
            $this->clinicDose('pcv', 3, '2025-04-18'),
            $this->clinicDose('rv', 3, '2025-04-18'),
            $this->clinicDose('hepb', 3, '2025-07-15'),
            $this->clinicDose('influenza', 1, '2025-07-20'),
            $this->clinicDose('ipv', 2, '2025-10-15'),
            $this->clinicDose('mmr', 1, '2025-10-15'),
            $this->clinicDose('hib', 4, '2026-01-13'),
            $this->clinicDose('pcv', 4, '2026-01-13'),
            $this->clinicDose('mmr', 2, '2026-01-20'),
            $this->clinicDose('var', 1, '2026-01-20'),
            $this->clinicDose('hep_a', 1, '2026-01-20'),
            $this->clinicDose('dtap', 4, '2026-04-15', 'Booster given during catch-up clinic.'),
        ], $this->users['nurse_lara'], $this->users['maria_lopez'], $this->archivedVersion);

        $this->children['liam_santos'] = $this->createChild(
            'liam_santos',
            $this->barangays['san_isidro'],
            $this->users['nurse_ian'],
            [
                'first_name' => 'Liam',
                'middle_name' => 'Jose',
                'last_name' => 'Santos',
                'birthdate' => '2025-09-02',
                'sex' => 'male',
                'guardian_name' => 'Ana Santos',
                'guardian_contact' => '09172222221',
                'address' => 'Block 4, San Isidro, Cabanatuan City',
            ],
            [
                ['user' => $this->users['ana_santos'], 'relationship' => 'mother'],
            ],
        );

        $this->seedHistory($this->children['liam_santos'], [
            $this->clinicDose('bcg', 1, '2025-09-02'),
            $this->clinicDose('hepb', 1, '2025-09-02'),
            $this->clinicDose('hepb', 2, '2025-10-03'),
            $this->clinicDose('dtap', 1, '2025-10-14'),
            $this->clinicDose('opv', 1, '2025-10-14'),
            $this->clinicDose('hib', 1, '2025-10-14'),
            $this->clinicDose('pcv', 1, '2025-10-14'),
            $this->clinicDose('rv', 1, '2025-10-14'),
            $this->clinicDose('dtap', 2, '2025-11-12'),
            $this->clinicDose('opv', 2, '2025-11-12'),
            $this->clinicDose('hib', 2, '2025-11-12'),
            $this->clinicDose('pcv', 2, '2025-11-12'),
            $this->clinicDose('rv', 2, '2025-11-12'),
            $this->clinicDose('dtap', 3, '2025-12-10'),
            $this->clinicDose('opv', 3, '2025-12-10'),
            $this->clinicDose('ipv', 1, '2025-12-10'),
            $this->clinicDose('hib', 3, '2025-12-10'),
            $this->clinicDose('pcv', 3, '2025-12-10'),
            $this->parentDose('liam-santos', 'influenza', 1, '2026-03-05', 'Ana Pediatric Clinic', 'Town Proper', 'pending', 'Brought vaccination card photo.', true),
        ], $this->users['nurse_ian'], $this->users['ana_santos'], $this->activeVersion);

        $this->children['mila_reyes_north'] = $this->createChild(
            'mila_reyes_north',
            $this->barangays['san_isidro'],
            $this->users['nurse_lara'],
            [
                'first_name' => 'Mila',
                'middle_name' => null,
                'last_name' => 'Reyes',
                'birthdate' => '2025-11-05',
                'sex' => 'female',
                'guardian_name' => 'Oliver Reyes',
                'guardian_contact' => '09176666661',
                'address' => 'Sitio Centro, San Isidro, Cabanatuan City',
            ],
            [
                ['user' => $this->users['oliver_reyes'], 'relationship' => 'father'],
            ],
        );

        $this->seedHistory($this->children['mila_reyes_north'], [
            $this->clinicDose('bcg', 1, '2025-11-05'),
            $this->clinicDose('hepb', 1, '2025-11-05'),
            $this->clinicDose('hepb', 2, '2025-12-06'),
            $this->clinicDose('dtap', 1, '2025-12-17'),
            $this->clinicDose('opv', 1, '2025-12-17'),
            $this->clinicDose('hib', 1, '2025-12-17'),
            $this->clinicDose('pcv', 1, '2025-12-17'),
        ], $this->users['nurse_lara'], $this->users['oliver_reyes'], $this->activeVersion);

        $this->children['noah_cruz'] = $this->createChild(
            'noah_cruz',
            $this->barangays['santa_maria'],
            $this->users['nurse_sofia'],
            [
                'first_name' => 'Noah',
                'middle_name' => 'Luis',
                'last_name' => 'Cruz',
                'birthdate' => '2026-02-20',
                'sex' => 'male',
                'guardian_name' => 'Jessa Cruz',
                'guardian_contact' => '09174444441',
                'address' => 'Zone 2, Santa Maria, Cabanatuan City',
            ],
            [
                ['user' => $this->users['jessa_cruz'], 'relationship' => 'mother'],
            ],
        );

        $this->seedHistory($this->children['noah_cruz'], [
            $this->clinicDose('bcg', 1, '2026-02-20'),
            $this->clinicDose('hepb', 1, '2026-02-20'),
            $this->parentDose('noah-cruz', 'dtap', 1, '2026-04-10', 'Family Health Clinic', 'Santa Maria', 'rejected', 'Parent submitted unclear details.', false),
            $this->parentDose('noah-cruz', 'opv', 1, '2026-04-10', 'Family Health Clinic', 'Santa Maria', 'pending', 'Awaiting nurse review.', false),
        ], $this->users['nurse_sofia'], $this->users['jessa_cruz'], $this->activeVersion);

        $this->children['emma_target'] = $this->createChild(
            'emma_target',
            $this->barangays['santa_maria'],
            $this->users['nurse_sofia'],
            [
                'first_name' => 'Emma',
                'middle_name' => 'Rose',
                'last_name' => 'Dela Cruz',
                'birthdate' => '2025-10-12',
                'sex' => 'female',
                'guardian_name' => 'Maricar Dela Cruz',
                'guardian_contact' => '09175555551',
                'address' => 'Sitio Uno Annex, Santa Maria, Cabanatuan City',
            ],
            [
                ['user' => $this->users['maricar_dela_cruz'], 'relationship' => 'mother'],
            ],
        );

        $this->seedHistory($this->children['emma_target'], [
            $this->clinicDose('bcg', 1, '2025-10-12'),
            $this->clinicDose('hepb', 1, '2025-10-12'),
            $this->clinicDose('hepb', 2, '2025-11-12'),
            $this->clinicDose('dtap', 1, '2025-11-24'),
            $this->clinicDose('opv', 1, '2025-11-24'),
            $this->clinicDose('hib', 1, '2025-11-24'),
            $this->clinicDose('pcv', 1, '2025-11-24'),
            $this->clinicDose('rv', 1, '2025-11-24'),
            $this->clinicDose('dtap', 2, '2025-12-22'),
            $this->clinicDose('opv', 2, '2025-12-22'),
            $this->clinicDose('hib', 2, '2025-12-22'),
            $this->clinicDose('pcv', 2, '2025-12-22'),
        ], $this->users['nurse_sofia'], $this->users['maricar_dela_cruz'], $this->activeVersion);

        $this->children['emma_duplicate'] = $this->createChild(
            'emma_duplicate',
            $this->barangays['santa_maria'],
            $this->users['nurse_sofia'],
            [
                'first_name' => 'Emma',
                'middle_name' => 'Rose',
                'last_name' => 'Dela Cruz',
                'birthdate' => '2025-10-12',
                'sex' => 'female',
                'guardian_name' => 'Maricar Dela Cruz',
                'guardian_contact' => '09175555551',
                'address' => 'Sitio Uno, Santa Maria, Cabanatuan City',
            ],
            [
                ['user' => $this->users['maricar_dela_cruz'], 'relationship' => 'mother'],
            ],
        );

        $this->seedHistory($this->children['emma_duplicate'], [
            $this->clinicDose('dtap', 3, '2026-01-21'),
            $this->clinicDose('opv', 3, '2026-01-21'),
            $this->clinicDose('ipv', 1, '2026-01-21'),
            $this->clinicDose('hib', 3, '2026-01-21'),
            $this->clinicDose('pcv', 3, '2026-01-21'),
        ], $this->users['nurse_sofia'], $this->users['maricar_dela_cruz'], $this->activeVersion);

        $this->children['mila_reyes_south'] = $this->createChild(
            'mila_reyes_south',
            $this->barangays['riverside'],
            $this->users['nurse_ella'],
            [
                'first_name' => 'Mila',
                'middle_name' => null,
                'last_name' => 'Reyes',
                'birthdate' => '2025-11-05',
                'sex' => 'female',
                'guardian_name' => 'Oliver Reyes',
                'guardian_contact' => '09176666661',
                'address' => 'Purok Riverside, Gapan City',
            ],
            [
                ['user' => $this->users['oliver_reyes'], 'relationship' => 'father'],
            ],
        );

        $this->seedHistory($this->children['mila_reyes_south'], [
            $this->clinicDose('bcg', 1, '2025-11-05'),
            $this->clinicDose('hepb', 1, '2025-11-05'),
        ], $this->users['nurse_ella'], $this->users['oliver_reyes'], $this->activeVersion);

        $this->children['ava_garcia'] = $this->createChild(
            'ava_garcia',
            $this->barangays['riverside'],
            $this->users['nurse_ella'],
            [
                'first_name' => 'Ava',
                'middle_name' => 'Jean',
                'last_name' => 'Garcia',
                'birthdate' => '2024-05-30',
                'sex' => 'female',
                'guardian_name' => 'Sylvia Garcia',
                'guardian_contact' => '09177777771',
                'address' => 'South Riverside, Gapan City',
            ],
            [
                ['user' => $this->users['sylvia_garcia'], 'relationship' => 'mother'],
            ],
        );

        $this->seedHistory($this->children['ava_garcia'], [
            $this->clinicDose('bcg', 1, '2024-05-30'),
            $this->clinicDose('hepb', 1, '2024-05-30'),
            $this->clinicDose('hepb', 2, '2024-06-30'),
            $this->clinicDose('dtap', 1, '2024-07-12'),
            $this->clinicDose('opv', 1, '2024-07-12'),
            $this->clinicDose('hib', 1, '2024-07-12'),
            $this->clinicDose('pcv', 1, '2024-07-12'),
            $this->clinicDose('rv', 1, '2024-07-12'),
            $this->clinicDose('dtap', 2, '2024-08-09'),
            $this->clinicDose('opv', 2, '2024-08-09'),
            $this->clinicDose('hib', 2, '2024-08-09'),
            $this->clinicDose('pcv', 2, '2024-08-09'),
            $this->clinicDose('rv', 2, '2024-08-09'),
            $this->clinicDose('dtap', 3, '2024-09-06'),
            $this->clinicDose('opv', 3, '2024-09-06'),
            $this->clinicDose('ipv', 1, '2024-09-06'),
            $this->clinicDose('hib', 3, '2024-09-06'),
            $this->clinicDose('pcv', 3, '2024-09-06'),
            $this->clinicDose('rv', 3, '2024-09-06'),
            $this->clinicDose('hepb', 3, '2024-11-30'),
            $this->clinicDose('influenza', 1, '2024-12-10'),
            $this->clinicDose('ipv', 2, '2025-02-28'),
            $this->clinicDose('mmr', 1, '2025-02-28'),
            $this->clinicDose('hib', 4, '2025-05-30'),
            $this->clinicDose('pcv', 4, '2025-05-30'),
            $this->clinicDose('mmr', 2, '2025-05-30'),
            $this->clinicDose('var', 1, '2025-05-30'),
            $this->clinicDose('hep_a', 1, '2025-05-30'),
            $this->clinicDose('dtap', 4, '2025-08-30'),
        ], $this->users['nurse_ella'], $this->users['sylvia_garcia'], $this->archivedVersion);
    }

    private function seedAefiReports(): void
    {
        $starterPcv = VaccinationRecord::query()
            ->where('child_profile_id', $this->children['starter_child']->id)
            ->where('dose_number', 1)
            ->where('vaccine_type_id', $this->vaccine('pcv')->id)
            ->firstOrFail();

        AdverseEventReport::updateOrCreate(
            [
                'child_profile_id' => $this->children['starter_child']->id,
                'vaccination_record_id' => $starterPcv->id,
            ],
            [
                'vaccine_type_id' => $this->vaccine('pcv')->id,
                'reported_by' => $this->users['starter_nurse']->id,
                'event_date' => '2026-01-29',
                'severity' => 'mild',
                'outcome' => 'Recovered',
                'symptoms' => 'Mild swelling and fussiness after PCV dose',
                'notes' => 'Observed and documented for AEFI list visibility in starter barangay.',
            ],
        );

        $sofiaMmr = VaccinationRecord::query()
            ->where('child_profile_id', $this->children['sofia_lopez']->id)
            ->where('dose_number', 2)
            ->where('vaccine_type_id', $this->vaccine('mmr')->id)
            ->firstOrFail();

        AdverseEventReport::updateOrCreate(
            [
                'child_profile_id' => $this->children['sofia_lopez']->id,
                'vaccination_record_id' => $sofiaMmr->id,
            ],
            [
                'vaccine_type_id' => $this->vaccine('mmr')->id,
                'reported_by' => $this->users['nurse_lara']->id,
                'event_date' => '2026-01-21',
                'severity' => 'mild',
                'outcome' => 'Recovered after paracetamol and observation',
                'symptoms' => 'Low-grade fever and mild rash the day after vaccination',
                'notes' => 'Mother was reassured and advised on home care.',
            ],
        );

        $avaVar = VaccinationRecord::query()
            ->where('child_profile_id', $this->children['ava_garcia']->id)
            ->where('dose_number', 1)
            ->where('vaccine_type_id', $this->vaccine('var')->id)
            ->firstOrFail();

        AdverseEventReport::updateOrCreate(
            [
                'child_profile_id' => $this->children['ava_garcia']->id,
                'vaccination_record_id' => $avaVar->id,
            ],
            [
                'vaccine_type_id' => $this->vaccine('var')->id,
                'reported_by' => $this->users['nurse_ella']->id,
                'event_date' => '2025-05-31',
                'severity' => 'moderate',
                'outcome' => 'Recovered',
                'symptoms' => 'Injection-site swelling with irritability for two days',
                'notes' => 'Observed in clinic, no referral needed.',
            ],
        );
    }

    private function seedReminders(): void
    {
        $today = Carbon::today();

        $this->upsertReminder(
            $this->children['starter_defaulter'],
            $this->users['starter_parent'],
            'DTaP / DTwP-containing vaccine',
            2,
            $today->copy()->subDays(260),
            'email',
            $this->users['starter_parent']->email,
            'sent',
            $today->copy()->subDays(250),
        );

        $this->upsertReminder(
            $this->children['noah_cruz'],
            $this->users['jessa_cruz'],
            'DTaP / DTwP-containing vaccine',
            2,
            $today->copy()->subDays(72),
            'email',
            $this->users['jessa_cruz']->email,
            'sent',
            $today->copy()->subDays(65),
        );

        $this->upsertReminder(
            $this->children['noah_cruz'],
            $this->users['jessa_cruz'],
            'DTaP / DTwP-containing vaccine',
            2,
            $today->copy()->subDays(72),
            'sms',
            $this->users['jessa_cruz']->phone,
            'failed',
            null,
            'SMS gateway timeout during reminder retry.',
        );

        $this->upsertReminder(
            $this->children['ava_garcia'],
            $this->users['sylvia_garcia'],
            'Hepatitis A',
            2,
            $today->copy()->subDays(30),
            'email',
            $this->users['sylvia_garcia']->email,
            'sent',
            $today->copy()->subDays(20),
        );

        $this->upsertReminder(
            $this->children['liam_santos'],
            $this->users['ana_santos'],
            'Measles, Mumps, Rubella',
            1,
            $today->copy()->addDays(6),
            'sms',
            $this->users['ana_santos']->phone,
            'pending',
        );
    }

    private function seedNotifications(): void
    {
        $child = $this->children['starter_defaulter'] ?? ChildProfile::query()->first();

        if ($child === null) {
            return;
        }

        $this->upsertNotification(
            $this->users['starter_parent'],
            'demo-vaccination-reminder',
            'Vaccination reminder',
            "{$child->full_name} is overdue for a vaccination. Please visit the clinic.",
            route('children.show', $child),
            'bell-alert',
        );

        $this->upsertNotification(
            $this->users['starter_parent'],
            'demo-submission-approved',
            'Vaccination submission approved',
            "The vaccination record for {$child->full_name} was approved by the clinic.",
            route('children.show', $child),
            'check-circle',
            read: true,
        );

        $this->upsertNotification(
            $this->users['starter_nurse'],
            'demo-new-submission',
            'New vaccination submission',
            'A parent submitted a vaccination record that needs verification.',
            route('verification-queue.index'),
            'clipboard-document-check',
        );

        // Announcement notifications are disabled with the announcements feature.
    }

    private function upsertNotification(
        User $user,
        string $key,
        string $title,
        string $body,
        string $actionUrl,
        string $icon,
        bool $read = false,
    ): void {
        $exists = $user->notifications()
            ->where('type', InAppNotification::class)
            ->get()
            ->contains(fn ($notification) => ($notification->data['key'] ?? null) === $key);

        if ($exists) {
            return;
        }

        $user->notify(new InAppNotification($key, $title, $body, $actionUrl, $icon));

        if ($read) {
            $user->notifications()->latest()->first()?->markAsRead();
        }
    }

    private function seedSyncData(): void
    {
        SyncStatus::updateOrCreate(
            ['scope' => 'manual_sync'],
            [
                'last_synced_by' => $this->users['san_isidro_admin']->id,
                'last_synced_at' => now()->subHours(6),
                'last_processed' => 42,
                'last_failed' => 1,
            ],
        );

        SyncStatus::updateOrCreate(
            ['scope' => 'parent_submissions'],
            [
                'last_synced_by' => $this->users['nurse_sofia']->id,
                'last_synced_at' => now()->subHours(2),
                'last_processed' => 8,
                'last_failed' => 0,
            ],
        );

        $pendingRecord = VaccinationRecord::query()
            ->where('child_profile_id', $this->children['liam_santos']->id)
            ->where('verification_status', 'pending')
            ->first();

        if ($pendingRecord !== null) {
            OfflineSyncOutbox::updateOrCreate(
                [
                    'model_type' => VaccinationRecord::class,
                    'model_sync_uuid' => $pendingRecord->sync_uuid,
                    'operation' => 'upsert',
                ],
                [
                    'payload' => [
                        'sync_uuid' => $pendingRecord->sync_uuid,
                        'child_sync_uuid' => $pendingRecord->child->sync_uuid,
                        'verification_status' => $pendingRecord->verification_status,
                        'source' => $pendingRecord->source,
                    ],
                    'queued_at' => now()->subHours(3),
                    'synced_at' => null,
                    'last_error' => 'Awaiting intermittent connectivity from mobile device.',
                    'attempts' => 2,
                ],
            );
        }

        $child = $this->children['emma_duplicate'];

        OfflineSyncOutbox::updateOrCreate(
            [
                'model_type' => ChildProfile::class,
                'model_sync_uuid' => $child->sync_uuid,
                'operation' => 'delete',
            ],
            [
                'payload' => [
                    'sync_uuid' => $child->sync_uuid,
                    'full_name' => $child->full_name,
                    'reason' => 'Marked for duplicate merge cleanup during training demo.',
                ],
                'queued_at' => now()->subMinutes(45),
                'synced_at' => null,
                'last_error' => null,
                'attempts' => 0,
            ],
        );
    }

    private function createChild(
        string $key,
        Barangay $barangay,
        User $creator,
        array $attributes,
        array $parents,
    ): ChildProfile {
        $child = ChildProfile::updateOrCreate(
            [
                'first_name' => $attributes['first_name'],
                'last_name' => $attributes['last_name'],
                'birthdate' => $attributes['birthdate'],
                'barangay_id' => $barangay->id,
                'address' => $attributes['address'],
            ],
            [
                'created_by' => $creator->id,
                'middle_name' => $attributes['middle_name'],
                'sex' => $attributes['sex'],
                'guardian_name' => $attributes['guardian_name'],
                'guardian_contact' => $attributes['guardian_contact'],
            ],
        );

        $child->parents()->sync(
            collect($parents)->mapWithKeys(fn (array $parent) => [
                $parent['user']->id => ['relationship' => $parent['relationship']],
            ])->all()
        );

        $this->children[$key] = $child;

        return $child;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function seedHistory(
        ChildProfile $child,
        array $records,
        User $nurse,
        User $parent,
        VaccineScheduleVersion $version,
    ): void {
        $usedVaccineIds = [];

        foreach ($records as $attributes) {
            $recordedBy = match ($attributes['recorded_by']) {
                'parent' => $parent->id,
                'nurse' => $nurse->id,
                null => $attributes['source'] === 'outside_clinic' ? $parent->id : $nurse->id,
                default => $attributes['recorded_by'],
            };

            $submittedBy = match ($attributes['submitted_by']) {
                'parent' => $parent->id,
                'nurse' => $nurse->id,
                null => $attributes['source'] === 'outside_clinic' ? $parent->id : null,
                default => $attributes['submitted_by'],
            };

            $verifiedBy = match ($attributes['verified_by']) {
                'parent' => $parent->id,
                'nurse' => $nurse->id,
                null => $attributes['verification_status'] === 'verified' ? $nurse->id : null,
                default => $attributes['verified_by'],
            };

            $record = VaccinationRecord::updateOrCreate(
                [
                    'child_profile_id' => $child->id,
                    'vaccine_type_id' => $this->vaccine($attributes['vaccine_code'])->id,
                    'dose_number' => $attributes['dose_number'],
                    'administered_at' => $attributes['administered_at'],
                    'source' => $attributes['source'],
                ],
                [
                    'recorded_by' => $recordedBy,
                    'submitted_by' => $submittedBy,
                    'verified_by' => $verifiedBy,
                    'verification_status' => $attributes['verification_status'],
                    'verified_at' => $attributes['verified_at'],
                    'clinic_name' => $attributes['clinic_name'],
                    'clinic_location' => $attributes['clinic_location'],
                    'proof_path' => $attributes['proof_path'],
                    'proof_paths' => $attributes['proof_paths'],
                    'client_submission_id' => $attributes['client_submission_id'],
                    'remarks' => $attributes['remarks'],
                ],
            );

            $usedVaccineIds[$record->vaccine_type_id] = true;
        }

        foreach (array_keys($usedVaccineIds) as $vaccineId) {
            ChildVaccineSeriesVersion::updateOrCreate(
                [
                    'child_profile_id' => $child->id,
                    'vaccine_type_id' => $vaccineId,
                ],
                [
                    'vaccine_schedule_version_id' => $version->id,
                    'assigned_at' => Carbon::parse($child->birthdate)->addWeek(),
                    'assignment_reason' => $version->is($this->activeVersion)
                        ? 'assigned_for_demo_seed'
                        : 'legacy_series_demo_seed',
                ],
            );
        }

        $this->refreshRecordSuggestions($child);
    }

    private function refreshRecordSuggestions(ChildProfile $child): void
    {
        $suggestion = app(ImmunizationSuggestionService::class)->suggestNextDose($child);

        VaccinationRecord::query()
            ->where('child_profile_id', $child->id)
            ->whereIn('verification_status', ['verified', 'pending'])
            ->update([
                'next_due_at' => $suggestion['due_at'],
                'suggested_vaccine' => $suggestion['vaccine_name'],
                'suggested_schedule_version_id' => $suggestion['suggested_schedule_version_id'],
                'suggestion_note' => $suggestion['note'],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function clinicDose(string $vaccineCode, int $doseNumber, string $administeredAt, ?string $remarks = null): array
    {
        return [
            'vaccine_code' => $vaccineCode,
            'dose_number' => $doseNumber,
            'administered_at' => $administeredAt,
            'source' => 'barangay_clinic',
            'verification_status' => 'verified',
            'verified_by' => null,
            'verified_at' => Carbon::parse($administeredAt)->setTime(10, 0),
            'clinic_name' => 'Barangay Health Station',
            'clinic_location' => 'Routine immunization clinic',
            'proof_path' => null,
            'proof_paths' => null,
            'client_submission_id' => null,
            'remarks' => $remarks,
            'submitted_by' => null,
            'recorded_by' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parentDose(
        string $childKey,
        string $vaccineCode,
        int $doseNumber,
        string $administeredAt,
        string $clinicName,
        string $clinicLocation,
        string $status,
        ?string $remarks,
        bool $withProof,
    ): array {
        $verifiedAt = $status === 'pending' ? null : Carbon::parse($administeredAt)->addDays(2)->setTime(9, 30);
        $proofPath = $withProof ? 'vaccination-proofs/'.$vaccineCode.'-'.$doseNumber.'-'.Str::uuid().'.jpg' : null;

        return [
            'vaccine_code' => $vaccineCode,
            'dose_number' => $doseNumber,
            'administered_at' => $administeredAt,
            'source' => 'outside_clinic',
            'verification_status' => $status,
            'verified_by' => $status === 'pending' ? null : 'nurse',
            'verified_at' => $verifiedAt,
            'clinic_name' => $clinicName,
            'clinic_location' => $clinicLocation,
            'proof_path' => $proofPath,
            'proof_paths' => $proofPath ? [$proofPath] : null,
            'client_submission_id' => 'seed-'.Str::slug($childKey.'-'.$vaccineCode.'-'.$doseNumber.'-'.$administeredAt),
            'remarks' => $remarks,
            'submitted_by' => 'parent',
            'recorded_by' => 'parent',
        ];
    }

    private function upsertReminder(
        ChildProfile $child,
        User $parent,
        string $vaccineName,
        int $doseNumber,
        Carbon $dueAt,
        string $channel,
        ?string $recipient,
        string $status,
        ?Carbon $sentAt = null,
        ?string $errorMessage = null,
    ): void {
        VaccinationReminder::updateOrCreate(
            [
                'child_profile_id' => $child->id,
                'parent_id' => $parent->id,
                'vaccine_name' => $vaccineName,
                'dose_number' => $doseNumber,
                'due_at' => $dueAt->toDateString(),
                'channel' => $channel,
            ],
            [
                'recipient' => $recipient ?? 'pending',
                'status' => $status,
                'error_message' => $errorMessage,
                'sent_at' => $sentAt,
            ],
        );
    }

    private function upsertUser(
        string $name,
        string $email,
        ?string $phone,
        string $role,
        ?Barangay $barangay = null,
        bool $active = true,
        ?Carbon $invitationAcceptedAt = null,
        ?Carbon $emailVerifiedAt = null,
    ): User {
        $acceptedAt = $active
            ? ($invitationAcceptedAt ?? ($role === 'nurse' || $role === 'barangay_admin' ? now()->subDays(30) : now()->subDays(120)))
            : $invitationAcceptedAt;
        $verifiedAt = $active ? ($emailVerifiedAt ?? now()->subDays(120)) : $emailVerifiedAt;

        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make('password123'),
                'role' => $role,
                'roles' => [$role],
                'barangay_id' => $barangay?->id,
                'is_active' => $active,
                'email_verified_at' => $verifiedAt,
                'invitation_accepted_at' => $acceptedAt,
            ],
        );
    }

    private function vaccine(string $code): VaccineType
    {
        return $this->vaccines->get($code) ?? throw new \RuntimeException("Missing vaccine code [{$code}] in demo seeder.");
    }
}
