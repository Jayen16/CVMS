<?php

use App\Livewire\ChildShowPage;
use App\Livewire\ImmunizationSchedulePage;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function createAiReviewChild(User $nurse, array $overrides = []): ChildProfile
{
    return ChildProfile::create(array_merge([
        'barangay_id' => $nurse->barangay_id,
        'created_by' => $nurse->id,
        'first_name' => 'Confidential', 'last_name' => 'Person',
        'birthdate' => today()->subDays(8), 'sex' => 'female',
        'guardian_name' => 'Confidential Guardian', 'guardian_contact' => '09123456789',
        'address' => 'Confidential Address',
    ], $overrides));
}

beforeEach(function () {
    config(['services.gemini.key' => 'test-key']);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $facts = json_decode($request['contents'][0]['parts'][0]['text'], true);
        $text = $facts['scope'] === 'one_record'
            ? "### What this means\n\nOne submitted record is awaiting verification.\n\n### Why it needs review\n\n- The pending entry still needs staff verification.\n\n### What to do next\n\n1. Review the pending submission to confirm the recorded information."
            : "### Clinic snapshot\n\nReview the outstanding records in this selected group.\n\n### What needs attention\n\n- Past-due statuses indicate records to check, not confirmed missed vaccinations.\n\n### Suggested work plan\n\n1. Check the vaccination cards to resolve outstanding documentation.";

        return Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $text]]]]]]);
    });
    $barangay = Barangay::create(['name' => 'Confidential Location']);
    $this->nurse = User::factory()->create([
        'role' => 'nurse', 'roles' => ['nurse'], 'barangay_id' => $barangay->id,
        'permissions' => ['view_children', 'view_defaulters'],
    ]);
});

test('child record summary sends computed counts without identifying or clinical details', function () {
    $child = createAiReviewChild($this->nurse);
    VaccinationRecord::create([
        'child_profile_id' => $child->id,
        'vaccine_type_id' => VaccineType::where('code', 'bcg')->firstOrFail()->id,
        'recorded_by' => $this->nurse->id, 'dose_number' => 1,
        'source' => 'outside_clinic', 'verification_status' => 'pending',
        'administered_at' => today(), 'remarks' => 'Private medical note',
    ]);

    Livewire::actingAs($this->nurse)->test(ChildShowPage::class, ['child' => $child])
        ->assertSee('AI Record Summary')
        ->call('generateAiRecordSummary')
        ->assertSet('aiNarrativeError', null)
        ->assertSee('One submitted record is awaiting verification.')
        ->assertSee('Why it needs review')
        ->assertSee('What to do next')
        ->assertSee('ai-review-panel--explanation', false);

    Http::assertSent(function (Request $request) use ($child): bool {
        $facts = json_decode($request['contents'][0]['parts'][0]['text'], true);

        return $facts['scope'] === 'one_record'
            && str_contains($request['systemInstruction']['parts'][0]['text'], 'Every bullet must link a supplied finding to its administrative meaning')
            && $facts['record_counts']['total'] === 1
            && $facts['record_counts']['pending_verification'] === 1
            && $facts['configured_schedule_item_counts']['pending_verification'] === 1
            && ! str_contains($request->body(), 'Confidential')
            && ! str_contains($request->body(), $child->id)
            && ! str_contains($request->body(), '09123456789')
            && ! str_contains($request->body(), 'Private medical note')
            && ! str_contains($request->body(), 'birthdate')
            && ! str_contains($request->body(), 'BCG');
    });
});

