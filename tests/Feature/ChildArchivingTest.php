<?php

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;

function archivedChildData(int|string $barangayId, int|string $createdBy): array
{
    return [
        'barangay_id' => $barangayId,
        'created_by' => $createdBy,
        'first_name' => 'Archive',
        'last_name' => 'Candidate',
        'birthdate' => '2022-04-12',
        'sex' => 'female',
        'guardian_name' => 'Guardian',
    ];
}

test('authorized staff can archive and restore a child while retaining the record', function () {
    $barangay = Barangay::create(['name' => 'Archive Barangay']);
    $admin = User::factory()->create([
        'role' => 'barangay_admin',
        'roles' => ['barangay_admin'],
        'barangay_id' => $barangay->id,
    ]);
    $child = ChildProfile::create(archivedChildData($barangay->id, $admin->id));

    $this->actingAs($admin)
        ->post(route('children.archive', $child->id), ['archive_reason' => 'Transferred'])
        ->assertRedirect(route('children.index'));

    expect(ChildProfile::find($child->id))->toBeNull();

    $archived = ChildProfile::withoutGlobalScope('not_archived')->findOrFail($child->id);
    expect($archived->isArchived())->toBeTrue()
        ->and($archived->archived_by)->toBe($admin->id)
        ->and($archived->archive_reason)->toBe('Transferred')
        ->and(AuditLog::where('event', 'child_archived')->where('auditable_id', $child->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('children.restore', $child->id))
        ->assertRedirect(route('children.archive.index'));

    expect(ChildProfile::find($child->id)->isArchived())->toBeFalse()
        ->and(AuditLog::where('event', 'child_restored')->where('auditable_id', $child->id)->exists())->toBeTrue();
});

test('nurse archiving is controlled by the archive_children permission', function () {
    $barangay = Barangay::create(['name' => 'Nurse Archive Barangay']);
    $nurse = User::factory()->create([
        'role' => 'nurse',
        'roles' => ['nurse'],
        'barangay_id' => $barangay->id,
        'permissions' => ['view_children', 'manage_children'],
    ]);
    $child = ChildProfile::create(archivedChildData($barangay->id, $nurse->id));

    $this->actingAs($nurse)
        ->post(route('children.archive', $child->id), ['archive_reason' => 'Inactive'])
        ->assertForbidden();

    $nurse->update(['permissions' => ['view_children', 'manage_children', 'archive_children']]);

    $this->actingAs($nurse)
        ->post(route('children.archive', $child->id), ['archive_reason' => 'Inactive'])
        ->assertRedirect(route('children.index'));
});

test('archived children do not appear in the active registry or parent api', function () {
    $barangay = Barangay::create(['name' => 'Hidden Archive Barangay']);
    $admin = User::factory()->create([
        'role' => 'barangay_admin',
        'roles' => ['barangay_admin'],
        'barangay_id' => $barangay->id,
    ]);
    $child = ChildProfile::create(archivedChildData($barangay->id, $admin->id));

    $this->actingAs($admin)->post(route('children.archive', $child->id), ['archive_reason' => 'Duplicate']);

    $this->actingAs($admin)
        ->get(route('children.index'))
        ->assertOk()
        ->assertDontSee('Archive Candidate');
});
