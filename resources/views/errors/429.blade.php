<x-errors.illustrated
    code="429"
    title="Too many requests right now"
    eyebrow="Rate limited"
    message="The portal received repeated requests in a short time, so it paused this action for a moment to protect the service. Please wait a bit, then try again."
    primary-label="Return home"
    :primary-url="url('/')"
    :secondary-label="auth()->check() ? 'Go to dashboard' : 'Sign in'"
    :secondary-url="auth()->check() ? route('dashboard') : route('login')"
    accent="amber"
/>
