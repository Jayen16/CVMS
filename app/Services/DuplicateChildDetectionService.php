<?php

namespace App\Services;

use App\Models\ChildProfile;
use Illuminate\Database\Eloquent\Collection;

class DuplicateChildDetectionService
{
    /**
     * @param  Collection<int, ChildProfile>  $children
     * @return list<array{signature: string, reason: string, children: list<ChildProfile>}>
     */
    public function detect(Collection $children): array
    {
        $groups = [];

        foreach ($children as $child) {
            $fullNameKey = $this->normalize("{$child->first_name} {$child->middle_name} {$child->last_name}");
            $contactKey = $this->normalize((string) $child->guardian_contact);
            $birthdate = optional($child->birthdate)->toDateString();

            if ($birthdate !== null && $fullNameKey !== '') {
                $groups["name:{$birthdate}:{$fullNameKey}"]['reason'] = 'Same birthdate and child name';
                $groups["name:{$birthdate}:{$fullNameKey}"]['children'][] = $child;
            }

            if ($birthdate !== null && $contactKey !== '') {
                $groups["contact:{$birthdate}:{$contactKey}"]['reason'] = 'Same birthdate and guardian contact';
                $groups["contact:{$birthdate}:{$contactKey}"]['children'][] = $child;
            }
        }

        return collect($groups)
            ->filter(fn (array $group) => count($group['children']) > 1)
            ->map(function (array $group, string $signature): array {
                $children = collect($group['children'])->unique('id')->sortBy('last_name')->values();

                return [
                    'signature' => $signature,
                    'reason' => $group['reason'],
                    'children' => $children->all(),
                    'dedupe_key' => $children->pluck('id')->sort()->implode('-'),
                ];
            })
            ->groupBy('dedupe_key')
            ->map(function ($duplicateGroups): array {
                $primary = $duplicateGroups->first();
                $reasons = $duplicateGroups
                    ->pluck('reason')
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'signature' => $primary['signature'],
                    'reason' => implode(' + ', $reasons),
                    'children' => $primary['children'],
                ];
            })
            ->sortByDesc(fn (array $group) => count($group['children']))
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }
}