test('follow-up briefing covers every filtered page and excludes other barangays', function () {
    for ($index = 0; $index < 12; $index++) {
        createAiReviewChild($this->nurse);
    }
    createAiReviewChild($this->nurse, ['birthdate' => today(), 'first_name' => 'DueNow']);
    $otherBarangay = Barangay::create(['name' => 'Other Location']);
    createAiReviewChild($this->nurse, ['barangay_id' => $otherBarangay->id]);

    $page = Livewire::actingAs($this->nurse)->test(ImmunizationSchedulePage::class)
        ->set('status', 'overdue')
        ->assertViewHas('totalMatching', 12)
        ->assertViewHas('rows', fn ($rows): bool => $rows->count() === 10)
        ->call('generateAiFollowUpBriefing')
        ->assertSet('aiNarrativeError', null)
        ->assertSee('Review the outstanding records in this selected group.')
        ->assertSee('What needs attention')
        ->assertSee('Suggested work plan')
        ->assertSee('ai-review-panel--explanation', false);

    Http::assertSent(function (Request $request): bool {
        $facts = json_decode($request['contents'][0]['parts'][0]['text'], true);

        return $facts['matching_children'] === 12
            && $facts['children_by_next_schedule_status'] === ['overdue' => 12]
            && $facts['contact_channels_by_schedule_status'] === ['overdue' => ['SMS priority' => 12]]
            && $facts['children_with_pending_verification'] === 0
            && str_contains($request['systemInstruction']['parts'][0]['text'], 'Do not recommend catch-up review unless the supplied catch_up_review count is positive')
            && ! str_contains($request->body(), 'Confidential')
            && ! str_contains($request->body(), 'DueNow')
            && ! str_contains($request->body(), '09123456789');
    });

    $page->set('search', 'NoMatch')->assertSet('aiNarrative', null)->assertSet('aiNarrativeAt', null)
        ->call('generateAiFollowUpBriefing')
        ->assertSet('aiNarrativeError', 'No children match the current filters. Change the filters before generating a briefing.');
    Http::assertSentCount(1);
});

test('a nurse cannot request a summary for another barangays child', function () {
    $otherBarangay = Barangay::create(['name' => 'Other Location']);
    $child = createAiReviewChild($this->nurse, ['barangay_id' => $otherBarangay->id]);
    Livewire::actingAs($this->nurse)->test(ChildShowPage::class, ['child' => $child])->assertForbidden();
    Http::assertNothingSent();
});

test('parents do not see the AI record tool and cannot invoke it', function () {
    $child = createAiReviewChild($this->nurse);
    $parent = User::factory()->create(['role' => 'parent', 'roles' => ['parent']]);
    $child->parents()->attach($parent);
    Livewire::actingAs($parent)->test(ChildShowPage::class, ['child' => $child])
        ->assertDontSee('AI Record Summary')
        ->call('generateAiRecordSummary')->assertForbidden();
    Http::assertNothingSent();
});

test('nurses without monitoring access cannot generate briefings', function () {
    $this->nurse->update(['permissions' => ['view_children']]);
    Livewire::actingAs($this->nurse)->test(ImmunizationSchedulePage::class)->assertForbidden();
    Http::assertNothingSent();
});

test('record summary respects the shared AI rate limit', function () {
    $child = createAiReviewChild($this->nurse);
    RateLimiter::hit('gemini-planning:'.$this->nurse->id, 60);
    RateLimiter::hit('gemini-planning:'.$this->nurse->id, 60);
    RateLimiter::hit('gemini-planning:'.$this->nurse->id, 60);
    Livewire::actingAs($this->nurse)->test(ChildShowPage::class, ['child' => $child])
        ->call('generateAiRecordSummary')
        ->assertSet('aiNarrativeError', 'Please wait a minute before generating another summary.');
    Http::assertNothingSent();
});

test('follow-up briefing shows a Gemini failure and recovers on retry', function () {
    createAiReviewChild($this->nurse);
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => 'Partial briefing.']]]]]])
        ->push(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'Briefing retry succeeded.']]]]]])]);

    Livewire::actingAs($this->nurse)->test(ImmunizationSchedulePage::class)
        ->call('generateAiFollowUpBriefing')
        ->assertSet('aiNarrative', null)
        ->assertSet('aiNarrativeAt', null)
        ->assertSee('Unable to generate the follow-up briefing')
        ->assertSee('Gemini did not return a complete summary.')
        ->assertSee('Try again')
        ->assertDontSee('Partial briefing.')
        ->call('generateAiFollowUpBriefing')
        ->assertSet('aiNarrativeError', null)
        ->assertSet('aiNarrative', 'Briefing retry succeeded.')
        ->assertSee('Refresh summary')
        ->assertDontSee('Unable to generate the follow-up briefing');

    Http::assertSentCount(2);
});
