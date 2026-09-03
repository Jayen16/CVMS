<?php

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\User;

test('staff archive requires an exit reason and records archive attribution', function () {
    $barangay = Barangay::create(['name' => 'Staff Archive Barangay']);
    $admin = User::factory()->create([
        'role' => 'barangay_admin',
        'roles' => ['barangay_admin'],
        'barangay_id' => $barangay->id,
    ]);
    $nurse = User::factory()->create([
        'role' => 'nurse',
        'roles' => ['nurse'],
        'barangay_id' => $barangay->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('nurses.destroy', $nurse), ['archive_reason' => 'Retired'])
        ->assertRedirect(route('nurses.index'));

    $nurse->refresh();
    expect($nurse->isArchived())->toBeTrue()
        ->and($nurse->archived_by)->toBe($admin->id)
        ->and($nurse->archive_reason)->toBe('Retired')
        ->and(AuditLog::where('event', 'staff_archived')->where('auditable_id', $nurse->id)->exists())->toBeTrue();
});

test('administrators can archive audit logs by date range and restore them', function () {
    $admin = User::factory()->create(['role' => 'superadmin', 'roles' => ['superadmin']]);
    $log = AuditLog::create([
        'user_id' => $admin->id,
        'event' => 'test_event',
        'auditable_type' => User::class,
        'auditable_id' => $admin->id,
        'description' => 'Historical audit event',
    ]);
    $log->forceFill([
        'created_at' => '2025-05-15 10:00:00',
        'updated_at' => '2025-05-15 10:00:00',
    ])->saveQuietly();

    $this->actingAs($admin)
        ->post(route('archives.store'), [
            'type' => 'audit_logs',
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
            'archive_reason' => 'Closed reporting year',
        ])
        ->assertRedirect(route('archives.index'));

    expect(AuditLog::find($log->id))->toBeNull()
        ->and(AuditLog::withoutGlobalScope('not_archived')->find($log->id)->isArchived())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('archives.restore', ['type' => 'audit_logs', 'recordId' => $log->id]))
        ->assertRedirect(route('archives.index'));

    expect(AuditLog::find($log->id)->isArchived())->toBeFalse();
});
