<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\Municipality;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineInventoryItem;
use App\Models\VaccineInventoryTransaction;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use App\Services\DummyVaccinationTimeline;
use App\Services\ImmunizationSuggestionService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class GenerateBarangayDummyData extends Command
{
    protected $signature = 'cvms:generate-dummy-data
        {--dry-run : Validate generation and roll back all database writes}
        {--randomize-stock-only : Vary existing dummy stock without creating families or changing vaccination histories}';

    protected $description = 'Add email-only dummy families, nurses, stock, histories and verification submissions for Indang';

    private const MARKER = 'CVMS dummy population v1';

    public function handle(): int
    {
        // These guards apply only to this process. Do not change the application's .env.
        Notification::fake();
        Mail::fake();
        Bus::fake();
        Http::preventStrayRequests();

        if ($this->option('randomize-stock-only')) {
            return $this->randomizeExistingStock();
        }

        $barangays = Barangay::query()->whereIn('municipality_id', Municipality::query()->where('name', 'Indang')->select('id'))->orderBy('name')->get();
        $version = VaccineScheduleVersion::query()->where('status', 'active')->orderByDesc('effective_date')->firstOrFail();
        $vaccines = VaccineType::query()->where('active', true)->get();
        $schedules = VaccineSchedule::query()->where('vaccine_schedule_version_id', $version->id)
            ->where('active', true)->whereIn('vaccine_type_id', $vaccines->modelKeys())->get()
            ->unique(fn ($schedule) => $schedule->vaccine_type_id.':'.$schedule->dose_number);

        if ($barangays->isEmpty() || $schedules->count() < 10) {
            $this->error('Indang barangays and at least 10 active scheduled doses are required.');

            return self::FAILURE;
        }
        if (ChildProfile::withoutGlobalScopes()->where('address', 'like', self::MARKER.'%')->exists()) {
            $this->error('This dummy population already exists. Refusing to duplicate it.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $batch = 'dummy-'.now()->format('YmdHis').'-'.Str::lower(Str::random(5));
        $password = 'password123';
        $hash = Hash::make($password);
        $accounts = [];
        $summary = [];

        DB::beginTransaction();
        try {
            Model::withoutEvents(function () use ($barangays, $version, $vaccines, $schedules, $batch, $hash, &$accounts, &$summary): void {
                foreach ($barangays as $barangay) {
                    $nurses = [];
                    for ($i = 0, $count = random_int(1, 5); $i < $count; $i++) {
                        $nurses[] = $this->account('nurse', $barangay, $batch, $hash, $accounts);
                    }
                    $childCount = random_int(10, 50);
                    $recordCount = 0;
                    $pendingCount = 0;
                    $items = [];
                    // Receive enough stock before recording any vaccination. The earliest
                    // generated birth is less than five years ago, after this receipt date.
                    foreach ($vaccines as $vaccine) {
                        $item = VaccineInventoryItem::create([
                            'item_code' => $batch.'-'.Str::uuid(),
                            'barangay_id' => $barangay->id, 'vaccine_type_id' => $vaccine->id,
                            'batch_number' => $batch, 'received_at' => today()->subYears(5),
                            'expiry_date' => today()->addYears(2), 'reference_number' => $batch,
                            'notes' => self::MARKER.' — synthetic stock for testing only.',
                        ]);
                        $items[$vaccine->id] = $item;
                        $this->movement($item, $nurses[0], 'receipt', $childCount * 10 + random_int(50, 150));
                    }
                    for ($i = 0; $i < $childCount; $i++) {
                        $nurse = $nurses[array_rand($nurses)];
                        $parent = $this->account('parent', $barangay, $batch, $hash, $accounts);
                        $plan = app(DummyVaccinationTimeline::class)->plan($schedules, $i);
                        $birthdate = $plan['birthdate'];
                        $sex = fake()->randomElement(['male', 'female']);
                        $child = ChildProfile::create([
                            'barangay_id' => $barangay->id, 'created_by' => $nurse->id,
                            'first_name' => fake()->firstName($sex), 'middle_name' => fake()->lastName(),
                            'last_name' => Str::afterLast($parent->name, ' '), 'birthdate' => $birthdate,
                            'sex' => $sex, 'guardian_name' => $parent->name, 'guardian_contact' => null,
                            'address' => self::MARKER.' | '.$barangay->name.', Indang, Cavite',
                            'sync_uuid' => (string) Str::uuid(), 'vaccine_card_token' => (string) Str::uuid(),
                            'registered_by_uuid' => $nurse->id, 'registered_by_name' => $nurse->name,
                            'registered_by_role' => 'nurse', 'sync_version' => 1,
                        ]);
                        $child->parents()->attach($parent->id, ['relationship' => 'guardian']);
                        $eligible = $plan['schedules'];
                        $doseCount = random_int(1, min(5, $eligible->count()));
                        // Guarantee queue entries in every barangay while retaining at least
                        // one verified administered dose for every child.
                        $pending = $i % 5 === 0;
                        if ($pending && $doseCount === 1) {
                            $doseCount = 2;
                        }
                        foreach ($eligible->take($doseCount) as $index => $schedule) {
                            $isPending = $pending && $index === $doseCount - 1;
                            $date = $plan['dates'][$index];
                            $record = VaccinationRecord::create([
                                'child_profile_id' => $child->id, 'vaccine_type_id' => $schedule->vaccine_type_id,
                                'dose_number' => $schedule->dose_number, 'administered_at' => $date,
                                'recorded_by' => $isPending ? $parent->id : $nurse->id,
                                'submitted_by' => $isPending ? $parent->id : null,
                                'verified_by' => $isPending ? null : $nurse->id,
                                'verified_at' => $isPending ? null : $date->copy()->setTime(10, 0),
                                'source' => $isPending ? 'outside_clinic' : 'barangay_clinic',
                                'verification_status' => $isPending ? 'pending' : 'verified',
                                'clinic_name' => $isPending ? 'Dummy Outside Clinic' : trim($barangay->name).' Health Station',
                                'clinic_location' => 'Indang, Cavite',
                                'sync_uuid' => (string) Str::uuid(), 'sync_version' => 1,
                                'client_submission_id' => $isPending ? $batch.'-'.Str::uuid() : null,
                                'remarks' => self::MARKER.($isPending ? ' — synthetic parent submission; no real proof attached.' : ' — synthetic administered dose.'),
                            ]);
                            ChildVaccineSeriesVersion::firstOrCreate([
                                'child_profile_id' => $child->id, 'vaccine_type_id' => $schedule->vaccine_type_id,
                            ], [
                                'vaccine_schedule_version_id' => $version->id, 'assigned_at' => $date,
                                'assignment_reason' => 'dummy_population_seed',
                            ]);
                            if ($isPending) {
                                $pendingCount++;
                            } else {
                                $this->movement($items[$schedule->vaccine_type_id], $nurse, 'usage', 1, $record);
                            }
                            $recordCount++;
                        }
                        $suggestion = app(ImmunizationSuggestionService::class)->suggestionForRecord($child);
                        $child->vaccinations()->update($suggestion);
                    }
                    $this->randomizeStock($items, $nurses[0]);
                    $summary[] = [trim($barangay->name), $childCount, $childCount, count($nurses), $recordCount, $pendingCount];
                    $this->line(trim($barangay->name).": {$childCount} children; {$recordCount} histories; {$pendingCount} pending.");
                }
            });
            if ($dryRun) {
                DB::rollBack();
                $this->info('Dry run passed. All database writes rolled back; no notifications sent.');
            } else {
                // Save the account manifest before committing so an unwritable report
                // destination cannot leave an unreported population in the database.
                $directory = storage_path('app/dummy-data');
                if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                    throw new \RuntimeException('Cannot create dummy data report directory.');
                }
                $path = $directory.'/'.$batch.'.json';
                $report = json_encode(compact('batch', 'password', 'accounts', 'summary'), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (file_put_contents($path, $report) === false) {
                    throw new \RuntimeException('Cannot save dummy data report.');
                }
                DB::commit();
                $this->info('Created dummy data. Account manifest: '.$path);
            }
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
        $this->table(['Barangay', 'Children', 'Parents', 'Nurses', 'Histories', 'Pending'], $summary);

        return self::SUCCESS;
    }

    private function account(string $role, Barangay $barangay, string $batch, string $hash, array &$accounts): User
    {
        $name = fake()->firstName().' '.fake()->lastName();
        $localPart = Str::slug($name, '.');
        $email = $localPart.'@example.com';
        $suffix = 2;
        while (User::query()->where('email', $email)->exists()) {
            $email = $localPart.$suffix++.'@example.com';
        }
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'phone' => null, 'login_channel' => 'email', 'password' => $hash,
            'role' => $role, 'roles' => [$role], 'barangay_id' => $barangay->id,
            'municipality_id' => $barangay->municipality_id, 'is_active' => true,
            'email_verified_at' => now(), 'invitation_accepted_at' => now(),
        ])->save();
        $accounts[] = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role, 'barangay' => trim($barangay->name)];

        return $user;
    }

    private function randomizeExistingStock(): int
    {
        $summary = [];
        DB::beginTransaction();
        try {
            $items = VaccineInventoryItem::query()
                ->where('notes', 'like', self::MARKER.'%')
                ->where('item_code', 'like', 'dummy-%')
                ->whereIn('barangay_id', Barangay::query()->whereIn(
                    'municipality_id', Municipality::query()->where('name', 'Indang')->select('id')
                )->select('id'))
                ->with('barangay')->orderBy('id')->lockForUpdate()->get();

            if ($items->isEmpty()) {
                $this->error('No generated dummy inventory found in Indang.');
                DB::rollBack();

                return self::FAILURE;
            }

            foreach ($items->groupBy('barangay_id') as $barangayItems) {
                $recorder = $barangayItems->first()->transactions()->where('movement', 'in')->firstOrFail()->recorder;
                $counts = $this->randomizeStock($barangayItems->all(), $recorder);
                $summary[] = [trim($barangayItems->first()->barangay->name), ...array_values($counts)];
            }

            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->info('Dry run passed. Stock changes rolled back.');
            } else {
                DB::commit();
                $this->info('Randomized dummy stock. Vaccination histories and non-dummy inventory preserved.');
            }
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
        $this->table(['Barangay', 'Zero', '1-5 doses', '6-20 doses', '40-150 doses', '400-1200 doses'], $summary);

        return self::SUCCESS;
    }

    private function randomizeStock(array $items, User $nurse): array
    {
        // Shuffle vaccine assignments, then cycle ranges to ensure each barangay
        // includes shortages and surplus rather than relying on chance alone.
        $ranges = [[0, 0], [1, 5], [6, 20], [40, 150], [400, 1200]];
        $counts = array_fill(0, count($ranges), 0);
        foreach (collect($items)->groupBy('vaccine_type_id')->shuffle()->values() as $index => $vaccineItems) {
            $category = $index % count($ranges);
            $target = random_int(...$ranges[$category]);
            $counts[$category]++;
            foreach ($vaccineItems->values() as $itemIndex => $item) {
                $balance = $item->availableStock();
                if ($balance < 0) {
                    throw new \RuntimeException('Dummy inventory has a negative balance: '.$item->item_code);
                }
                $difference = ($itemIndex === 0 ? $target : 0) - $balance;
                if ($difference !== 0) {
                    $this->movement($item, $nurse, $difference > 0 ? 'receipt' : 'adjustment', abs($difference), transactionDate: today());
                }
            }
        }

        return $counts;
    }

    private function movement(VaccineInventoryItem $item, User $nurse, string $type, int $quantity, ?VaccinationRecord $record = null, ?CarbonInterface $transactionDate = null): void
    {
        VaccineInventoryTransaction::create([
            'barangay_id' => $item->barangay_id, 'vaccine_type_id' => $item->vaccine_type_id,
            'vaccine_inventory_item_id' => $item->id, 'vaccination_record_id' => $record?->id,
            'recorded_by' => $nurse->id, 'transaction_type' => $type,
            'movement' => $type === 'receipt' ? 'in' : 'out', 'quantity' => $quantity,
            'batch_number' => $item->batch_number, 'expiry_date' => $item->expiry_date,
            'transaction_date' => $transactionDate ?? $record?->administered_at ?? $item->received_at,
            'reference_number' => $record?->id ?? $item->reference_number,
            'sync_uuid' => (string) Str::uuid(), 'sync_version' => 1,
            'notes' => self::MARKER.($transactionDate ? ' | Synthetic stock rebalance for shortage/surplus testing.' : ''),
        ]);
    }
}
