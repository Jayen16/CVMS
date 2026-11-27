<?php

use App\Models\VaccineSchedule;
use App\Services\DummyVaccinationTimeline;
use Illuminate\Support\Carbon;

test('dummy timelines keep dates valid and limit overdue schedules while mixing statuses', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-27 12:00:00'));
    try {
        $rows = collect([0, 0, 30, 42, 42, 42, 42, 42, 70, 70, 70, 70, 70, 98])->map(fn ($age, $i) => new VaccineSchedule([
            'vaccine_type_id' => 'vaccine-'.$i, 'dose_number' => 1, 'age_days' => $age,
        ]));
        $statuses = ['upcoming' => 0, 'due' => 0, 'delayed' => 0, 'overdue' => 0];
        foreach (range(0, 99) as $i) {
            $plan = (new DummyVaccinationTimeline)->plan($rows, $i);
            expect($plan['schedules']->count())->toBeBetween(1, $rows->count() - 1);
            foreach ($plan['dates'] as $date) {
                expect($date->gte($plan['birthdate']))->toBeTrue()
                    ->and($date->lte(Carbon::today()))->toBeTrue();
            }
            $next = $rows[$plan['schedules']->count()]->dueDateFromBirthdate($plan['birthdate']);
            $late = (int) $next->diffInDays(Carbon::today(), false);
            expect($late)->toBeLessThanOrEqual(60);
            $status = $late < 0 ? 'upcoming' : ($late === 0 ? 'due' : ($late < 7 ? 'delayed' : 'overdue'));
            $statuses[$status]++;
        }
        expect($statuses)->toBe(['upcoming' => 60, 'due' => 5, 'delayed' => 15, 'overdue' => 20]);
    } finally {
        Carbon::setTestNow();
    }
});
