<x-errors.illustrated
    code="404"
    title="We couldn't find that page"
    eyebrow="Page missing"
    message="The address may be outdated, the record may no longer exist, or the link may have been typed incorrectly. The rest of the portal should still be available."
    primary-label="Return home"
    :primary-url="url('/')"
    :secondary-label="auth()->check() ? 'Go to dashboard' : 'Sign in'"
    :secondary-url="auth()->check() ? route('dashboard') : route('login')"
    accent="sky"
/>
