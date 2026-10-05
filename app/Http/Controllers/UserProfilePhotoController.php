<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserProfilePhotoController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $user = Auth::user();

        abort_unless(filled($user->photo_path), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($user->photo_path), 404);

        return response()->file($disk->path($user->photo_path));
    }
}
