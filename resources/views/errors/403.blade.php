<x-errors.illustrated
    code="403"
    title="Access is restricted"
    eyebrow="Permission required"
    message="This page is available only to users with the right account role or approval level. If you expected to see it, try signing in with the correct account."
    primary-label="Go to dashboard"
    :primary-url="auth()->check() ? route('dashboard') : route('login')"
    secondary-label="Return home"
    :secondary-url="url('/')"
    accent="amber"
/>
