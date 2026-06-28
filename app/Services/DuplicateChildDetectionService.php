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
            ->map(fn (array $group, string $signature) => [
                'signature' => $signature,
                'reason' => $group['reason'],
                'children' => collect($group['children'])->unique('id')->sortBy('last_name')->values()->all(),
            ])
            ->sortByDesc(fn (array $group) => count($group['children']))
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }
}
