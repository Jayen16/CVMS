<x-errors.illustrated
    code="500"
    title="Something went wrong on our side"
    eyebrow="Server error"
    message="The system ran into an unexpected issue while preparing this page. Your information is not necessarily lost, but this request could not be completed."
    primary-label="Try the home page"
    :primary-url="url('/')"
    :secondary-label="auth()->check() ? 'Go to dashboard' : 'Sign in'"
    :secondary-url="auth()->check() ? route('dashboard') : route('login')"
    accent="red"
/>
