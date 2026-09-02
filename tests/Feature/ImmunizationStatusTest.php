<?php

use App\Services\ImmunizationSuggestionService;
use Illuminate\Support\Carbon;

test('immunization status is due on the guideline date', function () {
    $today = Carbon::parse('2026-09-02');

    expect(app(ImmunizationSuggestionService::class)->statusForDueDate($today, $today))
        ->toBe('due');
});

test('immunization status is delayed before the overdue threshold', function () {
    $today = Carbon::parse('2026-09-02');

    expect(app(ImmunizationSuggestionService::class)->statusForDueDate(Carbon::parse('2026-08-30'), $today))
        ->toBe('delayed');
});

test('immunization status is overdue at the configured threshold', function () {
    $today = Carbon::parse('2026-09-02');

    expect(app(ImmunizationSuggestionService::class)->statusForDueDate(Carbon::parse('2026-08-26'), $today))
        ->toBe('overdue');
});

test('immunization status is upcoming before the guideline date', function () {
    $today = Carbon::parse('2026-09-02');

    expect(app(ImmunizationSuggestionService::class)->statusForDueDate(Carbon::parse('2026-09-03'), $today))
        ->toBe('upcoming');
});
