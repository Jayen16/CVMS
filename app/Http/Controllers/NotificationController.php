<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class NotificationController extends Controller
{
    public function read(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            $notification->notifiable_type === User::class
                && $notification->notifiable_id === auth()->id(),
            403
        );

        $notification->markAsRead();

        $actionUrl = $notification->data['action_url'] ?? null;
        $path = is_string($actionUrl) ? parse_url($actionUrl, PHP_URL_PATH) : null;

        if (! is_string($path) || $path === '') {
            return to_route('notifications.index');
        }

        try {
            Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (HttpExceptionInterface|\Symfony\Component\Routing\Exception\RouteNotFoundException) {
            return to_route('notifications.index');
        }

        return redirect($actionUrl);
    }
}
