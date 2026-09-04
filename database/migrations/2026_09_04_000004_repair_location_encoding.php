<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['regions', 'provinces', 'municipalities', 'barangays'] as $table) {
            DB::table($table)->select(['id', 'name'])->orderBy('id')->get()->each(function (object $row) use ($table): void {
                $repaired = $this->repair($row->name);
                if ($repaired !== $row->name) {
                    DB::table($table)->where('id', $row->id)->update(['name' => $repaired]);
                }
            });
        }
    }

    public function down(): void
    {
        // Encoding repairs are intentionally not reversed.
    }

    private function repair(?string $value): ?string
    {
        if ($value === null || ! preg_match('/[ÃÂ]/u', $value)) {
            return $value;
        }

        $latin = iconv('UTF-8', 'ISO-8859-1//IGNORE', $value);

        return $latin === false ? $value : (iconv('ISO-8859-1', 'UTF-8//IGNORE', $latin) ?: $value);
    }
};
