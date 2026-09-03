<x-errors.illustrated
    code="422"
    title="We couldn't complete that action"
    eyebrow="Action needs attention"
    message="The information or record state is not valid for this action. Review the details and try again."
    primary-label="Try again"
    :primary-url="url()->previous()"
    secondary-label="Go to dashboard"
    :secondary-url="auth()->check() ? route('dashboard') : route('login')"
    accent="amber"
/> 
