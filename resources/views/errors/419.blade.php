<x-errors.illustrated
    code="419"
    title="Your session has expired"
    eyebrow="Session timeout"
    message="For security, the page sat idle too long or the request token is no longer valid. Refreshing the workflow usually fixes it."
    primary-label="Sign in again"
    :primary-url="route('login')"
    secondary-label="Return home"
    :secondary-url="url('/')"
    accent="amber"
/>
