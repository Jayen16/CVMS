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

        // Notification URLs may have been generated with a different local host
        // (for example, APP_URL=localhost while the browser uses 127.0.0.1).
        // Redirect to the validated path on the current host instead of sending
        // the user to the host stored in the notification payload.
        $destination = $path;
        $query = parse_url($actionUrl, PHP_URL_QUERY);
        $fragment = parse_url($actionUrl, PHP_URL_FRAGMENT);

        if (is_string($query) && $query !== '') {
            $destination .= '?'.$query;
        }

        if (is_string($fragment) && $fragment !== '') {
            $destination .= '#'.$fragment;
        }

        return redirect(url($destination));
    }
}
