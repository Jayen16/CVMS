<?php

use App\Livewire\PredictiveAnalyticsPage;
use App\Models\Barangay;
use App\Models\User;
use App\Services\GeminiPlanningSummaryService;
use App\Services\PredictiveAnalyticsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function planningDemandFixture(): Collection
{
    return collect([[
        'vaccine' => (object) ['name' => 'BCG'],
        'scheduled_due' => 30, 'catch_up_backlog' => 10, 'recent_three_months' => 25,
        'estimated_demand' => 45, 'available_stock' => 25,
        'projected_balance' => -20, 'stock_status' => 'shortage',
        'child_name' => 'Private Child', 'guardian_contact' => 'private@example.com',
    ]]);
}

beforeEach(function () {
    config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-flash-latest']);
    Http::preventStrayRequests();
});

test('gemini receives only allowlisted aggregate figures and returns visible text', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
            ['thought' => true, 'text' => 'Internal reasoning'],
            ['text' => 'Review the possible 20-dose shortage.'],
        ]]]],
    ])]);

    expect(app(GeminiPlanningSummaryService::class)->generate(planningDemandFixture(), 3))
        ->toBe('Review the possible 20-dose shortage.');

    Http::assertSent(function (Request $request) {
        $payload = json_decode($request['contents'][0]['parts'][0]['text'], true);

        return $request->hasHeader('x-goog-api-key', 'test-key')
            && $payload['forecast_months'] === 3
            && $payload['vaccines'][0]['projected_balance'] === -20
            && ! str_contains($request->body(), 'Private Child')
            && ! str_contains($request->body(), 'private@example.com');
    });
});

test('missing key and empty data make no external request', function () {
    $service = app(GeminiPlanningSummaryService::class);
    expect(fn () => $service->generate(collect(), 3))->toThrow(RuntimeException::class, 'No demand data');
    config(['services.gemini.key' => '']);
    expect(fn () => $service->generate(planningDemandFixture(), 3))->toThrow(RuntimeException::class, 'not configured');
    Http::assertNothingSent();
});

test('provider errors do not expose response bodies', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'private provider details'], 429)]);
    expect(fn () => app(GeminiPlanningSummaryService::class)->generate(planningDemandFixture(), 3))
        ->toThrow(RuntimeException::class, 'The AI service usage limit was reached. Please try again later.');
});

test('blocked or truncated responses cannot appear as completed summaries', function (array $response) {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response($response)]);
    expect(fn () => app(GeminiPlanningSummaryService::class)->generate(planningDemandFixture(), 3))
        ->toThrow(RuntimeException::class);
})->with([
    'blocked' => [['promptFeedback' => ['blockReason' => 'SAFETY']]],
    'truncated' => [['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => 'Partial']]]]]]],
    'empty' => [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => []]]]]],
]);

test('staff generate summaries and changing filters clears old results', function () {
    $barangay = Barangay::create(['name' => 'Planning Barangay']);
    $staff = User::factory()->create(['role' => 'barangay_admin', 'barangay_id' => $barangay->id]);
    $this->mock(PredictiveAnalyticsService::class, function ($mock) {
        $mock->shouldReceive('vaccineDemand')->andReturn(planningDemandFixture());
    });
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => "### Overview\n\nStock review.\n\n### Stock priorities\n\n- **BCG:** shortage of 20 doses.\n\n<script>alert(1)</script>"]]]]],
    ])]);

    Livewire::actingAs($staff)->test(PredictiveAnalyticsPage::class)
        ->call('generateAiSummary')
        ->assertSet('aiError', null)
        ->assertSee('<h3>Stock priorities</h3>', false)
        ->assertSee('<strong>BCG:</strong>', false)
        ->assertDontSee('Gemini')
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->set('months', 6)
        ->assertSet('aiSummary', null)
        ->assertSet('aiGeneratedAt', null);
});

test('unauthorized users cannot invoke generation', function () {
    $parent = User::factory()->create(['role' => 'parent']);
    Livewire::actingAs($parent)->test(PredictiveAnalyticsPage::class)->assertForbidden();
    Http::assertNothingSent();
});
