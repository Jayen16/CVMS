<x-errors.illustrated
    code="505"
    title="This browser request isn't supported"
    eyebrow="Protocol issue"
    message="The server understood the request, but the browser or client used a protocol version the application does not support. Opening the portal in a modern browser usually resolves it."
    primary-label="Return home"
    :primary-url="url('/')"
    secondary-label="Sign in"
    :secondary-url="route('login')"
    accent="sky"
/>
