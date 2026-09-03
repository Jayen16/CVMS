<x-errors.illustrated
    code="410"
    title="That link is no longer valid"
    eyebrow="Link expired"
    message="This link may have expired or already been used. Request a new link and try again."
    primary-label="Return home"
    :primary-url="url('/')"
    secondary-label="Sign in"
    :secondary-url="route('login')"
    accent="amber"
/> 
