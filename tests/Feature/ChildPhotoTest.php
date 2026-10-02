<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function photoChild(Barangay $barangay, User $nurse): ChildProfile
{
    return ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Mia',
        'last_name' => 'Santos',
        'birthdate' => now()->subYears(2)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Parent',
    ]);
}

test('a nurse can upload and view a child profile photo', function () {
    Storage::fake('local');
    $barangay = Barangay::create(['name' => 'Photo Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $child = photoChild($barangay, $nurse);

    $this->actingAs($nurse)
        ->post(route('children.photo.upload', $child), ['photo' => UploadedFile::fake()->image('child.jpg')])
        ->assertRedirect(route('children.show', $child, absolute: false));

    $child->refresh();
    expect($child->photo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($child->photo_path);

    $this->actingAs($nurse)->get(route('children.photo', $child))->assertOk();
});

test('a linked parent can upload a child profile photo but an unrelated parent cannot', function () {
    Storage::fake('local');
    $barangay = Barangay::create(['name' => 'Parent Photo Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $parent = User::factory()->create(['role' => 'parent']);
    $otherParent = User::factory()->create(['role' => 'parent']);
    $child = photoChild($barangay, $nurse);
    $child->parents()->attach($parent->id, ['relationship' => 'mother']);

    $this->actingAs($parent)
        ->post(route('children.photo.upload', $child), ['photo' => UploadedFile::fake()->image('child.jpg')])
        ->assertRedirect();

    $this->actingAs($otherParent)
        ->post(route('children.photo.upload', $child), ['photo' => UploadedFile::fake()->image('other.jpg')])
        ->assertForbidden();
});
