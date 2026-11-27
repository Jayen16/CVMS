<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DummyVaccinationTimeline
{
    /** Build synthetic dates from the configured schedule, never from display overrides. */
    public function plan(Collection $schedules, int $index): array
    {
        $today = Carbon::today();
        $origin = Carbon::create(2020, 1, 1)->startOfDay();
        $rows = $schedules->sort(function ($a, $b) use ($origin) {
            return ($a->dueDateFromBirthdate($origin) <=> $b->dueDateFromBirthdate($origin))
                ?: ($a->dose_number <=> $b->dose_number)
                ?: strcmp($a->vaccine_type_id, $b->vaccine_type_id);
        })->values();
        $boundaries = [];
        for ($count = 1; $count < $rows->count(); $count++) {
            if ($rows[$count]->dueDateFromBirthdate($origin)->lte($origin->copy()->addYears(4))
                && $rows[$count - 1]->dueDateFromBirthdate($origin)->lt($rows[$count]->dueDateFromBirthdate($origin))) {
                $boundaries[] = $count;
            }
        }
        if ($boundaries === []) {
            throw new \RuntimeException('Schedule needs a dose-age boundary within the first four years.');
        }
        $cohort = $index % 20;
        // Cover infant doses and later boosters instead of stopping at the first 10 rows.
        // Cycling age boundaries gives each barangay a spread even in a small population.
        $count = $boundaries[$index % count($boundaries)];
        $next = $rows[$count];
        $gap = (int) $rows[$count - 1]->dueDateFromBirthdate($origin)->diffInDays($next->dueDateFromBirthdate($origin));
        $daysLate = match (true) {
            // Spread upcoming children through each age gap, including later forecast periods.
            $cohort < 12 => -max(1, (int) floor(max(1, $gap - 1) * ([0.2, 0.4, 0.6, 0.8][$index % 4]))),
            $cohort === 12 => 0,
            $cohort < 16 => random_int(1, 6),
            default => random_int(7, 60),
        };
        $target = $today->copy()->subDays($daysLate);
        // Searching by age also handles calendar-month offsets correctly.
        $birthdate = $target->copy();
        for ($age = 0; $age < 3660; $age++) {
            $birthdate = $target->copy()->subDays($age);
            if ($next->dueDateFromBirthdate($birthdate)->lte($target)) {
                break;
            }
        }
        $dates = [];
        $previous = [];
        foreach ($rows->take($count) as $i => $row) {
            $date = $row->dueDateFromBirthdate($birthdate);
            if (($index % 4 === 0 && $i === 0) || ($cohort === 19 && $i < (int) ceil($count * 0.7))) {
                $date->addDays(random_int(8, 14));
            }
            if (isset($previous[$row->vaccine_type_id]) && $date->lt($previous[$row->vaccine_type_id])) {
                $date = $previous[$row->vaccine_type_id]->copy();
            }
            $dates[$i] = $date->min($today)->copy();
            $previous[$row->vaccine_type_id] = $dates[$i];
        }

        return ['birthdate' => $birthdate, 'schedules' => $rows->take($count), 'dates' => $dates];
    }
}
