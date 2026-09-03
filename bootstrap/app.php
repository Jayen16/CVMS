<?php

use App\Http\Middleware\RequireUnactivatedInstallation;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['unactivated.installation' => RequireUnactivatedInstallation::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            // Keep Laravel's normal validation response flow, including field
            // errors and redirects for browser forms.
            if ($exception instanceof AuthenticationException || $exception instanceof ValidationException) {
                return null;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'The request could not be completed. Please try again.',
                ], $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;
            $view = view()->exists('errors.'.$status) ? 'errors.'.$status : 'errors.500';

            return response()->view($view, [], $status);
        });
    })->create();
