<x-errors.illustrated
    code="503"
    title="The portal is temporarily unavailable"
    eyebrow="Maintenance or outage"
    message="The service is starting up, under maintenance, or briefly overloaded. Please check back again in a few minutes."
    primary-label="Return home"
    :primary-url="url('/')"
    secondary-label="Sign in later"
    :secondary-url="route('login')"
    accent="emerald"
/>
